<?php
/**
 * If a static front page is configured, that page must exist and be published.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class FrontPageCheck extends AbstractCheck {

	public function id(): string {
		return 'seo.front_page';
	}

	public function title(): string {
		return __( 'The front page is configured correctly', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function run( SiteContext $context ): Result {
		if ( 'page' !== (string) $context->option( 'show_on_front', 'posts' ) ) {
			return $this->pass( __( 'The front page shows the latest posts.', 'golive-audit' ) );
		}

		$page_id = (int) $context->option( 'page_on_front', 0 );
		$status  = $page_id > 0 ? $context->post_status( $page_id ) : null;

		if ( 'publish' === $status ) {
			return $this->pass( __( 'The static front page is published.', 'golive-audit' ) );
		}

		return $this->fail(
			0 === $page_id
				? __( 'A static front page is enabled but no page is selected.', 'golive-audit' )
				: __( 'The selected static front page is missing or not published.', 'golive-audit' ),
			__( 'Pick a published page as the homepage in Settings > Reading.', 'golive-audit' ),
			$context->admin_url( 'options-reading.php' )
		);
	}
}
