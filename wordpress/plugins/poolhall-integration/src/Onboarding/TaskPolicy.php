<?php
/**
 * Availability of candidate tasks, separate from retained task history.
 *
 * @package Poolhall\Integration
 */

declare(strict_types=1);

namespace Poolhall\Integration\Onboarding;

/** Archived tasks retain their evidence but cannot be issued or completed. */
final class TaskPolicy {

	public const TERMS_ARCHIVED_OPTION = 'poolhall_candidate_terms_archived';

	public static function archived( string $kind ): bool {
		return 'terms' === $kind && '1' === (string) get_option( self::TERMS_ARCHIVED_OPTION, '0' );
	}

	/** @return string[] */
	public static function tasks(): array {
		return array_values( array_filter( WorkflowState::TASKS, static fn( string $kind ): bool => ! self::archived( $kind ) ) );
	}

	/** @return string[] */
	public static function documents(): array {
		return array_values( array_diff( self::tasks(), array( 'full' ) ) );
	}
}
