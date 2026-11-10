<?php
/**
 * WordPress should not rely on the server's default PHP mail().
 *
 * This is a heuristic: it detects that a mail transport is configured, not
 * that messages are delivered.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class MailTransportCheck extends AbstractCheck {

	public function id(): string {
		return 'email.transport';
	}

	public function title(): string {
		return __( 'Outgoing email uses a configured transport', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function environments(): array {
		return array( 'production' );
	}

	public function run( SiteContext $context ): Result {
		if ( $context->has_mail_transport() ) {
			return $this->pass( __( 'A custom mail transport is configured. Delivery itself is not verified, so send a test message.', 'golive-audit' ) );
		}

		return $this->fail(
			__( 'No custom mail transport was detected, so WordPress will use PHP mail().', 'golive-audit' ),
			__( 'Configure SMTP or a transactional email service and send a real test message, because form notifications and password resets often go missing otherwise.', 'golive-audit' )
		);
	}
}
