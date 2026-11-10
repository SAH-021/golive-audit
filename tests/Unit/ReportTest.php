<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\Report;
use GoliveAudit\Audit\Result;
use GoliveAudit\Tests\Support\FakeCheck;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReportTest extends TestCase {

	private function failure( string $severity, string $id = 'test.fail' ): Result {
		$check = new FakeCheck( $id, $severity );

		return Result::fail( $check, 'broken', 'fix it' );
	}

	public function test_counts_separate_waived_failures_and_blockers(): void {
		$critical = $this->failure( Result::SEVERITY_CRITICAL, 'test.critical' );
		$waived   = $this->failure( Result::SEVERITY_CRITICAL, 'test.waived' )->with_waiver( 'client accepts' );
		$warning  = $this->failure( Result::SEVERITY_WARNING, 'test.warning' );

		$counts = ( new Report( 'production', array( $critical, $waived, $warning ), 0 ) )->counts();

		$this->assertSame( 2, $counts['fail'] );
		$this->assertSame( 1, $counts['waived'] );
		$this->assertSame( 1, $counts['blockers'] );
	}

	public function test_a_waived_critical_failure_does_not_block_launch(): void {
		$waived = $this->failure( Result::SEVERITY_CRITICAL )->with_waiver( 'accepted' );

		$this->assertFalse( $waived->blocks_launch() );
		$this->assertTrue( $waived->is_waived() );
		$this->assertSame( 'accepted', $waived->to_array()['waive_reason'] );
	}

	public function test_waiving_returns_a_copy(): void {
		$original = $this->failure( Result::SEVERITY_WARNING );
		$original->with_waiver( 'because' );

		$this->assertFalse( $original->is_waived() );
	}

	/**
	 * @dataProvider thresholds
	 */
	public function test_fail_threshold( string $failing_severity, string $threshold, bool $expected ): void {
		$report = new Report( 'production', array( $this->failure( $failing_severity ) ), 0 );

		$this->assertSame( $expected, $report->has_failures_at_or_above( $threshold ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: bool}>
	 */
	public function thresholds(): array {
		return array(
			'warning fails on warning'       => array( 'warning', 'warning', true ),
			'warning does not fail critical' => array( 'warning', 'critical', false ),
			'critical fails on low'          => array( 'critical', 'low', true ),
			'low does not fail warning'      => array( 'low', 'warning', false ),
			'none never fails'               => array( 'critical', 'none', false ),
		);
	}

	public function test_passes_and_errors_never_trip_the_threshold(): void {
		$check   = new FakeCheck( 'test.ok', Result::SEVERITY_CRITICAL );
		$results = array( Result::pass( $check, 'ok' ), Result::error( $check, 'Boom' ), Result::skipped( $check, 'n/a' ) );

		$this->assertFalse( ( new Report( 'production', $results, 0 ) )->has_failures_at_or_above( 'low' ) );
	}

	public function test_unknown_threshold_throws_instead_of_silently_passing(): void {
		$this->expectException( InvalidArgumentException::class );
		( new Report( 'production', array(), 0 ) )->has_failures_at_or_above( 'criticial' );
	}

	public function test_to_array_exposes_a_stable_shape(): void {
		$data = ( new Report( 'staging', array( $this->failure( Result::SEVERITY_LOW ) ), 0, '1.2.3' ) )->to_array();

		$this->assertSame( array( 'plugin_version', 'environment', 'generated_at', 'summary', 'results' ), array_keys( $data ) );
		$this->assertSame( '1970-01-01T00:00:00+00:00', $data['generated_at'] );
		$this->assertSame(
			array( 'id', 'title', 'category', 'severity', 'status', 'summary', 'recommendation', 'fix_url', 'waived', 'waive_reason' ),
			array_keys( $data['results'][0] )
		);
	}

	public function test_score_is_weighted_by_severity_and_ignores_skipped_and_errors(): void {
		$critical_fail = Result::fail( new FakeCheck( 'test.a', Result::SEVERITY_CRITICAL ), 's', 'r' ); // weight 10, lost.
		$warning_pass  = Result::pass( new FakeCheck( 'test.b', Result::SEVERITY_WARNING ), 's' );        // weight 3, earned.
		$low_pass      = Result::pass( new FakeCheck( 'test.c', Result::SEVERITY_LOW ), 's' );            // weight 1, earned.
		$skipped       = Result::skipped( new FakeCheck( 'test.d', Result::SEVERITY_CRITICAL ), 'n/a' );
		$error         = Result::error( new FakeCheck( 'test.e', Result::SEVERITY_CRITICAL ), 'Boom' );

		$report = new Report( 'production', array( $critical_fail, $warning_pass, $low_pass, $skipped, $error ), 0 );

		$this->assertSame( 29, $report->score() ); // 4 / 14.
	}

	public function test_waived_failures_count_as_earned_in_the_score(): void {
		$waived = Result::fail( new FakeCheck( 'test.a', Result::SEVERITY_CRITICAL ), 's', 'r' )->with_waiver( 'accepted' );

		$this->assertSame( 100, ( new Report( 'production', array( $waived ), 0 ) )->score() );
	}

	public function test_an_empty_report_scores_100_and_is_ready(): void {
		$report = new Report( 'production', array(), 0 );

		$this->assertSame( 100, $report->score() );
		$this->assertSame( Report::VERDICT_READY, $report->verdict() );
	}

	public function test_verdicts(): void {
		$critical = $this->failure( Result::SEVERITY_CRITICAL, 'test.c' );
		$warning  = $this->failure( Result::SEVERITY_WARNING, 'test.w' );
		$error    = Result::error( new FakeCheck( 'test.e' ), 'Boom' );
		$ok       = Result::pass( new FakeCheck( 'test.ok' ), 'ok' );

		$this->assertSame( Report::VERDICT_BLOCKED, ( new Report( 'production', array( $critical, $ok ), 0 ) )->verdict() );
		$this->assertSame( Report::VERDICT_ATTENTION, ( new Report( 'production', array( $warning, $ok ), 0 ) )->verdict() );
		$this->assertSame( Report::VERDICT_ATTENTION, ( new Report( 'production', array( $error, $ok ), 0 ) )->verdict(), 'an incomplete audit is never ready' );
		$this->assertSame( Report::VERDICT_READY, ( new Report( 'production', array( $ok ), 0 ) )->verdict() );
	}

	public function test_a_waived_blocker_downgrades_the_verdict(): void {
		$waived = $this->failure( Result::SEVERITY_CRITICAL )->with_waiver( 'accepted' );

		$this->assertSame( Report::VERDICT_READY, ( new Report( 'production', array( $waived ), 0 ) )->verdict() );
	}

	public function test_with_waivers_only_marks_failures_and_keeps_the_original_untouched(): void {
		$fail = $this->failure( Result::SEVERITY_WARNING, 'test.fail' );
		$pass = Result::pass( new FakeCheck( 'test.pass' ), 'ok' );
		$orig = new Report( 'production', array( $fail, $pass ), 123, '1.0.0' );

		$waived = $orig->with_waivers(
			array(
				'test.fail'    => 'because',
				'test.pass'    => 'ignored',
				'test.unknown' => 'ignored',
			)
		);

		$this->assertTrue( $waived->results()[0]->is_waived() );
		$this->assertFalse( $waived->results()[1]->is_waived() );
		$this->assertFalse( $orig->results()[0]->is_waived() );
		$this->assertSame( $orig->to_array()['generated_at'], $waived->to_array()['generated_at'] );
		$this->assertSame( 'ready', $waived->to_array()['summary']['verdict'] );
	}
}
