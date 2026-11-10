<?php
/**
 * Plain permalinks (?p=123) are bad for SEO and for sharing links.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class PermalinksCheck extends AbstractCheck {

	public function id(): string {
		return 'seo.permalinks';
	}

	public function title(): string {
		return __( 'Pretty permalinks are enabled', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function run( SiteContext $context ): Result {
		if ( '' !== trim( (string) $context->option( 'permalink_structure', '' ) ) ) {
			return $this->pass( __( 'The site uses a custom permalink structure.', 'golive-audit' ) );
		}

		return $this->fail(
			__( 'The site uses plain permalinks such as ?p=123.', 'golive-audit' ),
			__( 'Choose a descriptive structure (for example "Post name") in Settings > Permalinks. Set up redirects first if the site already has indexed URLs.', 'golive-audit' ),
			$context->admin_url( 'options-permalink.php' )
		);
	}
}
