<?php
/**
 * Report branding and the checklist preset.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Storage;

use GoliveAudit\Checklist\Catalog;

final class SettingsStore {

	public const OPTION = 'golive_audit_settings';

	private const LIMITS = array(
		'agency_name' => 100,
		'client_name' => 100,
		'note'        => 1000,
	);

	private OptionStore $store;

	public function __construct( OptionStore $store ) {
		$this->store = $store;
	}

	/**
	 * @return array{agency_name: string, client_name: string, note: string, preset: string}
	 */
	public function get(): array {
		$stored = $this->store->get( self::OPTION, array() );

		return $this->normalize( is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Merges the given fields into the stored settings. Unknown keys are ignored.
	 *
	 * @param array<string, mixed> $input Fields to change.
	 * @return array{agency_name: string, client_name: string, note: string, preset: string}
	 */
	public function update( array $input ): array {
		$settings = $this->normalize( array_merge( $this->get(), $input ) );

		$this->store->update( self::OPTION, $settings );

		return $settings;
	}

	/**
	 * @param array<string, mixed> $data Raw settings.
	 * @return array{agency_name: string, client_name: string, note: string, preset: string}
	 */
	private function normalize( array $data ): array {
		$settings = array();

		foreach ( self::LIMITS as $key => $limit ) {
			$value            = isset( $data[ $key ] ) && is_string( $data[ $key ] ) ? $data[ $key ] : '';
			$settings[ $key ] = mb_substr( trim( $value ), 0, $limit );
		}

		$preset             = isset( $data['preset'] ) && is_string( $data['preset'] ) ? $data['preset'] : '';
		$settings['preset'] = in_array( $preset, Catalog::PRESETS, true ) ? $preset : Catalog::DEFAULT_PRESET;

		return $settings;
	}
}
