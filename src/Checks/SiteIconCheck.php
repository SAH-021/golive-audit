<?php
/**
 * A site icon (favicon) should be set.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class SiteIconCheck extends AbstractCheck {

	public function id(): string {
		return 'seo.site_icon';
	}

	public function title(): string {
		return __( 'A site icon is set', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_LOW;
	}

	public function run( SiteContext $context ): Result {
		if ( (int) $context->option( 'site_icon', 0 ) > 0 ) {
			return $this->pass( __( 'A site icon is set.', 'golive-audit' ) );
		}

		return $this->fail(
			__( 'No site icon is set, so browsers show a generic icon.', 'golive-audit' ),
			__( 'Upload a square image of at least 512 x 512 pixels as the site icon in Appearance > Customize.', 'golive-audit' ),
			$context->admin_url( 'customize.php' )
		);
	}
}
