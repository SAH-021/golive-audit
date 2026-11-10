<?php
/**
 * The site should publish an XML sitemap, from core or from an SEO plugin.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class SitemapCheck extends AbstractCheck {

	/**
	 * Plugin folders of well-known SEO and sitemap plugins.
	 */
	private const SITEMAP_PLUGINS = array(
		'wordpress-seo',
		'wordpress-seo-premium',
		'seo-by-rank-math',
		'all-in-one-seo-pack',
		'wp-seopress',
		'autodescription',
		'google-sitemap-generator',
		'xml-sitemap-feed',
	);

	public function id(): string {
		return 'seo.sitemap';
	}

	public function title(): string {
		return __( 'An XML sitemap is available', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function environments(): array {
		return array( 'production' );
	}

	public function run( SiteContext $context ): Result {
		if ( $context->sitemaps_enabled() ) {
			return $this->pass( __( 'The core XML sitemap is enabled.', 'golive-audit' ) );
		}

		foreach ( $context->active_plugins() as $basename ) {
			if ( in_array( (string) strstr( $basename, '/', true ), self::SITEMAP_PLUGINS, true ) ) {
				return $this->pass( __( 'A known SEO or sitemap plugin is active.', 'golive-audit' ) );
			}
		}

		return $this->fail(
			__( 'No XML sitemap was detected.', 'golive-audit' ),
			__( 'Enable the core sitemap (it is disabled while search engines are discouraged) or use an SEO plugin that provides one, then submit it to Search Console.', 'golive-audit' ),
			$context->admin_url( 'options-reading.php' )
		);
	}
}
