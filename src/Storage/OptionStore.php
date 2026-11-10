<?php
/**
 * Minimal key-value storage port, so stores are testable without WordPress.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Storage;

interface OptionStore {

	/**
	 * @param string $key           Option key.
	 * @param mixed  $default_value Value when the key does not exist.
	 * @return mixed
	 */
	public function get( string $key, $default_value = null );

	/**
	 * @param string $key   Option key.
	 * @param mixed  $value Value to store.
	 */
	public function update( string $key, $value ): void;
}
