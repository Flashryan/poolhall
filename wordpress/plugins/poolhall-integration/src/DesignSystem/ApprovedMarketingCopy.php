<?php
/**
 * Client-approved public marketing copy.
 *
 * Keeps editable wording separate from the shortcode renderers so copy changes
 * do not become tangled with layout, integrations, forms, or job queries.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\DesignSystem;

/** Client-approved copy transcribed from the August 2026 content pack. */
final class ApprovedMarketingCopy {

	/** Global shell wording. */
	public static function shell(): array {
		return array(
			'topbar_location' => 'West Midlands base - National reach',
			'footer_intro'    => 'Independent recruiter. Specialists in Construction, Manufacturing and Digital, since 2021.',
		);
	}

	/** Home-page wording rendered by plugin shortcodes. */
	public static function home(): array {
		return array(
			'hero_label' => 'Recruitment delivered the way it should be',
			'hero_title' => 'We help the people & organisations who build, make and market British business.',
			'features'   => array(
				array( 'shield-check', 'Sector Specialists', 'A proven track reckon in the markets we serve' ),
				array( 'phone', 'A friendly voice on the phone', 'Consultants who pick up, take the time to listen, and offer genuine advice.' ),
				array( 'map-pin', 'National reach', 'Based in the Black country, our placements span throughout the UK' ),
			),
		);
	}

	/** Employer benefits used by the reusable points grid. */
	public static function employer_points(): array {
		return array(
			array( 'Hire better people', 'Specialist & experienced consultants who understand the role and the sector, so the shortlist is genuinely worth your time.' ),
			array( 'Better value for your budget', 'Not only do our results do the talking, but we also offer better contractual terms which show we are as invested in your hiring as you are. Find out how?' ),
			array( 'A strategic partner', 'From a single hire to a whole team, we plan with you, not just react to a vacancy. We understand that the strategy needs to be right for every hire, not a one size fits all approach.' ),
			array( 'Honest, friendly advice', 'We’ll always give you our genuine view on salary, market and timelines, gently and openly, so you can plan with confidence.' ),
		);
	}

	/** Detailed employer delivery-option copy. */
	public static function employer_delivery_details(): array {
		return array(
			array(
				'title'           => 'Temporary',
				'intro'           => 'Our temp-to-perm recruitment solution is designed to create the right long term match for both employers and candidates, offering flexibility, insight, and confidence before committing permanently.',
				'subheading'      => 'How it works',
				'paragraphs'      => array(
					'We place a candidate with your business on a temporary basis, with the intention of moving into a permanent role. During this period, the candidate remains on our payroll while working within your team, giving you the opportunity to assess their performance and fit.',
					'At the same time, candidates can experience the role, team, and company culture first hand, ensuring it aligns with their expectations and career goals.',
				),
				'closing_heading' => 'A two way trial',
				'closing'         => 'Temp-to-perm benefits both sides. Employers reduce hiring risk, while candidates gain the chance to trial the role and working environment before making a long term decision.',
				'bullets_heading' => 'Why choose temp-to-perm?',
				'bullets'         => array(
					'Reduce risk Make informed hiring decisions, benefits cash flow - we pay the workers weekly, you pay us on agreed credit terms (30 days from invoice)',
					'Better matches Ensure the right fit for both sides',
				),
			),
			array(
				'title'      => 'Permanent',
				'intro'      => 'Our permanent recruitment service offers a fully managed hiring solution, where we take control of the entire recruitment process on your behalf.',
				'paragraphs' => array(
					'Using a combination of specialist advertising media and our extensive UK candidate database, we proactively source and match the right talent to your role. From initial search and screening through to interview management and offer stage, we handle every step with care and precision.',
					'This approach allows you to focus on your business while we deliver high quality, well matched candidates efficiently, ensuring a smooth and successful permanent hire.',
				),
			),
			array(
				'title'      => 'Scale',
				'intro'      => 'The Poolhall team will build out the best strategic approach to fulfil your recruitment challenge.',
				'paragraphs' => array(
					'You will be in the decision making driving seat and we will present scalable recruitment hiring solutions with rebates, fees and replacement criteria to set a foundation for a successful working partnership.',
				),
			),
			array(
				'title'   => 'Pay Monthly',
				'intro'   => 'There are x2 interpretations of how this service can help employers.',
				'bullets' => array(
					'Plain and simple cash flow assistance - Break up a perm fee into a maximum of 3 monthly payments. This is offered for companies that use our services more than once within a 4 month period.',
					'“Invoice only” - lower level salary hiring, you take the candidate on directly from day one, we send 10 weekly invoices (paid monthly) similar to what a temporary employee fee would be.',
				),
				'closing' => 'Employers now are under more pressure to engage candidates with a full time contract from day 1, but percentage fee recruitment doesn’t work for high turnover or volume recruitment hiring. This was you get the people you need, at the cost you have been used to historically.',
			),
			array(
				'title'      => 'On-Site consultancy engagement',
				'intro'      => 'A fully managed on-site services.',
				'bullets'    => array(
					'Poolhall representative on site, on a day rate to help you with temp or permanent hiring.',
					'With over 13 years in recruitment our director has access to an extensive network of fantastic recruiters.',
				),
				'paragraphs' => array(
					'This gives you the control on site but the agency expertise and Poolhall stamp of quality.',
					'We have recent on site experience where we have set up internal recruitment functions from scratch.',
				),
				'closing'    => 'We can set you up with the best CRM/ATS tech, Pay as you go recruitment advertising on the leading job boards to find candidates, Invoice finance funding to pay contractors weekly. We can hire you a recruitment consultant to manage everything we set up for you as part of the agreement if needed.',
			),
		);
	}

	/** Copy for shortcode-owned secondary employer pages. */
	public static function secondary( string $key ): ?array {
		$pages = array(
			'why-us'  => array(
				'eyebrow'   => 'For employers',
				'title'     => 'Why Us',
				'lead'      => 'Find out why using an independent makes all the difference to your hiring performance and retention of staff',
				'photo'     => 'poolhall-office-culture.jpg',
				'cta_photo' => 'why-us-cta-matthew-call.webp',
				'cta_class' => 'photoband--why-us-call',
				'points'    => array(
					array( 'Senior by default', 'You’ll work with experienced consultants who take the time to understand your brief.' ),
					array( 'Genuinely independent', 'No franchise targets pulling our focus away from your role.' ),
					array( 'Honest advice', 'Straight talk on salary, market and timelines. We offer Job advertising services and HR support, we have the full circle of solutions to help and genuinely have a passion for delivery.' ),
					array( 'Long-term relationships', 'We’re here for your next hire, and the one after.' ),
				),
			),
			'bespoke' => array(
				'eyebrow'     => 'For employers',
				'title'       => 'Bespoke Search',
				'lead'        => 'Despite our sector specialist areas, we still believe that we are recruitment experts too. We have helped deliver recruitment hires for niche, hard to fill roles, & for all kinds of companies and organisations.',
				'photo'       => 'poolhall-matt-desk.jpg',
				'roles_intro' => 'Just to show our versatility check out the below roles we have placed for our clients',
				'roles'       => array(
					'Senior leadership & Management hires – commercial and operational for SME & PLC businesses',
					'Variety of franchise businesses for business owners, from insurance and estate agents to recruitment',
					'IT management & Network Engineers to Universities',
					'Cyber Security consultants',
					'Accountancy professionals',
					'Teaching & Learning support / mentors to school trusts',
					'Independent catering business to sports clubs',
					'Green Keepers for golf clubs',
					'Grounds maintenance operatives',
					'Forklift truck drivers, warehouse personnel and labourers',
				),
				'points'      => array(
					array( 'Mapped to a brief', 'We build a target list, not a job-board post.' ),
					array( 'Confidential', 'Sensitive and senior searches handled discreetly.' ),
					array( 'Thorough vetting', 'Deep screening before anyone reaches your desk.' ),
					array( 'Market intelligence', 'Real insight on who’s out there and what they cost' ),
				),
			),
			'bja'     => array(
				'eyebrow'            => 'For employers',
				'title'              => 'Better Job Adverts',
				'lead'               => 'The best part about being an independent business is being able to offer the right solution and not just the service that you\'re being targeted to sell.',
				'photo'              => 'sector-digital.webp',
				'cta_photo'          => 'better-job-adverts-cta-matthew-email.webp',
				'cta_class'          => 'photoband--bja-email',
				'feature_heading'    => 'Hire Without Percentage based Recruitment Fees',
				'feature_paragraphs' => array(
					'For a fixed fee of £799, you get a fully managed, employer‑branded job advertising campaign across Indeed, Reed, CV‑Library and Totaljobs, giving your vacancy the visibility it needs without the cost of agency hiring.',
					'If you already have HR or admin support, this model lets you run recruitment independently while removing the time and labour of CV filtering. We launch your adverts, pre‑filter all responses, and organise every application in a colour‑coded ATS, making it easy to identify strong candidates at a glance. It keeps your inbox clear, ensures candidates receive a prompt, well‑managed experience - including rejections and typically has your vacancy live within two days.',
					'Buying small credit bundles directly from job boards costs more, slows setup, and creates multiple invoices. We handle everything for you, provide a recruitment advice PDF, and give you full ATS access so you can manage interviews and hiring efficiently.',
					'Built for internal recruitment teams, HR consultants and business owners, this service delivers a powerful DIY hiring model: all the tools, structure and support you need without agency fees of £3,000–£5,000+ per hire. Efficient, cost‑effective, and designed for better hiring outcomes.',
				),
				'job_boards'         => array(
					array( 'CV-Library', 'bja-board-cv-library.webp' ),
					array( 'Reed', 'bja-board-reed.webp' ),
					array( 'Totaljobs', 'bja-board-totaljobs.webp' ),
					array( 'Indeed', 'bja-board-indeed.webp' ),
				),
				'points_heading'     => 'How We Support Your Team',
				'points_intro'       => 'We handle the most time‑consuming parts of recruitment so your team can focus on selecting the right people.',
				'points'             => array(
					array( 'Advertise Everywhere That Matters', 'Your vacancies are posted across leading job boards, giving you maximum exposure to active job seekers.', 'bja-support-advertise.webp' ),
					array( 'Stay On Brand', 'Every advert is written and designed in your company’s branding for a consistent, professional candidate experience.', 'bja-support-brand.webp' ),
					array( 'Attract More Applicants', 'Optimised job adverts with targeted keywords improve visibility and boost application numbers.', 'bja-support-applicants.webp' ),
					array( 'CV Screening Done For You', 'We filter and shortlist candidates so your team only sees relevant, high‑quality applications.', 'bja-support-screening.webp' ),
					array( 'Ready to Review in Your ATS', 'Shortlisted CVs are uploaded directly into your ATS, organised and ready for immediate review.', 'bja-support-ats.webp' ),
					array( 'Reach More Candidates', 'Multi‑platform advertising increases your chances of finding the right fit.', 'bja-support-reach.webp' ),
					array( 'Lower Advertising Costs', 'Our volume buying power means you pay less than purchasing job board credits individually.', 'bja-support-costs.webp' ),
				),
				'case_studies'       => array(
					array(
						'Client hiring outcome',
						'Helped fill 11 roles for a new store, then delivered more than 100 relevant CVs for the client’s next recruitment campaign.',
						'bja-case-client-outcome.webp',
					),
					array(
						'Aderans — Salon Manager',
						'A fully branded Salon Manager advert presented on CV-Library.',
						'bja-case-cv-library.webp',
					),
					array(
						'Construction — Site Operative',
						'A targeted Site Operative campaign distributed through Totaljobs.',
						'bja-case-totaljobs.webp',
					),
					array(
						'1st Choice Flooring Solutions — Floor Fitter',
						'A branded Floor Fitter advert published through Reed.',
						'bja-case-reed.webp',
					),
				),
				'benefits'           => array(
					'Faster hiring without increasing headcount.',
					'Less pressure on HR and internal teams.',
					'More qualified candidates.',
					'Better return on recruitment spend.',
				),
				'focus_heading'      => 'Focus on the Right Hire',
				'focus'              => 'We handle the admin and advertising while you focus on choosing the best person for your business.',
				'close_heading'      => 'Get Started Today',
				'close'              => 'Ready to streamline your recruitment and attract better candidates? Contact us to discuss your hiring needs and discover how much time and money you could save.',
			),
			'hr'      => array(
				'eyebrow'            => 'For employers',
				'title'              => 'HR Services',
				'lead'               => 'Support that goes beyond the hire, for businesses without a full HR function.',
				'photo'              => 'poolhall-office-culture.jpg',
				'legal_photo'        => 'hr-legal-responsibilities.webp',
				'legal_heading'      => 'Your Legal Responsibilities as an Employer',
				'legal_paragraphs'   => array(
					'Running a business means meeting key HR obligations - and they’re every bit as important as the legal requirements you follow for your car.',
					'Your employees must have written contracts, you must maintain legally compliant policies and procedures, and every member of staff has statutory rights covering holiday, sick pay, maternity and paternity leave, and more.',
					'If any of these areas are missing or incorrect, the consequences can be costly. Employees also expect you to have everything in place and to understand your responsibilities from day one.',
					'That’s why investing a small amount of time now can help you identify where to prioritise your people‑management compliance and protect your business.',
				),
				'question'           => 'Try us out with an HR-related question. What’s giving you a headache? We may just be able to help by asking one of our trusted HR consultants.',
				'onboarding_heading' => 'Complete Employee Onboarding Pack',
				'onboarding_intro'   => 'Everything your business needs to onboard new employees smoothly and professionally — all in one place.',
				'onboarding_note'    => 'This is a tailored service deliverable rather than a public download. Ask us about the pack and we’ll confirm what your business needs.',
				'onboarding_items'   => array(
					'Employee Privacy Notice Policy',
					'Offer Letter',
					'Working Time Regulations Policy',
					'Schedule of Particulars',
					'New Starter Form',
					'Employee Contract',
					'Full Employee Handbook and workplace policies',
				),
				'points'             => array(
					array( 'A partner on call', 'Someone to ask when an HR question lands.', 'hr-partner-matthew.webp' ),
					array( 'Adhoc or retainer based generalist HR support', '', 'hr-support-retainer.webp' ),
				),
				'support_heading'    => 'Common HR Support Areas we can help with',
				'support_photo'      => 'hr-common-support.webp',
				'support'            => array(
					'Employment contracts and onboarding',
					'Essential HR policies and procedures',
					'Sickness, absence and holiday management',
					'Performance, disciplinary and grievance issues',
					'Redundancy and restructuring',
					'Statutory entitlements (holiday, sick pay, maternity/paternity)',
					'HR compliance and record‑keeping',
				),
				'cta_photo'          => 'hr-cta-matthew-consultation.webp',
				'cta_class'          => 'photoband--hr-consultation',
			),
		);

		return $pages[ $key ] ?? null;
	}

	/** Candidate guide copy and licensed photography. */
	public static function guide( string $key ): ?array {
		$guides = array(
			'registration' => array(
				'title'     => 'What to expect when you register',
				'lead'      => 'Registering with Poolhall takes a couple of minutes. Here’s how we’ll work with you from there.',
				'hero'      => 'registration-hero.webp',
				'cta_label' => 'Register now',
				'cta_page'  => 'register',
				'cards'     => array(
					array( 'Tell us about yourself', 'Share your CV and what a great next move looks like: role, salary, location and anything that matters to you.', 'registration-card-cv.webp', 'Candidate presenting their CV during a friendly recruitment conversation' ),
					array( 'A real conversation', 'One of the team will call to understand your experience and ambitions properly, not just keyword-match your CV.', 'registration-card-conversation.webp', 'Recruiter having a friendly conversation with a candidate' ),
					array( 'We go to work', 'We only put you forward for roles that genuinely fit, and we’ll always ask before your CV goes anywhere.', 'registration-card-match.webp', 'Recruiter carefully reviewing a candidate CV' ),
					array( 'Offer and beyond', 'Interview prep, honest feedback and support through to your first day and your first weeks in the role.', 'registration-card-support.webp', 'Candidate and recruiter shaking hands after a successful meeting' ),
				),
			),
			'interview'    => array(
				'title'     => 'Interview prep that actually helps',
				'lead'      => 'Practical preparation beats nerves. A simple framework from consultants who hear interview feedback every week.',
				'hero'      => 'interview-hero.webp',
				'cta_label' => 'Browse live jobs',
				'cta_page'  => 'jobs',
				'cards'     => array(
					array( 'Know the company', 'Fifteen minutes on their website, recent work and competitors goes further than an hour of generic question practice.', 'interview-card-research.webp', 'Candidate using a laptop while preparing for an interview' ),
					array( 'Have five stories ready', 'Real examples of problems you solved, pressure you handled, and results you delivered. Numbers help.', 'interview-card-stories.webp', 'Candidate sharing examples during a job interview' ),
					array( 'Ask real questions', 'Ask about the team, the first six months and how success is measured. It shows you’re serious about the fit.', 'interview-card-questions.webp', 'Candidate and hiring manager discussing a role' ),
					array( 'Close well', 'Say you’re interested if you are. Ask about next steps. Follow up the same day through your consultant.', 'interview-card-close.webp', 'Candidate and interviewer shaking hands at the close of an interview' ),
				),
			),
			'cv'           => array(
				'title'     => 'Make your CV count',
				'lead'      => 'Straight from the consultants who read CVs all day: what gets you shortlisted and what gets in the way.',
				'hero'      => 'cv-hero.webp',
				'cta_label' => 'Register your CV',
				'cta_page'  => 'register',
				'cards'     => array(
					array( 'Lead with impact', 'Your first half-page decides everything. Put your strongest, most relevant experience and achievements up top.', 'cv-card-impact.webp', 'Candidate writing and refining their CV at a desk' ),
					array( 'Show results, not duties', '“Cut rework by a third” beats “responsible for quality”. Quantify wherever you honestly can.', 'cv-card-results.webp', 'Professional reviewing the detail in a printed CV' ),
					array( 'Tailor it', 'Mirror the language of the role you’re applying for. Two focused pages beat six generic ones.', 'cv-card-tailor.webp', 'Candidate tailoring a CV on a laptop' ),
					array( 'Keep it clean', 'Simple layout, consistent dates, no photos or gimmicks. Make it effortless to read on a screen.', 'cv-card-clean.webp', 'Professional working on a clean CV layout on a laptop' ),
				),
			),
		);

		return $guides[ $key ] ?? null;
	}

	/** Copy and licensed photography for the main sectors landing page. */
	public static function sectors_hub(): array {
		return array(
			'eyebrow' => 'Sectors we cover',
			'title'   => 'Specialists in the work that builds Britain',
			'lead'    => 'Three core specialisms, backed by decades of combined experience — and a bespoke search service shaped around your exact requirements.',
			'hero'    => 'sectors-hero-specialists.webp',
			'sectors' => array(
				array(
					'key'     => 'con',
					'title'   => 'Construction & Skilled Trade',
					'lead'    => 'National suppliers of both blue- and white-collar workers. From site operatives and supervisors to commercial leadership, we place the people who deliver projects for you.',
					'roles'   => array( 'CSCS Operatives', 'Site Managers', 'Project Managers', 'Quantity Surveyors', 'Estimators', 'Skilled Trades & Labour' ),
					'page'    => 'sectors/construction',
					'term'    => 'Construction & Skilled Trade',
					'reverse' => false,
					'images'  => array(
						array( 'sectors-construction-carpenter.webp', 'Carpenter cutting timber on site', 'Skilled trades' ),
						array( 'sectors-construction-flooring.webp', 'Flooring specialist fitting a timber floor', 'Finishing trades' ),
						array( 'sectors-construction-tradeswoman.webp', 'Tradeswoman working on an outdoor construction project', 'People on site' ),
					),
				),
				array(
					'key'     => 'man',
					'title'   => 'Manufacturing',
					'lead'    => 'From the shop floor to the drawing, sales or leadership offices, we have you covered with time-served, vetted candidates.',
					'roles'   => array( 'Welders & Fabricators', 'Skilled Machinists', 'Production Operatives', 'Maintenance Engineers', 'Quality', 'Management', 'CAD Technicians & Estimators' ),
					'page'    => 'sectors/manufacturing',
					'term'    => 'Manufacturing',
					'reverse' => true,
					'images'  => array(
						array( 'sectors-manufacturing-line.webp', 'Automotive production line in a manufacturing plant', 'Production' ),
						array( 'sectors-manufacturing-fabrication.webp', 'Fabricator grinding steel in a workshop', 'Fabrication' ),
						array( 'sectors-manufacturing-machinist.webp', 'Machinist checking a precision metal component', 'Engineering' ),
					),
				),
				array(
					'key'     => 'dig',
					'title'   => 'Digital & Office',
					'lead'    => 'Creative and performance marketers who grow brands — agency-side and in-house. We also cover office-based roles, from support to sales and management.',
					'roles'   => array( 'Marketing Managers', 'Paid Media & PPC', 'SEO Specialists', 'Content & Social', 'PR & Communications', 'Sales', 'Administration', 'Finance', 'IT', 'Management' ),
					'page'    => 'sectors/digital',
					'term'    => 'Marketing & PR',
					'reverse' => false,
					'images'  => array(
						array( 'sectors-digital-collaboration.webp', 'Colleagues discussing a digital project', 'Collaboration' ),
						array( 'sectors-digital-coworkers.webp', 'Marketing team reviewing campaign performance', 'Performance' ),
						array( 'sectors-digital-design.webp', 'Creative team developing design concepts together', 'Creative & office' ),
					),
				),
			),
		);
	}

	/** Approved sector-page variants. */
	public static function sector( string $key ): array {
		$sectors = array(
			'con' => array(
				'name'        => 'Construction',
				'blurb'       => 'The Poolhall way',
				'roles_title' => 'Talent across the whole construction chain.',
				'roles_lead'  => 'From hands-on roles through to design & leadership, we help across the whole construction chain, with consultants who genuinely understand the work, the compliance and the importance of looking after our own temporary workers.',
				'role_groups' => array( 'Labour & Trades', 'M&E', 'Design & Professional', 'Facilities Management' ),
				'roles'       => array( 'Project & Site Managers', 'Quantity Surveyors', 'Site Engineers', 'Skilled Trades & Operatives', 'Gas & HVAC Engineers', 'BIM & Design Managers', 'Sales &Estimators…' ),
				'network'     => 'Years of relationships across construction mean we reach people the job boards never see.',
				'compliance'  => 'Right-to-work, certs and checks managed properly, so you can hire with confidence and our workers are paid correctly and on time.',
			),
			'man' => array(
				'name'        => 'Manufacturing',
				'blurb'       => 'Delivered with care and understanding, its in our DNA.',
				'roles_title' => 'Talent across the board.',
				'roles_lead'  => 'From hands-on roles through to leadership, we help across the whole manufacturing spectrum with consultants who genuinely understand the work.',
				'roles_extra' => 'We help office based white collar workers into their next role too - Design, Sales, Management or quality, we are here to help and have a track record of delivering.',
				'roles'       => array( 'Welders & Fabricators', 'CNC & Conventional Machinists', 'Production Operatives', 'Maintenance Engineers', 'Quality Inspectors', 'Production Managers' ),
				'network'     => 'Years of relationships across manufacturing mean we reach people the job boards never see.',
				'compliance'  => 'Right-to-work, certs and checks managed properly, so you can hire with confidence.',
			),
			'dig' => array(
				'name'        => 'Digital',
				'blurb'       => 'Marketing, PPC, SEO and digital specialists for fast-growing agencies and in-house teams.',
				'roles_title' => 'Talent across the whole digital chain.',
				'roles_lead'  => 'From hands-on roles through to leadership, we help across the whole digital chain, with consultants who genuinely understand the work.',
				'roles'       => array( 'Paid Media & PPC', 'SEO Specialists', 'Content & Social', 'Designers', 'Developers', 'Marketing Managers' ),
				'network'     => 'Years of relationships across digital mean we reach people the job boards never see.',
				'compliance'  => 'Right-to-work, certs and checks managed properly, so you can hire with confidence.',
			),
		);

		return $sectors[ $key ] ?? $sectors['con'];
	}

	/** About-page team teaser. */
	public static function about_team(): array {
		return array(
			'eyebrow' => 'People behind it',
			'title'   => 'A small team you’ll actually deal with',
			'people'  => array(
				array( 'Matthew Tonks', 'Founder', array( 'Construction', 'Manufacturing' ), 'team-matthew.jpg' ),
				array( 'Jay Thornton', 'Recruitment Partner', array( 'Marketing & PR', 'Digital' ), 'team-jay.jpg' ),
				array( 'Sam Ogle', 'Marketing Specialist', array( 'Brand', 'Content' ), 'team-sam.jpg' ),
			),
		);
	}
}
