<?php
/**
 * Progress on the manual handoff checklist.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Checklist;

use GoliveAudit\Storage\OptionStore;
use InvalidArgumentException;

final class ChecklistStore {

	public const OPTION = 'golive_audit_checklist';

	public const STATE_TODO = 'todo';
	public const STATE_DONE = 'done';
	public const STATE_NA   = 'na';
	public const STATES     = array( self::STATE_TODO, self::STATE_DONE, self::STATE_NA );

	private OptionStore $store;

	public function __construct( OptionStore $store ) {
		$this->store = $store;
	}

	/**
	 * @return array<string, string> State keyed by item ID; items never touched are absent.
	 */
	public function states(): array {
		$stored = $this->store->get( self::OPTION, array() );
		$clean  = array();

		if ( ! is_array( $stored ) ) {
			return $clean;
		}

		foreach ( $stored as $id => $state ) {
			if ( is_string( $id ) && is_string( $state ) && in_array( $state, self::STATES, true ) ) {
				$clean[ $id ] = $state;
			}
		}

		return $clean;
	}

	/**
	 * @throws InvalidArgumentException For an unknown item or state.
	 */
	public function set( string $item_id, string $state ): void {
		if ( ! Catalog::has_item( $item_id ) ) {
			throw new InvalidArgumentException( 'Unknown checklist item.' );
		}

		if ( ! in_array( $state, self::STATES, true ) ) {
			throw new InvalidArgumentException( 'Unknown checklist state.' );
		}

		$states             = $this->states();
		$states[ $item_id ] = $state;

		$this->store->update( self::OPTION, $states );
	}

	/**
	 * Items of a preset with their current state and group label.
	 *
	 * @return array<int, array{id: string, group: string, group_label: string, label: string, state: string}>
	 */
	public function rows( string $preset ): array {
		$states = $this->states();
		$groups = Catalog::group_labels();
		$rows   = array();

		foreach ( Catalog::items( $preset ) as $item ) {
			$rows[] = array(
				'id'          => $item['id'],
				'group'       => $item['group'],
				'group_label' => $groups[ $item['group'] ] ?? $item['group'],
				'label'       => $item['label'],
				'state'       => $states[ $item['id'] ] ?? self::STATE_TODO,
			);
		}

		return $rows;
	}

	/**
	 * @return array{total: int, done: int, na: int, todo: int, complete: bool}
	 */
	public function progress( string $preset ): array {
		$progress = array(
			'total' => 0,
			'done'  => 0,
			'na'    => 0,
			'todo'  => 0,
		);

		foreach ( $this->rows( $preset ) as $row ) {
			++$progress['total'];
			++$progress[ $row['state'] ];
		}

		$progress['complete'] = 0 === $progress['todo'];

		return $progress;
	}
}
