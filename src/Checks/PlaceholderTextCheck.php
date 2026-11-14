<?php
/**
 * Published content must not still contain "lorem ipsum" filler text.
 *
 * Looks for the Latin filler and its common Persian spelling in the title or
 * content of published posts and pages. Only the number of matching items is
 * reported, never the text itself.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class PlaceholderTextCheck extends AbstractCheck {

	private const NEEDLES = array( 'lorem ipsum', 'لورم ایپسوم' );

	public function id(): string {
		return 'content.placeholder_text';
	}

	public function title(): string {
		return __( 'No placeholder (lorem ipsum) text is published', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function run( SiteContext $context ): Result {
		$matches = 0;

		foreach ( self::NEEDLES as $needle ) {
			$matches += $context->count_published_posts_matching( $needle );
		}

		if ( 0 === $matches ) {
			return $this->pass( __( 'No published post or page contains lorem ipsum filler text.', 'golive-audit' ) );
		}

		return $this->fail(
			sprintf(
				/* translators: %d: number of published posts or pages containing filler text. */
				_n(
					'%d published post or page still contains lorem ipsum filler text.',
					'%d published posts or pages still contain lorem ipsum filler text.',
					$matches,
					'golive-audit'
				),
				$matches
			),
			__( 'Search your pages for "lorem ipsum", then replace the filler with real content or unpublish those pages before launch.', 'golive-audit' ),
			$context->admin_url( 'edit.php?post_type=page&s=lorem+ipsum' )
		);
	}
}
