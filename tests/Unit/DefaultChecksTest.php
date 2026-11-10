<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\AuditRunner;
use GoliveAudit\Audit\Categories;
use GoliveAudit\Audit\CheckRegistry;
use GoliveAudit\Audit\Result;
use GoliveAudit\Plugin;
use PHPUnit\Framework\TestCase;

/**
 * Guards the conventions every shipped check must follow, including future ones.
 */
final class DefaultChecksTest extends TestCase {

	public function test_all_shipped_checks_register_without_conflicts(): void {
		$registry = new CheckRegistry();
		$checks   = Plugin::instance()->default_checks();

		foreach ( $checks as $check ) {
			$registry->register( $check );
		}

		$this->assertCount( count( $checks ), $registry->all() );
		$this->assertNotEmpty( $checks );
	}

	public function test_every_shipped_check_follows_the_metadata_conventions(): void {
		foreach ( Plugin::instance()->default_checks() as $check ) {
			$id = $check->id();

			$this->assertNotSame( '', $check->title(), $id );
			$this->assertSame( strstr( $id, '.', true ), $check->category(), "$id: category must be the first ID segment" );
			$this->assertArrayHasKey( $check->category(), Categories::labels(), "$id: category has no label" );
			$this->assertGreaterThan( 0, Result::severity_rank( $check->severity() ), "$id: unknown severity" );

			foreach ( $check->environments() as $environment ) {
				$this->assertTrue(
					'*' === $environment || in_array( $environment, AuditRunner::ENVIRONMENTS, true ),
					"$id: unknown environment $environment"
				);
			}
		}
	}

	public function test_version_1_ships_nineteen_checks(): void {
		$this->assertCount( 19, Plugin::instance()->default_checks() );
	}

	public function test_every_check_survives_a_bare_context_without_errors(): void {
		$context = new \GoliveAudit\Tests\Support\ArrayContext( 'production' );
		$runner  = new AuditRunner( $this->registry(), 'test' );

		$this->assertSame( 0, $runner->run( $context )->counts()['error'] );
		$this->assertSame( 0, $runner->run( $context, 'staging' )->counts()['error'] );
	}

	private function registry(): CheckRegistry {
		$registry = new CheckRegistry();

		foreach ( Plugin::instance()->default_checks() as $check ) {
			$registry->register( $check );
		}

		return $registry;
	}
}
