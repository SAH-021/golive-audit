<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\Result;
use GoliveAudit\Checks\AdminEmailCheck;
use GoliveAudit\Tests\Support\ArrayContext;
use PHPUnit\Framework\TestCase;

final class AdminEmailCheckTest extends TestCase {

	/**
	 * @dataProvider failing_addresses
	 */
	public function test_fails_for_bad_addresses( string $email ): void {
		$result = ( new AdminEmailCheck() )->run( new ArrayContext( 'production', array( 'admin_email' => $email ) ) );

		$this->assertSame( Result::STATUS_FAIL, $result->status(), $email );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function failing_addresses(): array {
		return array(
			'empty'             => array( '' ),
			'no at sign'        => array( 'not-an-email' ),
			'example.com'       => array( 'dev@example.com' ),
			'uppercase example' => array( 'Dev@EXAMPLE.ORG' ),
			'reserved test tld' => array( 'me@client.test' ),
			'local tld'         => array( 'me@box.local' ),
			'localhost'         => array( 'root@localhost' ),
		);
	}

	public function test_passes_for_a_real_domain(): void {
		$result = ( new AdminEmailCheck() )->run( new ArrayContext( 'production', array( 'admin_email' => 'owner@client-site.com' ) ) );

		$this->assertSame( Result::STATUS_PASS, $result->status() );
	}

	public function test_a_lookalike_domain_is_not_a_placeholder(): void {
		$result = ( new AdminEmailCheck() )->run( new ArrayContext( 'production', array( 'admin_email' => 'owner@myexample.com' ) ) );

		$this->assertSame( Result::STATUS_PASS, $result->status() );
	}

	public function test_the_address_is_never_written_to_the_result(): void {
		$result = ( new AdminEmailCheck() )->run( new ArrayContext( 'production', array( 'admin_email' => 'secret.person@example.com' ) ) );

		$this->assertStringNotContainsString( 'secret.person', wp_json_encode( $result->to_array() ) );
		$this->assertStringContainsString( 'example.com', $result->to_array()['summary'] );
	}
}
