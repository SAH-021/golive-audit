<?php
/**
 * The configured site URLs must point at a public production address.
 *
 * Catches sites that still carry a localhost, IP-address or staging host,
 * and WordPress Address / Site Address pairs that disagree on host or scheme.
 * Whether the site is actually served over HTTPS is covered by core's Site Health.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class UrlConsistencyCheck extends AbstractCheck {

	private const TEST_SUFFIXES = array( '.local', '.test', '.localhost', '.invalid', '.example' );

	private const STAGING_LABELS = array( 'staging', 'stage', 'dev', 'development', 'sandbox', 'preview' );

	public function id(): string {
		return 'env.url_consistency';
	}

	public function title(): string {
		return __( 'Site URLs are public and consistent', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_CRITICAL;
	}

	public function environments(): array {
		return array( 'production' );
	}

	public function run( SiteContext $context ): Result {
		$urls = array(
			__( 'WordPress Address', 'golive-audit' ) => (string) $context->option( 'siteurl', '' ),
			__( 'Site Address', 'golive-audit' )      => (string) $context->option( 'home', '' ),
		);

		$issues = array();
		$parsed = array();

		foreach ( $urls as $label => $url ) {
			$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Pure PHP engine, no WordPress available.
			$host  = is_array( $parts ) && isset( $parts['host'] ) ? trim( strtolower( $parts['host'] ), '[]' ) : '';

			if ( '' === $host ) {
				/* translators: %s: setting name, e.g. "Site Address". */
				$issues[] = sprintf( __( '%s is not a valid URL', 'golive-audit' ), $label );
				continue;
			}

			$parsed[ $label ] = array(
				'host'   => $host,
				'scheme' => isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '',
			);

			if ( $this->is_non_public_host( $host ) ) {
				$issues[] = sprintf(
					/* translators: 1: setting name, 2: host name. */
					__( '%1$s uses a local or staging host (%2$s)', 'golive-audit' ),
					$label,
					$host
				);
			}
		}

		if ( 2 === count( $parsed ) ) {
			$values = array_values( $parsed );

			if ( $values[0]['host'] !== $values[1]['host'] || $values[0]['scheme'] !== $values[1]['scheme'] ) {
				$issues[] = __( 'WordPress Address and Site Address use a different host or scheme', 'golive-audit' );
			}
		}

		if ( array() === $issues ) {
			return $this->pass( __( 'Both site URLs point at a public host and agree with each other.', 'golive-audit' ) );
		}

		return $this->fail(
			sprintf(
				/* translators: %s: semicolon separated list of problems. */
				__( 'Site URL problems: %s.', 'golive-audit' ),
				implode( '; ', $issues )
			),
			__( 'Update the URLs in Settings > General (or in wp-config.php) to the final public address, then check that no staging URLs remain in content.', 'golive-audit' ),
			$context->admin_url( 'options-general.php' )
		);
	}

	private function is_non_public_host( string $host ): bool {
		if ( 'localhost' === $host || false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return true;
		}

		foreach ( self::TEST_SUFFIXES as $suffix ) {
			if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		return array() !== array_intersect( explode( '.', $host ), self::STAGING_LABELS );
	}
}
