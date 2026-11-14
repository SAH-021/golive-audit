<?php
/**
 * Backups, dumps and debug scripts must not be left in the WordPress root.
 *
 * The check only sees that a file exists, not whether the web server serves
 * it, so a hit is a strong hint rather than proof. Only file names are
 * reported, never contents or full paths.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class SensitiveFilesCheck extends AbstractCheck {

	private const FILES = array(
		'wp-config.php.bak',
		'wp-config.php.old',
		'wp-config.php.orig',
		'wp-config.php.save',
		'wp-config.php.swp',
		'wp-config.php~',
		'wp-config.bak',
		'wp-config.old',
		'wp-config.txt',
		'.env',
		'database.sql',
		'db.sql',
		'dump.sql',
		'backup.sql',
		'phpinfo.php',
		'info.php',
	);

	public function id(): string {
		return 'security.sensitive_files';
	}

	public function title(): string {
		return __( 'No backups, database dumps or debug scripts in the site root', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_CRITICAL;
	}

	public function run( SiteContext $context ): Result {
		$found = array();

		foreach ( self::FILES as $name ) {
			if ( null !== $context->root_file_contents( $name, 1 ) ) {
				$found[] = $name;
			}
		}

		if ( array() === $found ) {
			return $this->pass( __( 'No well-known backup, dump or debug files were found in the site root.', 'golive-audit' ) );
		}

		return $this->fail(
			sprintf(
				/* translators: %s: comma separated list of file names. */
				__( 'Sensitive files exist in the site root: %s.', 'golive-audit' ),
				implode( ', ', $found )
			),
			__( 'Delete these files or move them outside the web root. A readable wp-config backup or SQL dump can expose database passwords and customer data.', 'golive-audit' )
		);
	}
}
