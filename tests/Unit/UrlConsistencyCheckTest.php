<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\Result;
use GoliveAudit\Checks\UrlConsistencyCheck;
use GoliveAudit\Tests\Support\ArrayContext;
use PHPUnit\Framework\TestCase;

final class UrlConsistencyCheckTest extends TestCase {

	private function status( string $siteurl, string $home ): string {
		return ( new UrlConsistencyCheck() )->run(
			new ArrayContext(
				'production',
				array(
					'siteurl' => $siteurl,
					'home'    => $home,
				)
			)
		)->status();
	}

	public function test_matching_public_urls_pass(): void {
		$this->assertSame( Result::STATUS_PASS, $this->status( 'https://client.com', 'https://client.com' ) );
	}

	public function test_a_subdirectory_install_with_same_host_passes(): void {
		$this->assertSame( Result::STATUS_PASS, $this->status( 'https://client.com/wp', 'https://client.com' ) );
	}

	/**
	 * @dataProvider bad_urls
	 */
	public function test_non_public_or_inconsistent_urls_fail( string $siteurl, string $home ): void {
		$this->assertSame( Result::STATUS_FAIL, $this->status( $siteurl, $home ) );
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public function bad_urls(): array {
		return array(
			'localhost'        => array( 'http://localhost:8080', 'http://localhost:8080' ),
			'ip address'       => array( 'http://192.168.1.20', 'http://192.168.1.20' ),
			'ipv6 loopback'    => array( 'http://[::1]', 'http://[::1]' ),
			'local tld'        => array( 'https://client.local', 'https://client.local' ),
			'staging label'    => array( 'https://staging.client.com', 'https://staging.client.com' ),
			'dev label'        => array( 'https://dev.client.com', 'https://dev.client.com' ),
			'different hosts'  => array( 'https://old.client.com', 'https://client.com' ),
			'different scheme' => array( 'http://client.com', 'https://client.com' ),
			'empty'            => array( '', '' ),
		);
	}

	public function test_a_word_containing_dev_is_not_a_staging_label(): void {
		$this->assertSame( Result::STATUS_PASS, $this->status( 'https://devotion.example-church.com', 'https://devotion.example-church.com' ) );
	}

	public function test_only_applies_to_production(): void {
		$this->assertSame( array( 'production' ), ( new UrlConsistencyCheck() )->environments() );
	}
}
