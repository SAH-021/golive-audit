<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Support\Jalali;
use GoliveAudit\Support\Language;
use PHPUnit\Framework\TestCase;

final class JalaliTest extends TestCase {

	/**
	 * @dataProvider known_dates
	 */
	public function test_gregorian_to_jalali( int $gy, int $gm, int $gd, array $expected ): void {
		$this->assertSame( $expected, Jalali::from_gregorian( $gy, $gm, $gd ) );
	}

	/**
	 * @return array<string, array{0: int, 1: int, 2: int, 3: array<int, int>}>
	 */
	public function known_dates(): array {
		return array(
			'nowruz 1405'           => array( 2026, 3, 21, array( 1405, 1, 1 ) ),
			'aban 21 1405'          => array( 2026, 11, 12, array( 1405, 8, 21 ) ),
			'last day of 1403'      => array( 2025, 3, 20, array( 1403, 12, 30 ) ),
			'first day of 1404'     => array( 2025, 3, 21, array( 1404, 1, 1 ) ),
			'unix epoch'            => array( 1970, 1, 1, array( 1348, 10, 11 ) ),
			'islamic revolution'    => array( 1979, 2, 11, array( 1357, 11, 22 ) ),
		);
	}

	public function test_format_uses_persian_digits_and_month_names(): void {
		// 2026-11-12 10:00 UTC with a +03:30 offset is 13:30 in Tehran.
		$timestamp = gmmktime( 10, 0, 0, 11, 12, 2026 );

		$this->assertSame( '۲۱ آبان ۱۴۰۵، ۱۳:۳۰', Jalali::format( $timestamp, 12600 ) );
	}

	public function test_persian_digits(): void {
		$this->assertSame( '۰۱۲۳۴۵۶۷۸۹', Jalali::persian_digits( '0123456789' ) );
	}

	public function test_language_overrides(): void {
		$this->assertSame( '', Language::override_locale( 'auto' ) );
		$this->assertSame( 'en_US', Language::override_locale( 'en' ) );
		$this->assertSame( 'fa_IR', Language::override_locale( 'fa' ) );
		$this->assertSame( '', Language::override_locale( 'xx' ) );
	}

	public function test_language_detection(): void {
		$this->assertTrue( Language::is_persian( 'fa_IR' ) );
		$this->assertFalse( Language::is_persian( 'en_US' ) );
		$this->assertTrue( Language::is_rtl_locale( 'fa_IR' ) );
		$this->assertTrue( Language::is_rtl_locale( 'ar' ) );
		$this->assertFalse( Language::is_rtl_locale( 'de_DE' ) );
	}
}
