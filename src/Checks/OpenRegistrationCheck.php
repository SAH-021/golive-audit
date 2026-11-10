<?php
/**
 * Open registration must never hand out privileged roles.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class OpenRegistrationCheck extends AbstractCheck {

	private const PRIVILEGED_ROLES = array( 'administrator', 'editor' );

	public function id(): string {
		return 'security.open_registration';
	}

	public function title(): string {
		return __( 'Open registration does not grant privileged roles', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_CRITICAL;
	}

	public function run( SiteContext $context ): Result {
		if ( '1' !== (string) $context->option( 'users_can_register', '0' ) ) {
			return $this->pass( __( 'Public registration is disabled.', 'golive-audit' ) );
		}

		$role = (string) $context->option( 'default_role', 'subscriber' );

		if ( in_array( $role, self::PRIVILEGED_ROLES, true ) ) {
			return $this->fail(
				sprintf(
					/* translators: %s: role name, e.g. "administrator". */
					__( 'Anyone can register and receives the "%s" role.', 'golive-audit' ),
					$role
				),
				__( 'Set the new user default role to Subscriber (or disable registration) in Settings > General.', 'golive-audit' ),
				$context->admin_url( 'options-general.php' )
			);
		}

		return $this->pass(
			sprintf(
				/* translators: %s: role name, e.g. "subscriber". */
				__( 'Public registration is open, but new users only receive the "%s" role.', 'golive-audit' ),
				$role
			)
		);
	}
}
