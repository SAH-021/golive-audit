<?php
/**
 * The authentication keys and salts must be defined and not left at their sample value.
 *
 * Only constant names are reported, never their values.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class SaltsCheck extends AbstractCheck {

	private const CONSTANTS = array(
		'AUTH_KEY',
		'SECURE_AUTH_KEY',
		'LOGGED_IN_KEY',
		'NONCE_KEY',
		'AUTH_SALT',
		'SECURE_AUTH_SALT',
		'LOGGED_IN_SALT',
		'NONCE_SALT',
	);

	private const SAMPLE_VALUE = 'put your unique phrase here';

	public function id(): string {
		return 'security.salts';
	}

	public function title(): string {
		return __( 'Authentication keys and salts are unique', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_CRITICAL;
	}

	public function run( SiteContext $context ): Result {
		$weak = array();

		foreach ( self::CONSTANTS as $name ) {
			$value = $context->constant_value( $name );
			$value = is_string( $value ) ? strtolower( trim( $value ) ) : '';

			if ( '' === $value || self::SAMPLE_VALUE === $value ) {
				$weak[] = $name;
			}
		}

		if ( array() === $weak ) {
			return $this->pass( __( 'All eight authentication keys and salts are defined.', 'golive-audit' ) );
		}

		return $this->fail(
			sprintf(
				/* translators: %s: comma separated list of constant names. */
				__( 'These keys or salts are missing or still use the sample value: %s.', 'golive-audit' ),
				implode( ', ', $weak )
			),
			__( 'Generate fresh values with the WordPress.org secret-key service and put them in wp-config.php. Existing sessions will be logged out.', 'golive-audit' )
		);
	}
}
