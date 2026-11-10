<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\Result;
use GoliveAudit\Checks\RobotsTxtCheck;
use GoliveAudit\Tests\Support\ArrayContext;
use PHPUnit\Framework\TestCase;

final class RobotsTxtCheckTest extends TestCase {

	private function status( ?string $robots ): string {
		$files = null === $robots ? array() : array( 'robots.txt' => $robots );

		return ( new RobotsTxtCheck() )->run( new ArrayContext( 'production', array(), array(), array( 'root_files' => $files ) ) )->status();
	}

	public function test_no_physical_file_passes(): void {
		$this->assertSame( Result::STATUS_PASS, $this->status( null ) );
	}

	public function test_blocking_everything_for_all_agents_fails(): void {
		$this->assertSame( Result::STATUS_FAIL, $this->status( "User-agent: *\nDisallow: /\n" ) );
	}

	public function test_case_comments_and_windows_line_endings_are_handled(): void {
		$this->assertSame( Result::STATUS_FAIL, $this->status( "# staging\r\nUSER-AGENT: *   # everyone\r\nDISALLOW: /\r\n" ) );
	}

	public function test_normal_rules_pass(): void {
		$this->assertSame( Result::STATUS_PASS, $this->status( "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n" ) );
	}

	public function test_an_empty_disallow_means_allow_everything(): void {
		$this->assertSame( Result::STATUS_PASS, $this->status( "User-agent: *\nDisallow:\n" ) );
	}

	public function test_blocking_only_one_bot_passes(): void {
		$this->assertSame( Result::STATUS_PASS, $this->status( "User-agent: BadBot\nDisallow: /\n\nUser-agent: *\nDisallow: /private/\n" ) );
	}

	public function test_a_block_in_a_later_group_for_all_agents_fails(): void {
		$this->assertSame( Result::STATUS_FAIL, $this->status( "User-agent: Googlebot\nAllow: /\n\nUser-agent: *\nDisallow: /\n" ) );
	}
}
