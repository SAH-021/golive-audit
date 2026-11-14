<?php
/**
 * Plugin language selection: follow the site, or force English or Persian.
 *
 * The mapping is pure so it can be unit tested; the WordPress hooks that use it
 * live in the Plugin class.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Support;

final class Language {

	/**
	 * Locale forced by the "language" setting, or an empty string to follow the site.
	 *
	 * @param string $setting One of "auto", "en" or "fa".
	 */
	public static function override_locale( string $setting ): string {
		switch ( $setting ) {
			case 'en':
				return 'en_US';
			case 'fa':
				return 'fa_IR';
			default:
				return '';
		}
	}

	/**
	 * Whether a locale such as "fa_IR" or "fa" is Persian.
	 */
	public static function is_persian( string $locale ): bool {
		return 0 === strpos( strtolower( $locale ), 'fa' );
	}

	/**
	 * Whether a locale is written right to left.
	 */
	public static function is_rtl_locale( string $locale ): bool {
		$language = strtolower( (string) strtok( $locale, '_-' ) );

		return in_array( $language, array( 'fa', 'ar', 'he', 'ur', 'ps', 'ckb', 'sd', 'ug', 'yi', 'dv' ), true );
	}
}
