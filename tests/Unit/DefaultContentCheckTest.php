<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\Result;
use GoliveAudit\Checks\DefaultContentCheck;
use GoliveAudit\Tests\Support\ArrayContext;
use PHPUnit\Framework\TestCase;

final class DefaultContentCheckTest extends TestCase {

	public function test_passes_when_no_sample_content_exists(): void {
		$result = ( new DefaultContentCheck() )->run( new ArrayContext( 'production' ) );

		$this->assertSame( Result::STATUS_PASS, $result->status() );
	}

	public function test_fails_when_hello_world_post_exists(): void {
		$result = ( new DefaultContentCheck() )->run( new ArrayContext( 'production', array(), array( 'post:hello-world' ) ) );

		$this->assertSame( Result::STATUS_FAIL, $result->status() );
		$this->assertStringContainsString( 'Hello world!', $result->to_array()['summary'] );
		$this->assertStringNotContainsString( 'Sample Page', $result->to_array()['summary'] );
	}

	public function test_lists_both_leftovers(): void {
		$context = new ArrayContext( 'staging', array(), array( 'post:hello-world', 'page:sample-page' ) );
		$summary = ( new DefaultContentCheck() )->run( $context )->to_array()['summary'];

		$this->assertStringContainsString( 'Hello world!', $summary );
		$this->assertStringContainsString( 'Sample Page', $summary );
	}

	public function test_a_page_with_the_post_slug_is_not_a_false_positive(): void {
		$result = ( new DefaultContentCheck() )->run( new ArrayContext( 'production', array(), array( 'page:hello-world' ) ) );

		$this->assertSame( Result::STATUS_PASS, $result->status() );
	}
}
