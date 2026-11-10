<?php
/**
 * Search engine visibility must match the environment.
 *
 * Production should be indexable; staging and development should not be.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class NoindexCheck extends AbstractCheck {

	public function id(): string {
		return 'seo.search_visibility';
	}

	public function title(): string {
		return __( 'Search engine visibility matches the environment', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_CRITICAL;
	}

	public function environments(): array {
		return array( 'production', 'staging', 'development' );
	}

	public function run( SiteContext $context ): Result {
		$discouraged = '0' === (string) $context->option( 'blog_public', '1' );
		$environment = $context->environment();
		$fix_url     = $context->admin_url( 'options-reading.php' );

		if ( 'production' === $environment ) {
			if ( $discouraged ) {
				return $this->fail(
					__( 'Search engines are discouraged from indexing this production site.', 'golive-audit' ),
					__( 'Untick "Discourage search engines from indexing this site" in Settings > Reading. If this site is not production, set WP_ENVIRONMENT_TYPE or run the audit with another environment.', 'golive-audit' ),
					$fix_url
				);
			}

			return $this->pass( __( 'Search engines are allowed to index this production site.', 'golive-audit' ) );
		}

		if ( $discouraged ) {
			return $this->pass(
				sprintf(
					/* translators: %s: environment name, e.g. "staging". */
					__( 'Indexing is disabled, as expected for a "%s" environment.', 'golive-audit' ),
					$environment
				)
			);
		}

		return $this->fail(
			sprintf(
				/* translators: %s: environment name, e.g. "staging". */
				__( 'This "%s" environment can be indexed by search engines.', 'golive-audit' ),
				$environment
			),
			__( 'Enable "Discourage search engines from indexing this site" in Settings > Reading so test content does not appear in search results.', 'golive-audit' ),
			$fix_url
		);
	}
}
