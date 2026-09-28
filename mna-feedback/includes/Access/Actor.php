<?php
/**
 * The identity performing a request, with its feedback role.
 *
 * @package MNA\Feedback
 */

namespace MNA\Feedback\Access;

defined( 'ABSPATH' ) || exit;

final class Actor {

	public function __construct(
		public readonly int $reviewer_id,
		public readonly string $type,
		public readonly int $wp_user_id,
		public readonly string $role,
		public readonly string $name,
		public readonly string $joined_at,
		public readonly int $session_id = 0,
		public readonly int $link_id = 0,
		public readonly string $source = 'ui',
	) {}

	public function is_guest(): bool {
		return 'guest' === $this->type;
	}

	public function is_manager(): bool {
		return 'manager' === $this->role;
	}

	public function is_implementer(): bool {
		return in_array( $this->role, array( 'implementer', 'manager' ), true );
	}

	/**
	 * The same identity acting through another channel (for example an agent).
	 */
	public function via( string $source ): self {
		return new self(
			$this->reviewer_id,
			$this->type,
			$this->wp_user_id,
			$this->role,
			$this->name,
			$this->joined_at,
			$this->session_id,
			$this->link_id,
			$source
		);
	}
}
