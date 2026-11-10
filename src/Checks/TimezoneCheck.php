<?php
/**
 * The timezone should be chosen explicitly, not left at the UTC default.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class TimezoneCheck extends AbstractCheck {

	public function id(): string {
		return 'locale.timezone';
	}

	public function title(): string {
		return __( 'The site timezone is set', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_LOW;
	}

	public function run( SiteContext $context ): Result {
		$timezone = trim( (string) $context->option( 'timezone_string', '' ) );
		$offset   = (float) $context->option( 'gmt_offset', 0 );

		if ( '' !== $timezone || 0.0 !== $offset ) {
			return $this->pass( __( 'A timezone is configured.', 'golive-audit' ) );
		}

		return $this->fail(
			__( 'The timezone is still the default (UTC), so scheduled posts and dates may be off.', 'golive-audit' ),
			__( 'Choose a city in the site timezone in Settings > General. Pick UTC explicitly if that is intended.', 'golive-audit' ),
			$context->admin_url( 'options-general.php' )
		);
	}
}
