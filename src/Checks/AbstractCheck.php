<?php
/**
 * Shared defaults for the bundled checks.
 *
 * The category is the first segment of the ID, and checks apply to
 * production and staging unless they say otherwise.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\CheckInterface;
use GoliveAudit\Audit\Result;

abstract class AbstractCheck implements CheckInterface {

	public function category(): string {
		return (string) strstr( $this->id(), '.', true );
	}

	public function environments(): array {
		return array( 'production', 'staging' );
	}

	protected function pass( string $summary ): Result {
		return Result::pass( $this, $summary );
	}

	protected function fail( string $summary, string $recommendation, string $fix_url = '' ): Result {
		return Result::fail( $this, $summary, $recommendation, $fix_url );
	}
}
