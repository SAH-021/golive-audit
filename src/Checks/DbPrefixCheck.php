<?php
/**
 * The default "wp_" database table prefix makes blind SQL injection attempts easier to aim.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class DbPrefixCheck extends AbstractCheck {

	public function id(): string {
		return 'security.db_prefix';
	}

	public function title(): string {
		return __( 'The database table prefix is not the default', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_LOW;
	}

	public function run( SiteContext $context ): Result {
		if ( 'wp_' !== strtolower( $context->db_prefix() ) ) {
			return $this->pass( __( 'A custom database table prefix is used.', 'golive-audit' ) );
		}

		return $this->fail(
			__( 'The database table prefix is the default "wp_".', 'golive-audit' ),
			__( 'Choose a custom $table_prefix in wp-config.php on new installs. Renaming tables on a live site is risky, so accept the risk if the site is already running.', 'golive-audit' )
		);
	}
}
