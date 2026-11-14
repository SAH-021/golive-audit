<?php
/**
 * A coming-soon or maintenance page must not be left on at launch.
 *
 * Detects the WooCommerce "coming soon" site visibility setting and the most
 * common coming-soon plugins. A plugin being active does not prove that its
 * maintenance page is switched on, so a hit is something to verify.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checks;

use GoliveAudit\Audit\Result;
use GoliveAudit\Audit\SiteContext;

final class ComingSoonCheck extends AbstractCheck {

	/**
	 * Plugin folders of well-known coming-soon and maintenance-mode plugins.
	 */
	private const PLUGINS = array(
		'coming-soon',
		'seedprod-coming-soon-pro-5',
		'wp-maintenance-mode',
		'maintenance',
		'under-construction-page',
		'cmp-coming-soon-maintenance',
		'minimal-coming-soon-maintenance-mode',
	);

	public function id(): string {
		return 'env.coming_soon';
	}

	public function title(): string {
		return __( 'No coming-soon or maintenance page is active', 'golive-audit' );
	}

	public function severity(): string {
		return Result::SEVERITY_WARNING;
	}

	public function environments(): array {
		return array( 'production' );
	}

	public function run( SiteContext $context ): Result {
		if ( 'yes' === (string) $context->option( 'woocommerce_coming_soon', 'no' ) ) {
			return $this->fail(
				__( 'WooCommerce is set to "coming soon", so visitors do not see the shop.', 'golive-audit' ),
				__( 'Set the site visibility to "Live" in WooCommerce > Settings > Site visibility.', 'golive-audit' ),
				$context->admin_url( 'admin.php?page=wc-settings&tab=site-visibility' )
			);
		}

		foreach ( $context->active_plugins() as $basename ) {
			if ( in_array( (string) strstr( $basename, '/', true ), self::PLUGINS, true ) ) {
				return $this->fail(
					__( 'A coming-soon or maintenance plugin is active.', 'golive-audit' ),
					__( 'Deactivate it, or make sure its coming-soon mode is switched off, so visitors see the real site.', 'golive-audit' ),
					$context->admin_url( 'plugins.php' )
				);
			}
		}

		return $this->pass( __( 'No coming-soon or maintenance plugin or setting was detected.', 'golive-audit' ) );
	}
}
