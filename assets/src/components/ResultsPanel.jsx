import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	Modal,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { effectiveStatus, filterResults, safeUrl, sortResults } from '../utils';

const STATUS_LABELS = () => ( {
	pass: __( 'Passed', 'golive-audit' ),
	fail: __( 'Failed', 'golive-audit' ),
	waived: __( 'Accepted risk', 'golive-audit' ),
	error: __( 'Could not run', 'golive-audit' ),
	skipped: __( 'Not applicable', 'golive-audit' ),
} );

const SEVERITY_LABELS = () => ( {
	critical: __( 'Critical', 'golive-audit' ),
	warning: __( 'Warning', 'golive-audit' ),
	low: __( 'Low', 'golive-audit' ),
} );

function WaiverModal( { result, onSave, onClose } ) {
	const [ reason, setReason ] = useState( '' );
	const [ saving, setSaving ] = useState( false );

	const save = async () => {
		setSaving( true );
		try {
			await onSave( result.id, reason );
			onClose();
		} finally {
			setSaving( false );
		}
	};

	return (
		<Modal
			title={ sprintf(
				/* translators: %s: check title. */
				__( 'Accept risk: %s', 'golive-audit' ),
				result.title
			) }
			onRequestClose={ onClose }
		>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Why is this acceptable?', 'golive-audit' ) }
				help={ __(
					'The reason appears in the exported report.',
					'golive-audit'
				) }
				value={ reason }
				onChange={ setReason }
				rows={ 4 }
				maxLength={ 500 }
			/>
			<div className="golive-audit-modal-actions">
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ saving }
					disabled={ saving || reason.trim() === '' }
				>
					{ __( 'Accept risk', 'golive-audit' ) }
				</Button>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'golive-audit' ) }
				</Button>
			</div>
		</Modal>
	);
}

export default function ResultsPanel( {
	results,
	categories,
	onWaive,
	onRemoveWaiver,
} ) {
	const [ filters, setFilters ] = useState( {
		severity: 'all',
		status: 'all',
		category: 'all',
		search: '',
	} );
	const [ waiving, setWaiving ] = useState( null );

	const statusLabels = STATUS_LABELS();
	const severityLabels = SEVERITY_LABELS();
	const visible = sortResults( filterResults( results, filters ) );
	const setFilter = ( key ) => ( value ) =>
		setFilters( ( current ) => ( { ...current, [ key ]: value } ) );

	return (
		<div className="golive-audit-results">
			<div className="golive-audit-filters">
				<TextControl
					__nextHasNoMarginBottom
					type="search"
					label={ __( 'Search checks', 'golive-audit' ) }
					value={ filters.search }
					onChange={ setFilter( 'search' ) }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Status', 'golive-audit' ) }
					value={ filters.status }
					onChange={ setFilter( 'status' ) }
					options={ [
						{ value: 'all', label: __( 'All', 'golive-audit' ) },
						{ value: 'fail', label: statusLabels.fail },
						{ value: 'waived', label: statusLabels.waived },
						{ value: 'error', label: statusLabels.error },
						{ value: 'pass', label: statusLabels.pass },
						{ value: 'skipped', label: statusLabels.skipped },
					] }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Severity', 'golive-audit' ) }
					value={ filters.severity }
					onChange={ setFilter( 'severity' ) }
					options={ [
						{ value: 'all', label: __( 'All', 'golive-audit' ) },
						...Object.entries( severityLabels ).map(
							( [ value, label ] ) => ( { value, label } )
						),
					] }
				/>
				<SelectControl
					__nextHasNoMarginBottom
					label={ __( 'Category', 'golive-audit' ) }
					value={ filters.category }
					onChange={ setFilter( 'category' ) }
					options={ [
						{ value: 'all', label: __( 'All', 'golive-audit' ) },
						...Object.entries( categories ).map(
							( [ value, label ] ) => ( { value, label } )
						),
					] }
				/>
			</div>

			{ visible.length === 0 ? (
				<p className="golive-audit-empty">
					{ __( 'No checks match these filters.', 'golive-audit' ) }
				</p>
			) : (
				<div className="golive-audit-table-wrap">
					<table className="widefat striped golive-audit-table">
						<caption className="screen-reader-text">
							{ __( 'Audit results', 'golive-audit' ) }
						</caption>
						<thead>
							<tr>
								<th scope="col">
									{ __( 'Status', 'golive-audit' ) }
								</th>
								<th scope="col">
									{ __( 'Severity', 'golive-audit' ) }
								</th>
								<th scope="col">
									{ __( 'Check', 'golive-audit' ) }
								</th>
								<th scope="col">
									{ __( 'Actions', 'golive-audit' ) }
								</th>
							</tr>
						</thead>
						<tbody>
							{ visible.map( ( result ) => {
								const status = effectiveStatus( result );
								const fixUrl = safeUrl( result.fix_url );

								return (
									<tr
										key={ result.id }
										className={ `is-${ status }` }
									>
										<td>
											<span
												className={ `golive-audit-badge is-${ status }` }
											>
												{ statusLabels[ status ] }
											</span>
										</td>
										<td>
											{
												severityLabels[
													result.severity
												]
											}
										</td>
										<td>
											<strong>{ result.title }</strong>{ ' ' }
											<span className="golive-audit-category">
												{ categories[
													result.category
												] || result.category }
											</span>
											<p>{ result.summary }</p>
											{ status === 'fail' &&
												result.recommendation && (
													<p className="golive-audit-recommendation">
														{
															result.recommendation
														}
													</p>
												) }
											{ result.waived && (
												<p className="golive-audit-recommendation">
													{ sprintf(
														/* translators: %s: reason the risk was accepted. */
														__(
															'Accepted because: %s',
															'golive-audit'
														),
														result.waive_reason
													) }
												</p>
											) }
										</td>
										<td className="golive-audit-actions">
											{ status === 'fail' && fixUrl && (
												<Button
													variant="link"
													href={ fixUrl }
													aria-label={ sprintf(
														/* translators: %s: check title. */
														__(
															'Fix: %s',
															'golive-audit'
														),
														result.title
													) }
												>
													{ __(
														'Fix',
														'golive-audit'
													) }
												</Button>
											) }
											{ status === 'fail' && (
												<Button
													variant="tertiary"
													onClick={ () =>
														setWaiving( result )
													}
													aria-label={ sprintf(
														/* translators: %s: check title. */
														__(
															'Accept risk: %s',
															'golive-audit'
														),
														result.title
													) }
												>
													{ __(
														'Accept risk',
														'golive-audit'
													) }
												</Button>
											) }
											{ status === 'waived' && (
												<Button
													variant="tertiary"
													onClick={ () =>
														onRemoveWaiver(
															result.id
														)
													}
													aria-label={ sprintf(
														/* translators: %s: check title. */
														__(
															'Remove waiver: %s',
															'golive-audit'
														),
														result.title
													) }
												>
													{ __(
														'Remove waiver',
														'golive-audit'
													) }
												</Button>
											) }
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				</div>
			) }

			{ waiving && (
				<WaiverModal
					result={ waiving }
					onSave={ onWaive }
					onClose={ () => setWaiving( null ) }
				/>
			) }
		</div>
	);
}
