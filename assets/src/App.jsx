import { useCallback, useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Notice, Spinner, TabPanel } from '@wordpress/components';
import * as api from './api';
import Summary from './components/Summary';
import Toolbar from './components/Toolbar';
import ResultsPanel from './components/ResultsPanel';
import ChecklistPanel from './components/ChecklistPanel';
import SettingsPanel from './components/SettingsPanel';
import HistoryPanel from './components/HistoryPanel';
import { localizeDigits, runDelta, setLocale } from './utils';

const messageOf = ( error ) =>
	( error && error.message ) || __( 'Something went wrong.', 'golive-audit' );

export default function App() {
	const [ state, setState ] = useState( null );
	const [ report, setReport ] = useState( null );
	const [ history, setHistory ] = useState( [] );
	const [ environment, setEnvironment ] = useState( '' );
	const [ running, setRunning ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ announcement, setAnnouncement ] = useState( '' );

	const run = useCallback( async ( env ) => {
		setRunning( true );
		setError( '' );
		try {
			const result = await api.runAudit( env );
			setReport( result );
			if ( Array.isArray( result.history ) ) {
				setHistory( result.history );
			}
			setAnnouncement(
				localizeDigits(
					sprintf(
						/* translators: 1: readiness score, 2: number of launch blockers. */
						__(
							'Audit finished. Readiness score %1$d, %2$d launch blockers.',
							'golive-audit'
						),
						result.summary.score,
						result.summary.blockers
					)
				)
			);
		} catch ( e ) {
			setError( messageOf( e ) );
		} finally {
			setRunning( false );
		}
	}, [] );

	useEffect( () => {
		api.getState()
			.then( ( loaded ) => {
				setLocale( loaded.locale );
				setState( loaded );
				setHistory( loaded.history || [] );
				setEnvironment( loaded.environment );
				return run( loaded.environment );
			} )
			.catch( ( e ) => setError( messageOf( e ) ) );
	}, [ run ] );

	const changeEnvironment = ( env ) => {
		setEnvironment( env );
		run( env );
	};

	const waive = async ( id, reason ) => {
		try {
			const response = await api.setWaiver( id, reason );
			setState( ( s ) => ( { ...s, waivers: response.waivers } ) );
			await run( environment );
		} catch ( e ) {
			setError( messageOf( e ) );
			throw e;
		}
	};

	const removeWaiver = async ( id ) => {
		try {
			const response = await api.removeWaiver( id );
			setState( ( s ) => ( { ...s, waivers: response.waivers } ) );
			await run( environment );
		} catch ( e ) {
			setError( messageOf( e ) );
		}
	};

	const changeChecklist = async ( id, value ) => {
		const previous = state;
		setState( ( s ) => ( {
			...s,
			checklist: s.checklist.map( ( row ) =>
				row.id === id ? { ...row, state: value } : row
			),
		} ) );
		try {
			const response = await api.setChecklistItem( id, value );
			setState( ( s ) => ( { ...s, ...response } ) );
		} catch ( e ) {
			setState( previous );
			setError( messageOf( e ) );
		}
	};

	const saveSettings = async ( settings ) => {
		try {
			const response = await api.saveSettings( settings );
			setState( ( s ) => ( { ...s, ...response } ) );
		} catch ( e ) {
			setError( messageOf( e ) );
			throw e;
		}
	};

	const clearHistory = async () => {
		try {
			const response = await api.clearHistory();
			setHistory( response.history || [] );
		} catch ( e ) {
			setError( messageOf( e ) );
			throw e;
		}
	};

	if ( ! state ) {
		return (
			<div className="golive-audit-app">
				{ error ? (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) : (
					<p>
						<Spinner /> { __( 'Loading…', 'golive-audit' ) }
					</p>
				) }
			</div>
		);
	}

	const progress = state.checklist_progress;
	const environmentLabels = {
		local: __( 'Local', 'golive-audit' ),
		development: __( 'Development', 'golive-audit' ),
		staging: __( 'Staging', 'golive-audit' ),
		production: __( 'Production', 'golive-audit' ),
	};
	const verdictLabels = {
		ready: __( 'Ready to launch', 'golive-audit' ),
		attention: __( 'Needs attention', 'golive-audit' ),
		blocked: __( 'Launch blocked', 'golive-audit' ),
	};
	const tabs = [
		{ name: 'audit', title: __( 'Audit', 'golive-audit' ) },
		{
			name: 'checklist',
			title: localizeDigits(
				sprintf(
					/* translators: 1: checklist items finished, 2: total items. */
					__( 'Handoff checklist (%1$d/%2$d)', 'golive-audit' ),
					progress.done + progress.na,
					progress.total
				)
			),
		},
		{
			name: 'history',
			title: localizeDigits(
				sprintf(
					/* translators: %d: number of recorded audits. */
					__( 'History (%d)', 'golive-audit' ),
					history.length
				)
			),
		},
		{ name: 'settings', title: __( 'Settings', 'golive-audit' ) },
	];

	return (
		<div className="golive-audit-app">
			<div
				role="status"
				aria-live="polite"
				className="screen-reader-text"
			>
				{ announcement }
			</div>
			{ error && (
				<Notice status="error" onRemove={ () => setError( '' ) }>
					{ error }
				</Notice>
			) }
			<Toolbar
				environments={ state.environments }
				environment={ environment }
				detected={ state.environment }
				running={ running }
				exportBase={ state.export_url }
				onEnvironmentChange={ changeEnvironment }
				onRun={ () => run( environment ) }
			/>
			<TabPanel className="golive-audit-tabs" tabs={ tabs }>
				{ ( tab ) => {
					if ( tab.name === 'checklist' ) {
						return (
							<ChecklistPanel
								rows={ state.checklist }
								progress={ progress }
								onChange={ changeChecklist }
							/>
						);
					}
					if ( tab.name === 'history' ) {
						return (
							<HistoryPanel
								history={ history }
								checks={ state.checks }
								environmentLabels={ environmentLabels }
								verdictLabels={ verdictLabels }
								onClear={ clearHistory }
							/>
						);
					}
					if ( tab.name === 'settings' ) {
						return (
							<SettingsPanel
								settings={ state.settings }
								presets={ state.presets }
								languages={ state.languages || {} }
								schedules={ state.schedules || {} }
								nextRun={ state.next_run || 0 }
								onSave={ saveSettings }
							/>
						);
					}
					return report ? (
						<>
							<Summary
								summary={ report.summary }
								delta={ runDelta( history ) }
							/>
							<ResultsPanel
								results={ report.results }
								categories={ state.categories }
								onWaive={ waive }
								onRemoveWaiver={ removeWaiver }
							/>
						</>
					) : (
						<p>
							<Spinner />{ ' ' }
							{ __( 'Running the audit…', 'golive-audit' ) }
						</p>
					);
				} }
			</TabPanel>
		</div>
	);
}
