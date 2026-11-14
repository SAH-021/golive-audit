<?php
/**
 * Files that ship with WordPress or accumulate on servers and reveal details about the site.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class LeftoverFilesCheck extends AbstractCheck {

	private const FILES = array( 'readme.html', 'license.txt', 'wp-config-sample.php', 'error_log' );

	public function id(): string {
		return 'security.leftover_files';
	}

	public function title(): string {
		return __( 'Install leftovers are removed from the site root', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_LOW;
	}

	public function run( SiteContext $context ): Result {
		$found = array();

		foreach ( self::FILES as $name ) {
			if ( null !== $context->root_file_contents( $name, 1 ) ) {
				$found[] = $name;
			}
		}

		if ( array() === $found ) {
			return $this->pass( __( 'No install leftovers were found in the site root.', 'golive-audit' ) );
		}

		return $this->fail(
			sprintf(
				/* translators: %s: comma separated list of file names. */
				__( 'These files are still in the site root: %s.', 'golive-audit' ),
				implode( ', ', $found )
			),
			__( 'Delete them. readme.html and license.txt reveal the WordPress version, and error_log can expose paths and warnings.', 'golive-audit' )
		);
	}
}
