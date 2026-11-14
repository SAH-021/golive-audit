<?php
/**
 * Minimal Gregorian to Jalali (Persian calendar) conversion for reports.
 *
 * Pure PHP with no WordPress dependency. Valid for the Gregorian years
 * 1901-2099, which is far beyond what an audit report needs.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Support;

final class Jalali {

	private const CUMULATIVE_DAYS = array( 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 );

	private const PERSIAN_DIGITS = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

	/**
	 * Converts a Gregorian date to a Jalali one.
	 *
	 * @return array{0: int, 1: int, 2: int} Year, month (1-12), day (1-31).
	 */
	public static function from_gregorian( int $gy, int $gm, int $gd ): array {
		$gy2  = $gm > 2 ? $gy + 1 : $gy;
		$days = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + self::CUMULATIVE_DAYS[ $gm - 1 ];

		$jy    = -1595 + ( 33 * intdiv( $days, 12053 ) );
		$days %= 12053;
		$jy   += 4 * intdiv( $days, 1461 );
		$days %= 1461;

		if ( $days > 365 ) {
			$jy   += intdiv( $days - 1, 365 );
			$days  = ( $days - 1 ) % 365;
		}

		if ( $days < 186 ) {
			$jm = 1 + intdiv( $days, 31 );
			$jd = 1 + ( $days % 31 );
		} else {
			$jm = 7 + intdiv( $days - 186, 30 );
			$jd = 1 + ( ( $days - 186 ) % 30 );
		}

		return array( $jy, $jm, $jd );
	}

	/**
	 * Names of the twelve Jalali months, starting with Farvardin.
	 *
	 * @return string[]
	 */
	public static function month_names(): array {
		return array( 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' );
	}

	/**
	 * Replaces Latin digits with Persian ones.
	 */
	public static function persian_digits( string $text ): string {
		return str_replace( array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ), self::PERSIAN_DIGITS, $text );
	}

	/**
	 * Formats a Unix timestamp as, for example, "۲۱ آبان ۱۴۰۵، ۱۴:۳۰".
	 *
	 * @param int $timestamp  Unix timestamp.
	 * @param int $utc_offset Site UTC offset in seconds (for example 12600 for Tehran in winter).
	 */
	public static function format( int $timestamp, int $utc_offset = 0 ): string {
		$local = $timestamp + $utc_offset;

		list( $jy, $jm, $jd ) = self::from_gregorian(
			(int) gmdate( 'Y', $local ),
			(int) gmdate( 'n', $local ),
			(int) gmdate( 'j', $local )
		);

		$months = self::month_names();
		$text   = $jd . ' ' . $months[ $jm - 1 ] . ' ' . $jy . '، ' . gmdate( 'H:i', $local );

		return self::persian_digits( $text );
	}
}
