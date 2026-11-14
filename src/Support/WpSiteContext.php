<?php
/**
 * SiteContext backed by the real WordPress APIs.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Support;

use GoliveAudit\Audit\AuditRunner;
use GoliveAudit\Audit\SiteContext;
use WP_Query;
use WP_User_Query;

final class WpSiteContext implements SiteContext {

	public function environment(): string {
		$type = wp_get_environment_type();

		return in_array( $type, AuditRunner::ENVIRONMENTS, true ) ? $type : 'production';
	}

	public function option( string $name, $default_value = null ) {
		return get_option( $name, $default_value );
	}

	public function constant_value( string $name ) {
		return defined( $name ) ? constant( $name ) : null;
	}

	public function root_file_contents( string $name, int $max_bytes = 65536 ): ?string {
		if ( ! $this->is_plain_file_name( $name ) ) {
			return null;
		}

		$path = ABSPATH . $name;

		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		// Local, read-only access to a single known file; no remote URL is involved.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $path, false, null, 0, $max_bytes );

		return false === $contents ? null : $contents;
	}

	public function content_file_exists( string $name ): bool {
		return $this->is_plain_file_name( $name ) && is_file( WP_CONTENT_DIR . '/' . $name );
	}

	public function post_status( int $post_id ): ?string {
		$status = get_post_status( $post_id );

		return false === $status ? null : (string) $status;
	}

	public function published_post_exists( string $slug, string $post_type ): bool {
		$post = get_page_by_path( $slug, OBJECT, $post_type );

		return null !== $post && 'publish' === $post->post_status;
	}

	public function username_exists( string $login ): bool {
		return (bool) username_exists( $login );
	}

	public function user_count_by_role( string $role ): int {
		$query = new WP_User_Query(
			array(
				'role'        => $role,
				'fields'      => 'ID',
				'number'      => 1,
				'count_total' => true,
			)
		);

		return (int) $query->get_total();
	}

	public function sitemaps_enabled(): bool {
		if ( ! function_exists( 'wp_sitemaps_get_server' ) ) {
			return false;
		}

		$server = wp_sitemaps_get_server();

		return $server->sitemaps_enabled();
	}

	public function active_plugins(): array {
		$plugins = (array) get_option( 'active_plugins', array() );

		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}

		return array_values( array_map( 'strval', $plugins ) );
	}

	public function has_mail_transport(): bool {
		return false !== has_action( 'phpmailer_init' ) || false !== has_filter( 'pre_wp_mail' );
	}

	public function admin_url( string $path ): string {
		return admin_url( $path );
	}

	public function xmlrpc_enabled(): bool {
		/** This filter is documented in wp-includes/class-wp-xmlrpc-server.php */
		return (bool) apply_filters( 'xmlrpc_enabled', true );
	}

	public function db_prefix(): string {
		global $wpdb;

		return isset( $wpdb->prefix ) ? (string) $wpdb->prefix : '';
	}

	public function count_published_posts_matching( string $needle ): int {
		$needle = trim( $needle );

		if ( '' === $needle ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				's'                      => $needle,
				'post_type'              => array( 'post', 'page' ),
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'ignore_sticky_posts'    => true,
			)
		);

		return (int) $query->found_posts;
	}

	/**
	 * Accepts "robots.txt" but rejects anything containing a path separator or "..".
	 */
	private function is_plain_file_name( string $name ): bool {
		return '' !== $name && basename( $name ) === $name && false === strpos( $name, '..' );
	}
}
