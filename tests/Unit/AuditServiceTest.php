<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\AuditRunner;
use GoliveAudit\Audit\AuditService;
use GoliveAudit\Audit\CheckRegistry;
use GoliveAudit\Audit\Result;
use GoliveAudit\Storage\WaiverStore;
use GoliveAudit\Tests\Support\ArrayContext;
use GoliveAudit\Tests\Support\FakeCheck;
use GoliveAudit\Tests\Support\MemoryOptionStore;
use PHPUnit\Framework\TestCase;

final class AuditServiceTest extends TestCase {

	public function test_stored_waivers_are_applied_to_the_run(): void {
		$failing = new FakeCheck(
			'seo.thing',
			Result::SEVERITY_CRITICAL,
			array( '*' ),
			static function ( $context, $check ) {
				return Result::fail( $check, 'broken', 'fix' );
			}
		);

		$registry = new CheckRegistry();
		$registry->register( $failing );

		$waivers = new WaiverStore( new MemoryOptionStore() );
		$service = new AuditService( new AuditRunner( $registry ), $waivers );

		$this->assertSame( 'blocked', $service->run( new ArrayContext() )->to_array()['summary']['verdict'] );

		$waivers->set( 'seo.thing', 'Client signed off' );
		$report = $service->run( new ArrayContext() );

		$this->assertSame( 'ready', $report->to_array()['summary']['verdict'] );
		$this->assertSame( 'Client signed off', $report->results()[0]->to_array()['waive_reason'] );
	}
}
