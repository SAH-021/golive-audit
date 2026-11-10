<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\AuditRunner;
use GoliveAudit\Audit\CheckRegistry;
use GoliveAudit\Audit\Result;
use GoliveAudit\Tests\Support\ArrayContext;
use GoliveAudit\Tests\Support\FakeCheck;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuditRunnerTest extends TestCase {

	private function runner( FakeCheck ...$checks ): AuditRunner {
		$registry = new CheckRegistry();

		foreach ( $checks as $check ) {
			$registry->register( $check );
		}

		return new AuditRunner( $registry, '9.9.9' );
	}

	public function test_a_check_that_throws_becomes_an_error_not_a_failure(): void {
		$boom = new FakeCheck(
			'test.boom',
			Result::SEVERITY_CRITICAL,
			array( '*' ),
			static function () {
				throw new RuntimeException( '/var/www/secret/path.php exploded' );
			}
		);

		$report = $this->runner( $boom, new FakeCheck( 'test.ok' ) )->run( new ArrayContext() );
		$counts = $report->counts();

		$this->assertSame( 1, $counts['error'] );
		$this->assertSame( 1, $counts['pass'] );
		$this->assertSame( 0, $counts['fail'] );
		$this->assertSame( 0, $counts['blockers'] );
	}

	public function test_error_results_do_not_leak_exception_messages(): void {
		$boom = new FakeCheck(
			'test.boom',
			Result::SEVERITY_WARNING,
			array( '*' ),
			static function () {
				throw new RuntimeException( '/var/www/secret/path.php exploded' );
			}
		);

		$json = wp_json_encode( $this->runner( $boom )->run( new ArrayContext() )->to_array() );

		$this->assertStringNotContainsString( '/var/www', $json );
		$this->assertStringContainsString( 'RuntimeException', $json );
	}

	public function test_checks_outside_their_environments_are_skipped(): void {
		$prod_only = new FakeCheck( 'test.prod', Result::SEVERITY_WARNING, array( 'production' ) );

		$report = $this->runner( $prod_only )->run( new ArrayContext( 'staging' ) );

		$this->assertSame( 1, $report->counts()['skipped'] );
		$this->assertSame( Result::STATUS_SKIPPED, $report->results()[0]->status() );
	}

	public function test_environment_override_reaches_the_checks(): void {
		$echo = new FakeCheck(
			'test.env',
			Result::SEVERITY_LOW,
			array( '*' ),
			static function ( $context, $check ) {
				return Result::pass( $check, $context->environment() );
			}
		);

		$report = $this->runner( $echo )->run( new ArrayContext( 'staging' ), 'production' );

		$this->assertSame( 'production', $report->environment() );
		$this->assertSame( 'production', $report->results()[0]->to_array()['summary'] );
	}

	public function test_without_override_the_context_environment_is_used(): void {
		$report = $this->runner( new FakeCheck() )->run( new ArrayContext( 'development' ) );

		$this->assertSame( 'development', $report->environment() );
	}

	public function test_unknown_environment_is_rejected(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->runner( new FakeCheck() )->run( new ArrayContext(), 'prod' );
	}

	public function test_report_carries_the_plugin_version(): void {
		$report = $this->runner( new FakeCheck() )->run( new ArrayContext() );

		$this->assertSame( '9.9.9', $report->to_array()['plugin_version'] );
	}
}
