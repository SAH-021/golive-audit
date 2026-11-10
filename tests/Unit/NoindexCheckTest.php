<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\Result;
use GoliveAudit\Checks\NoindexCheck;
use GoliveAudit\Tests\Support\ArrayContext;
use PHPUnit\Framework\TestCase;

final class NoindexCheckTest extends TestCase {

	public function test_production_allowing_indexing_passes(): void {
		$result = ( new NoindexCheck() )->run( new ArrayContext( 'production', array( 'blog_public' => '1' ) ) );

		$this->assertSame( Result::STATUS_PASS, $result->status() );
	}

	public function test_production_discouraging_indexing_is_a_critical_failure(): void {
		$result = ( new NoindexCheck() )->run( new ArrayContext( 'production', array( 'blog_public' => '0' ) ) );

		$this->assertSame( Result::STATUS_FAIL, $result->status() );
		$this->assertTrue( $result->blocks_launch() );
		$this->assertSame( 'https://example.test/wp-admin/options-reading.php', $result->to_array()['fix_url'] );
	}

	public function test_staging_discouraging_indexing_passes(): void {
		$result = ( new NoindexCheck() )->run( new ArrayContext( 'staging', array( 'blog_public' => '0' ) ) );

		$this->assertSame( Result::STATUS_PASS, $result->status() );
	}

	public function test_staging_open_to_indexing_fails(): void {
		$result = ( new NoindexCheck() )->run( new ArrayContext( 'staging', array( 'blog_public' => '1' ) ) );

		$this->assertSame( Result::STATUS_FAIL, $result->status() );
	}

	public function test_missing_option_is_treated_as_indexable(): void {
		$result = ( new NoindexCheck() )->run( new ArrayContext( 'production' ) );

		$this->assertSame( Result::STATUS_PASS, $result->status() );
	}

	public function test_does_not_apply_to_local(): void {
		$this->assertNotContains( 'local', ( new NoindexCheck() )->environments() );
	}
}
