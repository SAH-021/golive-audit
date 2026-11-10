<?php

namespace GoliveAudit\Tests\Unit;

use GoliveAudit\Checklist\Catalog;
use GoliveAudit\Checklist\ChecklistStore;
use GoliveAudit\Storage\SettingsStore;
use GoliveAudit\Storage\WaiverStore;
use GoliveAudit\Tests\Support\MemoryOptionStore;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class StoresTest extends TestCase {

	public function test_waiver_round_trip(): void {
		$store   = new MemoryOptionStore();
		$waivers = new WaiverStore( $store );

		$waivers->set( 'seo.sitemap', '  Client uses an external sitemap  ', 1000 );

		$this->assertSame( array( 'seo.sitemap' => 'Client uses an external sitemap' ), $waivers->reasons() );
		$this->assertSame( 1000, $waivers->all()['seo.sitemap']['waived_at'] );

		$waivers->remove( 'seo.sitemap' );
		$this->assertSame( array(), $waivers->all() );
	}

	public function test_waiver_requires_a_reason(): void {
		$this->expectException( InvalidArgumentException::class );
		( new WaiverStore( new MemoryOptionStore() ) )->set( 'seo.sitemap', '   ' );
	}

	public function test_waiver_reason_is_capped_and_multibyte_safe(): void {
		$waivers = new WaiverStore( new MemoryOptionStore() );
		$waivers->set( 'seo.sitemap', str_repeat( 'پ', 800 ) );

		$this->assertSame( WaiverStore::MAX_REASON, mb_strlen( $waivers->reasons()['seo.sitemap'] ) );
	}

	public function test_corrupted_waiver_data_is_ignored(): void {
		$store       = new MemoryOptionStore();
		$store->data = array(
			WaiverStore::OPTION => array(
				'ok.id'   => array( 'reason' => 'fine' ),
				'bad.one' => 'oops',
				5         => array( 'reason' => 'x' ),
			),
		);

		$this->assertSame( array( 'ok.id' => 'fine' ), ( new WaiverStore( $store ) )->reasons() );

		$store->data = array( WaiverStore::OPTION => 'garbage' );
		$this->assertSame( array(), ( new WaiverStore( $store ) )->all() );
	}

	public function test_settings_defaults_and_whitelisting(): void {
		$settings = new SettingsStore( new MemoryOptionStore() );

		$this->assertSame( Catalog::DEFAULT_PRESET, $settings->get()['preset'] );

		$updated = $settings->update(
			array(
				'agency_name' => '  Acme  ',
				'preset'      => 'shop',
				'evil'        => 'x',
			)
		);

		$this->assertSame( 'Acme', $updated['agency_name'] );
		$this->assertSame( 'shop', $updated['preset'] );
		$this->assertArrayNotHasKey( 'evil', $updated );
	}

	public function test_settings_reject_unknown_presets_and_cap_lengths(): void {
		$settings = new SettingsStore( new MemoryOptionStore() );
		$updated  = $settings->update(
			array(
				'preset' => 'nope',
				'note'   => str_repeat( 'a', 5000 ),
			)
		);

		$this->assertSame( Catalog::DEFAULT_PRESET, $updated['preset'] );
		$this->assertSame( 1000, strlen( $updated['note'] ) );
	}

	public function test_settings_updates_merge_instead_of_replacing(): void {
		$settings = new SettingsStore( new MemoryOptionStore() );
		$settings->update( array( 'agency_name' => 'Acme' ) );
		$settings->update( array( 'client_name' => 'Client' ) );

		$this->assertSame( 'Acme', $settings->get()['agency_name'] );
		$this->assertSame( 'Client', $settings->get()['client_name'] );
	}

	public function test_checklist_state_progress_and_presets(): void {
		$checklist = new ChecklistStore( new MemoryOptionStore() );
		$business  = $checklist->progress( 'business' );

		$this->assertSame( $business['total'], $business['todo'] );
		$this->assertFalse( $business['complete'] );

		$checklist->set( 'forms_tested', ChecklistStore::STATE_DONE );
		$checklist->set( 'redirects_from_old_site', ChecklistStore::STATE_NA );

		$progress = $checklist->progress( 'business' );
		$this->assertSame( 1, $progress['done'] );
		$this->assertSame( 1, $progress['na'] );
		$this->assertGreaterThan( $business['total'], $checklist->progress( 'shop' )['total'] );
	}

	public function test_checklist_state_survives_switching_presets(): void {
		$checklist = new ChecklistStore( new MemoryOptionStore() );
		$checklist->set( 'shop_payments_live', ChecklistStore::STATE_DONE );

		$ids = array_column( $checklist->rows( 'business' ), 'id' );
		$this->assertNotContains( 'shop_payments_live', $ids );

		$shop = array_column( $checklist->rows( 'shop' ), 'state', 'id' );
		$this->assertSame( 'done', $shop['shop_payments_live'] );
	}

	public function test_checklist_rejects_unknown_items_and_states(): void {
		$checklist = new ChecklistStore( new MemoryOptionStore() );

		try {
			$checklist->set( 'nonsense', 'done' );
			$this->fail( 'Unknown item accepted.' );
		} catch ( InvalidArgumentException $e ) {
			$this->assertTrue( true );
		}

		$this->expectException( InvalidArgumentException::class );
		$checklist->set( 'forms_tested', 'maybe' );
	}

	public function test_checklist_item_ids_are_unique_within_a_preset_and_rest_safe(): void {
		foreach ( Catalog::PRESETS as $preset ) {
			$ids = array_column( Catalog::items( $preset ), 'id' );

			$this->assertSame( $ids, array_values( array_unique( $ids ) ), "$preset has duplicate item IDs" );

			foreach ( Catalog::items( $preset ) as $item ) {
				$this->assertMatchesRegularExpression( '/^[a-z0-9_]+$/', $item['id'] );
				$this->assertArrayHasKey( $item['group'], Catalog::group_labels() );
				$this->assertNotSame( '', $item['label'] );
			}
		}
	}
}
