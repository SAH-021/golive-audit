<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Audit\CheckRegistry;
use GoliveAudit\Tests\Support\FakeCheck;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CheckRegistryTest extends TestCase {

	public function test_keeps_registration_order(): void {
		$registry = new CheckRegistry();
		$registry->register( new FakeCheck( 'b.second' ) );
		$registry->register( new FakeCheck( 'a.first' ) );

		$ids = array_map(
			static function ( $check ) {
				return $check->id();
			},
			$registry->all()
		);

		$this->assertSame( array( 'b.second', 'a.first' ), $ids );
	}

	public function test_get_returns_the_check_or_null(): void {
		$registry = new CheckRegistry();
		$check    = new FakeCheck( 'seo.thing' );
		$registry->register( $check );

		$this->assertSame( $check, $registry->get( 'seo.thing' ) );
		$this->assertNull( $registry->get( 'seo.missing' ) );
	}

	public function test_rejects_duplicate_ids(): void {
		$registry = new CheckRegistry();
		$registry->register( new FakeCheck( 'seo.thing' ) );

		$this->expectException( InvalidArgumentException::class );
		$registry->register( new FakeCheck( 'seo.thing' ) );
	}

	/**
	 * @dataProvider invalid_ids
	 */
	public function test_rejects_malformed_ids( string $id ): void {
		$this->expectException( InvalidArgumentException::class );
		( new CheckRegistry() )->register( new FakeCheck( $id ) );
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function invalid_ids(): array {
		return array(
			'no dot'     => array( 'nodot' ),
			'uppercase'  => array( 'SEO.Thing' ),
			'empty part' => array( 'seo.' ),
			'spaces'     => array( 'seo.my thing' ),
		);
	}
}
