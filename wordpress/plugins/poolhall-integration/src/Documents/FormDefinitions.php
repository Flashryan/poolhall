<?php
/**
 * Gravity Forms definitions for the candidate document workflow.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Documents;

/**
 * Pure array builders for the three document forms (full registration,
 * starter statement / P45 declaration, candidate terms). Kept as plain
 * data so the shape is unit-testable without WordPress or Gravity Forms
 * loaded; DocumentWorkflow feeds these to GFAPI on the setup run.
 *
 * Notifications are deliberately empty: the workflow sends its own
 * emails after the PDF is generated (custom wp_mail path), which is the
 * reliable ordering for attachments.
 */
final class FormDefinitions {

	public const TITLE_FULL    = 'Poolhall Full Candidate Registration';
	public const TITLE_STARTER = 'Poolhall Starter Statement / P45 Declaration';
	public const TITLE_TERMS   = 'Poolhall Candidate Terms of Service';

	public const DECLARATION = 'I confirm that the information I have provided is true, complete and accurate. I understand that false or misleading information may affect my registration or employment. By ticking this declaration and signing electronically, I confirm that I intend this electronic signature to have the same effect as a handwritten signature.';

	private const STARTER_DECLARATION = 'I confirm the information provided in this Starter Statement is true and accurate to the best of my knowledge. I understand this information may be used for payroll and tax processing purposes. By signing electronically, I confirm that I intend this electronic signature to have the same effect as a handwritten signature.';

	private const TERMS_PLACEHOLDER = 'By registering with Poolhall Recruitment, I agree to comply with all reasonable policies, procedures, assignment instructions, attendance requirements, health and safety requirements, confidentiality obligations and conduct expectations communicated to me by Poolhall Recruitment or any client site where I may be placed. I understand that assignment details, pay rates, working hours and other terms may vary by role and will be confirmed separately where applicable.';

	private const TERMS_DECLARATION = 'I confirm that I have read, understood and agree to the terms shown above. By ticking the confirmation boxes and signing electronically, I confirm that I intend this electronic signature to have the same effect as a handwritten signature.';

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

	private static function html( int $id, string $content ): array {
		return self::field( $id, 'html', '', false, array( 'content' => $content ) );
	}

	private static function signature_block( int $html_id, string $declaration, int $checkbox_id, int $signature_id, int $date_id ): array {
		return array(
			self::html( $html_id, '<div class="poolhall-form-note"><p>' . esc_html( $declaration ) . '</p></div>' ),
			self::field(
				$checkbox_id,
				'checkbox',
				'Declaration',
				true,
				array(
					'choices' => self::choices( array( 'I have read and agree to the declaration above.' ) ),
					'inputs'  => array(
						array(
							'id'    => $checkbox_id . '.1',
							'label' => 'I have read and agree to the declaration above.',
						),
					),
				)
			),
			self::field( $signature_id, 'poolhall_signature', 'Electronic signature', true ),
			self::field(
				$date_id,
				'date',
				'Date signed',
				true,
				array(
					'dateFormat'   => 'dmy',
					'defaultValue' => '{date_dmy}',
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
				'preventIP' => false,
			),
			'save'              => array(
				'enabled' => $save_enabled,
				'button'  => array(
					'type' => 'link',
					'text' => 'Save and continue later',
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

		// Page 1 — Personal details.
		$f[] = self::field( 1, 'select', 'Title', true, array( 'choices' => self::choices( array( 'Mr', 'Mrs', 'Miss', 'Ms', 'Mx', 'Dr', 'Other' ) ) ) );
		$f[] = self::field( 2, 'text', 'Forename', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 3, 'text', 'Surname', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 4, 'text', 'Address line 1', true );
		$f[] = self::field( 5, 'text', 'Address line 2' );
		$f[] = self::field( 6, 'text', 'Town / City', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 7, 'text', 'County', false, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 8, 'text', 'Postcode', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field(
			9,
			'date',
			'Date of birth',
			true,
			array(
				'dateFormat' => 'dmy',
				'cssClass'   => 'gf_right_half ph-dob',
			)
		);
		$f[] = self::field(
			10,
			'number',
			'Age',
			false,
			array(
				'cssClass'    => 'gf_left_half ph-age',
				'description' => 'Calculated from your date of birth.',
			)
		);
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
		$f[] = self::field(
			13,
			'phone',
			'Home phone',
			false,
			array(
				'phoneFormat' => 'international',
				'cssClass'    => 'gf_right_half',
			)
		);
		$f[] = self::field(
			14,
			'text',
			'National Insurance number',
			true,
			array(
				'cssClass'    => 'ph-ni',
				'description' => 'For example QQ 12 34 56 C.',
			)
		);
		$f[] = self::field( 15, 'text', 'Emergency contact name', true );
		$f[] = self::field(
			16,
			'phone',
			'Emergency contact phone',
			true,
			array(
				'phoneFormat' => 'international',
				'cssClass'    => 'gf_left_half',
			)
		);
		$f[] = self::field( 17, 'text', 'Emergency contact relationship', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::page_break( 18 );

		// Page 2 — Eligibility & work details.
		$f[] = self::yes_no( 19, 'Are you eligible to work in the UK?' );
		$f[] = self::yes_no( 20, 'Do you require or hold a work permit?' );
		$f[] = self::field( 21, 'textarea', 'Work permit details', true, array( 'conditionalLogic' => self::when( array( array( 20, 'Yes' ) ) ) ) );
		$f[] = self::yes_no( 22, 'Do you have any CCJs, IVAs or declaration of bankruptcy?' );
		$f[] = self::field( 23, 'textarea', 'CCJ / IVA / bankruptcy details', true, array( 'conditionalLogic' => self::when( array( array( 22, 'Yes' ) ) ) ) );
		$f[] = self::yes_no( 24, 'Do you have any unspent criminal offences?' );
		$f[] = self::field( 25, 'textarea', 'Criminal offence details', true, array( 'conditionalLogic' => self::when( array( array( 24, 'Yes' ) ) ) ) );
		$f[] = self::field( 26, 'date', 'Date you are available to begin work', true, array( 'dateFormat' => 'dmy' ) );
		$f[] = self::yes_no( 27, 'Do you own relevant PPE?' );
		$f[] = self::field( 28, 'select', 'Your preferred shift', true, array( 'choices' => self::choices( array( 'Days', 'Nights', 'Weekends', 'Rotating shifts', 'Flexible / any' ) ) ) );
		$f[] = self::yes_no( 29, 'Do you have your own transport?' );
		$f[] = self::page_break( 30 );

		// Page 3 — Equal opportunities.
		$f[] = self::html( 31, '<div class="poolhall-form-note"><p>This section is for equal opportunities monitoring only. It is not used in any placement decision.</p></div>' );
		$f[] = self::field(
			32,
			'radio',
			'Ethnic group',
			true,
			array(
				'choices' => self::choices(
					array(
						'White - English / Welsh / Scottish / Northern Irish / British',
						'White - Irish',
						'White - Gypsy or Irish Traveller',
						'White - Any other White background',
						'Mixed - White and Black Caribbean',
						'Mixed - White and Black African',
						'Mixed - White and Asian',
						'Mixed - Any other Mixed / Multiple background',
						'Asian / Asian British - Indian',
						'Asian / Asian British - Pakistani',
						'Asian / Asian British - Bangladeshi',
						'Asian / Asian British - Chinese',
						'Asian / Asian British - Any other Asian background',
						'Black / African / Caribbean / Black British - African',
						'Black / African / Caribbean / Black British - Caribbean',
						'Black / African / Caribbean / Black British - Any other background',
						'Other - Arab',
						'Any other ethnic group',
						'Prefer not to say',
					)
				),
			)
		);
		$f[] = self::page_break( 33 );

		// Page 4 — Health questionnaire.
		$f[]    = self::html( 34, '<div class="poolhall-form-note"><p>This helps us understand any health considerations relevant to placing you in suitable work. Select all that apply.</p></div>' );
		$health = array(
			'Contact dermatitis / skin allergy',
			'Fits, fainting or epilepsy',
			'Recurring headaches / migraine',
			'Heart condition, high blood pressure or palpitations',
			'Skin trouble / eczema',
			'Diabetes',
			'Back complaint, lumbago or sciatica',
			'Arthritis or rheumatism',
			'Vertigo / sickness-type condition',
			'Depression',
			'Stomach complaints including ulcers',
			'Visual complaints',
			'Hearing complaints',
			'Chest disorders',
			'Asthma, hay fever or bronchitis',
			'Serious or duodenal ulcer',
			'Other stomach, bowel or liver disorders',
			'Other condition',
			'None of the above',
		);
		$inputs = array();
		foreach ( $health as $i => $label ) {
			$inputs[] = array(
				'id'    => '35.' . ( $i + 1 ),
				'label' => $label,
			);
		}
		$f[]           = self::field(
			35,
			'checkbox',
			'Health questionnaire',
			true,
			array(
				'choices'  => self::choices( $health ),
				'inputs'   => $inputs,
				'cssClass' => 'ph-health',
			)
		);
		$details_rules = array();
		foreach ( $health as $label ) {
			if ( 'None of the above' !== $label ) {
				$details_rules[] = array( 35, $label );
			}
		}
		$f[] = self::field( 36, 'textarea', 'Please provide details', true, array( 'conditionalLogic' => self::when( $details_rules, 'any' ) ) );
		$f[] = self::page_break( 37 );

		// Page 5 — Payment details.
		$f[] = self::html( 90, '<div class="poolhall-form-note"><p>Your payment details are used for payroll only, are never included in confirmation emails, and are stored securely.</p></div>' );
		$f[] = self::field( 38, 'text', 'Full name (as it appears on the account)', true );
		$f[] = self::field( 39, 'text', 'Bank name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 40, 'text', 'Account holder', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field(
			41,
			'text',
			'Account number',
			true,
			array(
				'cssClass'    => 'gf_left_half ph-account',
				'description' => '8 digits.',
			)
		);
		$f[] = self::field(
			42,
			'text',
			'Sort code',
			true,
			array(
				'cssClass'    => 'gf_right_half ph-sortcode',
				'description' => 'For example 00-00-00.',
			)
		);
		$f[] = self::field( 43, 'text', 'National Insurance number', true, array( 'cssClass' => 'gf_left_half ph-ni' ) );
		$f[] = self::field( 44, 'email', 'Email', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field(
			45,
			'phone',
			'Mobile',
			true,
			array(
				'phoneFormat' => 'international',
				'cssClass'    => 'gf_left_half',
			)
		);
		$f[] = self::field(
			46,
			'phone',
			'Home phone',
			false,
			array(
				'phoneFormat' => 'international',
				'cssClass'    => 'gf_right_half',
			)
		);
		$f[] = self::page_break( 47 );

		// Page 6 — Employment history (three conditional blocks).
		$employer = static function ( int $base, string $suffix, ?array $logic ): array {
			$extra = null === $logic ? array() : array( 'conditionalLogic' => $logic );
			$req   = null === $logic; // Only the first block's employer name is hard-required.
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

		// Page 7 — Reference & additional information.
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
		$f[] = self::yes_no( 83, 'Have you provided a CV?' );
		$f[] = self::field(
			84,
			'fileupload',
			'CV upload',
			true,
			array(
				'allowedExtensions' => 'pdf,doc,docx',
				'maxFileSize'       => 10,
				'conditionalLogic'  => self::when( array( array( 83, 'Yes' ) ) ),
			)
		);
		$f[] = self::yes_no( 85, 'Do you have additional qualifications?' );
		$f[] = self::field( 86, 'textarea', 'Additional qualification details', true, array( 'conditionalLogic' => self::when( array( array( 85, 'Yes' ) ) ) ) );
		$f[] = self::page_break( 87 );

		// Page 8 — Declaration & signature (with the final-confirmation note).
		$f   = array_merge( $f, self::signature_block( 88, self::DECLARATION, 89, 93, 94 ) );
		$f[] = self::field( 91, 'text', 'Full name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 92, 'text', 'Job role', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 95, 'text', 'Hourly rate', false, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::html( 96, '<div class="poolhall-form-note poolhall-form-final"><p><strong>Ready to submit?</strong> When you press the button below, your signed registration will be securely sent to the Poolhall Recruitment team. You will see a confirmation on screen and receive a short email receipt.</p></div>' );

		return self::form(
			self::TITLE_FULL,
			'Full candidate registration for Poolhall Recruitment.',
			$f,
			array( 'Personal details', 'Eligibility & work', 'Equal opportunities', 'Health', 'Payment details', 'Employment history', 'Reference & extras', 'Declaration & signature' ),
			true
		);
	}

	// -------------------------------------------------- starter statement --

	/** @return array<string,mixed> */
	public static function starter_statement(): array {
		$f   = array();
		$f[] = self::field( 1, 'text', 'Full name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 2, 'email', 'Email', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 3, 'text', 'National Insurance number', true, array( 'cssClass' => 'gf_left_half ph-ni' ) );
		$f[] = self::field(
			4,
			'date',
			'Date of birth',
			true,
			array(
				'dateFormat' => 'dmy',
				'cssClass'   => 'gf_right_half',
			)
		);
		$f[] = self::field( 5, 'text', 'Job role', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field(
			6,
			'date',
			'Start date',
			true,
			array(
				'dateFormat' => 'dmy',
				'cssClass'   => 'gf_right_half',
			)
		);
		$f[] = self::yes_no( 7, 'Do you have a P45 from a previous employer?' );
		$f[] = self::field(
			8,
			'fileupload',
			'Upload your P45',
			false,
			array(
				'allowedExtensions' => 'pdf,jpg,jpeg,png,doc,docx',
				'maxFileSize'       => 10,
				'conditionalLogic'  => self::when( array( array( 7, 'Yes' ) ) ),
			)
		);
		$f[] = self::html( 9, '<div class="poolhall-form-note"><p>No P45? Choose the statement below that applies to you. This is used for payroll and tax purposes only.</p></div>' );
		$f[] = self::field(
			10,
			'radio',
			'Starter statement',
			true,
			array(
				'choices' => self::choices(
					array(
						'A - This is my first job since last 6 April and I have not been receiving taxable Jobseeker\'s Allowance, Employment and Support Allowance, taxable Incapacity Benefit, State or Occupational Pension.',
						'B - This is now my only job but since last 6 April I have had another job, or have received taxable Jobseeker\'s Allowance, Employment and Support Allowance or taxable Incapacity Benefit. I do not receive a State or Occupational Pension.',
						'C - As well as my new job, I have another job or receive a State or Occupational Pension.',
					)
				),
			)
		);
		$f[] = self::yes_no( 11, 'Do you have a student loan?' );
		$f[] = self::field(
			12,
			'select',
			'Student loan plan type (if known)',
			false,
			array(
				'choices'          => self::choices( array( 'Plan 1', 'Plan 2', 'Plan 4', 'Plan 5', 'Not sure' ) ),
				'conditionalLogic' => self::when( array( array( 11, 'Yes' ) ) ),
			)
		);
		$f[] = self::yes_no( 13, 'Do you have a postgraduate loan?' );
		$f   = array_merge( $f, self::signature_block( 14, self::STARTER_DECLARATION, 15, 16, 17 ) );

		return self::form(
			self::TITLE_STARTER,
			'Starter Statement / P45 Declaration for payroll setup. This is not an official HMRC P45.',
			$f
		);
	}

	// ------------------------------------------------------------- terms --

	/** @return array<string,mixed> */
	public static function candidate_terms(): array {
		$f   = array();
		$f[] = self::field( 1, 'text', 'Candidate full name', true, array( 'cssClass' => 'gf_left_half' ) );
		$f[] = self::field( 2, 'email', 'Email', true, array( 'cssClass' => 'gf_right_half' ) );
		$f[] = self::field( 3, 'text', 'Job role', true );
		$f[] = self::html(
			4,
			'<div class="poolhall-form-note poolhall-terms-body"><h3>Candidate terms of service</h3><p>' . esc_html( self::TERMS_PLACEHOLDER ) . '</p><p><em>[Draft wording - Poolhall Recruitment must review and replace this text with the final legal terms before sending this form to candidates.]</em></p></div>'
		);
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

		return self::form(
			self::TITLE_TERMS,
			'Candidate terms of service agreement for Poolhall Recruitment.',
			$f
		);
	}
}
