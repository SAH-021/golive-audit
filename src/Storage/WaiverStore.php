<?php
/**
 * Accepted risks: a failed check can be waived with a written reason.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Storage;

use InvalidArgumentException;

final class WaiverStore {

	public const OPTION     = 'golive_audit_waivers';
	public const MAX_REASON = 500;

	private OptionStore $store;

	public function __construct( OptionStore $store ) {
		$this->store = $store;
	}

	/**
	 * @return array<string, array{reason: string, waived_at: int}> Waivers keyed by check ID.
	 */
	public function all(): array {
		$stored = $this->store->get( self::OPTION, array() );
		$clean  = array();

		if ( ! is_array( $stored ) ) {
			return $clean;
		}

		foreach ( $stored as $id => $waiver ) {
			if ( is_string( $id ) && is_array( $waiver ) && isset( $waiver['reason'] ) && is_string( $waiver['reason'] ) ) {
				$clean[ $id ] = array(
					'reason'    => $waiver['reason'],
					'waived_at' => isset( $waiver['waived_at'] ) ? (int) $waiver['waived_at'] : 0,
				);
			}
		}

		return $clean;
	}

	/**
	 * @return array<string, string> Reasons keyed by check ID, as Report::with_waivers() expects.
	 */
	public function reasons(): array {
		return array_map(
			static function ( array $waiver ): string {
				return $waiver['reason'];
			},
			$this->all()
		);
	}

	/**
	 * @throws InvalidArgumentException When the reason is empty.
	 */
	public function set( string $check_id, string $reason, int $now = 0 ): void {
		$reason = mb_substr( trim( $reason ), 0, self::MAX_REASON );

		if ( '' === $reason ) {
			throw new InvalidArgumentException( 'A waiver needs a reason.' );
		}

		$waivers              = $this->all();
		$waivers[ $check_id ] = array(
			'reason'    => $reason,
			'waived_at' => $now > 0 ? $now : time(),
		);

		$this->store->update( self::OPTION, $waivers );
	}

	public function remove( string $check_id ): void {
		$waivers = $this->all();

		if ( isset( $waivers[ $check_id ] ) ) {
			unset( $waivers[ $check_id ] );
			$this->store->update( self::OPTION, $waivers );
		}
	}
}
