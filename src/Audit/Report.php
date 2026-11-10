<?php
/**
 * The outcome of one audit run.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Audit;

use InvalidArgumentException;

final class Report {

	public const VERDICT_READY     = 'ready';
	public const VERDICT_ATTENTION = 'attention';
	public const VERDICT_BLOCKED   = 'blocked';

	private string $environment;

	/**
	 * @var Result[]
	 */
	private array $results;

	private int $generated_at;
	private string $version;

	/**
	 * @param string   $environment  Environment profile the audit ran as.
	 * @param Result[] $results      Results in check order.
	 * @param int      $generated_at Unix timestamp of the run.
	 * @param string   $version      Plugin version that produced the report.
	 */
	public function __construct( string $environment, array $results, int $generated_at, string $version = '' ) {
		$this->environment  = $environment;
		$this->results      = array_values( $results );
		$this->generated_at = $generated_at;
		$this->version      = $version;
	}

	public function environment(): string {
		return $this->environment;
	}

	/**
	 * @return Result[]
	 */
	public function results(): array {
		return $this->results;
	}

	/**
	 * Returns a copy in which failed checks listed in $waivers are marked as accepted risks.
	 *
	 * @param array<string, string> $waivers Waiver reasons keyed by check ID.
	 */
	public function with_waivers( array $waivers ): self {
		$results = array();

		foreach ( $this->results as $result ) {
			$id = $result->id();

			if ( Result::STATUS_FAIL === $result->status() && isset( $waivers[ $id ] ) ) {
				$result = $result->with_waiver( (string) $waivers[ $id ] );
			}

			$results[] = $result;
		}

		return new self( $this->environment, $results, $this->generated_at, $this->version );
	}

	/**
	 * Readiness score from 0 to 100, weighted by severity.
	 *
	 * Only checks that ran count: skipped and errored checks are excluded.
	 * Accepted risks (waived failures) count as passed; they stay visible in
	 * the report and in the "waived" count.
	 */
	public function score(): int {
		$total  = 0;
		$earned = 0;

		foreach ( $this->results as $result ) {
			if ( Result::STATUS_PASS !== $result->status() && Result::STATUS_FAIL !== $result->status() ) {
				continue;
			}

			$total += $result->weight();

			if ( Result::STATUS_PASS === $result->status() || $result->is_waived() ) {
				$earned += $result->weight();
			}
		}

		return 0 === $total ? 100 : (int) round( 100 * $earned / $total );
	}

	/**
	 * Overall verdict: blocked by critical failures, needs attention, or ready.
	 *
	 * A run with errored checks is never "ready", because it was not complete.
	 */
	public function verdict(): string {
		$counts = $this->counts();

		if ( $counts['blockers'] > 0 ) {
			return self::VERDICT_BLOCKED;
		}

		if ( $counts[ Result::STATUS_FAIL ] > 0 || $counts[ Result::STATUS_ERROR ] > 0 ) {
			return self::VERDICT_ATTENTION;
		}

		return self::VERDICT_READY;
	}

	/**
	 * Counts per outcome. Waived failures are counted separately and are not
	 * included under "fail". "blockers" are unwaived critical failures.
	 *
	 * @return array<string, int>
	 */
	public function counts(): array {
		$counts = array(
			Result::STATUS_PASS    => 0,
			Result::STATUS_FAIL    => 0,
			Result::STATUS_ERROR   => 0,
			Result::STATUS_SKIPPED => 0,
			'waived'               => 0,
			'blockers'             => 0,
		);

		foreach ( $this->results as $result ) {
			if ( Result::STATUS_FAIL === $result->status() && $result->is_waived() ) {
				++$counts['waived'];
			} else {
				++$counts[ $result->status() ];
			}

			if ( $result->blocks_launch() ) {
				++$counts['blockers'];
			}
		}

		return $counts;
	}

	/**
	 * Whether any unwaived failure has at least the given severity.
	 *
	 * @param string $severity "none", "low", "warning" or "critical".
	 * @throws InvalidArgumentException For an unknown severity, so a typo in a CI
	 *                                  threshold can never silently pass.
	 */
	public function has_failures_at_or_above( string $severity ): bool {
		if ( 'none' === $severity ) {
			return false;
		}

		if ( 0 === Result::severity_rank( $severity ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'Unknown severity "%s".', $severity ) ) );
		}

		foreach ( $this->results as $result ) {
			if ( $result->fails_at_or_above( $severity ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'plugin_version' => $this->version,
			'environment'    => $this->environment,
			'generated_at'   => gmdate( 'c', $this->generated_at ),
			'summary'        => array_merge(
				$this->counts(),
				array(
					'score'   => $this->score(),
					'verdict' => $this->verdict(),
				)
			),
			'results'        => array_map(
				static function ( Result $result ): array {
					return $result->to_array();
				},
				$this->results
			),
		);
	}
}
