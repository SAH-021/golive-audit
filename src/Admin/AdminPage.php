<?php
/**
 * Registers the Tools > Golive Audit screen and loads the React dashboard.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Admin;

final class AdminPage {

	public const SLUG = 'golive-audit';

	private string $hook_suffix = '';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_menu(): void {
		$hook = add_management_page(
			__( 'Golive Audit', 'golive-audit' ),
			__( 'Golive Audit', 'golive-audit' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);

		$this->hook_suffix = is_string( $hook ) ? $hook : '';
	}

	public function render(): void {
		echo '<div class="wrap golive-audit-wrap">';
		echo '<h1>' . esc_html__( 'Golive Audit', 'golive-audit' ) . '</h1><hr class="wp-header-end">';

		if ( ! is_readable( GOLIVE_AUDIT_DIR . 'assets/build/index.asset.php' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The dashboard has not been built. Run "npm install && npm run build" in the plugin folder.', 'golive-audit' ) . '</p></div>';
		}

		echo '<div id="golive-audit-root"></div>';
		echo '<noscript><p>' . esc_html__( 'Golive Audit needs JavaScript. You can still use WP-CLI: wp golive-audit run', 'golive-audit' ) . '</p></noscript>';
		echo '</div>';
	}

	/**
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ): void {
		if ( '' === $this->hook_suffix || $hook !== $this->hook_suffix ) {
			return;
		}

		$asset_file = GOLIVE_AUDIT_DIR . 'assets/build/index.asset.php';

		if ( ! is_readable( $asset_file ) ) {
			return;
		}

		$asset = require $asset_file;

		wp_enqueue_script(
			'golive-audit-admin',
			plugins_url( 'assets/build/index.js', GOLIVE_AUDIT_FILE ),
			$asset['dependencies'],
			$asset['version'],
			true
		);
		wp_set_script_translations( 'golive-audit-admin', 'golive-audit' );

		$style = GOLIVE_AUDIT_DIR . 'assets/build/style-index.css';

		if ( is_readable( $style ) ) {
			wp_enqueue_style(
				'golive-audit-admin',
				plugins_url( 'assets/build/style-index.css', GOLIVE_AUDIT_FILE ),
				array( 'wp-components' ),
				$asset['version']
			);
			wp_style_add_data( 'golive-audit-admin', 'rtl', 'replace' );
		}
	}
}
