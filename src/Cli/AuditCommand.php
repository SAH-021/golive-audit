<?php
/**
 * WP-CLI interface to the audit engine.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Cli;

use GoliveAudit\Plugin;

/**
 * Runs the launch-readiness audit.
 */
final class AuditCommand {

	/**
	 * Runs the audit and prints the results.
	 *
	 * ## OPTIONS
	 *
	 * [--env=<environment>]
	 * : Environment profile to audit against. Defaults to the detected environment type.
	 * ---
	 * options:
	 *   - local
	 *   - development
	 *   - staging
	 *   - production
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * [--fail-on=<severity>]
	 * : Exit with a non-zero code when a failed check has at least this severity.
	 * ---
	 * default: critical
	 * options:
	 *   - none
	 *   - low
	 *   - warning
	 *   - critical
	 * ---
	 *
	 * [--record]
	 * : Add this run to the history shown in the dashboard.
	 *
	 * ## EXAMPLES
	 *
	 *     # Audit as production and fail the build on any warning or worse.
	 *     wp golive-audit run --env=production --fail-on=warning
	 *
	 *     # Keep the result in the dashboard history.
	 *     wp golive-audit run --record
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 */
	public function run( $args, $assoc_args ): void {
		$environment = isset( $assoc_args['env'] ) ? (string) $assoc_args['env'] : null;
		$format      = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$fail_on     = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'fail-on', 'critical' );

		$report = Plugin::instance()->run_audit( $environment, (bool) \WP_CLI\Utils\get_flag_value( $assoc_args, 'record', false ) );

		if ( 'json' === $format ) {
			\WP_CLI::line( (string) wp_json_encode( $report->to_array(), JSON_PRETTY_PRINT ) );
		} else {
			$items = array();

			foreach ( $report->results() as $result ) {
				$row     = $result->to_array();
				$items[] = array(
					'id'       => $row['id'],
					'status'   => $result->is_waived() ? 'waived' : $row['status'],
					'severity' => $row['severity'],
					'summary'  => $row['summary'],
				);
			}

			\WP_CLI\Utils\format_items( $format, $items, array( 'id', 'status', 'severity', 'summary' ) );
		}

		$counts = $report->counts();

		if ( $counts['error'] > 0 ) {
			\WP_CLI::warning( sprintf( '%d check(s) could not be completed.', $counts['error'] ) );
		}

		if ( $report->has_failures_at_or_above( $fail_on ) ) {
			\WP_CLI::halt( 1 );
		}
	}

	/**
	 * Lists the registered checks.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * @subcommand list-checks
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_checks( $args, $assoc_args ): void {
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$items  = array();

		foreach ( Plugin::instance()->registry()->all() as $check ) {
			$items[] = array(
				'id'           => $check->id(),
				'category'     => $check->category(),
				'severity'     => $check->severity(),
				'environments' => implode( ',', $check->environments() ),
			);
		}

		\WP_CLI\Utils\format_items( $format, $items, array( 'id', 'category', 'severity', 'environments' ) );
	}

	/**
	 * Shows the stored audit history, newest first.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 * ---
	 *
	 * @param array $args       Positional arguments (unused).
	 * @param array $assoc_args Associative arguments.
	 */
	public function history( $args, $assoc_args ): void {
		$format = (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' );
		$items  = array();

		foreach ( array_reverse( Plugin::instance()->history()->all() ) as $entry ) {
			$items[] = array(
				'date'        => gmdate( 'Y-m-d H:i', (int) $entry['t'] ) . ' UTC',
				'environment' => $entry['env'],
				'score'       => $entry['score'],
				'verdict'     => $entry['verdict'],
				'blockers'    => $entry['blockers'],
				'failed'      => count( $entry['failed'] ),
			);
		}

		\WP_CLI\Utils\format_items( $format, $items, array( 'date', 'environment', 'score', 'verdict', 'blockers', 'failed' ) );
	}
}
