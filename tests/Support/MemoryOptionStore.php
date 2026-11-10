<?php

namespace GoliveAudit\Tests\Support;

use GoliveAudit\Storage\OptionStore;

final class MemoryOptionStore implements OptionStore {

	public array $data = array();

	public function get( string $key, $default_value = null ) {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default_value;
	}

	public function update( string $key, $value ): void {
		$this->data[ $key ] = $value;
	}
}
