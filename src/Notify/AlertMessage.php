<?php
/**
 * Builds the plain-text email sent after a scheduled audit finds new problems.
 *
 * Pure: it receives the finished report and the diff against the previous run,
 * and returns the subject and body, or null when there is nothing to report.
 * The body only contains information that is also safe to put in a client report.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Notify;

use GoliveAudit\Audit\Report;
use GoliveAudit\Audit\Result;

final class AlertMessage {

	/**
	 * @param Report                                                         $report    The audit report.
	 * @param array{new_failures: string[], fixed: string[], score_delta: int} $diff      Changes since the previous run.
	 * @param bool                                                           $first_run Whether there is no earlier run to compare with.
	 * @param array<string, string>                                          $meta      site_name, site_url and dashboard_url.
	 * @return array{subject: string, body: string}|null
	 */
	public static function build( Report $report, array $diff, bool $first_run, array $meta ): ?array {
		$summary = $report->to_array()['summary'];
		$new_ids = $diff['new_failures'];

		if ( $first_run ) {
			if ( Report::VERDICT_READY === $summary['verdict'] ) {
				return null;
			}
		} elseif ( array() === $new_ids ) {
			return null;
		}

		$site = trim( (string) ( $meta['site_name'] ?? '' ) );
		$site = '' !== $site ? $site : (string) ( $meta['site_url'] ?? '' );

		$subject = sprintf(
			/* translators: 1: site name, 2: readiness score from 0 to 100. */
			__( '[%1$s] Golive Audit found new problems (score %2$d/100)', 'golive-audit' ),
			$site,
			(int) $summary['score']
		);

		$lines   = array();
		$lines[] = sprintf(
			/* translators: 1: environment name, 2: readiness score, 3: change in score since the last audit. */
			__( 'Environment: %1$s. Readiness score: %2$d/100 (%3$+d since the last audit).', 'golive-audit' ),
			$report->environment(),
			(int) $summary['score'],
			(int) $diff['score_delta']
		);
		$lines[] = sprintf(
			/* translators: 1: verdict, 2: number of launch blockers. */
			__( 'Verdict: %1$s. Launch blockers: %2$d.', 'golive-audit' ),
			self::verdict_label( (string) $summary['verdict'] ),
			(int) $summary['blockers']
		);
		$lines[] = '';

		$problems = array();

		foreach ( $report->results() as $result ) {
			if ( Result::STATUS_FAIL !== $result->status() || $result->is_waived() ) {
				continue;
			}

			if ( $first_run || in_array( $result->id(), $new_ids, true ) ) {
				$problems[] = $result->to_array();
			}
		}

		if ( array() !== $problems ) {
			$lines[] = $first_run ? __( 'Open problems:', 'golive-audit' ) : __( 'New problems since the last audit:', 'golive-audit' );

			foreach ( $problems as $problem ) {
				$lines[] = sprintf( '- %1$s [%2$s]: %3$s', $problem['title'], $problem['severity'], $problem['summary'] );
			}

			$lines[] = '';
		}

		if ( ! $first_run && array() !== $diff['fixed'] ) {
			$lines[] = sprintf(
				/* translators: %d: number of problems fixed since the last audit. */
				_n( '%d problem was fixed since the last audit.', '%d problems were fixed since the last audit.', count( $diff['fixed'] ), 'golive-audit' ),
				count( $diff['fixed'] )
			);
			$lines[] = '';
		}

		$url = (string) ( $meta['dashboard_url'] ?? '' );

		if ( '' !== $url ) {
			$lines[] = sprintf(
				/* translators: %s: URL of the Golive Audit dashboard. */
				__( 'Open the dashboard: %s', 'golive-audit' ),
				$url
			);
		}

		return array(
			'subject' => $subject,
			'body'    => implode( "\n", $lines ),
		);
	}

	private static function verdict_label( string $verdict ): string {
		switch ( $verdict ) {
			case Report::VERDICT_READY:
				return __( 'Ready to launch', 'golive-audit' );
			case Report::VERDICT_BLOCKED:
				return __( 'Launch blocked', 'golive-audit' );
			default:
				return __( 'Needs attention', 'golive-audit' );
		}
	}
}
