<?php
/**
 * The administration email must be set and must not use a placeholder domain.
 *
 * The address itself is never written to the result: reports can be shared
 * with clients, so only the domain is mentioned.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class AdminEmailCheck extends AbstractCheck {

	/**
	 * Domains reserved for documentation and testing (RFC 2606 / RFC 6761).
	 */
	private const PLACEHOLDER_DOMAINS = array( 'example.com', 'example.org', 'example.net', 'localhost' );

	/**
	 * Reserved or non-routable top-level domains.
	 */
	private const PLACEHOLDER_SUFFIXES = array( '.test', '.example', '.invalid', '.localhost', '.local' );

	public function id(): string {
		return 'email.admin_address';
	}

	public function title(): string {
		return __( 'Administration email address is real', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function environments(): array {
		return array( 'production' );
	}

	public function run( SiteContext $context ): Result {
		$email          = strtolower( trim( (string) $context->option( 'admin_email', '' ) ) );
		$fix_url        = $context->admin_url( 'options-general.php' );
		$recommendation = __( 'Set a monitored address in Settings > General so WordPress notifications reach the site owner.', 'golive-audit' );

		if ( false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return $this->fail( __( 'The administration email address is missing or invalid.', 'golive-audit' ), $recommendation, $fix_url );
		}

		$domain = substr( $email, (int) strrpos( $email, '@' ) + 1 );

		if ( $this->is_placeholder_domain( $domain ) ) {
			return $this->fail(
				sprintf(
					/* translators: %s: email domain, e.g. "example.com". */
					__( 'The administration email address uses a placeholder or reserved domain (%s).', 'golive-audit' ),
					$domain
				),
				$recommendation,
				$fix_url
			);
		}

		return $this->pass( __( 'The administration email address uses a non-placeholder domain.', 'golive-audit' ) );
	}

	private function is_placeholder_domain( string $domain ): bool {
		if ( in_array( $domain, self::PLACEHOLDER_DOMAINS, true ) ) {
			return true;
		}

		foreach ( self::PLACEHOLDER_SUFFIXES as $suffix ) {
			if ( substr( $domain, -strlen( $suffix ) ) === $suffix ) {
				return true;
			}
		}

		return false;
	}
}
