<?php
/**
 * Avoids the guessable "admin" account and an excess of administrators.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class AdminUsersCheck extends AbstractCheck {

	public const MAX_ADMINISTRATORS = 3;

	public function id(): string {
		return 'security.admin_users';
	}

	public function title(): string {
		return __( 'Administrator accounts are limited and not guessable', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function run( SiteContext $context ): Result {
		$issues = array();

		if ( $context->username_exists( 'admin' ) ) {
			$issues[] = __( 'a user named "admin" exists', 'golive-audit' );
		}

		$administrators = $context->user_count_by_role( 'administrator' );

		if ( $administrators > self::MAX_ADMINISTRATORS ) {
			$issues[] = sprintf(
				/* translators: 1: number of administrators, 2: recommended maximum. */
				__( '%1$d administrator accounts exist (more than %2$d)', 'golive-audit' ),
				$administrators,
				self::MAX_ADMINISTRATORS
			);
		}

		if ( array() === $issues ) {
			return $this->pass( __( 'No "admin" user was found and the number of administrators is small.', 'golive-audit' ) );
		}

		return $this->fail(
			sprintf(
				/* translators: %s: list of problems. */
				__( 'Account hygiene: %s.', 'golive-audit' ),
				implode( '; ', $issues )
			),
			__( 'Rename or replace the "admin" login and give people only the role they need.', 'golive-audit' ),
			$context->admin_url( 'users.php?role=administrator' )
		);
	}
}
