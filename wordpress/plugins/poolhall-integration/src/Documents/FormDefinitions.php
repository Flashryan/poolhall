<?php
/**
 * Gravity Forms definitions for the candidate document workflow.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Documents;

/**
 * Pure array builders for the five document forms (candidate profile,
 * Agency Worker Contract, HMRC starter checklist, payroll details,
 * candidate terms).
 * Kept as plain data so the shape is unit-testable without WordPress or Gravity Forms
 * loaded; DocumentWorkflow feeds these to GFAPI on the setup run.
 *
 * Notifications are deliberately empty: the workflow sends its own
 * emails after the PDF is generated (custom wp_mail path), which is the
 * reliable ordering for attachments.
 */
final class FormDefinitions {

	public const TITLE_FULL     = 'Poolhall Full Candidate Registration';
	public const TITLE_CONTRACT = 'Poolhall Agency Worker Contract';
	public const TITLE_STARTER  = 'Poolhall Starter Checklist';
	public const TITLE_PAYROLL  = 'Poolhall Payroll Details';
	public const TITLE_TERMS    = 'Poolhall Candidate Terms of Service';

	public const DECLARATION = 'I confirm that the information I have provided is true, complete and accurate. I understand that false or misleading information may affect my registration or employment. By ticking this declaration and signing electronically, I confirm that I intend this electronic signature to have the same effect as a handwritten signature.';

	private const STARTER_DECLARATION = 'I confirm that the information I\'ve given on this form is correct. By signing electronically, I confirm that I intend this electronic signature to have the same effect as a handwritten signature.';

	private const PAYROLL_DECLARATION = 'I confirm that the payroll and payment information I have provided is true, complete and accurate. I understand that Poolhall Recruitment will use it to administer payroll and contact my nominated person in an emergency. By ticking this declaration and signing electronically, I confirm that I intend this electronic signature to have the same effect as a handwritten signature.';

	private const CONTRACT_DECLARATION = 'I confirm that I have read and understood the Contract for Services supplied above and agree to be bound by its terms. By signing electronically, I confirm that I intend this electronic signature to have the same effect as a handwritten signature.';

	private const TERMS_PLACEHOLDER = 'Approved candidate terms have not yet been supplied. This form is inactive and cannot be issued to candidates.';

	private const TERMS_DECLARATION = 'I confirm that I have read, understood and agree to the terms shown above. By ticking the confirmation boxes and signing electronically, I confirm that I intend this electronic signature to have the same effect as a handwritten signature.';

	public static function declaration_for( string $kind ): string {
		return match ( $kind ) {
			'contract' => self::CONTRACT_DECLARATION,
			'starter' => self::STARTER_DECLARATION,
			'payroll' => self::PAYROLL_DECLARATION,
			'terms'   => self::TERMS_DECLARATION,
			default   => self::DECLARATION,
		};
	}

	/** Canonical text used for immutable Terms version hashes. */
	public static function canonical_terms_text( string $terms ): string {
		$canonical = preg_replace( '/\s+/u', ' ', trim( $terms ) );
		return is_string( $canonical ) ? $canonical : '';
	}

	/** Whether the current approved Terms exactly match a retained snapshot. */
	public static function candidate_terms_ready(): bool {
		if ( ! function_exists( 'get_option' ) ) {
			return false;
		}

		$text    = self::canonical_terms_text( (string) get_option( 'poolhall_candidate_terms_text', '' ) );
		$version = self::canonical_terms_text( (string) get_option( 'poolhall_candidate_terms_version', '' ) );
		$hash    = '' !== $text ? hash( 'sha256', $text ) : '';
		$history = (array) get_option( 'poolhall_candidate_terms_versions', array() );
		$bucket  = isset( $history[ $hash ] ) && is_array( $history[ $hash ] ) ? $history[ $hash ] : array();
		// New snapshots are nested by version so identical wording can retain
		// multiple labels. Accept the earlier flat shape during migration.
		$snapshot = isset( $bucket[ $version ] ) && is_array( $bucket[ $version ] ) ? $bucket[ $version ] : $bucket;
		$known    = self::canonical_terms_text( (string) ( $snapshot['text'] ?? '' ) );
		$label    = self::canonical_terms_text( (string) ( $snapshot['version'] ?? '' ) );

		return '1' === (string) get_option( 'poolhall_candidate_terms_approved', '' )
			&& '' !== $text
			&& '' !== $version
			&& '' !== $known
			&& hash_equals( $text, $known )
			&& hash_equals( $version, $label )
			&& hash_equals( $hash, hash( 'sha256', $known ) );
	}

	// ------------------------------------------------------------ helpers --

	/**
	 * @param array<string,mixed> $extra Extra field settings.
	 * @return array<string,mixed>
	 */
	private static function field( int $id, string $type, string $label, bool $required = false, array $extra = array() ): array {
		return array_merge(
			array(
				'id'         => $id,
				'type'       => $type,
				'label'      => $label,
				'isRequired' => $required,
			),
			$extra
		);
	}

	/**
	 * UK-friendly date input with both a calendar picker and manual entry.
	 *
	 * @param array<string,mixed> $extra Extra field settings.
	 * @return array<string,mixed>
	 */
	private static function date_field( int $id, string $label, bool $required = false, array $extra = array() ): array {
		return self::field(
			$id,
			'date',
			$label,
			$required,
			array_merge(
				array(
					'dateType'         => 'datepicker',
					'dateFormat'       => 'dmy',
					'calendarIconType' => 'calendar',
				),
				$extra
			)
		);
	}

	/**
	 * @param string[] $labels Choice labels.
	 * @return array<int,array<string,string>>
	 */
	private static function choices( array $labels ): array {
		return array_map(
			static fn( string $l ): array => array(
				'text'  => $l,
				'value' => $l,
			),
			$labels
		);
	}

	/**
	 * @param array<string,mixed> $extra Extra settings.
	 * @return array<string,mixed>
	 */
	private static function yes_no( int $id, string $label, bool $required = true, array $extra = array() ): array {
		return self::field(
			$id,
			'radio',
			$label,
			$required,
			array_merge( array( 'choices' => self::choices( array( 'Yes', 'No' ) ) ), $extra )
		);
	}

	/**
	 * Show-this-field-when rule set.
	 *
	 * @param array<int,array{0:int,1:string}> $rules   fieldId/value pairs.
	 */
	private static function when( array $rules, string $logic = 'all' ): array {
		return array(
			'actionType' => 'show',
			'logicType'  => $logic,
			'rules'      => array_map(
				static fn( array $r ): array => array(
					'fieldId'  => $r[0],
					'operator' => 'is',
					'value'    => $r[1],
				),
				$rules
			),
		);
	}

	private static function page_break( int $id ): array {
		return self::field( $id, 'page', '' );
	}

	private static function html( int $id, string $content, array $extra = array() ): array {
		return self::field( $id, 'html', '', false, array_merge( array( 'content' => $content ), $extra ) );
	}

	private static function signature_block( int $html_id, string $declaration, int $checkbox_id, int $signature_id, int $date_id, ?array $conditional_logic = null ): array {
		$conditional = null === $conditional_logic ? array() : array( 'conditionalLogic' => $conditional_logic );
		return array(
			self::html( $html_id, '<div class="poolhall-form-note"><p>' . esc_html( $declaration ) . '</p></div>', $conditional ),
			self::field(
				$checkbox_id,
				'checkbox',
				'Declaration',
				true,
				array_merge(
					array(
						'choices' => self::choices( array( 'I have read and agree to the declaration above.' ) ),
						'inputs'  => array(
							array(
								'id'    => $checkbox_id . '.1',
								'label' => 'I have read and agree to the declaration above.',
							),
						),
					),
					$conditional
				)
			),
			self::field( $signature_id, 'poolhall_signature', 'Electronic signature', true, $conditional ),
			self::date_field(
				$date_id,
				'Date signed',
				true,
				array_merge(
					array(
						'defaultValue' => '{date_dmy}',
					),
					$conditional
				)
			),
		);
	}

	/**
	 * @param array<int,array<string,mixed>> $fields Form fields.
	 * @param string[]                       $pages  Page titles for the progress steps.
	 * @return array<string,mixed>
	 */
	private static function form( string $title, string $description, array $fields, array $pages = array(), bool $save_enabled = false ): array {
		$form = array(
			'title'             => $title,
			'description'       => $description,
			'labelPlacement'    => 'top_label',
			'button'            => array(
				'type' => 'text',
				'text' => 'Submit signed form',
			),
			'fields'            => $fields,
			'enableHoneypot'    => true,
			'honeypotAction'    => 'abort',
			'validationSummary' => true,
			'requireLogin'      => false,
			'cssClass'          => 'poolhall-form poolhall-document-form',
			'notifications'     => array(),
			'confirmations'     => array(
				array(
					'id'        => 'default',
					'name'      => 'Default',
					'isDefault' => true,
					'type'      => 'message',
					'message'   => '<h3>Thank you.</h3><p>Your signed form has been submitted to Poolhall Recruitment. A member of the team will contact you if anything else is required.</p>',
				),
			),
			'personalData'      => array(
				'preventIP' => true,
			),
			'save'              => array(
				'enabled' => $save_enabled,
				'button'  => array(
					'type' => 'link',
					'text' => 'Save and finish later',
				),
			),
			'is_active'         => '1',
			'is_trash'          => '0',
		);
		if ( array() !== $pages ) {
			$form['pagination'] = array(
				'type'  => 'steps',
				'pages' => $pages,
			);
		}
		return $form;
	}

	// ------------------------------------------------- full registration --

	/** @return array<string,mixed> */
	public static function full_registration(): array {
		$f = array();

		// Page 1 — Contact details. Payroll-only identity fields live in the
		// separate payroll form and are deliberately not collected here.
		$f[] = self::field( 1, 'select', 'Title', true, array( 'choices' => self::choices( array( 'Mr', 'Mrs', 'Miss', 'Ms', 'Mx', 'Dr', 'Other' ) ) ) );
		$f[] = self::field( 2, 'text', 'Forename', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 3, 'text', 'Surname', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 4, 'text', 'Address line 1', true );
		$f[] = self::field( 5, 'text', 'Address line 2' );
		$f[] = self::field( 6, 'text', 'Town / City', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 7, 'text', 'County', false, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 8, 'text', 'Postcode', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 11, 'email', 'Email', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field(
			12,
			'phone',
			'Mobile',
			true,
			array(
				'phoneFormat' => 'international',
				'cssClass'    => 'gf_left_half',
			)
		);
		$f[] = self::page_break( 18 );

		// Page 2 — Eligibility and work preferences.
		$f[] = self::yes_no( 19, 'Are you eligible to work in the UK?' );
		$f[] = self::yes_no( 20, 'Do you require or hold a work permit?' );
		$f[] = self::field( 21, 'textarea', 'Work permit details', true, array( 'conditionalLogic' => self::when( array( array( 20, 'Yes' ) ) ) ) );
		$f[] = self::date_field( 26, 'Date you are available to begin work', true );
		$f[] = self::field( 28, 'select', 'Your preferred shift', true, array( 'choices' => self::choices( array( 'Days', 'Nights', 'Weekends', 'Rotating shifts', 'Flexible / any' ) ) ) );
		$f[] = self::yes_no( 29, 'Do you have your own transport?' );
		$f[] = self::page_break( 30 );

		// Page 3 — Employment history (three conditional blocks).
		$employer = static function ( int $base, string $suffix, ?array $logic ): array {
			$extra = null === $logic ? array() : array( 'conditionalLogic' => $logic );
			return array(
				self::field( $base, 'text', 'Employer name' . $suffix, false, $extra ),
				self::field( $base + 1, 'text', 'Location' . $suffix, false, $extra ),
				self::field( $base + 2, 'text', 'Role' . $suffix, false, $extra ),
				self::field(
					$base + 3,
					'select',
					'From - month' . $suffix,
					false,
					array_merge(
						$extra,
						array(
							'choices'  => self::choices( array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ) ),
							'cssClass' => 'gf_left_half',
						)
					)
				),
				self::field( $base + 4, 'text', 'From - year' . $suffix, false, array_merge( $extra, array( 'cssClass' => 'gf_right_half ph-year' ) ) ),
				self::field(
					$base + 5,
					'select',
					'To - month' . $suffix,
					false,
					array_merge(
						$extra,
						array(
							'choices'  => self::choices( array( 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December' ) ),
							'cssClass' => 'gf_left_half',
						)
					)
				),
				self::field( $base + 6, 'text', 'To - year' . $suffix, false, array_merge( $extra, array( 'cssClass' => 'gf_right_half ph-year' ) ) ),
				self::field( $base + 7, 'textarea', 'Responsibilities and reason for leaving' . $suffix, false, $extra ),
			);
		};

		$f[] = self::field(
			74,
			'checkbox',
			'Employment history',
			false,
			array(
				'choices' => self::choices( array( 'I have no previous employment' ) ),
				'inputs'  => array(
					array(
						'id'    => '74.1',
						'label' => 'I have no previous employment',
					),
				),
			)
		);
		$f   = array_merge( $f, $employer( 48, ' (most recent employer)', null ) );
		$f[] = self::yes_no( 56, 'Add another employer?', false );
		$f   = array_merge( $f, $employer( 57, ' (second employer)', self::when( array( array( 56, 'Yes' ) ) ) ) );
		$f[] = self::yes_no( 65, 'Add a third employer?', false, array( 'conditionalLogic' => self::when( array( array( 56, 'Yes' ) ) ) ) );
		$f   = array_merge( $f, $employer( 66, ' (third employer)', self::when( array( array( 65, 'Yes' ) ) ) ) );
		$f[] = self::yes_no( 75, 'Do you have any gaps in your employment history?' );
		$f[] = self::field( 76, 'textarea', 'Please provide details of the gaps', true, array( 'conditionalLogic' => self::when( array( array( 75, 'Yes' ) ) ) ) );
		$f[] = self::page_break( 77 );

		// Page 4 — Reference and qualifications. The CV captured at first
		// contact is reused, so there is no second CV question or upload.
		$f[] = self::yes_no( 78, 'This person has agreed to be contacted' );
		$f[] = self::field( 79, 'text', 'Referee full name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 80, 'text', 'Referee job title', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 81, 'email', 'Referee email', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field(
			82,
			'phone',
			'Referee telephone',
			true,
			array(
				'phoneFormat' => 'international',
				'cssClass'    => 'gf_right_half',
			)
		);
		$f[] = self::yes_no( 85, 'Do you have additional qualifications?' );
		$f[] = self::field( 86, 'textarea', 'Additional qualification details', true, array( 'conditionalLogic' => self::when( array( array( 85, 'Yes' ) ) ) ) );
		$f[] = self::page_break( 87 );

		// Page 5 — Declaration and signature.
		$f[] = self::field( 91, 'text', 'Full name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 92, 'text', 'Job role', true, array( 'cssClass' => 'gf_right_half' ) );
		$f   = array_merge( $f, self::signature_block( 88, self::DECLARATION, 89, 93, 94 ) );
		$f[] = self::html( 96, '<div class="poolhall-form-note poolhall-form-final"><p><strong>Ready to submit?</strong> When you press the button below, your signed registration will be securely sent to the Poolhall Recruitment team. You will see a confirmation on screen and receive a short email receipt.</p></div>' );

		return self::form(
			self::TITLE_FULL,
			'Complete your candidate profile for Poolhall Recruitment. Payroll and starter information is requested separately only when needed.',
			$f,
			array( 'Contact details', 'Eligibility & preferences', 'Employment history', 'References & qualifications', 'Declaration & signature' ),
			true
		);
	}

	// ---------------------------------------------- Agency Worker Contract --

	/** @return array<string,mixed> */
	public static function agency_worker_contract(): array {
		$f   = array();
		$f[] = self::field( 1, 'text', 'Candidate full name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 2, 'email', 'Email', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::date_field(
			3,
			'Contract effective date',
			true,
			array(
				'cssClass'     => 'gf_left_half',
				'defaultValue' => '{date_dmy}',
			)
		);
		$f[] = self::field( 4, 'text', 'Address line 1', true );
		$f[] = self::field( 5, 'text', 'Address line 2' );
		$f[] = self::field( 6, 'text', 'Town / City', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 7, 'text', 'County', false, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 8, 'text', 'Postcode', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::html(
			9,
			'<div class="poolhall-contract-review"><h3>Read the Contract for Services</h3><p>The name, effective date and address above will be added to the first page. Your electronic signature, printed name and signing date will be added to the execution section on the final page.</p><p><a class="button poolhall-contract-review__link" href="{{poolhall_contract_review_url}}" target="_blank" rel="noopener">Open the 12-page contract PDF</a></p><object class="poolhall-contract-review__viewer" data="{{poolhall_contract_review_url}}" type="application/pdf" aria-label="Poolhall Contract for Services"><p>Your browser cannot show the PDF here. <a href="{{poolhall_contract_review_url}}" target="_blank" rel="noopener">Open the contract in a new tab</a>.</p></object></div>'
		);
		$f[] = self::field(
			10,
			'checkbox',
			'Contract agreement',
			true,
			array(
				'choices' => self::choices( array( 'I have read and understood the Contract for Services and agree to be bound by its terms.' ) ),
				'inputs'  => array(
					array(
						'id'    => '10.1',
						'label' => 'I have read and understood the Contract for Services and agree to be bound by its terms.',
					),
				),
			)
		);
		$f   = array_merge( $f, self::signature_block( 11, self::CONTRACT_DECLARATION, 12, 13, 14 ) );

		return self::form(
			self::TITLE_CONTRACT,
			'Review and electronically sign the Poolhall Recruitment Contract for Services issued after your offer has been accepted.',
			$f
		);
	}

	// -------------------------------------------------- starter checklist --

	/**
	 * HMRC's Starter checklist (HMRC 09/22) in four short steps. Questions
	 * 8-10 appear one at a time and the matching HMRC statement shows as soon
	 * as it is decided, so the candidate never has to choose A, B or C; the
	 * server works out and stores the same result (StarterChecklist). The P45
	 * upload sits on the last step so the file goes straight to private
	 * storage on submit rather than waiting in Gravity Forms' temp folder
	 * between pages.
	 *
	 * @return array<string,mixed>
	 */
	public static function starter_statement(): array {
		$f      = array();
		$inline = array( 'cssClass' => 'gf_list_inline' );

		// Step 1 - HMRC questions 1-7.
		$f[] = self::html(
			StarterChecklist::INTRO,
			'<div class="poolhall-form-note ph-starter-intro"><p><strong>This is HMRC&rsquo;s starter checklist, completed online.</strong> It takes about five minutes.</p>'
			. '<p>Payroll uses your answers to set the right tax code and any student loan repayments from your first payslip, so you don&rsquo;t pay too much or too little tax. Your answers come securely to Poolhall &ndash; there&rsquo;s nothing to send to HMRC.</p>'
			. '<p><strong>Useful to have:</strong> your National Insurance number and, if you have one, the P45 from a job you&rsquo;ve left.</p></div>'
		);
		$f[] = self::field(
			StarterChecklist::FIRST_NAMES,
			'text',
			'First names',
			true,
			array(
				'description'           => 'All your first names in full, including any middle names. Please don&rsquo;t use initials or shortened names &ndash; for example, James not Jim.',
				'enableAutocomplete'    => true,
				'autocompleteAttribute' => 'given-name',
			)
		);
		$f[] = self::field(
			StarterChecklist::LAST_NAME,
			'text',
			'Last name',
			true,
			array(
				'enableAutocomplete'    => true,
				'autocompleteAttribute' => 'family-name',
			)
		);
		$f[] = self::field(
			StarterChecklist::SEX,
			'radio',
			'What is your sex?',
			true,
			array_merge(
				$inline,
				array(
					'description' => 'As shown on your birth certificate or gender recognition certificate. HMRC uses this to match your tax record.',
					'choices'     => self::choices( array( 'Male', 'Female' ) ),
				)
			)
		);
		$f[] = self::date_field(
			StarterChecklist::BIRTH_DATE,
			'Date of birth',
			true,
			array(
				'dateType'    => 'datefield',
				'description' => 'For example, 27 03 1990',
			)
		);
		$f[] = self::field(
			StarterChecklist::NI_NUMBER,
			'text',
			'National Insurance number (if you know it)',
			false,
			array(
				'description' => 'It&rsquo;s on your payslips, P60 or P45, and in the HMRC app. It looks like QQ 12 34 56 C. Leave this blank if you don&rsquo;t know it.',
				'cssClass'    => 'ph-ni',
				'placeholder' => 'QQ 12 34 56 C',
			)
		);
		$f[] = self::date_field(
			StarterChecklist::START_DATE,
			'Employment start date',
			true,
			array(
				'description' => 'Your first day working through Poolhall.',
			)
		);
		$f[] = self::field( 27, 'section', 'Home address' );
		$f[] = self::field( StarterChecklist::ADDRESS_1, 'text', 'Address line 1', true, self::autocomplete( 'address-line1' ) );
		$f[] = self::field( StarterChecklist::ADDRESS_2, 'text', 'Address line 2', false, self::autocomplete( 'address-line2' ) );
		$f[] = self::field( StarterChecklist::TOWN, 'text', 'Town or city', true, self::autocomplete( 'address-level2', 'gf_left_half' ) );
		$f[] = self::field( StarterChecklist::COUNTY, 'text', 'County', false, self::autocomplete( 'address-level1', 'gf_right_half' ) );
		$f[] = self::field( StarterChecklist::POSTCODE, 'text', 'Postcode', true, self::autocomplete( 'postal-code', 'gf_left_half ph-postcode' ) );
		$f[] = self::field( StarterChecklist::COUNTRY, 'text', 'Country', true, array_merge( self::autocomplete( 'country-name', 'gf_right_half' ), array( 'defaultValue' => 'United Kingdom' ) ) );
		$f[] = self::step_break( 34 );

		// Step 2 - HMRC questions 8-10 and the statement they lead to.
		$f[] = self::html( 35, '<div class="poolhall-form-note"><p><strong>Three quick questions set your tax code.</strong> Your answers pick HMRC&rsquo;s employee statement A, B or C for you, so there&rsquo;s nothing to look up.</p></div>' );
		$f[] = self::yes_no(
			StarterChecklist::ANOTHER_JOB,
			'Do you have another job?',
			true,
			array_merge( $inline, array( 'description' => 'A job you&rsquo;ll keep doing alongside your work through Poolhall.' ) )
		);
		$f[] = self::yes_no(
			StarterChecklist::PENSION,
			'Do you receive payments from a State, workplace or private pension?',
			true,
			array_merge(
				$inline,
				array(
					'description'      => self::details(
						'What counts as a pension?',
						'<ul><li><strong>State Pension</strong> &ndash; paid when you reach State Pension age.</li>'
						. '<li><strong>Workplace pension</strong> &ndash; arranged by an employer and being paid to you.</li>'
						. '<li><strong>Private pension</strong> &ndash; arranged by you and being paid to you.</li></ul>'
					),
					'conditionalLogic' => self::when( array( array( StarterChecklist::ANOTHER_JOB, 'No' ) ) ),
				)
			)
		);
		$f[] = self::yes_no(
			StarterChecklist::SINCE_APRIL,
			'Since 6 April, have you received payments from another job which has ended, or from Jobseeker’s Allowance, Employment and Support Allowance or Incapacity Benefit?',
			true,
			array_merge(
				$inline,
				array(
					'description'      => 'That&rsquo;s since {{tax_year_start}}, when this tax year began. Only count the benefits named here &ndash; no other government or HMRC benefits need to be considered.'
						. self::details(
							'About these benefits',
							'<ul><li><strong>Jobseeker&rsquo;s Allowance (JSA)</strong> &ndash; an unemployment benefit you can claim while looking for work.</li>'
							. '<li><strong>Employment and Support Allowance (ESA)</strong> &ndash; a benefit you can claim if you have a disability or health condition that affects how much you can work.</li>'
							. '<li><strong>Incapacity Benefit</strong> &ndash; help if you could not work because of an illness or disability before 31 January 2011.</li></ul>'
						),
					'conditionalLogic' => self::when(
						array(
							array( StarterChecklist::ANOTHER_JOB, 'No' ),
							array( StarterChecklist::PENSION, 'No' ),
						)
					),
				)
			)
		);
		$f[] = self::html(
			39,
			self::statement_card( 'C', 'Payroll will use tax code BR, so pay from this job is taxed at the basic rate. That&rsquo;s because your tax-free personal allowance is usually used by your other job or pension. If HMRC issues a different code, payroll will switch to it.' ),
			array(
				'conditionalLogic' => self::when(
					array(
						array( StarterChecklist::ANOTHER_JOB, 'Yes' ),
						array( StarterChecklist::PENSION, 'Yes' ),
					),
					'any'
				),
			)
		);
		$f[] = self::html(
			40,
			self::statement_card( 'B', 'Payroll will use your tax-free personal allowance on a &lsquo;week 1/month 1&rsquo; basis, so each payslip is taxed on its own until HMRC sends your full tax code. Got a P45 from a job this tax year? You can upload it on the last step.' ),
			array(
				'conditionalLogic' => self::when(
					array(
						array( StarterChecklist::ANOTHER_JOB, 'No' ),
						array( StarterChecklist::PENSION, 'No' ),
						array( StarterChecklist::SINCE_APRIL, 'Yes' ),
					)
				),
			)
		);
		$f[] = self::html(
			41,
			self::statement_card( 'A', 'Payroll will use the standard tax code, with your full tax-free personal allowance.' ),
			array(
				'conditionalLogic' => self::when(
					array(
						array( StarterChecklist::ANOTHER_JOB, 'No' ),
						array( StarterChecklist::PENSION, 'No' ),
						array( StarterChecklist::SINCE_APRIL, 'No' ),
					)
				),
			)
		);
		// Filled in on the server from questions 8-10; whatever the browser posts is ignored.
		$f[] = self::field( StarterChecklist::STATEMENT, 'hidden', 'Employee statement (from questions 8-10)' );
		$f[] = self::step_break( 43 );

		// Step 3 - HMRC questions 11-13.
		$f[] = self::html( 48, '<div class="poolhall-form-note"><p>Telling payroll about student loans now means the right repayments are taken &ndash; and stops you repaying more than you need to.</p></div>' );
		$f[] = self::yes_no( StarterChecklist::HAS_LOAN, 'Do you have a student or postgraduate loan?', true, $inline );
		$f[] = self::yes_no(
			StarterChecklist::LOAN_EXEMPT,
			'Do any of these apply to you?',
			true,
			array_merge(
				$inline,
				array(
					'description'      => '<ul class="ph-hint-list"><li>you&rsquo;re still studying on a course that your student loan relates to</li>'
						. '<li>you completed or left your course after {{tax_year_start}}</li>'
						. '<li>you&rsquo;ve already repaid your loan in full</li>'
						. '<li>you&rsquo;re paying the Student Loans Company by Direct Debit from your bank to manage the end of your loan repayments</li></ul>',
					'conditionalLogic' => self::when( array( array( StarterChecklist::HAS_LOAN, 'Yes' ) ) ),
				)
			)
		);
		$f[] = self::html(
			51,
			'<div class="poolhall-form-note"><p><strong>That&rsquo;s all for student loans.</strong> Payroll won&rsquo;t take loan repayments from your pay for now &ndash; if that changes, HMRC will let us know.</p></div>',
			array(
				'conditionalLogic' => self::when(
					array(
						array( StarterChecklist::HAS_LOAN, 'Yes' ),
						array( StarterChecklist::LOAN_EXEMPT, 'Yes' ),
					)
				),
			)
		);
		$loan_choices = array();
		$loan_inputs  = array();
		$labels       = array( 'Not sure' => 'I&rsquo;m not sure which plan I&rsquo;m on' );
		$i            = 0;
		foreach ( StarterChecklist::LOAN_CHOICES as $value => $hint ) {
			++$i;
			$loan_choices[] = array(
				'text'  => '<span class="ph-choice-label">' . ( $labels[ $value ] ?? esc_html( $value ) ) . '</span><span class="ph-choice-hint">' . esc_html( $hint ) . '</span>',
				'value' => $value,
			);
			$loan_inputs[]  = array(
				'id'    => StarterChecklist::LOAN_TYPES . '.' . $i,
				'label' => $value,
			);
		}
		$f[] = self::field(
			StarterChecklist::LOAN_TYPES,
			'checkbox',
			'Which student loans do you have?',
			true,
			array(
				'description'      => 'Tick all that apply. Not sure? Check your plan type in your <a href="https://www.gov.uk/sign-in-to-manage-your-student-loan-balance" target="_blank" rel="noopener">Student Loans Company account</a>.',
				'choices'          => $loan_choices,
				'inputs'           => $loan_inputs,
				'cssClass'         => 'ph-loan-types',
				'conditionalLogic' => self::when(
					array(
						array( StarterChecklist::HAS_LOAN, 'Yes' ),
						array( StarterChecklist::LOAN_EXEMPT, 'No' ),
					)
				),
			)
		);
		$f[] = self::step_break( 53 );

		// Step 4 - check answers, optional P45, HMRC declaration.
		$f[] = self::html( 54, self::starter_summary_panel() );
		$f[] = self::field( 56, 'section', 'Your P45', false, array( 'description' => 'The form you get from an employer when you leave a job. It&rsquo;s optional &ndash; but if it&rsquo;s from this tax year, payroll can use the exact tax code on it.' ) );
		$f[] = self::field(
			StarterChecklist::P45,
			'radio',
			'Do you have a P45 from a previous employer?',
			true,
			array(
				'choices' => array(
					array(
						'text'  => 'Yes – I’ll upload it now',
						'value' => StarterChecklist::P45_UPLOADED,
					),
					array(
						'text'  => 'Yes, but I don’t have it with me',
						'value' => StarterChecklist::P45_LATER,
					),
					array(
						'text'  => 'No',
						'value' => StarterChecklist::P45_NONE,
					),
				),
			)
		);
		$f[] = self::field(
			StarterChecklist::P45_UPLOAD,
			'fileupload',
			'Upload your P45',
			true,
			array(
				'description'       => 'A PDF or a clear photo where the tax code and leaving date can be read. Up to 10 MB.',
				'allowedExtensions' => 'pdf,jpg,jpeg,png,doc,docx',
				'maxFileSize'       => 10,
				'conditionalLogic'  => self::when( array( array( StarterChecklist::P45, StarterChecklist::P45_UPLOADED ) ) ),
			)
		);
		$f[] = self::html(
			46,
			'<div class="poolhall-form-note"><p><strong>No problem.</strong> Carry on and submit your checklist. When you find your P45, tell your Poolhall consultant and they&rsquo;ll add it to your file. Until then, payroll will use the statement worked out from your answers.</p></div>',
			array( 'conditionalLogic' => self::when( array( array( StarterChecklist::P45, StarterChecklist::P45_LATER ) ) ) )
		);
		$f[]                 = self::field( 57, 'section', 'Declaration' );
		$sign                = self::signature_block( 14, self::STARTER_DECLARATION, 15, 16, 17 );
		$sign[0]['content'] .= '<p class="ph-signing-as" data-ph-starter-signer hidden>Signing as <strong></strong></p>';
		$f                   = array_merge( $f, $sign );
		$f[]                 = self::html( 55, '<div class="poolhall-form-note poolhall-form-final"><p><strong>Ready to submit?</strong> Your checklist goes securely to Poolhall&rsquo;s payroll team and you&rsquo;ll get an email receipt. There&rsquo;s nothing to send to HMRC.</p></div>' );

		$form = self::form(
			self::TITLE_STARTER,
			'HMRC Starter checklist (HMRC 09/22) completed online for payroll. For employer use only - do not send to HMRC.',
			$f,
			array( 'Your details', 'Tax code', 'Student loans', 'P45 & sign' )
		);

		$form['descriptionPlacement'] = 'above';
		$form['button']['text']       = 'Submit my starter checklist';
		$form['lastPageButton']       = array(
			'type' => 'text',
			'text' => 'Back',
		);
		$done                         = '<h3>Thank you &ndash; your starter checklist is complete</h3><p>It&rsquo;s gone securely to Poolhall&rsquo;s payroll team, who will use it to set your tax code and any student loan repayments. We&rsquo;ve emailed you a receipt.</p>';
		$form['confirmations']        = array(
			array(
				'id'               => 'p45-later',
				'name'             => 'P45 to follow',
				'isDefault'        => false,
				'type'             => 'message',
				'message'          => $done . '<p><strong>Don&rsquo;t forget your P45.</strong> When you find it, tell your Poolhall consultant and they&rsquo;ll add it to your file.</p>',
				'conditionalLogic' => self::when( array( array( StarterChecklist::P45, StarterChecklist::P45_LATER ) ) ),
			),
			array(
				'id'        => 'default',
				'name'      => 'Default',
				'isDefault' => true,
				'type'      => 'message',
				'message'   => $done,
			),
		);

		return $form;
	}

	/** The HMRC statement the candidate's answers lead to, with what it means for their pay. */
	private static function statement_card( string $letter, string $meaning ): string {
		return '<div class="ph-statement ph-statement--' . strtolower( $letter ) . '" role="status">'
			. '<p class="ph-statement__eyebrow ph3-eyebrow">Your employee statement &ndash; worked out from your answers</p>'
			. '<p class="ph-statement__title">Statement ' . esc_html( $letter ) . '</p>'
			. '<blockquote class="ph-statement__quote">' . esc_html( StarterChecklist::STATEMENTS[ $letter ] ) . '</blockquote>'
			. '<p class="ph-statement__meaning"><strong>What this means:</strong> ' . $meaning . '</p></div>';
	}

	/**
	 * Placeholder the documents script fills with a "check your answers"
	 * list. The field map is plain key=id pairs rather than JSON because
	 * Gravity Forms treats {...:id} in HTML content as a merge tag.
	 */
	private static function starter_summary_panel(): string {
		$map = array(
			'first'   => StarterChecklist::FIRST_NAMES,
			'last'    => StarterChecklist::LAST_NAME,
			'sex'     => StarterChecklist::SEX,
			'dob'     => StarterChecklist::BIRTH_DATE,
			'ni'      => StarterChecklist::NI_NUMBER,
			'start'   => StarterChecklist::START_DATE,
			'address' => implode( ',', array( StarterChecklist::ADDRESS_1, StarterChecklist::ADDRESS_2, StarterChecklist::TOWN, StarterChecklist::COUNTY, StarterChecklist::POSTCODE, StarterChecklist::COUNTRY ) ),
			'job'     => StarterChecklist::ANOTHER_JOB,
			'pension' => StarterChecklist::PENSION,
			'april'   => StarterChecklist::SINCE_APRIL,
			'loan'    => StarterChecklist::HAS_LOAN,
			'exempt'  => StarterChecklist::LOAN_EXEMPT,
			'loans'   => StarterChecklist::LOAN_TYPES,
		);
		$pairs = array();
		foreach ( $map as $key => $ids ) {
			$pairs[] = $key . '=' . $ids;
		}
		return '<div class="ph-summary" data-ph-starter-summary data-fields="' . esc_attr( implode( ';', $pairs ) ) . '">'
			. '<h3 class="ph-summary__title">Check your answers</h3>'
			. '<p class="ph-summary__fallback">Use <strong>Back</strong> to check or change anything before you sign.</p></div>';
	}

	/** Collapsed help, so definitions are there when wanted and out of the way when not. */
	private static function details( string $summary, string $body ): string {
		return '<details class="ph-details"><summary>' . esc_html( $summary ) . '</summary>' . $body . '</details>';
	}

	/** @return array<string,mixed> */
	private static function autocomplete( string $token, string $css_class = '' ): array {
		$extra = array(
			'enableAutocomplete'    => true,
			'autocompleteAttribute' => $token,
		);
		if ( '' !== $css_class ) {
			$extra['cssClass'] = $css_class;
		}
		return $extra;
	}

	/** Page break with plain-English step buttons. */
	private static function step_break( int $id ): array {
		return self::field(
			$id,
			'page',
			'',
			false,
			array(
				'nextButton'     => array(
					'type' => 'text',
					'text' => 'Continue',
				),
				'previousButton' => array(
					'type' => 'text',
					'text' => 'Back',
				),
			)
		);
	}

	// ----------------------------------------------------------- payroll --

	/** @return array<string,mixed> */
	public static function payroll_details(): array {
		$f   = array();
		$f[] = self::field( 1, 'text', 'Full name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 2, 'email', 'Email', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 3, 'text', 'National Insurance number', true, array( 'cssClass' => 'gf_left_half ph-ni' ) );
		$f[] = self::date_field(
			4,
			'Date of birth',
			true,
			array(
				'cssClass' => 'gf_right_half',
			)
		);
		$f[] = self::field( 5, 'text', 'Account holder name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 6, 'text', 'Bank or building society name', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 7, 'text', 'Account number', true, array( 'cssClass' => 'gf_left_half ph-account-number' ) );
		$f[] = self::field( 8, 'text', 'Sort code', true, array( 'cssClass' => 'gf_right_half ph-sort-code' ) );
		$f[] = self::field( 9, 'text', 'Emergency contact name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field(
			10,
			'phone',
			'Emergency contact phone',
			true,
			array(
				'phoneFormat' => 'international',
				'cssClass'    => 'gf_right_half',
			)
		);
		$f[] = self::field( 11, 'text', 'Emergency contact relationship', true );
		$f   = array_merge( $f, self::signature_block( 12, self::PAYROLL_DECLARATION, 13, 14, 15 ) );

		return self::form(
			self::TITLE_PAYROLL,
			'Payroll and emergency contact details for candidates who have been approved to progress.',
			$f
		);
	}

	// ------------------------------------------------------------- terms --

	/**
	 * Current option copy is active only when its approval, version and retained
	 * snapshot all agree. Without that evidence this returns an inactive draft.
	 *
	 * @return array<string,mixed>
	 */
	public static function candidate_terms( ?string $approved_terms = null, ?string $approved_version = null ): array {
		$current_terms    = function_exists( 'get_option' ) ? (string) get_option( 'poolhall_candidate_terms_text', '' ) : '';
		$current_version  = function_exists( 'get_option' ) ? (string) get_option( 'poolhall_candidate_terms_version', '' ) : '';
		$approved_terms   = trim( $approved_terms ?? $current_terms );
		$approved_version = self::canonical_terms_text( $approved_version ?? $current_version );
		$canonical_terms  = self::canonical_terms_text( $approved_terms );
		$is_approved      = self::candidate_terms_ready()
			&& hash_equals( self::canonical_terms_text( $current_terms ), $canonical_terms )
			&& hash_equals( self::canonical_terms_text( $current_version ), $approved_version );
		$terms_hash       = $is_approved ? hash( 'sha256', $canonical_terms ) : '';
		if ( $is_approved ) {
			$terms_content = '<div class="poolhall-form-note poolhall-terms-body"><h3>Candidate terms of service</h3><p><strong>Terms version:</strong> '
				. esc_html( $approved_version ) . '</p><p>' . nl2br( esc_html( $approved_terms ) ) . '</p></div>';
			$description   = 'Approved candidate terms of service for electronic acceptance.';
		} else {
			$terms_content = '<div class="poolhall-form-note poolhall-terms-body"><h3>Candidate terms of service</h3><p>'
				. esc_html( self::TERMS_PLACEHOLDER )
				. '</p><p><em>[Draft wording - Poolhall Recruitment must review and replace this text with the final legal terms before sending this form to candidates.]</em></p></div>';
			$description   = 'Draft candidate terms of service form. Do not send until Poolhall Recruitment has approved final legal wording.';
		}

		$f   = array();
		$f[] = self::field( 1, 'text', 'Candidate full name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 2, 'email', 'Email', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 3, 'text', 'Job role', true );
		$f[] = self::html( 4, $terms_content );
		$f[] = self::field(
			5,
			'checkbox',
			'Agreement',
			true,
			array(
				'choices' => self::choices( array( 'I have read, understood and agree to the terms.' ) ),
				'inputs'  => array(
					array(
						'id'    => '5.1',
						'label' => 'I have read, understood and agree to the terms.',
					),
				),
			)
		);
		$f[] = self::field(
			6,
			'checkbox',
			'Electronic signing',
			true,
			array(
				'choices' => self::choices( array( 'I understand this agreement will be signed electronically.' ) ),
				'inputs'  => array(
					array(
						'id'    => '6.1',
						'label' => 'I understand this agreement will be signed electronically.',
					),
				),
			)
		);
		$f   = array_merge( $f, self::signature_block( 7, self::TERMS_DECLARATION, 8, 9, 10 ) );
		$f[] = self::field( 11, 'hidden', 'Terms version', true, array( 'defaultValue' => $is_approved ? $approved_version : '' ) );
		$f[] = self::field( 12, 'hidden', 'Terms SHA-256', true, array( 'defaultValue' => $terms_hash ) );

		$form                           = self::form(
			self::TITLE_TERMS,
			$description,
			$f
		);
		$form['is_active']              = $is_approved && ! \Poolhall\Integration\Onboarding\TaskPolicy::archived( 'terms' ) ? '1' : '0';
		$form['poolhall_terms_version'] = $is_approved ? $approved_version : '';
		$form['poolhall_terms_sha256']  = $terms_hash;

		return $form;
	}
}
