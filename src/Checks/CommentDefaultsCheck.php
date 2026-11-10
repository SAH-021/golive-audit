<?php
/**
 * Default comment settings should not invite spam.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class CommentDefaultsCheck extends AbstractCheck {

	public function id(): string {
		return 'content.comment_defaults';
	}

	public function title(): string {
		return __( 'Comment and pingback defaults are intentional', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_LOW;
	}

	public function run( SiteContext $context ): Result {
		$issues = array();

		if ( 'open' === (string) $context->option( 'default_ping_status', 'open' ) ) {
			$issues[] = __( 'pingbacks and trackbacks are enabled for new content', 'golive-audit' );
		}

		$comments_open = 'open' === (string) $context->option( 'default_comment_status', 'open' );
		$moderated     = '1' === (string) $context->option( 'comment_moderation', '0' )
			|| '1' === (string) $context->option( 'comment_previously_approved', '1' );

		if ( $comments_open && ! $moderated ) {
			$issues[] = __( 'new comments are published without any moderation', 'golive-audit' );
		}

		if ( array() === $issues ) {
			return $this->pass( __( 'Comment and pingback defaults look intentional.', 'golive-audit' ) );
		}

		return $this->fail(
			sprintf(
				/* translators: %s: list of problems. */
				__( 'Discussion defaults: %s.', 'golive-audit' ),
				implode( '; ', $issues )
			),
			__( 'Review Settings > Discussion: disable pingbacks unless needed and require moderation or a previously approved comment.', 'golive-audit' ),
			$context->admin_url( 'options-discussion.php' )
		);
	}
}
