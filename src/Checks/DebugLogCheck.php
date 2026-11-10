<?php
/**
 * A leftover debug.log can expose paths, queries and secrets.
 *
 * The check only sees that the file exists; it cannot tell whether the web
 * server serves it publicly.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class DebugLogCheck extends AbstractCheck {

	public function id(): string {
		return 'security.debug_log';
	}

	public function title(): string {
		return __( 'No leftover debug.log in wp-content', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function run( SiteContext $context ): Result {
		if ( ! $context->content_file_exists( 'debug.log' ) ) {
			return $this->pass( __( 'No debug.log file was found in wp-content.', 'golive-audit' ) );
		}

		return $this->fail(
			__( 'A debug.log file exists in wp-content.', 'golive-audit' ),
			__( 'Review and delete it, turn off WP_DEBUG_LOG on live sites, and make sure your server does not serve .log files.', 'golive-audit' )
		);
	}
}
