<?php
/**
 * Dynamic sector-job cards for Elementor pages.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\DesignSystem;

use Poolhall\Integration\Jobs\JobPostType;

/**
 * Keeps sector landing pages connected to the canonical synced job posts.
 *
 * Static page content remains editable in Elementor; this small adapter owns
 * only the live query and card data that must never be copied into page JSON.
 */
final class SectorJobs {

	public const SHORTCODE = 'poolhall_sector_jobs';

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Put requested live roles first, then fill remaining slots in source order.
	 *
	 * @param array<int,array{id:int,title:string}> $rows             Candidate rows.
	 * @param string[]                             $preferred_titles Preferred titles.
	 * @return array<int,array{id:int,title:string}>
	 */
	public static function prioritise( array $rows, array $preferred_titles, int $limit ): array {
		$limit = max( 1, $limit );
		$used  = array();
		$out   = array();

		foreach ( $preferred_titles as $preferred ) {
			$needle = self::normalise_title( $preferred );
			foreach ( $rows as $row ) {
				$id = (int) ( $row['id'] ?? 0 );
				if ( $id <= 0 || isset( $used[ $id ] ) ) {
					continue;
				}
				if ( self::normalise_title( (string) ( $row['title'] ?? '' ) ) === $needle ) {
					$out[]       = $row;
					$used[ $id ] = true;
					break;
				}
			}
			if ( count( $out ) >= $limit ) {
				return array_slice( $out, 0, $limit );
			}
		}

		foreach ( $rows as $row ) {
			$id = (int) ( $row['id'] ?? 0 );
			if ( $id <= 0 || isset( $used[ $id ] ) ) {
				continue;
			}
			$out[]       = $row;
			$used[ $id ] = true;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * Render live cards for a sector.
	 *
	 * @param array<string,string>|string $atts Shortcode attributes.
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'sector' => 'construction',
				'limit'  => '2',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);

		$sector = sanitize_key( (string) $atts['sector'] );
		$limit  = min( 4, max( 1, (int) $atts['limit'] ) );
		$ids    = $this->job_ids( $sector );
		$rows   = array_map(
			static fn( int $post_id ): array => array(
				'id'    => $post_id,
				'title' => (string) get_the_title( $post_id ),
			),
			$ids
		);

		$preferred = 'construction' === $sector
			? array( 'Quality Assurance – Clerk of works', 'Quantity Surveyor' )
			: array();
		$rows      = self::prioritise( $rows, $preferred, $limit );

		if ( array() === $rows ) {
			return '<div class="ph3-jobs-empty"><p>No matching roles are live today. Register your CV and we will let you know when the right one lands.</p></div>';
		}

		$cards = '';
		foreach ( $rows as $row ) {
			$cards .= $this->card( (int) $row['id'] );
		}

		return '<div class="ph3-job-list">' . $cards . '</div>';
	}

	/** @return int[] */
	private function job_ids( string $sector ): array {
		$needles = match ( $sector ) {
			'manufacturing' => array( 'manufact' ),
			'digital'       => array( 'digital', 'market', 'creative', 'media', 'seo' ),
			default         => array( 'construct' ),
		};
		$terms   = get_terms(
			array(
				'taxonomy'   => JobPostType::TAX_SECTOR,
				'hide_empty' => true,
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$term_ids = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$name = strtolower( $term->name );
			foreach ( $needles as $needle ) {
				if ( str_contains( $name, $needle ) ) {
					$term_ids[] = (int) $term->term_id;
					break;
				}
			}
		}
		if ( array() === $term_ids ) {
			return array();
		}

		$found = get_posts(
			array(
				'post_type'      => JobPostType::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 12,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- bounded display query on the job CPT.
					array(
						'taxonomy' => JobPostType::TAX_SECTOR,
						'field'    => 'term_id',
						'terms'    => array_values( array_unique( $term_ids ) ),
					),
				),
			)
		);

		return array_map( 'intval', is_array( $found ) ? $found : array() );
	}

	private function card( int $post_id ): string {
		$title    = (string) get_the_title( $post_id );
		$url      = (string) get_permalink( $post_id );
		$location = (string) get_post_meta( $post_id, 'location_display', true );
		$salary   = (string) get_post_meta( $post_id, 'salary_display', true );
		$types    = wp_get_object_terms( $post_id, JobPostType::TAX_JOB_TYPE, array( 'fields' => 'names' ) );
		$type     = is_array( $types ) && array() !== $types ? (string) $types[0] : '';
		$ref      = (string) get_post_meta( $post_id, 'source_job_id', true );
		$source   = V2Fragments::strip_leading_meta( (string) get_post_field( 'post_content', $post_id ) );
		$snippet  = wp_trim_words( wp_strip_all_tags( str_replace( '<', ' <', $source ) ), 30, '&hellip;' );

		$meta = '';
		if ( '' !== $location ) {
			$meta .= '<span><i class="fas fa-map-marker-alt" aria-hidden="true"></i>' . esc_html( $location ) . '</span>';
		}
		if ( '' !== $salary ) {
			$meta .= '<span><i class="fas fa-coins" aria-hidden="true"></i>' . esc_html( $salary ) . '</span>';
		}
		if ( '' !== $type ) {
			$meta .= '<span><i class="fas fa-briefcase" aria-hidden="true"></i>' . esc_html( $type ) . '</span>';
		}

		return '<article class="ph3-job-card">'
			. '<h3><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></h3>'
			. ( '' !== $meta ? '<div class="ph3-job-card__meta">' . $meta . '</div>' : '' )
			. ( '' !== $snippet ? '<p>' . esc_html( $snippet ) . '</p>' : '' )
			. '<div class="ph3-job-card__foot"><a href="' . esc_url( $url ) . '">View job <i class="fas fa-arrow-right" aria-hidden="true"></i></a>'
			. ( '' !== $ref ? '<span>#' . esc_html( $ref ) . '</span>' : '' )
			. '</div></article>';
	}

	private static function normalise_title( string $title ): string {
		$title = strtolower( html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		return trim( (string) preg_replace( '/[^a-z0-9]+/', ' ', $title ) );
	}
}
