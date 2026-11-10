<?php
/**
 * A privacy policy page must be assigned and published.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class PrivacyPolicyCheck extends AbstractCheck {

	public function id(): string {
		return 'legal.privacy_policy';
	}

	public function title(): string {
		return __( 'A privacy policy page is published', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function environments(): array {
		return array( 'production' );
	}

	public function run( SiteContext $context ): Result {
		$page_id = (int) $context->option( 'wp_page_for_privacy_policy', 0 );
		$status  = $page_id > 0 ? $context->post_status( $page_id ) : null;

		if ( 'publish' === $status ) {
			return $this->pass( __( 'A privacy policy page is assigned and published.', 'golive-audit' ) );
		}

		return $this->fail(
			0 === $page_id
				? __( 'No privacy policy page is assigned.', 'golive-audit' )
				: __( 'The assigned privacy policy page is missing or not published.', 'golive-audit' ),
			__( 'Write the policy (WordPress provides a template) and assign the published page in Settings > Privacy.', 'golive-audit' ),
			$context->admin_url( 'options-privacy.php' )
		);
	}
}
