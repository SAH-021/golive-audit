<?php

namespace GoliveAudit\Tests\Support;

use GoliveAudit\Audit\SiteContext;

/**
 * In-memory SiteContext for unit tests.
 */
final class ArrayContext implements SiteContext {

	private string $environment;
	private array $options;
	private array $posts;
	private array $extra;

	/**
	 * @param string               $environment Environment profile.
	 * @param array<string, mixed> $options     Option values by name.
	 * @param string[]             $posts       Published posts as "type:slug".
	 * @param array<string, mixed> $extra       Optional: constants, root_files, content_files, statuses,
	 *                                          usernames, role_counts, sitemaps, plugins, mail.
	 */
	public function __construct( string $environment = 'production', array $options = array(), array $posts = array(), array $extra = array() ) {
		$this->environment = $environment;
		$this->options     = $options;
		$this->posts       = $posts;
		$this->extra       = $extra;
	}

	public function environment(): string {
		return $this->environment;
	}

	public function option( string $name, $default_value = null ) {
		return array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $default_value;
	}

	public function constant_value( string $name ) {
		return $this->extra['constants'][ $name ] ?? null;
	}

	public function root_file_contents( string $name, int $max_bytes = 65536 ): ?string {
		return $this->extra['root_files'][ $name ] ?? null;
	}

	public function content_file_exists( string $name ): bool {
		return in_array( $name, $this->extra['content_files'] ?? array(), true );
	}

	public function post_status( int $post_id ): ?string {
		return $this->extra['statuses'][ $post_id ] ?? null;
	}

	public function published_post_exists( string $slug, string $post_type ): bool {
		return in_array( $post_type . ':' . $slug, $this->posts, true );
	}

	public function username_exists( string $login ): bool {
		return in_array( $login, $this->extra['usernames'] ?? array(), true );
	}

	public function user_count_by_role( string $role ): int {
		return (int) ( $this->extra['role_counts'][ $role ] ?? 0 );
	}

	public function sitemaps_enabled(): bool {
		return (bool) ( $this->extra['sitemaps'] ?? false );
	}

	public function active_plugins(): array {
		return $this->extra['plugins'] ?? array();
	}

	public function has_mail_transport(): bool {
		return (bool) ( $this->extra['mail'] ?? false );
	}

	public function admin_url( string $path ): string {
		return 'https://example.test/wp-admin/' . $path;
	}
}
