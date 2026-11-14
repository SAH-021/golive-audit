<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\CheckInterface;
use GoliveAudit\Audit\Result;
use GoliveAudit\Checks;
use GoliveAudit\Tests\Support\ArrayContext;
use PHPUnit\Framework\TestCase;

/**
 * Scenarios for the checks added in 1.1.
 */
final class NewChecksTest extends TestCase {

	/**
	 * @dataProvider scenarios
	 */
	public function test_scenario( CheckInterface $check, ArrayContext $context, string $expected ): void {
		$this->assertSame( $expected, $check->run( $context )->status() );
	}

	/**
	 * @return array<string, array{0: CheckInterface, 1: ArrayContext, 2: string}>
	 */
	public function scenarios(): array {
		return array(
			'placeholder none passes'            => array( new Checks\PlaceholderTextCheck(), new ArrayContext( 'production' ), Result::STATUS_PASS ),
			'placeholder latin fails'            => array( new Checks\PlaceholderTextCheck(), new ArrayContext( 'production', array(), array(), array( 'matches' => array( 'lorem ipsum' => 2 ) ) ), Result::STATUS_FAIL ),
			'placeholder persian fails'          => array( new Checks\PlaceholderTextCheck(), new ArrayContext( 'production', array(), array(), array( 'matches' => array( 'لورم ایپسوم' => 1 ) ) ), Result::STATUS_FAIL ),

			'sensitive files none passes'        => array( new Checks\SensitiveFilesCheck(), new ArrayContext( 'production' ), Result::STATUS_PASS ),
			'sensitive wp-config backup fails'   => array( new Checks\SensitiveFilesCheck(), new ArrayContext( 'production', array(), array(), array( 'root_files' => array( 'wp-config.php.bak' => 'x' ) ) ), Result::STATUS_FAIL ),
			'sensitive empty sql dump fails'     => array( new Checks\SensitiveFilesCheck(), new ArrayContext( 'production', array(), array(), array( 'root_files' => array( 'dump.sql' => '' ) ) ), Result::STATUS_FAIL ),
			'sensitive readme only passes'       => array( new Checks\SensitiveFilesCheck(), new ArrayContext( 'production', array(), array(), array( 'root_files' => array( 'readme.html' => 'x' ) ) ), Result::STATUS_PASS ),

			'leftover none passes'               => array( new Checks\LeftoverFilesCheck(), new ArrayContext( 'production' ), Result::STATUS_PASS ),
			'leftover readme fails'              => array( new Checks\LeftoverFilesCheck(), new ArrayContext( 'production', array(), array(), array( 'root_files' => array( 'readme.html' => 'x' ) ) ), Result::STATUS_FAIL ),
			'leftover error_log fails'           => array( new Checks\LeftoverFilesCheck(), new ArrayContext( 'production', array(), array(), array( 'root_files' => array( 'error_log' => 'x' ) ) ), Result::STATUS_FAIL ),

			'xmlrpc enabled fails'               => array( new Checks\XmlRpcCheck(), new ArrayContext( 'production', array(), array(), array( 'xmlrpc' => true ) ), Result::STATUS_FAIL ),
			'xmlrpc disabled passes'             => array( new Checks\XmlRpcCheck(), new ArrayContext( 'production', array(), array(), array( 'xmlrpc' => false ) ), Result::STATUS_PASS ),

			'db prefix default fails'            => array( new Checks\DbPrefixCheck(), new ArrayContext( 'production', array(), array(), array( 'db_prefix' => 'wp_' ) ), Result::STATUS_FAIL ),
			'db prefix default uppercase fails'  => array( new Checks\DbPrefixCheck(), new ArrayContext( 'production', array(), array(), array( 'db_prefix' => 'WP_' ) ), Result::STATUS_FAIL ),
			'db prefix custom passes'            => array( new Checks\DbPrefixCheck(), new ArrayContext( 'production', array(), array(), array( 'db_prefix' => 'xk3_' ) ), Result::STATUS_PASS ),

			'coming soon nothing passes'         => array( new Checks\ComingSoonCheck(), new ArrayContext( 'production' ), Result::STATUS_PASS ),
			'coming soon woocommerce fails'      => array( new Checks\ComingSoonCheck(), new ArrayContext( 'production', array( 'woocommerce_coming_soon' => 'yes' ) ), Result::STATUS_FAIL ),
			'coming soon woocommerce live passes' => array( new Checks\ComingSoonCheck(), new ArrayContext( 'production', array( 'woocommerce_coming_soon' => 'no' ) ), Result::STATUS_PASS ),
			'coming soon plugin fails'           => array( new Checks\ComingSoonCheck(), new ArrayContext( 'production', array(), array(), array( 'plugins' => array( 'coming-soon/coming-soon.php' ) ) ), Result::STATUS_FAIL ),
			'coming soon lookalike plugin passes' => array( new Checks\ComingSoonCheck(), new ArrayContext( 'production', array(), array(), array( 'plugins' => array( 'coming-soon-helper/x.php' ) ) ), Result::STATUS_PASS ),
		);
	}

	public function test_file_names_are_reported_but_never_contents(): void {
		$context = new ArrayContext( 'production', array(), array(), array( 'root_files' => array( '.env' => 'DB_PASSWORD=hunter2' ) ) );
		$result  = ( new Checks\SensitiveFilesCheck() )->run( $context )->to_array();

		$this->assertStringContainsString( '.env', $result['summary'] );
		$this->assertStringNotContainsString( 'hunter2', wp_json_encode( $result ) );
	}

	public function test_coming_soon_only_applies_to_production(): void {
		$this->assertSame( array( 'production' ), ( new Checks\ComingSoonCheck() )->environments() );
	}

	public function test_placeholder_count_is_reported_but_not_the_text(): void {
		$context = new ArrayContext( 'production', array(), array(), array( 'matches' => array( 'lorem ipsum' => 3 ) ) );
		$result  = ( new Checks\PlaceholderTextCheck() )->run( $context )->to_array();

		$this->assertStringContainsString( '3', $result['summary'] );
	}
}
