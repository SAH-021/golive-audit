<?php
/**
 * XML-RPC is rarely needed and is a common target for brute-force and pingback abuse.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class XmlRpcCheck extends AbstractCheck {

	public function id(): string {
		return 'security.xmlrpc';
	}

	public function title(): string {
		return __( 'XML-RPC is disabled unless it is needed', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_LOW;
	}

	public function run( SiteContext $context ): Result {
		if ( ! $context->xmlrpc_enabled() ) {
			return $this->pass( __( 'XML-RPC is disabled.', 'golive-audit' ) );
		}

		return $this->fail(
			__( 'XML-RPC is enabled.', 'golive-audit' ),
			__( 'Disable it with the xmlrpc_enabled filter or a security plugin, unless you use the WordPress mobile app, Jetpack or another service that needs it. Accept the risk if it is needed.', 'golive-audit' )
		);
	}
}
