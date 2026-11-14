<?php
/**
 * Decorator that pins the environment while delegating everything else.
 *
 * Lets the same checks run against a chosen profile (for example `--env=production`
 * on a staging server) without any check knowing about the override.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Audit;

final class EnvironmentOverride implements SiteContext {

	private SiteContext $inner;
	private string $environment;

	public function __construct( SiteContext $inner, string $environment ) {
		$this->inner       = $inner;
		$this->environment = $environment;
	}

	public function environment(): string {
		return $this->environment;
	}

	public function option( string $name, $default_value = null ) {
		return $this->inner->option( $name, $default_value );
	}

	public function constant_value( string $name ) {
		return $this->inner->constant_value( $name );
	}

	public function root_file_contents( string $name, int $max_bytes = 65536 ): ?string {
		return $this->inner->root_file_contents( $name, $max_bytes );
	}

	public function content_file_exists( string $name ): bool {
		return $this->inner->content_file_exists( $name );
	}

	public function post_status( int $post_id ): ?string {
		return $this->inner->post_status( $post_id );
	}

	public function published_post_exists( string $slug, string $post_type ): bool {
		return $this->inner->published_post_exists( $slug, $post_type );
	}

	public function username_exists( string $login ): bool {
		return $this->inner->username_exists( $login );
	}

	public function user_count_by_role( string $role ): int {
		return $this->inner->user_count_by_role( $role );
	}

	public function sitemaps_enabled(): bool {
		return $this->inner->sitemaps_enabled();
	}

	public function active_plugins(): array {
		return $this->inner->active_plugins();
	}

	public function has_mail_transport(): bool {
		return $this->inner->has_mail_transport();
	}

	public function admin_url( string $path ): string {
		return $this->inner->admin_url( $path );
	}

	public function xmlrpc_enabled(): bool {
		return $this->inner->xmlrpc_enabled();
	}

	public function db_prefix(): string {
		return $this->inner->db_prefix();
	}

	public function count_published_posts_matching( string $needle ): int {
		return $this->inner->count_published_posts_matching( $needle );
	}
}
