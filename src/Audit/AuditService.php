<?php
/**
 * Application service: runs the audit and applies the stored waivers.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Audit;

use GoliveAudit\Storage\WaiverStore;

final class AuditService {

	private AuditRunner $runner;
	private WaiverStore $waivers;

	public function __construct( AuditRunner $runner, WaiverStore $waivers ) {
		$this->runner  = $runner;
		$this->waivers = $waivers;
	}

	/**
	 * @param SiteContext $context     Site to audit.
	 * @param string|null $environment Optional environment override.
	 */
	public function run( SiteContext $context, ?string $environment = null ): Report {
		return $this->runner->run( $context, $environment )->with_waivers( $this->waivers->reasons() );
	}
}
