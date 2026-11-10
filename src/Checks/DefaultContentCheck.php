<?php
/**
 * Flags the sample post and page WordPress creates on install.
 *
 * Known limitation: detection uses the English default slugs, so installs
 * whose default slugs were translated are not detected.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class DefaultContentCheck extends AbstractCheck {

	public function id(): string {
		return 'content.default_content';
	}

	public function title(): string {
		return __( 'Default sample content has been removed', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function run( SiteContext $context ): Result {
		$found = array();

		if ( $context->published_post_exists( 'hello-world', 'post' ) ) {
			$found[] = __( 'the "Hello world!" post', 'golive-audit' );
		}

		if ( $context->published_post_exists( 'sample-page', 'page' ) ) {
			$found[] = __( 'the "Sample Page" page', 'golive-audit' );
		}

		if ( array() === $found ) {
			return $this->pass( __( 'No default sample content was found.', 'golive-audit' ) );
		}

		return $this->fail(
			sprintf(
				/* translators: %s: list of leftover items, e.g. 'the "Hello world!" post, the "Sample Page" page'. */
				__( 'Published sample content is still present: %s.', 'golive-audit' ),
				implode( ', ', $found )
			),
			__( 'Delete or replace the default sample post and page before launch.', 'golive-audit' ),
			$context->admin_url( 'edit.php' )
		);
	}
}
