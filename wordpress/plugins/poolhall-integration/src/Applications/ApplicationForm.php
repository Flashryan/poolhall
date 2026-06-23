<?php
/**
 * Apply CTA + application popup markup.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Applications;

use Poolhall\Integration\Jobs\JobPostType;
use Poolhall\Integration\Support\Options;

/**
 * Renders the Apply call-to-action and the application popup (build
 * reference: applypopupjourney.html). `[poolhall_apply_button]` sits in the
 * single-job sidebar; when the apply channel is on it is a popup trigger
 * carrying the job context in data attributes, otherwise it falls back to
 * the contact page (also the no-JS fallback). The modal itself is printed
 * once in the footer on single-job pages and is populated from the trigger
 * by the enhancement script. Everything posts to the `poolhall_apply`
 * endpoint with a nonce, honeypot and the Turnstile seam.
 */
final class ApplicationForm {

	public const SHORTCODE_BUTTON = 'poolhall_apply_button';

	private const MESSAGES = array(
		'first_name_required' => 'Please enter your first name.',
		'last_name_required'  => 'Please enter your last name.',
		'email_invalid'       => 'Enter a valid email address.',
		'phone_required'      => 'Enter a contact phone number.',
		'consent_required'    => 'Please tick to continue.',
		'cv_required'         => 'Please attach your CV (PDF or Word, under 25 MB).',
		'cv_too_large'        => 'That file is over 25 MB. Please attach a smaller CV.',
		'cv_wrong_type'       => 'Please attach a PDF or Word document.',
		'cv_upload_failed'    => 'That upload did not complete. Please try again.',
	);

	public function __construct( private readonly Options $options ) {
	}

	public function register(): void {
		add_shortcode( self::SHORTCODE_BUTTON, array( $this, 'render_button' ) );
		add_action( 'wp_footer', array( $this, 'print_modal' ) );
	}

	public function render_button(): string {
		$post = get_post();
		if ( ! $post instanceof \WP_Post || JobPostType::POST_TYPE !== $post->post_type ) {
			return '';
		}

		$contact  = get_page_by_path( 'contact' );
		$fallback = $contact instanceof \WP_Post ? (string) get_permalink( $contact ) : home_url( '/contact/' );

		if ( ! $this->options->apply_to_poolhall_enabled() ) {
			// Channel off: keep the safe contact CTA (and no popup).
			return '<a class="ph-button ph-button--primary ph-button--full ph-button--lg" href="' . esc_url( $fallback ) . '">'
				. esc_html__( 'Contact us about this role', 'poolhall-integration' ) . '</a>';
		}

		$sector = get_the_term_list( $post->ID, JobPostType::TAX_SECTOR, '', ', ' );

		// The CTA is a real link to Contact (no-JS fallback); the script
		// intercepts it and opens the popup, reading the data attributes.
		return '<a class="ph-button ph-button--primary ph-button--full ph-button--lg ph-apply-trigger" href="' . esc_url( $fallback ) . '"'
			. ' data-ph-apply'
			. ' data-job-id="' . esc_attr( (string) $post->ID ) . '"'
			. ' data-job-title="' . esc_attr( get_the_title( $post ) ) . '"'
			. ' data-job-sector="' . esc_attr( is_string( $sector ) ? wp_strip_all_tags( $sector ) : '' ) . '"'
			. ' data-job-salary="' . esc_attr( (string) get_post_meta( $post->ID, 'salary_display', true ) ) . '"'
			. ' data-job-location="' . esc_attr( (string) get_post_meta( $post->ID, 'location_display', true ) ) . '"'
			. ' data-job-ref="' . esc_attr( (string) get_post_meta( $post->ID, 'job_reference', true ) ) . '">'
			. esc_html__( 'Apply for this role', 'poolhall-integration' )
			. '</a>';
	}

	public function print_modal(): void {
		if ( ! is_singular( JobPostType::POST_TYPE ) || ! $this->options->apply_to_poolhall_enabled() ) {
			return;
		}

		$action       = esc_url( admin_url( 'admin-post.php' ) );
		$nonce        = wp_create_nonce( ApplicationEndpoints::ACTION );
		$privacy      = get_privacy_policy_url();
		$consent_link = '' !== $privacy
			? sprintf(
				/* translators: %s: privacy policy URL. */
				__( 'I consent to Poolhall Recruitment storing and processing my data to handle this application, in line with the <a href="%s" target="_blank" rel="noopener">Privacy Policy</a>.', 'poolhall-integration' ),
				esc_url( $privacy )
			)
			: __( 'I consent to Poolhall Recruitment storing and processing my data to handle this application.', 'poolhall-integration' );

		// Inline JSON the script reads for field-level error messages.
		$messages = wp_json_encode( self::MESSAGES );

		?>
		<div class="ph-modal-overlay" id="ph-apply" data-ph-apply-overlay hidden>
			<div class="ph-modal" role="dialog" aria-modal="true" aria-labelledby="ph-apply-title">
				<div class="ph-modal__head">
					<span class="ph-modal__sector" data-ph-job-sector></span>
					<h2 class="ph-modal__title" id="ph-apply-title" data-ph-job-title></h2>
					<p class="ph-modal__facts">
						<span data-ph-job-location-row hidden><span data-ph-job-location></span></span>
						<span data-ph-job-salary-row hidden><span data-ph-job-salary></span></span>
						<span data-ph-job-ref-row hidden><?php esc_html_e( 'Ref', 'poolhall-integration' ); ?> <span data-ph-job-ref></span></span>
					</p>
					<button type="button" class="ph-modal__close" data-ph-apply-close aria-label="<?php esc_attr_e( 'Close', 'poolhall-integration' ); ?>">&times;</button>
				</div>

				<div class="ph-modal__progress" aria-hidden="true">
					<span class="ph-modal__step is-on" data-ph-step="1"><span class="ph-modal__dot">1</span> <?php esc_html_e( 'Your details', 'poolhall-integration' ); ?></span>
					<span class="ph-modal__bar"><i data-ph-progress></i></span>
					<span class="ph-modal__step" data-ph-step="2"><span class="ph-modal__dot">2</span> <?php esc_html_e( 'CV &amp; message', 'poolhall-integration' ); ?></span>
				</div>

				<form class="ph-modal__form" method="post" action="<?php echo $action; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url above. ?>" enctype="multipart/form-data" data-ph-apply-form data-messages="<?php echo esc_attr( (string) $messages ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( ApplicationEndpoints::ACTION ); ?>" />
					<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>" />
					<input type="hidden" name="job_id" value="" data-ph-job-id-field />
					<div class="ph-visually-hidden" aria-hidden="true"><label><?php esc_html_e( 'Leave this field empty', 'poolhall-integration' ); ?>
						<input type="text" name="<?php echo esc_attr( ApplicationEndpoints::HONEYPOT_FIELD ); ?>" tabindex="-1" autocomplete="off" value="" /></label></div>

					<div class="ph-modal__body" data-ph-panel="1">
						<p class="ph-small ph-text-muted"><?php esc_html_e( 'Takes about two minutes. Fields marked * are required.', 'poolhall-integration' ); ?></p>
						<div class="ph-form-grid">
							<?php
							echo $this->text_field( 'first_name', __( 'First name', 'poolhall-integration' ), 'text', 'given-name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* internally.
							echo $this->text_field( 'last_name', __( 'Last name', 'poolhall-integration' ), 'text', 'family-name' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
						<?php
						echo $this->text_field( 'email', __( 'Email', 'poolhall-integration' ), 'email', 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $this->text_field( 'phone', __( 'Phone', 'poolhall-integration' ), 'tel', 'tel' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
						<div class="ph-modal__foot">
							<button type="button" class="ph-button ph-button--ghost" data-ph-apply-close><?php esc_html_e( 'Cancel', 'poolhall-integration' ); ?></button>
							<span class="ph-modal__spacer"></span>
							<button type="button" class="ph-button ph-button--primary" data-ph-next><?php esc_html_e( 'Continue', 'poolhall-integration' ); ?></button>
						</div>
					</div>

					<div class="ph-modal__body" data-ph-panel="2" hidden>
						<p class="ph-small ph-text-muted"><?php esc_html_e( 'Almost there. Add your CV and an optional note.', 'poolhall-integration' ); ?></p>
						<div class="ph-field">
							<label class="ph-field__label" for="ph-apply-cv"><?php esc_html_e( 'Upload CV *', 'poolhall-integration' ); ?></label>
							<label class="ph-dropzone" data-ph-dropzone>
								<svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
								<span class="ph-dropzone__main" data-ph-dropzone-label><?php esc_html_e( 'Drag and drop or browse', 'poolhall-integration' ); ?></span>
								<span class="ph-dropzone__sub"><?php esc_html_e( 'PDF or Word, max 25 MB', 'poolhall-integration' ); ?></span>
								<input type="file" name="cv" id="ph-apply-cv" accept=".pdf,.doc,.docx" hidden data-ph-cv />
							</label>
							<p class="ph-field__error" data-ph-error="cv" hidden></p>
						</div>
						<div class="ph-field">
							<label class="ph-field__label" for="ph-apply-message"><?php esc_html_e( 'Message (optional)', 'poolhall-integration' ); ?></label>
							<textarea class="ph-field__control" id="ph-apply-message" name="message" placeholder="<?php esc_attr_e( 'A short note about why you are a great fit', 'poolhall-integration' ); ?>"></textarea>
						</div>
						<label class="ph-checkbox"><input type="checkbox" name="consent" value="1" data-ph-consent /> <span class="ph-body"><?php echo wp_kses_post( $consent_link ); ?> *</span></label>
						<p class="ph-field__error" data-ph-error="consent" hidden></p>
						<div class="ph-modal__foot">
							<button type="button" class="ph-button ph-button--ghost" data-ph-back><?php esc_html_e( 'Back', 'poolhall-integration' ); ?></button>
							<span class="ph-modal__spacer"></span>
							<button type="submit" class="ph-button ph-button--primary" data-ph-submit><?php esc_html_e( 'Submit application', 'poolhall-integration' ); ?></button>
						</div>
						<p class="ph-modal__secure"><?php esc_html_e( 'Secure and GDPR-compliant. Protected against spam.', 'poolhall-integration' ); ?></p>
					</div>
				</form>

				<div class="ph-modal__success" data-ph-apply-success hidden>
					<span class="ph-modal__tick" aria-hidden="true"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></span>
					<h2 class="ph-h3" style="margin: 0;"><?php esc_html_e( 'Application sent', 'poolhall-integration' ); ?></h2>
					<p class="ph-body" data-ph-success-text><?php esc_html_e( 'Thanks! Your application is on its way to our team. We will be in touch within two working days.', 'poolhall-integration' ); ?></p>
					<div class="ph-cluster" style="justify-content: center;">
						<a class="ph-button ph-button--primary" href="<?php echo esc_url( home_url( '/jobs/' ) ); ?>"><?php esc_html_e( 'Browse more jobs', 'poolhall-integration' ); ?></a>
						<button type="button" class="ph-button ph-button--ghost" data-ph-apply-close><?php esc_html_e( 'Close', 'poolhall-integration' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	private function text_field( string $name, string $label, string $type, string $autocomplete ): string {
		$id = 'ph-apply-' . $name;
		return '<div class="ph-field">'
			. '<label class="ph-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $label ) . ' *</label>'
			. '<input class="ph-field__control" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" type="' . esc_attr( $type ) . '" autocomplete="' . esc_attr( $autocomplete ) . '" />'
			. '<p class="ph-field__error" data-ph-error="' . esc_attr( $name ) . '" hidden></p>'
			. '</div>';
	}
}
