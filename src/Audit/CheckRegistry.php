<?php
/**
 * Holds the registered checks in registration order.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Audit;

use InvalidArgumentException;

final class CheckRegistry {

	/**
	 * @var array<string, CheckInterface>
	 */
	private array $checks = array();

	/**
	 * Registers a check.
	 *
	 * @throws InvalidArgumentException When the ID is malformed or already taken.
	 */
	public function register( CheckInterface $check ): void {
		$id = $check->id();

		if ( 1 !== preg_match( '/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $id ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'Invalid check ID "%s". Use lowercase dot-separated segments such as "seo.search_visibility".', $id ) )
			);
		}

		if ( isset( $this->checks[ $id ] ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'A check with the ID "%s" is already registered.', $id ) )
			);
		}

		$this->checks[ $id ] = $check;
	}

	public function get( string $id ): ?CheckInterface {
		return $this->checks[ $id ] ?? null;
	}

	/**
	 * @return CheckInterface[] Checks in registration order.
	 */
	public function all(): array {
		return array_values( $this->checks );
	}
}
