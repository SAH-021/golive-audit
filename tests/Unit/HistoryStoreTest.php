<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\Report;
use GoliveAudit\Audit\Result;
use GoliveAudit\Storage\HistoryStore;
use GoliveAudit\Tests\Support\FakeCheck;
use GoliveAudit\Tests\Support\MemoryOptionStore;
use PHPUnit\Framework\TestCase;

final class HistoryStoreTest extends TestCase {

	private function report( bool $with_failure, string $environment = 'production' ): Report {
		$results = array( Result::pass( new FakeCheck( 'seo.a', Result::SEVERITY_LOW ), 'ok' ) );

		if ( $with_failure ) {
			$results[] = Result::fail( new FakeCheck( 'seo.b', Result::SEVERITY_CRITICAL ), 'bad', 'fix' );
		}

		return new Report( $environment, $results, 0, 'test' );
	}

	public function test_it_starts_empty_and_records_a_snapshot(): void {
		$history = new HistoryStore( new MemoryOptionStore() );

		$this->assertSame( array(), $history->all() );

		$entry = $history->record( $this->report( true ), 1000 );

		$this->assertSame( 1000, $entry['t'] );
		$this->assertSame( 'production', $entry['env'] );
		$this->assertSame( 'blocked', $entry['verdict'] );
		$this->assertSame( array( 'seo.b' ), $entry['failed'] );
		$this->assertCount( 1, $history->all() );
	}

	public function test_a_snapshot_never_contains_summaries(): void {
		$history = new HistoryStore( new MemoryOptionStore() );
		$history->record( $this->report( true ), 1000 );

		$this->assertStringNotContainsString( 'bad', wp_json_encode( $history->all() ) );
	}

	public function test_an_unchanged_run_only_refreshes_the_timestamp(): void {
		$history = new HistoryStore( new MemoryOptionStore() );
		$history->record( $this->report( true ), 1000 );
		$history->record( $this->report( true ), 2000 );

		$all = $history->all();

		$this->assertCount( 1, $all );
		$this->assertSame( 2000, $all[0]['t'] );
	}

	public function test_a_changed_run_is_appended(): void {
		$history = new HistoryStore( new MemoryOptionStore() );
		$history->record( $this->report( true ), 1000 );
		$history->record( $this->report( false ), 2000 );
		$history->record( $this->report( false, 'staging' ), 3000 );

		$this->assertCount( 3, $history->all() );
	}

	public function test_the_list_is_capped(): void {
		$store   = new MemoryOptionStore();
		$history = new HistoryStore( $store );

		for ( $i = 0; $i < HistoryStore::LIMIT + 10; $i++ ) {
			$history->record( $this->report( 0 === $i % 2 ), 1000 + $i );
		}

		$all = $history->all();

		$this->assertCount( HistoryStore::LIMIT, $all );
		$this->assertSame( 1000 + HistoryStore::LIMIT + 9, $all[ HistoryStore::LIMIT - 1 ]['t'] );
	}

	public function test_garbage_in_the_option_is_ignored(): void {
		$store                          = new MemoryOptionStore();
		$store->data[ HistoryStore::OPTION ] = array( 'nonsense', array( 't' => 1 ), array( 't' => 5, 'env' => 'production', 'score' => 400, 'verdict' => 'ready' ) );

		$all = ( new HistoryStore( $store ) )->all();

		$this->assertCount( 1, $all );
		$this->assertSame( 100, $all[0]['score'] );
		$this->assertSame( array(), $all[0]['failed'] );
	}

	public function test_clear_removes_everything(): void {
		$history = new HistoryStore( new MemoryOptionStore() );
		$history->record( $this->report( true ), 1000 );
		$history->clear();

		$this->assertSame( array(), $history->all() );
	}

	public function test_diff_reports_new_and_fixed_problems(): void {
		$before = array(
			'score'  => 70,
			'failed' => array( 'a.one', 'a.two' ),
		);
		$after  = array(
			'score'  => 80,
			'failed' => array( 'a.two', 'a.three' ),
		);

		$diff = HistoryStore::diff( $before, $after );

		$this->assertSame( array( 'a.three' ), $diff['new_failures'] );
		$this->assertSame( array( 'a.one' ), $diff['fixed'] );
		$this->assertSame( 10, $diff['score_delta'] );
	}

	public function test_diff_without_a_previous_run_is_empty(): void {
		$diff = HistoryStore::diff( null, array( 'score' => 50, 'failed' => array( 'a.one' ) ) );

		$this->assertSame( array(), $diff['new_failures'] );
		$this->assertSame( 0, $diff['score_delta'] );
	}
}
