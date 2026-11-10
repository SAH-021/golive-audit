<?php
/**
 * Read-only view of the site that checks run against.
 *
 * Checks never call WordPress functions directly. They ask this port, which
 * keeps them unit-testable without a WordPress install.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Audit;

interface SiteContext {

	/**
	 * Environment profile: local, development, staging or production.
	 */
	public function environment(): string;

	/**
	 * Reads a site option.
	 *
	 * @param string $name    Option name.
	 * @param mixed  $default_value Value when the option does not exist.
	 * @return mixed
	 */
	public function option( string $name, $default_value = null );

	/**
	 * Value of a PHP constant, or null when it is not defined.
	 *
	 * @return mixed
	 */
	public function constant_value( string $name );

	/**
	 * Contents of a file in the WordPress root, or null when it is missing or unreadable.
	 * Only plain file names are accepted (no directories).
	 */
	public function root_file_contents( string $name, int $max_bytes = 65536 ): ?string;

	/**
	 * Whether a file exists directly inside wp-content. Only plain file names are accepted.
	 */
	public function content_file_exists( string $name ): bool;

	/**
	 * Post status of a post ID (for example "publish"), or null when it does not exist.
	 */
	public function post_status( int $post_id ): ?string;

	/**
	 * Whether a published post of the given type exists with this slug.
	 */
	public function published_post_exists( string $slug, string $post_type ): bool;

	/**
	 * Whether a user with this login exists.
	 */
	public function username_exists( string $login ): bool;

	/**
	 * Number of users with the given role.
	 */
	public function user_count_by_role( string $role ): int;

	/**
	 * Whether the core XML sitemaps are enabled.
	 */
	public function sitemaps_enabled(): bool;

	/**
	 * Basenames of the active plugins, for example "wordpress-seo/wp-seo.php".
	 *
	 * @return string[]
	 */
	public function active_plugins(): array;

	/**
	 * Whether something customizes how WordPress sends email (SMTP plugin, API mailer).
	 * This is a heuristic: it cannot prove that mail is actually delivered.
	 */
	public function has_mail_transport(): bool;

	/**
	 * Absolute admin URL, used for "fix it here" links.
	 */
	public function admin_url( string $path ): string;
}
