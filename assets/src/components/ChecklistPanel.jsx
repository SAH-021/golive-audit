import { __, sprintf } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { groupChecklist } from '../utils';

export default function ChecklistPanel( { rows, progress, onChange } ) {
	const options = [
		{ value: 'todo', label: __( 'To do', 'golive-audit' ) },
		{ value: 'done', label: __( 'Done', 'golive-audit' ) },
		{ value: 'na', label: __( 'Not applicable', 'golive-audit' ) },
	];

	return (
		<div className="golive-audit-checklist">
			<p className="golive-audit-progress">
				{ sprintf(
					/* translators: 1: items done, 2: items not applicable, 3: total items. */
					__(
						'%1$d done, %2$d not applicable, of %3$d items.',
						'golive-audit'
					),
					progress.done,
					progress.na,
					progress.total
				) }
			</p>
			<p className="description">
				{ __(
					'These items cannot be verified automatically. They appear in the exported report.',
					'golive-audit'
				) }
			</p>
			{ groupChecklist( rows ).map( ( group ) => (
				<fieldset key={ group.group } className="golive-audit-group">
					<legend>{ group.label }</legend>
					{ group.rows.map( ( row ) => (
						<div key={ row.id } className="golive-audit-item">
							<SelectControl
								__nextHasNoMarginBottom
								label={ row.label }
								value={ row.state }
								options={ options }
								onChange={ ( state ) =>
									onChange( row.id, state )
								}
							/>
						</div>
					) ) }
				</fieldset>
			) ) }
		</div>
	);
}
