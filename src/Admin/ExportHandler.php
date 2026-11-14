<?php
/**
 * Serves the report as a printable page, CSV or JSON via admin-post.php.
 *
 * Access needs the manage_options capability and a valid nonce.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Admin;

use GoliveAudit\Audit\AuditRunner;
use GoliveAudit\Export\ReportExporter;
use GoliveAudit\Plugin;
use GoliveAudit\Support\Jalali;
use GoliveAudit\Support\Language;

final class ExportHandler {

	private const FORMATS = array( 'html', 'csv', 'json', 'md' );

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( 'admin_post_golive_audit_export', array( $this, 'handle' ) );
	}

	public function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to export this report.', 'golive-audit' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( 'golive_audit_export' );

		$format      = isset( $_GET['format'] ) ? sanitize_key( wp_unslash( $_GET['format'] ) ) : 'html';
		$environment = isset( $_GET['environment'] ) ? sanitize_key( wp_unslash( $_GET['environment'] ) ) : '';

		if ( ! in_array( $format, self::FORMATS, true ) ) {
			wp_die( esc_html__( 'Unknown export format.', 'golive-audit' ), '', array( 'response' => 400 ) );
		}

		$report    = $this->plugin->run_audit( in_array( $environment, AuditRunner::ENVIRONMENTS, true ) ? $environment : null );
		$settings  = $this->plugin->settings()->get();
		$checklist = $this->plugin->checklist()->rows( $settings['preset'] );
		$exporter  = new ReportExporter();
		$locale    = $this->plugin->effective_locale();
		$meta      = array(
			'site_name'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'    => home_url(),
			'generated'   => $this->format_date( time(), $locale ),
			'agency_name' => $settings['agency_name'],
			'client_name' => $settings['client_name'],
			'note'        => $settings['note'],
			'is_rtl'      => Language::is_rtl_locale( $locale ),
			'lang'        => str_replace( '_', '-', $locale ),
			'version'     => GOLIVE_AUDIT_VERSION,
		);

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );

		if ( 'csv' === $format ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $this->filename( 'csv' ) . '"' );
			echo $exporter->to_csv( $report ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, cells are quoted and formula-neutralized.
			exit;
		}

		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $this->filename( 'json' ) . '"' );
			echo wp_json_encode( $exporter->to_data( $report, $meta, $checklist ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
			exit;
		}

		if ( 'md' === $format ) {
			header( 'Content-Type: text/markdown; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $this->filename( 'md' ) . '"' );
			echo $exporter->to_markdown( $report, $meta, $checklist ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markdown download, not rendered as HTML by the server.
			exit;
		}

		$history = $this->history_rows( $locale );

		$script_nonce = wp_generate_password( 16, false );

		header( 'Content-Type: text/html; charset=utf-8' );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-" . $script_nonce . "'; base-uri 'none'; form-action 'none'" );
		echo $exporter->to_html( $report, $meta, $checklist, $script_nonce, $history ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic value is escaped inside ReportExporter::to_html().
		exit;
	}

	/**
	 * Formats a timestamp: Jalali for Persian, the site's date and time format otherwise.
	 */
	private function format_date( int $timestamp, string $locale ): string {
		if ( Language::is_persian( $locale ) ) {
			return Jalali::format( $timestamp, (int) wp_timezone()->getOffset( new \DateTimeImmutable( '@' . $timestamp ) ) );
		}

		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * The most recent runs, newest first, with dates already formatted for the report.
	 *
	 * @return array<int, array{date: string, score: int, verdict: string, failed: int}>
	 */
	private function history_rows( string $locale ): array {
		$rows = array();

		foreach ( array_reverse( array_slice( $this->plugin->history()->all(), -5 ) ) as $entry ) {
			$rows[] = array(
				'date'    => $this->format_date( (int) $entry['t'], $locale ),
				'score'   => (int) $entry['score'],
				'verdict' => (string) $entry['verdict'],
				'failed'  => count( $entry['failed'] ),
			);
		}

		return $rows;
	}

	private function filename( string $extension ): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return sanitize_file_name( 'golive-audit-' . ( is_string( $host ) ? $host : 'site' ) . '-' . gmdate( 'Ymd' ) . '.' . $extension );
	}
}
