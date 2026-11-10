<?php

namespace GoliveAudit\Tests\Support;

use GoliveAudit\Audit\CheckInterface;
use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

/**
 * Configurable check for engine tests.
 */
final class FakeCheck implements CheckInterface {

	private string $id;
	private string $severity;
	private array $environments;
	private $callback;

	/**
	 * @param string        $id           Check ID.
	 * @param string        $severity     Failure severity.
	 * @param string[]      $environments Applicable environments.
	 * @param callable|null $callback     function ( SiteContext, FakeCheck ): Result.
	 */
	public function __construct( string $id = 'test.fake', string $severity = Result::SEVERITY_WARNING, array $environments = array( '*' ), ?callable $callback = null ) {
		$this->id           = $id;
		$this->severity     = $severity;
		$this->environments = $environments;
		$this->callback     = $callback;
	}

	public function id(): string {
		return $this->id;
	}

	public function title(): string {
		return 'Fake check';
	}

	public function category(): string {
		return 'test';
	}

	public function severity(): string {
		return $this->severity;
	}

	public function environments(): array {
		return $this->environments;
	}

	public function run( SiteContext $context ): Result {
		if ( null === $this->callback ) {
			return Result::pass( $this, 'ok' );
		}

		return ( $this->callback )( $context, $this );
	}
}
