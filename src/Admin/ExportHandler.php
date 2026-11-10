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

final class ExportHandler {

	private const FORMATS = array( 'html', 'csv', 'json' );

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
		$meta      = array(
			'site_name'   => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'    => home_url(),
			'generated'   => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
			'agency_name' => $settings['agency_name'],
			'client_name' => $settings['client_name'],
			'note'        => $settings['note'],
			'is_rtl'      => is_rtl(),
			'lang'        => str_replace( '_', '-', get_locale() ),
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

		$script_nonce = wp_generate_password( 16, false );

		header( 'Content-Type: text/html; charset=utf-8' );
		header( "Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-" . $script_nonce . "'; base-uri 'none'; form-action 'none'" );
		echo $exporter->to_html( $report, $meta, $checklist, $script_nonce ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic value is escaped inside ReportExporter::to_html().
		exit;
	}

	private function filename( string $extension ): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return sanitize_file_name( 'golive-audit-' . ( is_string( $host ) ? $host : 'site' ) . '-' . gmdate( 'Ymd' ) . '.' . $extension );
	}
}
