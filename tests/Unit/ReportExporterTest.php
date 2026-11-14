<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\Report;
use GoliveAudit\Audit\Result;
use GoliveAudit\Export\ReportExporter;
use GoliveAudit\Tests\Support\FakeCheck;
use PHPUnit\Framework\TestCase;

final class ReportExporterTest extends TestCase {

	private function report(): Report {
		$fail = Result::fail( new FakeCheck( 'seo.thing', Result::SEVERITY_CRITICAL ), '=HYPERLINK("http://evil")', 'Do <b>this</b>' );
		$ok   = Result::pass( new FakeCheck( 'seo.other', Result::SEVERITY_LOW ), 'Fine' );
		$wv   = Result::fail( new FakeCheck( 'seo.third', Result::SEVERITY_WARNING ), 'Bad', 'Fix' )->with_waiver( '<script>alert(1)</script>' );

		return new Report( 'production', array( $ok, $fail, $wv ), 0, '1.0.0' );
	}

	public function test_csv_has_bom_header_and_neutralizes_formulas(): void {
		$csv = ( new ReportExporter() )->to_csv( $this->report() );

		$this->assertSame( "\xEF\xBB\xBF", substr( $csv, 0, 3 ) );
		$this->assertStringContainsString( '"id","title"', $csv );
		$this->assertStringContainsString( "\"'=HYPERLINK(\"\"http://evil\"\")\"", $csv );
		$this->assertStringNotContainsString( ',"=HYPERLINK', $csv );
	}

	/**
	 * @dataProvider formula_prefixes
	 */
	public function test_every_formula_prefix_is_neutralized( string $prefix ): void {
		$result = Result::fail( new FakeCheck( 'seo.thing' ), $prefix . 'cmd', 'x' );
		$csv    = ( new ReportExporter() )->to_csv( new Report( 'production', array( $result ), 0 ) );

		$this->assertStringContainsString( '"\'' . $prefix . 'cmd"', $csv );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function formula_prefixes(): array {
		return array(
			'equals' => array( '=' ),
			'plus'   => array( '+' ),
			'minus'  => array( '-' ),
			'at'     => array( '@' ),
		);
	}

	public function test_csv_keeps_non_latin_text_intact(): void {
		$result = Result::pass( new FakeCheck( 'seo.thing' ), 'سایت آماده است' );

		$this->assertStringContainsString( 'سایت آماده است', ( new ReportExporter() )->to_csv( new Report( 'production', array( $result ), 0 ) ) );
	}

	public function test_html_escapes_every_dynamic_value(): void {
		$meta = array(
			'site_name'   => '<img src=x onerror=alert(1)>',
			'site_url'    => 'https://client.com',
			'generated'   => 'today',
			'agency_name' => '"><script>bad()</script>',
			'client_name' => 'Client',
			'note'        => "line one\n<b>two</b>",
			'is_rtl'      => false,
			'lang'        => 'en-US',
			'version'     => '1.0.0',
		);
		$html = ( new ReportExporter() )->to_html(
			$this->report(),
			$meta,
			array(
				array(
					'state' => 'done',
					'label' => '<i>Backups</i>',
				),
			),
			'abc123'
		);

		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringNotContainsString( '<script>bad()', $html );
		$this->assertStringNotContainsString( '<script>alert(1)', $html );
		$this->assertStringNotContainsString( '<b>this</b>', $html );
		$this->assertStringNotContainsString( '<i>Backups</i>', $html );
		$this->assertStringContainsString( '&lt;b&gt;two&lt;/b&gt;', $html );
		$this->assertSame( 1, substr_count( $html, '<script' ), 'only the print handler script may exist' );
		$this->assertStringContainsString( 'nonce="abc123"', $html );
	}

	public function test_html_orders_blockers_first_and_marks_waived_risks(): void {
		$html = ( new ReportExporter() )->to_html( $this->report(), array( 'version' => '1.0.0' ), array(), 'n' );

		$fail   = strpos( $html, 'class="status-fail"' );
		$waived = strpos( $html, 'class="status-waived"' );
		$pass   = strpos( $html, 'class="status-pass"' );

		$this->assertNotFalse( $fail );
		$this->assertLessThan( $waived, $fail );
		$this->assertLessThan( $pass, $waived );
		$this->assertStringContainsString( 'Launch blocked', $html );
		$this->assertStringContainsString( 'Accepted risk', $html );
		$this->assertStringContainsString( 'Accepted because', $html );
	}

	public function test_html_sets_direction_and_language(): void {
		$html = ( new ReportExporter() )->to_html(
			new Report( 'staging', array(), 0 ),
			array(
				'is_rtl' => true,
				'lang'   => 'fa-IR',
			),
			array(),
			'n'
		);

		$this->assertStringContainsString( 'lang="fa-IR" dir="rtl"', $html );
	}

	public function test_json_data_bundles_meta_report_and_checklist(): void {
		$data = ( new ReportExporter() )->to_data( $this->report(), array( 'site_name' => 'Acme' ), array( array( 'id' => 'forms_tested' ) ) );

		$this->assertSame( array( 'meta', 'report', 'checklist' ), array_keys( $data ) );
		$this->assertSame( 'Acme', $data['meta']['site_name'] );
		$this->assertSame( 'production', $data['report']['environment'] );
	}

	public function test_markdown_lists_checks_and_flattens_line_breaks(): void {
		$fail   = Result::fail( new FakeCheck( 'seo.thing', Result::SEVERITY_CRITICAL ), "Line one\nline two", 'Do this' );
		$report = new Report( 'production', array( $fail ), 0, '1.1.0' );

		$md = ( new ReportExporter() )->to_markdown(
			$report,
			array(
				'site_name' => 'Acme',
				'site_url'  => 'https://acme.test',
			),
			array(
				array(
					'state' => 'done',
					'label' => 'Backups tested',
				),
				array(
					'state' => 'todo',
					'label' => 'Forms tested',
				),
			)
		);

		$this->assertStringStartsWith( '# ', $md );
		$this->assertStringContainsString( 'Line one line two', $md );
		$this->assertStringContainsString( '- [ ] **Fake check**', $md );
		$this->assertStringContainsString( '- [x] Backups tested', $md );
		$this->assertStringContainsString( '- [ ] Forms tested', $md );
	}

	public function test_html_report_shows_recent_audits_only_with_two_or_more_runs(): void {
		$exporter = new ReportExporter();
		$one      = array(
			array(
				'date'    => '2026-11-12',
				'score'   => 80,
				'verdict' => 'attention',
				'failed'  => 2,
			),
		);

		$this->assertStringNotContainsString( 'Recent audits', $exporter->to_html( $this->report(), array(), array(), 'n', $one ) );
		$this->assertStringContainsString( 'Recent audits', $exporter->to_html( $this->report(), array(), array(), 'n', array_merge( $one, $one ) ) );
	}
}
