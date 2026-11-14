<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\Report;
use GoliveAudit\Audit\Result;
use GoliveAudit\Notify\AlertMessage;
use GoliveAudit\Tests\Support\FakeCheck;
use PHPUnit\Framework\TestCase;

final class AlertMessageTest extends TestCase {

	private const META = array(
		'site_name'     => 'Acme',
		'site_url'      => 'https://acme.test',
		'dashboard_url' => 'https://acme.test/wp-admin/tools.php?page=golive-audit',
	);

	private function report( bool $failing ): Report {
		$results = array( Result::pass( new FakeCheck( 'seo.a', Result::SEVERITY_LOW ), 'ok' ) );

		if ( $failing ) {
			$results[] = Result::fail( new FakeCheck( 'seo.b', Result::SEVERITY_CRITICAL ), 'Indexing is blocked', 'Fix it' );
		}

		return new Report( 'production', $results, 0, 'test' );
	}

	private function diff( array $new = array(), array $fixed = array(), int $delta = 0 ): array {
		return array(
			'new_failures' => $new,
			'fixed'        => $fixed,
			'score_delta'  => $delta,
		);
	}

	public function test_no_alert_when_nothing_is_new(): void {
		$this->assertNull( AlertMessage::build( $this->report( true ), $this->diff(), false, self::META ) );
	}

	public function test_alert_lists_only_new_problems(): void {
		$message = AlertMessage::build( $this->report( true ), $this->diff( array( 'seo.b' ), array(), -12 ), false, self::META );

		$this->assertNotNull( $message );
		$this->assertStringContainsString( 'Acme', $message['subject'] );
		$this->assertStringContainsString( 'Indexing is blocked', $message['body'] );
		$this->assertStringContainsString( '-12', $message['body'] );
		$this->assertStringContainsString( 'tools.php?page=golive-audit', $message['body'] );
	}

	public function test_first_run_alerts_only_when_the_site_is_not_ready(): void {
		$this->assertNull( AlertMessage::build( $this->report( false ), $this->diff(), true, self::META ) );
		$this->assertNotNull( AlertMessage::build( $this->report( true ), $this->diff(), true, self::META ) );
	}

	public function test_waived_failures_are_not_reported(): void {
		$waived = Result::fail( new FakeCheck( 'seo.b', Result::SEVERITY_CRITICAL ), 'Indexing is blocked', 'Fix it' )->with_waiver( 'Accepted' );
		$report = new Report( 'production', array( $waived ), 0, 'test' );

		$message = AlertMessage::build( $report, $this->diff( array( 'seo.b' ) ), false, self::META );

		$this->assertNotNull( $message );
		$this->assertStringNotContainsString( 'Indexing is blocked', $message['body'] );
	}
}
