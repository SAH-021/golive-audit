<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\CheckInterface;
use GoliveAudit\Audit\Result;
use GoliveAudit\Checks;
use GoliveAudit\Tests\Support\ArrayContext;
use PHPUnit\Framework\TestCase;

/**
 * One scenario table for the small checks; the larger ones have their own test classes.
 */
final class BundledChecksTest extends TestCase {

	private const SALTS = array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' );

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
		$good_salts = array_fill_keys( self::SALTS, 'a-long-random-value-1234567890' );
		$bad_salts  = array_merge( $good_salts, array( 'NONCE_SALT' => 'put your unique phrase here' ) );
		$one_empty  = array_merge( $good_salts, array( 'AUTH_KEY' => '' ) );
		$missing    = $good_salts;
		unset( $missing['LOGGED_IN_SALT'] );

		return array(
			'permalinks plain fails'          => array( new Checks\PermalinksCheck(), new ArrayContext( 'production', array( 'permalink_structure' => '' ) ), Result::STATUS_FAIL ),
			'permalinks pretty passes'        => array( new Checks\PermalinksCheck(), new ArrayContext( 'production', array( 'permalink_structure' => '/%postname%/' ) ), Result::STATUS_PASS ),

			'sitemap from core passes'        => array( new Checks\SitemapCheck(), new ArrayContext( 'production', array(), array(), array( 'sitemaps' => true ) ), Result::STATUS_PASS ),
			'sitemap from seo plugin passes'  => array( new Checks\SitemapCheck(), new ArrayContext( 'production', array(), array(), array( 'plugins' => array( 'wordpress-seo/wp-seo.php' ) ) ), Result::STATUS_PASS ),
			'sitemap unrelated plugin fails'  => array( new Checks\SitemapCheck(), new ArrayContext( 'production', array(), array(), array( 'plugins' => array( 'hello-dolly/hello.php' ) ) ), Result::STATUS_FAIL ),
			'sitemap lookalike plugin fails'  => array( new Checks\SitemapCheck(), new ArrayContext( 'production', array(), array(), array( 'plugins' => array( 'wordpress-seo-helper/x.php' ) ) ), Result::STATUS_FAIL ),
			'sitemap none fails'              => array( new Checks\SitemapCheck(), new ArrayContext( 'production' ), Result::STATUS_FAIL ),

			'identity default tagline fails'  => array(
				new Checks\SiteIdentityCheck(),
				new ArrayContext(
					'production',
					array(
						'blogname'        => 'Acme',
						'blogdescription' => 'Just another WordPress site',
					)
				),
				Result::STATUS_FAIL,
			),
			'identity empty title fails'      => array(
				new Checks\SiteIdentityCheck(),
				new ArrayContext(
					'production',
					array(
						'blogname'        => ' ',
						'blogdescription' => 'Great things',
					)
				),
				Result::STATUS_FAIL,
			),
			'identity empty tagline passes'   => array(
				new Checks\SiteIdentityCheck(),
				new ArrayContext(
					'production',
					array(
						'blogname'        => 'Acme',
						'blogdescription' => '',
					)
				),
				Result::STATUS_PASS,
			),

			'icon set passes'                 => array( new Checks\SiteIconCheck(), new ArrayContext( 'production', array( 'site_icon' => '42' ) ), Result::STATUS_PASS ),
			'icon missing fails'              => array( new Checks\SiteIconCheck(), new ArrayContext( 'production', array( 'site_icon' => '0' ) ), Result::STATUS_FAIL ),

			'front page latest posts passes'  => array( new Checks\FrontPageCheck(), new ArrayContext( 'production', array( 'show_on_front' => 'posts' ) ), Result::STATUS_PASS ),
			'front page published passes'     => array(
				new Checks\FrontPageCheck(),
				new ArrayContext(
					'production',
					array(
						'show_on_front' => 'page',
						'page_on_front' => '7',
					),
					array(),
					array( 'statuses' => array( 7 => 'publish' ) )
				),
				Result::STATUS_PASS,
			),
			'front page draft fails'          => array(
				new Checks\FrontPageCheck(),
				new ArrayContext(
					'production',
					array(
						'show_on_front' => 'page',
						'page_on_front' => '7',
					),
					array(),
					array( 'statuses' => array( 7 => 'draft' ) )
				),
				Result::STATUS_FAIL,
			),
			'front page none selected fails'  => array(
				new Checks\FrontPageCheck(),
				new ArrayContext(
					'production',
					array(
						'show_on_front' => 'page',
						'page_on_front' => '0',
					)
				),
				Result::STATUS_FAIL,
			),
			'front page deleted fails'        => array(
				new Checks\FrontPageCheck(),
				new ArrayContext(
					'production',
					array(
						'show_on_front' => 'page',
						'page_on_front' => '9',
					)
				),
				Result::STATUS_FAIL,
			),

			'comments default install fails'  => array(
				new Checks\CommentDefaultsCheck(),
				new ArrayContext(
					'production',
					array(
						'default_ping_status'    => 'open',
						'default_comment_status' => 'open',
					)
				),
				Result::STATUS_FAIL,
			),
			'comments hardened passes'        => array(
				new Checks\CommentDefaultsCheck(),
				new ArrayContext(
					'production',
					array(
						'default_ping_status'    => 'closed',
						'default_comment_status' => 'open',
						'comment_moderation'     => '1',
					)
				),
				Result::STATUS_PASS,
			),
			'comments closed passes'          => array(
				new Checks\CommentDefaultsCheck(),
				new ArrayContext(
					'production',
					array(
						'default_ping_status'         => 'closed',
						'default_comment_status'      => 'closed',
						'comment_previously_approved' => '0',
					)
				),
				Result::STATUS_PASS,
			),
			'comments open unmoderated fails' => array(
				new Checks\CommentDefaultsCheck(),
				new ArrayContext(
					'production',
					array(
						'default_ping_status'         => 'closed',
						'default_comment_status'      => 'open',
						'comment_moderation'          => '0',
						'comment_previously_approved' => '0',
					)
				),
				Result::STATUS_FAIL,
			),

			'privacy published passes'        => array( new Checks\PrivacyPolicyCheck(), new ArrayContext( 'production', array( 'wp_page_for_privacy_policy' => '3' ), array(), array( 'statuses' => array( 3 => 'publish' ) ) ), Result::STATUS_PASS ),
			'privacy draft fails'             => array( new Checks\PrivacyPolicyCheck(), new ArrayContext( 'production', array( 'wp_page_for_privacy_policy' => '3' ), array(), array( 'statuses' => array( 3 => 'draft' ) ) ), Result::STATUS_FAIL ),
			'privacy unset fails'             => array( new Checks\PrivacyPolicyCheck(), new ArrayContext( 'production' ), Result::STATUS_FAIL ),

			'admin user named admin fails'    => array( new Checks\AdminUsersCheck(), new ArrayContext( 'production', array(), array(), array( 'usernames' => array( 'admin' ) ) ), Result::STATUS_FAIL ),
			'too many admins fails'           => array( new Checks\AdminUsersCheck(), new ArrayContext( 'production', array(), array(), array( 'role_counts' => array( 'administrator' => 4 ) ) ), Result::STATUS_FAIL ),
			'three admins passes'             => array( new Checks\AdminUsersCheck(), new ArrayContext( 'production', array(), array(), array( 'role_counts' => array( 'administrator' => 3 ) ) ), Result::STATUS_PASS ),

			'registration closed passes'      => array(
				new Checks\OpenRegistrationCheck(),
				new ArrayContext(
					'production',
					array(
						'users_can_register' => '0',
						'default_role'       => 'administrator',
					)
				),
				Result::STATUS_PASS,
			),
			'registration open subscriber ok' => array(
				new Checks\OpenRegistrationCheck(),
				new ArrayContext(
					'production',
					array(
						'users_can_register' => '1',
						'default_role'       => 'subscriber',
					)
				),
				Result::STATUS_PASS,
			),
			'registration open admin fails'   => array(
				new Checks\OpenRegistrationCheck(),
				new ArrayContext(
					'production',
					array(
						'users_can_register' => '1',
						'default_role'       => 'administrator',
					)
				),
				Result::STATUS_FAIL,
			),
			'registration open editor fails'  => array(
				new Checks\OpenRegistrationCheck(),
				new ArrayContext(
					'production',
					array(
						'users_can_register' => '1',
						'default_role'       => 'editor',
					)
				),
				Result::STATUS_FAIL,
			),

			'salts all good passes'           => array( new Checks\SaltsCheck(), new ArrayContext( 'production', array(), array(), array( 'constants' => $good_salts ) ), Result::STATUS_PASS ),
			'salt with sample value fails'    => array( new Checks\SaltsCheck(), new ArrayContext( 'production', array(), array(), array( 'constants' => $bad_salts ) ), Result::STATUS_FAIL ),
			'salt empty fails'                => array( new Checks\SaltsCheck(), new ArrayContext( 'production', array(), array(), array( 'constants' => $one_empty ) ), Result::STATUS_FAIL ),
			'salt undefined fails'            => array( new Checks\SaltsCheck(), new ArrayContext( 'production', array(), array(), array( 'constants' => $missing ) ), Result::STATUS_FAIL ),

			'file edit disabled passes'       => array( new Checks\FileEditorCheck(), new ArrayContext( 'production', array(), array(), array( 'constants' => array( 'DISALLOW_FILE_EDIT' => true ) ) ), Result::STATUS_PASS ),
			'file mods disabled passes'       => array( new Checks\FileEditorCheck(), new ArrayContext( 'production', array(), array(), array( 'constants' => array( 'DISALLOW_FILE_MODS' => true ) ) ), Result::STATUS_PASS ),
			'file edit false fails'           => array( new Checks\FileEditorCheck(), new ArrayContext( 'production', array(), array(), array( 'constants' => array( 'DISALLOW_FILE_EDIT' => false ) ) ), Result::STATUS_FAIL ),
			'file edit undefined fails'       => array( new Checks\FileEditorCheck(), new ArrayContext( 'production' ), Result::STATUS_FAIL ),

			'debug log present fails'         => array( new Checks\DebugLogCheck(), new ArrayContext( 'production', array(), array(), array( 'content_files' => array( 'debug.log' ) ) ), Result::STATUS_FAIL ),
			'debug log absent passes'         => array( new Checks\DebugLogCheck(), new ArrayContext( 'production' ), Result::STATUS_PASS ),

			'mail transport present passes'   => array( new Checks\MailTransportCheck(), new ArrayContext( 'production', array(), array(), array( 'mail' => true ) ), Result::STATUS_PASS ),
			'mail transport absent fails'     => array( new Checks\MailTransportCheck(), new ArrayContext( 'production' ), Result::STATUS_FAIL ),

			'timezone default fails'          => array(
				new Checks\TimezoneCheck(),
				new ArrayContext(
					'production',
					array(
						'timezone_string' => '',
						'gmt_offset'      => '0',
					)
				),
				Result::STATUS_FAIL,
			),
			'timezone city passes'            => array(
				new Checks\TimezoneCheck(),
				new ArrayContext(
					'production',
					array(
						'timezone_string' => 'Asia/Tehran',
						'gmt_offset'      => '0',
					)
				),
				Result::STATUS_PASS,
			),
			'timezone explicit utc passes'    => array(
				new Checks\TimezoneCheck(),
				new ArrayContext(
					'production',
					array(
						'timezone_string' => 'UTC',
						'gmt_offset'      => '0',
					)
				),
				Result::STATUS_PASS,
			),
			'timezone offset passes'          => array(
				new Checks\TimezoneCheck(),
				new ArrayContext(
					'production',
					array(
						'timezone_string' => '',
						'gmt_offset'      => '3.5',
					)
				),
				Result::STATUS_PASS,
			),
		);
	}

	public function test_salt_values_never_reach_the_result(): void {
		$context = new ArrayContext(
			'production',
			array(),
			array(),
			array(
				'constants' => array(
					'AUTH_KEY'  => 'super-secret-value',
					'NONCE_KEY' => 'put your unique phrase here',
				),
			)
		);
		$json    = (string) wp_json_encode( ( new Checks\SaltsCheck() )->run( $context )->to_array() );

		$this->assertStringNotContainsString( 'super-secret-value', $json );
		$this->assertStringContainsString( 'NONCE_KEY', $json );
	}
}
