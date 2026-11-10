<?php
/**
 * Turns a report into JSON data, CSV or a standalone printable HTML page.
 *
 * Reports can be handed to clients, so they never contain secrets, key values,
 * the administrator's email address or server paths. Everything printed as
 * HTML is escaped here.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Export;

use GoliveAudit\Audit\Categories;
use GoliveAudit\Audit\Report;
use GoliveAudit\Audit\Result;

final class ReportExporter {

	/**
	 * Structured data for the JSON export.
	 *
	 * @param Report               $report    The audit report.
	 * @param array<string, mixed> $meta      Site and branding details.
	 * @param array<int, array>    $checklist Checklist rows.
	 * @return array<string, mixed>
	 */
	public function to_data( Report $report, array $meta, array $checklist ): array {
		return array(
			'meta'      => $meta,
			'report'    => $report->to_array(),
			'checklist' => $checklist,
		);
	}

	/**
	 * CSV with a UTF-8 byte order mark (so spreadsheets read Persian and other
	 * non-Latin text correctly). Cells that could be run as spreadsheet
	 * formulas are neutralized.
	 */
	public function to_csv( Report $report ): string {
		$lines   = array();
		$lines[] = $this->csv_line( array( 'id', 'title', 'category', 'severity', 'status', 'waived', 'waive_reason', 'summary', 'recommendation' ) );

		foreach ( $report->results() as $result ) {
			$row     = $result->to_array();
			$lines[] = $this->csv_line(
				array(
					$row['id'],
					$row['title'],
					$row['category'],
					$row['severity'],
					$row['status'],
					$row['waived'] ? 'yes' : 'no',
					$row['waive_reason'],
					$row['summary'],
					$row['recommendation'],
				)
			);
		}

		return "\xEF\xBB\xBF" . implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Standalone, print-friendly HTML page.
	 *
	 * @param Report               $report       The audit report.
	 * @param array<string, mixed> $meta         site_name, site_url, generated, agency_name, client_name, note, is_rtl, lang, version.
	 * @param array<int, array>    $checklist    Checklist rows.
	 * @param string               $script_nonce Nonce for the one inline script (CSP).
	 */
	public function to_html( Report $report, array $meta, array $checklist, string $script_nonce ): string {
		$summary    = $report->to_array()['summary'];
		$categories = Categories::labels();
		$dir        = ! empty( $meta['is_rtl'] ) ? 'rtl' : 'ltr';
		$lang       = (string) ( $meta['lang'] ?? 'en' );

		$html  = '<!DOCTYPE html><html lang="' . esc_html( $lang ) . '" dir="' . esc_html( $dir ) . '"><head><meta charset="utf-8">';
		$html .= '<meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">';
		$html .= '<title>' . esc_html__( 'Launch readiness report', 'golive-audit' ) . '</title>';
		$html .= '<style>' . $this->css() . '</style></head><body>';

		$html .= '<p class="no-print"><button id="golive-print" type="button">' . esc_html__( 'Print or save as PDF', 'golive-audit' ) . '</button></p>';
		$html .= '<h1>' . esc_html__( 'Launch readiness report', 'golive-audit' ) . '</h1>';
		$html .= '<dl class="meta">';
		$html .= $this->meta_row( __( 'Site', 'golive-audit' ), trim( (string) ( $meta['site_name'] ?? '' ) ) . ' ' . (string) ( $meta['site_url'] ?? '' ) );
		$html .= $this->meta_row( __( 'Environment', 'golive-audit' ), $report->environment() );
		$html .= $this->meta_row( __( 'Generated', 'golive-audit' ), (string) ( $meta['generated'] ?? '' ) );
		$html .= $this->meta_row( __( 'Prepared for', 'golive-audit' ), (string) ( $meta['client_name'] ?? '' ) );
		$html .= $this->meta_row( __( 'Prepared by', 'golive-audit' ), (string) ( $meta['agency_name'] ?? '' ) );
		$html .= '</dl>';

		$html .= '<h2>' . esc_html__( 'Summary', 'golive-audit' ) . '</h2>';
		$html .= '<p class="verdict verdict-' . esc_html( (string) $summary['verdict'] ) . '"><strong>' . esc_html( $this->verdict_label( (string) $summary['verdict'] ) ) . '</strong>';
		$html .= ' &mdash; ' . esc_html(
			sprintf(
				/* translators: %d: readiness score from 0 to 100. */
				__( 'Readiness score %d / 100', 'golive-audit' ),
				(int) $summary['score']
			)
		) . '</p>';
		$html .= '<p>' . esc_html(
			sprintf(
				/* translators: 1: launch blockers, 2: failed checks, 3: accepted risks, 4: passed checks, 5: skipped checks, 6: checks that errored. */
				__( 'Blockers: %1$d. Failed: %2$d. Accepted risks: %3$d. Passed: %4$d. Not applicable: %5$d. Could not run: %6$d.', 'golive-audit' ),
				(int) $summary['blockers'],
				(int) $summary['fail'],
				(int) $summary['waived'],
				(int) $summary['pass'],
				(int) $summary['skipped'],
				(int) $summary['error']
			)
		) . '</p>';

		$note = trim( (string) ( $meta['note'] ?? '' ) );
		if ( '' !== $note ) {
			$html .= '<h2>' . esc_html__( 'Notes', 'golive-audit' ) . '</h2><p class="note">' . nl2br( esc_html( $note ) ) . '</p>';
		}

		$html .= '<h2>' . esc_html__( 'Automated checks', 'golive-audit' ) . '</h2><table><thead><tr>';
		$html .= '<th>' . esc_html__( 'Status', 'golive-audit' ) . '</th><th>' . esc_html__( 'Severity', 'golive-audit' ) . '</th>';
		$html .= '<th>' . esc_html__( 'Check', 'golive-audit' ) . '</th></tr></thead><tbody>';

		foreach ( $this->sorted_results( $report ) as $result ) {
			$row   = $result->to_array();
			$label = $row['waived'] ? __( 'Accepted risk', 'golive-audit' ) : $this->status_label( $row['status'] );

			$html .= '<tr class="status-' . esc_html( $row['waived'] ? 'waived' : $row['status'] ) . '">';
			$html .= '<td>' . esc_html( $label ) . '</td><td>' . esc_html( $this->severity_label( $row['severity'] ) ) . '</td><td>';
			$html .= '<strong>' . esc_html( $row['title'] ) . '</strong> <span class="cat">(' . esc_html( $categories[ $row['category'] ] ?? $row['category'] ) . ')</span>';
			$html .= '<br>' . esc_html( $row['summary'] );

			if ( Result::STATUS_FAIL === $row['status'] && '' !== $row['recommendation'] && ! $row['waived'] ) {
				$html .= '<br><em>' . esc_html( $row['recommendation'] ) . '</em>';
			}

			if ( $row['waived'] ) {
				$html .= '<br><em>' . esc_html(
					sprintf(
						/* translators: %s: reason the risk was accepted. */
						__( 'Accepted because: %s', 'golive-audit' ),
						$row['waive_reason']
					)
				) . '</em>';
			}

			$html .= '</td></tr>';
		}

		$html .= '</tbody></table>';

		if ( array() !== $checklist ) {
			$html .= '<h2>' . esc_html__( 'Manual handoff checklist', 'golive-audit' ) . '</h2><table><thead><tr><th>' . esc_html__( 'Status', 'golive-audit' ) . '</th><th>' . esc_html__( 'Item', 'golive-audit' ) . '</th></tr></thead><tbody>';

			foreach ( $checklist as $item ) {
				$html .= '<tr><td>' . esc_html( $this->checklist_state_label( (string) $item['state'] ) ) . '</td><td>' . esc_html( (string) $item['label'] ) . '</td></tr>';
			}

			$html .= '</tbody></table>';
		}

		$html .= '<p class="footer">' . esc_html(
			sprintf(
				/* translators: %s: plugin version. */
				__( 'Generated by Golive Audit %s. Automated checks cannot prove a site is correct; review the manual checklist too.', 'golive-audit' ),
				(string) ( $meta['version'] ?? '' )
			)
		) . '</p>';
		$html .= wp_get_inline_script_tag(
			'document.getElementById("golive-print").addEventListener("click",function(){window.print();});',
			array( 'nonce' => $script_nonce )
		);
		$html .= '</body></html>';

		return $html;
	}

	/**
	 * @param string[] $cells Row values.
	 */
	private function csv_line( array $cells ): string {
		return implode(
			',',
			array_map(
				function ( $cell ): string {
					return '"' . str_replace( '"', '""', $this->neutralize_formula( (string) $cell ) ) . '"';
				},
				$cells
			)
		);
	}

	/**
	 * Prefixes cells that start with a formula character so spreadsheets treat them as text.
	 */
	private function neutralize_formula( string $value ): string {
		if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
			return "'" . $value;
		}

		return $value;
	}

	private function meta_row( string $label, string $value ): string {
		$value = trim( $value );

		if ( '' === $value ) {
			return '';
		}

		return '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
	}

	/**
	 * Failures first (most severe first), then errors, accepted risks, passes, skipped.
	 *
	 * @return Result[]
	 */
	private function sorted_results( Report $report ): array {
		$results = $report->results();

		usort(
			$results,
			function ( Result $a, Result $b ): int {
				return $this->priority( $a ) <=> $this->priority( $b );
			}
		);

		return $results;
	}

	private function priority( Result $result ): int {
		if ( Result::STATUS_FAIL === $result->status() && ! $result->is_waived() ) {
			return 10 - Result::severity_rank( $result->severity() );
		}

		if ( Result::STATUS_ERROR === $result->status() ) {
			return 20;
		}

		if ( $result->is_waived() ) {
			return 30;
		}

		return Result::STATUS_PASS === $result->status() ? 40 : 50;
	}

	private function verdict_label( string $verdict ): string {
		switch ( $verdict ) {
			case Report::VERDICT_READY:
				return __( 'Ready to launch', 'golive-audit' );
			case Report::VERDICT_BLOCKED:
				return __( 'Launch blocked', 'golive-audit' );
			default:
				return __( 'Needs attention', 'golive-audit' );
		}
	}

	private function status_label( string $status ): string {
		switch ( $status ) {
			case Result::STATUS_PASS:
				return __( 'Passed', 'golive-audit' );
			case Result::STATUS_FAIL:
				return __( 'Failed', 'golive-audit' );
			case Result::STATUS_ERROR:
				return __( 'Could not run', 'golive-audit' );
			default:
				return __( 'Not applicable', 'golive-audit' );
		}
	}

	private function severity_label( string $severity ): string {
		switch ( $severity ) {
			case Result::SEVERITY_CRITICAL:
				return __( 'Critical', 'golive-audit' );
			case Result::SEVERITY_WARNING:
				return __( 'Warning', 'golive-audit' );
			default:
				return __( 'Low', 'golive-audit' );
		}
	}

	private function checklist_state_label( string $state ): string {
		switch ( $state ) {
			case 'done':
				return __( 'Done', 'golive-audit' );
			case 'na':
				return __( 'Not applicable', 'golive-audit' );
			default:
				return __( 'To do', 'golive-audit' );
		}
	}

	private function css(): string {
		return 'body{font:15px/1.5 system-ui,-apple-system,"Segoe UI",Tahoma,sans-serif;color:#1d2327;max-width:920px;margin:2rem auto;padding:0 1rem}'
			. 'h1{font-size:1.6rem}h2{font-size:1.15rem;margin-top:2rem;border-bottom:1px solid #ccd0d4;padding-bottom:.25rem}'
			. 'table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccd0d4;padding:.45rem .6rem;text-align:start;vertical-align:top}th{background:#f0f0f1}'
			. 'dl.meta{display:grid;grid-template-columns:max-content 1fr;gap:.2rem 1rem}dl.meta dt{font-weight:600}dl.meta dd{margin:0}'
			. '.verdict{font-size:1.1rem}.verdict-blocked strong{color:#b32d2e}.verdict-attention strong{color:#996800}.verdict-ready strong{color:#007017}'
			. '.status-fail td:first-child{font-weight:700;color:#b32d2e}.status-waived td:first-child{font-weight:700;color:#996800}.cat{color:#646970}.footer{margin-top:2rem;color:#646970;font-size:.85rem}'
			. 'button{font:inherit;padding:.4rem .9rem;cursor:pointer}@media print{.no-print{display:none}body{margin:0;max-width:none}tr{break-inside:avoid}}';
	}
}
