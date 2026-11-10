<?php
/**
 * A physical robots.txt must not block the whole site for every crawler.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class RobotsTxtCheck extends AbstractCheck {

	public function id(): string {
		return 'seo.robots_txt';
	}

	public function title(): string {
		return __( 'robots.txt does not block the whole site', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_CRITICAL;
	}

	public function environments(): array {
		return array( 'production' );
	}

	public function run( SiteContext $context ): Result {
		$contents = $context->root_file_contents( 'robots.txt' );

		if ( null === $contents ) {
			return $this->pass( __( 'There is no physical robots.txt; WordPress serves its own, controlled by the search visibility setting.', 'golive-audit' ) );
		}

		if ( $this->blocks_everything( $contents ) ) {
			return $this->fail(
				__( 'robots.txt tells all crawlers to stay away from the entire site.', 'golive-audit' ),
				__( 'Remove the "Disallow: /" rule for "User-agent: *" from the robots.txt file in the WordPress root.', 'golive-audit' )
			);
		}

		return $this->pass( __( 'robots.txt does not block the whole site.', 'golive-audit' ) );
	}

	/**
	 * Whether a rule group for "User-agent: *" contains "Disallow: /".
	 */
	private function blocks_everything( string $contents ): bool {
		$agents   = array();
		$in_rules = false;

		foreach ( (array) preg_split( '/\R/', $contents ) as $line ) {
			$line = trim( (string) preg_replace( '/#.*$/', '', (string) $line ) );

			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $key, $value ) = array_map( 'trim', explode( ':', $line, 2 ) );
			$key                 = strtolower( $key );

			if ( 'user-agent' === $key ) {
				if ( $in_rules ) {
					$agents   = array();
					$in_rules = false;
				}

				$agents[] = strtolower( $value );
				continue;
			}

			if ( 'allow' === $key || 'disallow' === $key ) {
				$in_rules = true;
			}

			if ( 'disallow' === $key && '/' === $value && in_array( '*', $agents, true ) ) {
				return true;
			}
		}

		return false;
	}
}
