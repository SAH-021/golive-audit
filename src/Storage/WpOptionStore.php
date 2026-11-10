<?php
/**
 * OptionStore backed by the WordPress options table (never autoloaded).
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Storage;

final class WpOptionStore implements OptionStore {

	public function get( string $key, $default_value = null ) {
		return get_option( $key, $default_value );
	}

	public function update( string $key, $value ): void {
		update_option( $key, $value, false );
	}
}
