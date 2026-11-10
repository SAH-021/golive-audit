<?php
/**
 * The site needs a title and must not keep the default tagline.
 *
 * Known limitation: the default tagline is detected in English only.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class SiteIdentityCheck extends AbstractCheck {

	private const DEFAULT_TAGLINE = 'just another wordpress site';

	public function id(): string {
		return 'seo.site_identity';
	}

	public function title(): string {
		return __( 'Site title and tagline are set', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function run( SiteContext $context ): Result {
		$issues = array();

		if ( '' === trim( (string) $context->option( 'blogname', '' ) ) ) {
			$issues[] = __( 'the site title is empty', 'golive-audit' );
		}

		if ( self::DEFAULT_TAGLINE === strtolower( trim( (string) $context->option( 'blogdescription', '' ) ) ) ) {
			$issues[] = __( 'the tagline is still "Just another WordPress site"', 'golive-audit' );
		}

		if ( array() === $issues ) {
			return $this->pass( __( 'The site has a title and no default tagline.', 'golive-audit' ) );
		}

		return $this->fail(
			sprintf(
				/* translators: %s: list of problems. */
				__( 'Site identity problems: %s.', 'golive-audit' ),
				implode( '; ', $issues )
			),
			__( 'Set a real title and tagline (or clear the tagline) in Settings > General.', 'golive-audit' ),
			$context->admin_url( 'options-general.php' )
		);
	}
}
