<?php
/**
 * Test bootstrap. The engine is WordPress-free, so only the two translation and
 * escaping helpers it calls are stubbed here.
 */

if ( ! function_exists( '__' ) ) {
	function __( $text ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $flags = 0 ) {
		return json_encode( $data, $flags ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test stand-in for wp_json_encode().
	}
}

if ( ! function_exists( 'wp_get_inline_script_tag' ) ) {
	function wp_get_inline_script_tag( $js, $attributes = array() ) {
		$attr = '';
		foreach ( $attributes as $name => $value ) {
			$attr .= ' ' . $name . '="' . htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' ) . '"';
		}
		return "<script{$attr}>{$js}</script>";
	}
}

$autoload = dirname( __DIR__ ) . '/vendor/autoload.php';

if ( is_readable( $autoload ) ) {
	require $autoload;
	return;
}

spl_autoload_register(
	static function ( $class_name ) {
		$map = array(
			'GoliveAudit\\Tests\\' => __DIR__ . '/',
			'GoliveAudit\\'        => dirname( __DIR__ ) . '/src/',
		);

		foreach ( $map as $prefix => $base ) {
			if ( 0 === strpos( $class_name, $prefix ) ) {
				$file = $base . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
				return;
			}
		}
	}
);
