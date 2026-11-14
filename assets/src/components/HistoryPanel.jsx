import { useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';
import {
	formatDateTime,
	isRtlLayout,
	localizeDigits,
	runDelta,
	trendPoints,
} from '../utils';

const WIDTH = 640;
const HEIGHT = 160;

function TrendChart( { history } ) {
	const rtl = isRtlLayout();
	const points = trendPoints( history, WIDTH, HEIGHT ).map( ( p ) =>
		rtl ? { ...p, x: WIDTH - p.x } : p
	);
	const line = points.map( ( p ) => `${ p.x },${ p.y }` ).join( ' ' );
	const first = history[ 0 ].score;
	const last = history[ history.length - 1 ].score;

	return (
		<svg
			className="golive-audit-trend"
			viewBox={ `0 0 ${ WIDTH } ${ HEIGHT }` }
			role="img"
			aria-label={ sprintf(
				/* translators: 1: number of audits, 2: first score, 3: latest score. */
				__(
					'Readiness score over %1$d audits, from %2$d to %3$d.',
					'golive-audit'
				),
				history.length,
				first,
				last
			) }
		>
			{ [ 0, 50, 100 ].map( ( value ) => {
				const y = 12 + ( HEIGHT - 24 ) * ( 1 - value / 100 );

				return (
					<g key={ value }>
						<line
							className="golive-audit-trend-grid"
							x1="12"
							x2={ WIDTH - 12 }
							y1={ y }
							y2={ y }
						/>
						<text
							className="golive-audit-trend-label"
							x={ rtl ? 12 : WIDTH - 12 }
							y={ y - 3 }
							textAnchor={ rtl ? 'start' : 'end' }
						>
							{ localizeDigits( value ) }
						</text>
					</g>
				);
			} ) }
			<polyline className="golive-audit-trend-line" points={ line } />
			{ points.map( ( p ) => (
				<circle
					key={ p.entry.t }
					className={ `golive-audit-trend-dot is-${ p.entry.verdict }` }
					cx={ p.x }
					cy={ p.y }
					r="4"
				/>
			) ) }
		</svg>
	);
}

function ClearModal( { onConfirm, onClose } ) {
	const [ busy, setBusy ] = useState( false );

	const confirm = async () => {
		setBusy( true );
		try {
			await onConfirm();
			onClose();
		} finally {
			setBusy( false );
		}
	};

	return (
		<Modal
			title={ __( 'Clear audit history?', 'golive-audit' ) }
			onRequestClose={ onClose }
		>
			<p>
				{ __(
					'The recorded runs and the trend chart are deleted. Accepted risks and checklist progress are kept.',
					'golive-audit'
				) }
			</p>
			<div className="golive-audit-modal-actions">
				<Button
					variant="primary"
					isDestructive
					onClick={ confirm }
					isBusy={ busy }
					disabled={ busy }
				>
					{ __( 'Clear history', 'golive-audit' ) }
				</Button>
				<Button variant="tertiary" onClick={ onClose }>
					{ __( 'Cancel', 'golive-audit' ) }
				</Button>
			</div>
		</Modal>
	);
}

export default function HistoryPanel( {
	history,
	checks,
	environmentLabels,
	verdictLabels,
	onClear,
} ) {
	const [ clearing, setClearing ] = useState( false );

	if ( history.length === 0 ) {
		return (
			<p className="golive-audit-empty">
				{ __(
					'No audits recorded yet. Every audit you run from this screen is recorded here.',
					'golive-audit'
				) }
			</p>
		);
	}

	const titles = Object.fromEntries( checks.map( ( c ) => [ c.id, c.title ] ) );
	const titleOf = ( id ) => titles[ id ] || id;
	const delta = runDelta( history );
	const rows = [ ...history ].reverse();

	return (
		<div className="golive-audit-history">
			{ history.length > 1 && <TrendChart history={ history } /> }

			{ delta && (
				<div className="golive-audit-changes">
					<h3>{ __( 'Since the previous audit', 'golive-audit' ) }</h3>
					{ delta.newFailures.length === 0 &&
						delta.fixed.length === 0 && (
							<p>
								{ __(
									'No problems appeared or were fixed.',
									'golive-audit'
								) }
							</p>
						) }
					{ delta.newFailures.length > 0 && (
						<>
							<p className="golive-audit-changes-new">
								{ localizeDigits(
									sprintf(
										/* translators: %d: number of new problems. */
										_n(
											'%d new problem',
											'%d new problems',
											delta.newFailures.length,
											'golive-audit'
										),
										delta.newFailures.length
									)
								) }
							</p>
							<ul>
								{ delta.newFailures.map( ( id ) => (
									<li key={ id }>{ titleOf( id ) }</li>
								) ) }
							</ul>
						</>
					) }
					{ delta.fixed.length > 0 && (
						<>
							<p className="golive-audit-changes-fixed">
								{ localizeDigits(
									sprintf(
										/* translators: %d: number of fixed problems. */
										_n(
											'%d problem fixed',
											'%d problems fixed',
											delta.fixed.length,
											'golive-audit'
										),
										delta.fixed.length
									)
								) }
							</p>
							<ul>
								{ delta.fixed.map( ( id ) => (
									<li key={ id }>{ titleOf( id ) }</li>
								) ) }
							</ul>
						</>
					) }
				</div>
			) }

			<div className="golive-audit-table-wrap">
				<table className="widefat striped golive-audit-table">
					<caption className="screen-reader-text">
						{ __( 'Audit history', 'golive-audit' ) }
					</caption>
					<thead>
						<tr>
							<th scope="col">{ __( 'Date', 'golive-audit' ) }</th>
							<th scope="col">
								{ __( 'Environment', 'golive-audit' ) }
							</th>
							<th scope="col">{ __( 'Score', 'golive-audit' ) }</th>
							<th scope="col">
								{ __( 'Verdict', 'golive-audit' ) }
							</th>
							<th scope="col">
								{ __( 'Open problems', 'golive-audit' ) }
							</th>
						</tr>
					</thead>
					<tbody>
						{ rows.map( ( entry ) => (
							<tr key={ entry.t }>
								<td>{ formatDateTime( entry.t ) }</td>
								<td>
									{ environmentLabels[ entry.env ] ||
										entry.env }
								</td>
								<td>{ localizeDigits( entry.score ) }</td>
								<td>
									{ verdictLabels[ entry.verdict ] ||
										entry.verdict }
								</td>
								<td>{ localizeDigits( entry.failed.length ) }</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</div>

			<div className="golive-audit-settings-actions">
				<Button variant="tertiary" isDestructive onClick={ () => setClearing( true ) }>
					{ __( 'Clear history', 'golive-audit' ) }
				</Button>
			</div>

			{ clearing && (
				<ClearModal
					onConfirm={ onClear }
					onClose={ () => setClearing( false ) }
				/>
			) }
		</div>
	);
}
