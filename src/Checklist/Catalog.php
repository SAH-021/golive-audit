<?php
/**
 * The manual handoff checklist: things no plugin can verify automatically.
 *
 * Presets only add items (no dependency on any other plugin is implied).
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checklist;

final class Catalog {

	public const PRESETS        = array( 'business', 'blog', 'shop' );
	public const DEFAULT_PRESET = 'business';

	/**
	 * @return array<string, string> Preset slug => label.
	 */
	public static function preset_labels(): array {
		return array(
			'business' => __( 'Business / brochure site', 'golive-audit' ),
			'blog'     => __( 'Blog / publication', 'golive-audit' ),
			'shop'     => __( 'Online shop', 'golive-audit' ),
		);
	}

	/**
	 * @return array<string, string> Group slug => label.
	 */
	public static function group_labels(): array {
		return array(
			'infrastructure' => __( 'Infrastructure', 'golive-audit' ),
			'quality'        => __( 'Quality assurance', 'golive-audit' ),
			'compliance'     => __( 'Privacy & compliance', 'golive-audit' ),
			'handover'       => __( 'Handover', 'golive-audit' ),
		);
	}

	/**
	 * @return array<int, array{id: string, group: string, label: string}> Items for a preset.
	 */
	public static function items( string $preset ): array {
		$items = self::common_items();

		if ( 'blog' === $preset ) {
			$items = array_merge( $items, self::blog_items() );
		} elseif ( 'shop' === $preset ) {
			$items = array_merge( $items, self::shop_items() );
		}

		return $items;
	}

	/**
	 * Whether an item exists in any preset, so state survives switching presets.
	 */
	public static function has_item( string $id ): bool {
		foreach ( self::PRESETS as $preset ) {
			foreach ( self::items( $preset ) as $item ) {
				if ( $item['id'] === $id ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * @return array<int, array{id: string, group: string, label: string}>
	 */
	private static function common_items(): array {
		return array(
			array(
				'id'    => 'backup_restore_tested',
				'group' => 'infrastructure',
				'label' => __( 'A full backup exists and a restore has been tested', 'golive-audit' ),
			),
			array(
				'id'    => 'dns_ssl_ready',
				'group' => 'infrastructure',
				'label' => __( 'DNS is configured and the SSL certificate renews automatically', 'golive-audit' ),
			),
			array(
				'id'    => 'redirects_from_old_site',
				'group' => 'infrastructure',
				'label' => __( 'Redirects from the old site\'s URLs are in place (for redesigns)', 'golive-audit' ),
			),
			array(
				'id'    => 'forms_tested',
				'group' => 'quality',
				'label' => __( 'Every form was submitted and the notification emails arrived', 'golive-audit' ),
			),
			array(
				'id'    => 'browsers_tested',
				'group' => 'quality',
				'label' => __( 'The site was tested in the main browsers and on mobile', 'golive-audit' ),
			),
			array(
				'id'    => 'error_page_ready',
				'group' => 'quality',
				'label' => __( 'A helpful 404 page exists', 'golive-audit' ),
			),
			array(
				'id'    => 'analytics_setup',
				'group' => 'quality',
				'label' => __( 'Analytics and Search Console are set up', 'golive-audit' ),
			),
			array(
				'id'    => 'backups_scheduled',
				'group' => 'infrastructure',
				'label' => __( 'Automatic backups are scheduled and stored off the server', 'golive-audit' ),
			),
			array(
				'id'    => 'uptime_monitoring',
				'group' => 'infrastructure',
				'label' => __( 'Uptime monitoring and failure alerts are set up', 'golive-audit' ),
			),
			array(
				'id'    => 'staging_blocked',
				'group' => 'infrastructure',
				'label' => __( 'Old staging or test copies are password-protected or removed', 'golive-audit' ),
			),
			array(
				'id'    => 'email_authentication',
				'group' => 'infrastructure',
				'label' => __( 'SPF, DKIM and DMARC are configured for the sending domain', 'golive-audit' ),
			),
			array(
				'id'    => 'speed_tested',
				'group' => 'quality',
				'label' => __( 'Page speed was tested on mobile and the worst offenders fixed', 'golive-audit' ),
			),
			array(
				'id'    => 'social_previews_tested',
				'group' => 'quality',
				'label' => __( 'Link previews were tested on social networks and messengers', 'golive-audit' ),
			),
			array(
				'id'    => 'sitemap_submitted',
				'group' => 'quality',
				'label' => __( 'The XML sitemap is submitted to Search Console', 'golive-audit' ),
			),
			array(
				'id'    => 'caches_cleared',
				'group' => 'quality',
				'label' => __( 'Caches (plugin, server and CDN) were cleared after the final changes', 'golive-audit' ),
			),
			array(
				'id'    => 'consent_handled',
				'group' => 'compliance',
				'label' => __( 'Cookie consent and privacy requirements are handled for the target markets', 'golive-audit' ),
			),
			array(
				'id'    => 'access_handed_over',
				'group' => 'handover',
				'label' => __( 'Hosting, domain and admin credentials are handed over to the client', 'golive-audit' ),
			),
			array(
				'id'    => 'handover_training',
				'group' => 'handover',
				'label' => __( 'The client was trained to edit content and knows who to contact for support', 'golive-audit' ),
			),
		);
	}

	/**
	 * @return array<int, array{id: string, group: string, label: string}>
	 */
	private static function blog_items(): array {
		return array(
			array(
				'id'    => 'blog_feed_checked',
				'group' => 'quality',
				'label' => __( 'The RSS feed works and any newsletter sign-up is tested', 'golive-audit' ),
			),
			array(
				'id'    => 'blog_authors_reviewed',
				'group' => 'quality',
				'label' => __( 'Author profiles, categories and tags have been reviewed', 'golive-audit' ),
			),
		);
	}

	/**
	 * @return array<int, array{id: string, group: string, label: string}>
	 */
	private static function shop_items(): array {
		return array(
			array(
				'id'    => 'shop_payments_live',
				'group' => 'quality',
				'label' => __( 'The payment gateway is in live mode and a real test order was placed', 'golive-audit' ),
			),
			array(
				'id'    => 'shop_legal_pages',
				'group' => 'compliance',
				'label' => __( 'Terms, returns and shipping pages are published', 'golive-audit' ),
			),
			array(
				'id'    => 'shop_order_emails',
				'group' => 'quality',
				'label' => __( 'Order and shipping emails were tested end to end', 'golive-audit' ),
			),
			array(
				'id'    => 'shop_tax_shipping',
				'group' => 'quality',
				'label' => __( 'Taxes, shipping zones and shipping rates were checked with real addresses', 'golive-audit' ),
			),
			array(
				'id'    => 'shop_trust_badge',
				'group' => 'compliance',
				'label' => __( 'Any required business registration or trust badge (for example Enamad in Iran) is displayed', 'golive-audit' ),
			),
		);
	}
}
