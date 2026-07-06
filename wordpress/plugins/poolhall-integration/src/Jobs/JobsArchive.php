<?php
/**
 * Server-rendered jobs archive browser (filters + results).
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Jobs;

/**
 * `[poolhall_jobs_browser]` renders the whole jobs listing UX (directive
 * §3.2, prototype Jobs.jsx): a sticky filter sidebar (sector, job type,
 * working pattern, minimum salary), a results column with a count header,
 * sort control, applied-filter chips, stacked result cards, numbered
 * pagination and a zero-result empty state, plus a mobile filter drawer.
 *
 * Everything is server-rendered and driven by GET parameters against the
 * job CPT, so it works with JavaScript disabled; a small enhancement
 * script (child theme) auto-submits selects and runs the drawer. The
 * options and counts come from JobFacets (live data only), and the query
 * rules from ArchiveQuery::build_args so the list and the filters never
 * disagree.
 */
final class JobsArchive {

	public const SHORTCODE    = 'poolhall_jobs_browser';
	private const PER_PAGE    = 10;
	private const SALARY_STEP = 10000;

	public function __construct(
		private ArchiveQuery $query,
		private JobFacets $facets,
		private JobCardBits $cards
	) {
	}

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	public function render(): string {
		$request = SearchRequest::from_query( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$facets  = $this->facets->compute();

		$results = new \WP_Query( $this->query->build_args( $request, self::PER_PAGE ) );

		$sidebar = '<aside class="ph-filter-panel" id="ph-jobs-filters" aria-label="' . esc_attr__( 'Filter jobs', 'poolhall-integration' ) . '">'
			. '<div class="ph-filter-panel__head"><span class="ph-h4">' . esc_html__( 'Filters', 'poolhall-integration' ) . '</span>'
			. '<button type="button" class="ph-icon-button ph-filter-panel__close" data-ph-filters-close aria-label="' . esc_attr__( 'Close filters', 'poolhall-integration' ) . '">&times;</button></div>'
			. $this->filters_form( $request, $facets )
			. '</aside>';

		$results_col = '<section class="ph-jobs__results">'
			. $this->results_head( $request, $facets, (int) $results->found_posts )
			. $this->chips( $request, $facets )
			. $this->results_list( $results, $request )
			. '</section>';

		wp_reset_postdata();

		return '<div class="ph-jobs">'
			. '<div class="ph-jobs__layout">' . $sidebar . $results_col . '</div>'
			. '<div class="ph-drawer-overlay" data-ph-filters-close hidden></div>'
			. '</div>';
	}

	/**
	 * @param array<string,mixed> $facets
	 */
	private function results_head( SearchRequest $request, array $facets, int $found ): string {
		$title = __( 'All roles', 'poolhall-integration' );
		if ( '' !== $request->sector ) {
			foreach ( $facets['sectors'] as $sector ) {
				if ( $sector['slug'] === $request->sector ) {
					$title = $sector['name'];
					break;
				}
			}
		}

		$count = sprintf(
			/* translators: %s: number of jobs. */
			_n( '%s job found', '%s jobs found', $found, 'poolhall-integration' ),
			number_format_i18n( $found )
		);

		return '<div class="ph-jobs__head">'
			. '<div class="ph-jobs__head-text"><h1 class="ph-h3" style="color: var(--ph-color-navy-700); margin: 0;">' . esc_html( $title ) . '</h1>'
			. '<p class="ph-small ph-text-muted" style="margin: 0;">' . esc_html( $count ) . '</p></div>'
			. '<div class="ph-jobs__head-actions">'
			. '<button type="button" class="ph-button ph-button--ghost ph-jobs__filter-toggle" data-ph-filters-open aria-controls="ph-jobs-filters">'
			. esc_html__( 'Filters', 'poolhall-integration' ) . '</button>'
			. $this->sort_form( $request )
			. '</div></div>';
	}

	private function sort_form( SearchRequest $request ): string {
		$labels  = array(
			'recent' => __( 'Most recent', 'poolhall-integration' ),
			'salary' => __( 'Highest salary', 'poolhall-integration' ),
			'az'     => __( 'A to Z', 'poolhall-integration' ),
		);
		$options = '';
		foreach ( $labels as $value => $label ) {
			$options .= '<option value="' . esc_attr( $value ) . '"' . selected( $value, $request->sort, false ) . '>' . esc_html( $label ) . '</option>';
		}

		return '<form class="ph-jobs__sort" method="get" action="' . esc_url( $this->archive_url() ) . '">'
			. $this->hidden_inputs( $request, array( 'sort', 'salary_min' ) )
			. $this->salary_hidden( $request )
			. '<label class="ph-visually-hidden" for="ph-jobs-sort">' . esc_html__( 'Sort jobs', 'poolhall-integration' ) . '</label>'
			. '<select id="ph-jobs-sort" name="sort" data-ph-autosubmit>' . $options . '</select>'
			. '<button class="ph-button ph-button--ghost" type="submit">' . esc_html__( 'Sort', 'poolhall-integration' ) . '</button>'
			. '</form>';
	}

	/**
	 * @param array<string,mixed> $facets
	 */
	private function filters_form( SearchRequest $request, array $facets ): string {
		$form = '<form class="ph-filter-form" method="get" action="' . esc_url( $this->archive_url() ) . '">'
			. $this->hidden_field( 'q', $request->keyword )
			. $this->hidden_field( 'location', $request->location )
			. $this->hidden_field( 'sort', 'recent' === $request->sort ? '' : $request->sort );

		// Sector.
		if ( array() !== $facets['sectors'] ) {
			$opts = $this->radio( 'sector', '', __( 'All sectors', 'poolhall-integration' ), null, '' === $request->sector );
			foreach ( $facets['sectors'] as $sector ) {
				$opts .= $this->radio( 'sector', $sector['slug'], $sector['name'], $sector['count'], $sector['slug'] === $request->sector );
			}
			$form .= $this->group( __( 'Sector', 'poolhall-integration' ), $opts );
		}

		// Job type (hidden until the source populates employment types).
		if ( array() !== $facets['types'] ) {
			$opts = $this->radio( 'type', '', __( 'Any type', 'poolhall-integration' ), null, '' === $request->type );
			foreach ( $facets['types'] as $type ) {
				$opts .= $this->radio( 'type', $type['slug'], $type['label'], $type['count'], $type['slug'] === $request->type );
			}
			$form .= $this->group( __( 'Job type', 'poolhall-integration' ), $opts );
		}

		// Working pattern.
		if ( array() !== $facets['work_modes'] ) {
			$opts = $this->radio( 'work_mode', '', __( 'Any pattern', 'poolhall-integration' ), null, '' === $request->work_mode );
			foreach ( $facets['work_modes'] as $mode ) {
				$opts .= $this->radio( 'work_mode', $mode['slug'], $mode['label'], $mode['count'], $mode['slug'] === $request->work_mode );
			}
			$form .= $this->group( __( 'Working pattern', 'poolhall-integration' ), $opts );
		}

		// Minimum salary.
		if ( $facets['salary_max'] >= 1000 ) {
			$form .= $this->group( __( 'Minimum salary', 'poolhall-integration' ), $this->salary_select( $request, $facets ) );
		}

		$form .= '<div class="ph-filter-form__actions">'
			. '<button class="ph-button ph-button--primary ph-button--full" type="submit">' . esc_html__( 'Apply filters', 'poolhall-integration' ) . '</button>'
			. '<a class="ph-button ph-button--ghost ph-button--full" href="' . esc_url( $this->archive_url() ) . '">' . esc_html__( 'Clear filters', 'poolhall-integration' ) . '</a>'
			. '</div></form>';

		return $form;
	}

	/**
	 * @param array<string,mixed> $facets
	 */
	private function salary_select( SearchRequest $request, array $facets ): string {
		$floor = (int) ( floor( $facets['salary_min'] / self::SALARY_STEP ) * self::SALARY_STEP );
		$floor = max( self::SALARY_STEP, $floor );
		$top   = (int) ( ceil( $facets['salary_max'] / self::SALARY_STEP ) * self::SALARY_STEP );

		$options = '<option value="0">' . esc_html__( 'Any salary', 'poolhall-integration' ) . '</option>';
		for ( $value = $floor; $value <= $top; $value += self::SALARY_STEP ) {
			$options .= '<option value="' . esc_attr( (string) $value ) . '"' . selected( $value, $request->salary_min, false ) . '>'
				. esc_html( sprintf( /* translators: %s: salary amount. */ __( '£%sk and up', 'poolhall-integration' ), number_format_i18n( $value / 1000 ) ) )
				. '</option>';
		}

		return '<label class="ph-visually-hidden" for="ph-jobs-salary">' . esc_html__( 'Minimum salary', 'poolhall-integration' ) . '</label>'
			. '<select id="ph-jobs-salary" name="salary_min" class="ph-field__control" data-ph-autosubmit>' . $options . '</select>';
	}

	/**
	 * @param array<string,mixed> $facets
	 */
	private function chips( SearchRequest $request, array $facets ): string {
		$active = $request->active_filters();
		if ( array() === $active ) {
			return '';
		}

		$labels = array(
			'q'          => __( 'Keyword', 'poolhall-integration' ),
			'location'   => __( 'Location', 'poolhall-integration' ),
			'sector'     => __( 'Sector', 'poolhall-integration' ),
			'work_mode'  => __( 'Pattern', 'poolhall-integration' ),
			'type'       => __( 'Type', 'poolhall-integration' ),
			'salary_min' => __( 'Salary', 'poolhall-integration' ),
		);

		$chips = '';
		foreach ( $active as $param => $value ) {
			$display = $this->chip_value( $param, $value, $facets );
			$remove  = remove_query_arg( array( $param, 'pg' ), $this->current_url() );
			$chips  .= '<a class="ph-chip ph-chip--filter" href="' . esc_url( $remove ) . '">'
				. '<span>' . esc_html( $labels[ $param ] . ': ' . $display ) . '</span>'
				. '<span class="ph-chip__x" aria-hidden="true">&times;</span>'
				. '<span class="ph-visually-hidden">' . esc_html__( 'Remove filter', 'poolhall-integration' ) . '</span></a>';
		}

		return '<div class="ph-jobs__chips">' . $chips
			. '<a class="ph-link" href="' . esc_url( $this->archive_url() ) . '">' . esc_html__( 'Clear all', 'poolhall-integration' ) . '</a></div>';
	}

	/**
	 * @param array<string,mixed> $facets
	 */
	private function chip_value( string $param, string $value, array $facets ): string {
		if ( 'sector' === $param ) {
			foreach ( $facets['sectors'] as $sector ) {
				if ( $sector['slug'] === $value ) {
					return $sector['name'];
				}
			}
		}
		if ( 'work_mode' === $param ) {
			$label = JobFacets::work_mode_label( $value );
			return '' === $label ? $value : $label;
		}
		if ( 'type' === $param ) {
			foreach ( $facets['types'] as $type ) {
				if ( $type['slug'] === $value ) {
					return $type['label'];
				}
			}
		}
		if ( 'salary_min' === $param ) {
			return sprintf( /* translators: %s: salary amount. */ __( '£%sk+', 'poolhall-integration' ), number_format_i18n( (int) $value / 1000 ) );
		}
		return $value;
	}

	private function results_list( \WP_Query $results, SearchRequest $request ): string {
		if ( ! $results->have_posts() ) {
			return $this->empty_state();
		}

		$rows = '';
		while ( $results->have_posts() ) {
			$results->the_post();
			$rows .= $this->row( get_post() );
		}

		return '<div class="ph-jobs__list">' . $rows . '</div>' . $this->pagination( $results, $request );
	}

	private function row( ?\WP_Post $post ): string {
		if ( ! $post instanceof \WP_Post ) {
			return '';
		}
		$permalink = (string) get_permalink( $post );
		$salary    = (string) get_post_meta( $post->ID, 'salary_display', true );

		return '<article class="ph-card ph-card--job ph-job-row">'
			. '<div class="ph-job-row__main">'
			. $this->cards->render_flair()
			. '<h2 class="ph-h3" style="margin: 0;"><a class="ph-job-row__title" href="' . esc_url( $permalink ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h2>'
			. $this->cards->render_meta()
			. '</div>'
			. '<div class="ph-job-row__aside">'
			. ( '' !== $salary ? '<span class="ph-job-salary">' . esc_html( $salary ) . '</span>' : '' )
			. '<a class="ph-link ph-link--arrow" href="' . esc_url( $permalink ) . '" tabindex="-1">' . esc_html__( 'View job', 'poolhall-integration' ) . '</a>'
			. '</div></article>';
	}

	private function empty_state(): string {
		return '<div class="ph-card ph-empty-state">'
			. '<span class="ph-empty-state__icon" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></span>'
			. '<h2 class="ph-h4" style="margin: 0;">' . esc_html__( 'No roles match those filters', 'poolhall-integration' ) . '</h2>'
			. '<p class="ph-body">' . esc_html__( 'Try widening your search, or register your CV and we will let you know as soon as something right comes up.', 'poolhall-integration' ) . '</p>'
			. '<div class="ph-cluster">'
			. '<a class="ph-button ph-button--ghost" href="' . esc_url( $this->archive_url() ) . '">' . esc_html__( 'Clear all filters', 'poolhall-integration' ) . '</a>'
			. '<a class="ph-button ph-button--primary" href="' . esc_url( home_url( '/candidate/register/' ) ) . '">' . esc_html__( 'Register your CV', 'poolhall-integration' ) . '</a>'
			. '</div></div>';
	}

	private function pagination( \WP_Query $results, SearchRequest $request ): string {
		$total = (int) $results->max_num_pages;
		if ( $total < 2 ) {
			return '';
		}

		$links = paginate_links(
			array(
				'base'      => add_query_arg( 'pg', '%#%', $this->current_url() ),
				'format'    => '',
				'current'   => max( 1, $request->page ),
				'total'     => $total,
				'type'      => 'array',
				'prev_text' => '&larr;',
				'next_text' => '&rarr;',
				'end_size'  => 1,
				'mid_size'  => 1,
			)
		);
		if ( ! is_array( $links ) ) {
			return '';
		}

		return '<nav class="ph-pagination" aria-label="' . esc_attr__( 'Jobs pages', 'poolhall-integration' ) . '">' . implode( '', $links ) . '</nav>';
	}

	// --------------------------------------------------------------- bits ----

	private function group( string $title, string $body ): string {
		return '<div class="ph-filter-group"><span class="ph-filter-group__title">' . esc_html( $title ) . '</span>' . $body . '</div>';
	}

	private function radio( string $name, string $value, string $label, ?int $count, bool $checked ): string {
		return '<label class="ph-filter-option">'
			. '<input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '"' . checked( true, $checked, false ) . ' data-ph-autosubmit />'
			. '<span class="ph-filter-option__label">' . esc_html( $label ) . '</span>'
			. ( null === $count ? '' : '<span class="ph-filter-option__count">' . esc_html( number_format_i18n( $count ) ) . '</span>' )
			. '</label>';
	}

	private function hidden_field( string $name, string $value ): string {
		return '' === $value ? '' : '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
	}

	private function salary_hidden( SearchRequest $request ): string {
		return $request->salary_min > 0 ? $this->hidden_field( 'salary_min', (string) $request->salary_min ) : '';
	}

	/**
	 * Hidden inputs carrying the active params a given form does not own, so
	 * submitting one control preserves the rest of the query.
	 *
	 * @param string[] $except Param names this form owns (omit from hidden set).
	 */
	private function hidden_inputs( SearchRequest $request, array $except ): string {
		$out = '';
		foreach ( $request->active_filters() as $param => $value ) {
			if ( in_array( $param, $except, true ) ) {
				continue;
			}
			$out .= $this->hidden_field( $param, $value );
		}
		return $out;
	}

	private function archive_url(): string {
		$page = get_page_by_path( 'jobs' );
		return $page instanceof \WP_Post ? (string) get_permalink( $page ) : home_url( '/jobs/' );
	}

	private function current_url(): string {
		return $this->archive_url() . $this->query_string();
	}

	private function query_string(): string {
		$request = SearchRequest::from_query( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$args    = $request->active_filters();
		if ( 'recent' !== $request->sort ) {
			$args['sort'] = $request->sort;
		}
		return array() === $args ? '' : '?' . http_build_query( $args );
	}
}
