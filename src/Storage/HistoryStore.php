<?php
/**
 * Compact history of past audit runs, for trends and "what changed" views.
 *
 * Only a snapshot is stored (score, verdict, counts and the IDs of unwaived
 * failures), never summaries, so nothing sensitive is kept. The list is capped.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Storage;

use GoliveAudit\Audit\Report;
use GoliveAudit\Audit\Result;

final class HistoryStore {

	public const OPTION = 'golive_audit_history';
	public const LIMIT  = 30;

	private OptionStore $store;

	public function __construct( OptionStore $store ) {
		$this->store = $store;
	}

	/**
	 * Entries from oldest to newest.
	 *
	 * @return array<int, array{t: int, env: string, score: int, verdict: string, blockers: int, fail: int, waived: int, pass: int, error: int, failed: string[]}>
	 */
	public function all(): array {
		$stored = $this->store->get( self::OPTION, array() );
		$clean  = array();

		if ( ! is_array( $stored ) ) {
			return $clean;
		}

		foreach ( $stored as $entry ) {
			$normal = $this->normalize( $entry );

			if ( null !== $normal ) {
				$clean[] = $normal;
			}
		}

		return array_slice( $clean, -self::LIMIT );
	}

	/**
	 * Records a run. When nothing changed since the previous run (same
	 * environment, score and failures) only its timestamp is refreshed, so
	 * re-running the audit does not flood the list.
	 *
	 * @return array<string, mixed> The stored entry.
	 */
	public function record( Report $report, int $now = 0 ): array {
		$entry   = $this->snapshot( $report, $now > 0 ? $now : time() );
		$entries = $this->all();
		$last    = end( $entries );

		if ( false !== $last && $this->same_state( $last, $entry ) ) {
			$entries[ count( $entries ) - 1 ] = $entry;
		} else {
			$entries[] = $entry;
		}

		$this->store->update( self::OPTION, array_slice( $entries, -self::LIMIT ) );

		return $entry;
	}

	public function clear(): void {
		$this->store->update( self::OPTION, array() );
	}

	/**
	 * What changed between two entries.
	 *
	 * @param array<string, mixed>|null $previous Earlier entry, or null when there is none.
	 * @param array<string, mixed>      $current  Later entry.
	 * @return array{new_failures: string[], fixed: string[], score_delta: int}
	 */
	public static function diff( ?array $previous, array $current ): array {
		$now = isset( $current['failed'] ) && is_array( $current['failed'] ) ? array_values( array_map( 'strval', $current['failed'] ) ) : array();

		if ( null === $previous ) {
			return array(
				'new_failures' => array(),
				'fixed'        => array(),
				'score_delta'  => 0,
			);
		}

		$before = isset( $previous['failed'] ) && is_array( $previous['failed'] ) ? array_values( array_map( 'strval', $previous['failed'] ) ) : array();

		return array(
			'new_failures' => array_values( array_diff( $now, $before ) ),
			'fixed'        => array_values( array_diff( $before, $now ) ),
			'score_delta'  => (int) ( $current['score'] ?? 0 ) - (int) ( $previous['score'] ?? 0 ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function snapshot( Report $report, int $now ): array {
		$counts = $report->counts();
		$failed = array();

		foreach ( $report->results() as $result ) {
			if ( Result::STATUS_FAIL === $result->status() && ! $result->is_waived() ) {
				$failed[] = $result->id();
			}
		}

		return array(
			't'        => $now,
			'env'      => $report->environment(),
			'score'    => $report->score(),
			'verdict'  => $report->verdict(),
			'blockers' => $counts['blockers'],
			'fail'     => $counts[ Result::STATUS_FAIL ],
			'waived'   => $counts['waived'],
			'pass'     => $counts[ Result::STATUS_PASS ],
			'error'    => $counts[ Result::STATUS_ERROR ],
			'failed'   => $failed,
		);
	}

	/**
	 * @param array<string, mixed> $a Entry.
	 * @param array<string, mixed> $b Entry.
	 */
	private function same_state( array $a, array $b ): bool {
		return $a['env'] === $b['env'] && $a['score'] === $b['score'] && $a['failed'] === $b['failed'];
	}

	/**
	 * @param mixed $entry Raw stored value.
	 * @return array<string, mixed>|null
	 */
	private function normalize( $entry ): ?array {
		if ( ! is_array( $entry ) || ! isset( $entry['t'], $entry['env'], $entry['score'], $entry['verdict'] ) ) {
			return null;
		}

		if ( ! is_string( $entry['env'] ) || ! is_string( $entry['verdict'] ) ) {
			return null;
		}

		$failed = array();

		if ( isset( $entry['failed'] ) && is_array( $entry['failed'] ) ) {
			foreach ( $entry['failed'] as $id ) {
				if ( is_string( $id ) ) {
					$failed[] = $id;
				}
			}
		}

		return array(
			't'        => (int) $entry['t'],
			'env'      => $entry['env'],
			'score'    => max( 0, min( 100, (int) $entry['score'] ) ),
			'verdict'  => $entry['verdict'],
			'blockers' => (int) ( $entry['blockers'] ?? 0 ),
			'fail'     => (int) ( $entry['fail'] ?? 0 ),
			'waived'   => (int) ( $entry['waived'] ?? 0 ),
			'pass'     => (int) ( $entry['pass'] ?? 0 ),
			'error'    => (int) ( $entry['error'] ?? 0 ),
			'failed'   => $failed,
		);
	}
}
