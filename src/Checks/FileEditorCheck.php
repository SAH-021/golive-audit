<?php
/**
 * The built-in theme and plugin file editor should be disabled on live sites.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class FileEditorCheck extends AbstractCheck {

	public function id(): string {
		return 'security.file_editor';
	}

	public function title(): string {
		return __( 'The dashboard file editor is disabled', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_LOW;
	}

	public function run( SiteContext $context ): Result {
		if ( $context->constant_value( 'DISALLOW_FILE_EDIT' ) || $context->constant_value( 'DISALLOW_FILE_MODS' ) ) {
			return $this->pass( __( 'The dashboard file editor is disabled.', 'golive-audit' ) );
		}

		return $this->fail(
			__( 'Administrators can edit theme and plugin code from the dashboard.', 'golive-audit' ),
			__( "Add define( 'DISALLOW_FILE_EDIT', true ); to wp-config.php so a compromised admin account cannot change code from the browser.", 'golive-audit' )
		);
	}
}
