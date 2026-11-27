<?php
/**
 * Reversible Candidate Terms archive, preserving forms and signed records.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

use Poolhall\Integration\Documents\DocumentWorkflow;
use Poolhall\Integration\Documents\FormDefinitions;

/** Applies availability to Gravity Forms and reconciles existing workflows. */
final class TermsArchive {

	private const PAGE_STATUS_OPTION = 'poolhall_candidate_terms_archived_page';

	/** @return array{form_updated:bool,page_updated:bool,records_reconciled:int} */
	public function set_archived( bool $archived ): array {
		update_option( TaskPolicy::TERMS_ARCHIVED_OPTION, $archived ? '1' : '0', false );
		$map     = (array) get_option( DocumentWorkflow::FORM_MAP_OPTION, array() );
		$form_id = (int) ( $map['terms'] ?? 0 );
		$updated = false;
		if ( $form_id > 0 && class_exists( '\\GFAPI' ) ) {
			$form = \GFAPI::get_form( $form_id );
			if ( is_array( $form ) ) {
				$active  = ! $archived && FormDefinitions::candidate_terms_ready() ? '1' : '0';
				$result  = \GFAPI::update_form_property( $form_id, 'is_active', $active );
				$updated = ! is_wp_error( $result ) && false !== $result;
			}
		}

		$page_updated = $this->sync_page( $archived );
		$state   = new WorkflowState();
		$invites = new InviteManager( $state );
		$count   = 0;
		do {
			$records = get_posts(
				array(
					'post_type'      => 'poolhall_application',
					'post_status'    => 'any',
					'posts_per_page' => 100,
					'offset'         => $count,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					// Status and evidence are retained; only the active stage is recalculated.
					'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'relation' => 'AND',
						array(
							'key'     => 'kind',
							'value'   => array( 'registration', 'job_application' ),
							'compare' => 'IN',
						),
						array(
							'key'     => 'task_terms_status',
							'compare' => 'EXISTS',
						),
					),
				)
			);
			$batch_count = count( $records );
			foreach ( $records as $record_id ) {
				if ( $archived ) {
					$invites->revoke_document( (int) $record_id, 'terms' );
				}
				$state->reconcile( (int) $record_id );
				++$count;
			}
		} while ( 100 === $batch_count );

		return array(
			'form_updated' => $updated,
			'page_updated' => $page_updated,
			'records_reconciled' => $count,
		);
	}

	/** Keep the Elementor page as a draft and restore its previous status later. */
	private function sync_page( bool $archived ): bool {
		$page   = get_page_by_path( 'candidate-terms' );
		$hidden = array_map( 'intval', (array) get_option( DocumentWorkflow::HIDDEN_PAGES_OPTION, array() ) );
		if ( ! $page instanceof \WP_Post || ! in_array( $page->ID, $hidden, true ) || 'trash' === $page->post_status ) {
			return false;
		}
		$previous = (array) get_option( self::PAGE_STATUS_OPTION, array() );
		if ( $archived ) {
			if ( (int) ( $previous['id'] ?? 0 ) !== $page->ID ) {
				update_option(
					self::PAGE_STATUS_OPTION,
					array(
						'id' => $page->ID,
						'status' => $page->post_status,
					),
					false
				);
			}
			$status = 'draft';
		} else {
			if ( (int) ( $previous['id'] ?? 0 ) !== $page->ID ) {
				return true;
			}
			$status = (string) ( $previous['status'] ?? 'draft' );
		}
		$updated = (int) wp_update_post(
			array(
				'ID' => $page->ID,
				'post_status' => $status,
			)
		) === $page->ID;
		if ( $updated && ! $archived ) {
			delete_option( self::PAGE_STATUS_OPTION );
		}
		return $updated;
	}
}
