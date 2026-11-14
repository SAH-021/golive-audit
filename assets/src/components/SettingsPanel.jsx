import { useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { formatDateTime, localizeDigits } from '../utils';

const toOptions = ( map ) =>
	Object.entries( map ).map( ( [ value, label ] ) => ( { value, label } ) );

export default function SettingsPanel( {
	settings,
	presets,
	languages,
	schedules,
	nextRun,
	onSave,
} ) {
	const [ form, setForm ] = useState( settings );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ needsReload, setNeedsReload ] = useState( false );
	const savedLanguage = useRef( settings.language );

	const update = ( key ) => ( value ) => {
		setSaved( false );
		setForm( ( current ) => ( { ...current, [ key ]: value } ) );
	};

	const save = async () => {
		setSaving( true );
		try {
			await onSave( form );
			setSaved( true );
			if ( form.language !== savedLanguage.current ) {
				savedLanguage.current = form.language;
				setNeedsReload( true );
			}
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="golive-audit-settings">
			<p className="description">
				{ __(
					'These details appear on the printable report and in the Markdown and JSON exports.',
					'golive-audit'
				) }
			</p>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Agency or author name', 'golive-audit' ) }
				value={ form.agency_name }
				onChange={ update( 'agency_name' ) }
				maxLength={ 100 }
			/>
			<TextControl
				__nextHasNoMarginBottom
				label={ __( 'Client name', 'golive-audit' ) }
				value={ form.client_name }
				onChange={ update( 'client_name' ) }
				maxLength={ 100 }
			/>
			<TextareaControl
				__nextHasNoMarginBottom
				label={ __( 'Note for the report', 'golive-audit' ) }
				value={ form.note }
				onChange={ update( 'note' ) }
				rows={ 4 }
				maxLength={ 1000 }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Handoff checklist type', 'golive-audit' ) }
				help={ __(
					'Adds items for the kind of site you are delivering. Progress is kept when you switch.',
					'golive-audit'
				) }
				value={ form.preset }
				options={ toOptions( presets ) }
				onChange={ update( 'preset' ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Language', 'golive-audit' ) }
				help={ __(
					'Used by this screen, the reports and alert emails. "Site default" follows the WordPress language.',
					'golive-audit'
				) }
				value={ form.language }
				options={ toOptions( languages ) }
				onChange={ update( 'language' ) }
			/>
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Scheduled audits', 'golive-audit' ) }
				help={
					form.schedule !== 'off' && nextRun > 0
						? sprintf(
								/* translators: %s: date and time of the next scheduled audit. */
								__( 'Next audit: %s', 'golive-audit' ),
								localizeDigits( formatDateTime( nextRun ) )
						  )
						: __(
								'Runs the audit in the background with WP-Cron and records the result in the history. An email is sent when new problems appear.',
								'golive-audit'
						  )
				}
				value={ form.schedule }
				options={ toOptions( schedules ) }
				onChange={ update( 'schedule' ) }
			/>
			{ form.schedule !== 'off' && (
				<TextControl
					__nextHasNoMarginBottom
					type="email"
					label={ __( 'Alert email address', 'golive-audit' ) }
					help={ __(
						'Leave empty to use the site administration email address.',
						'golive-audit'
					) }
					value={ form.alert_email }
					onChange={ update( 'alert_email' ) }
					maxLength={ 254 }
				/>
			) }
			<div className="golive-audit-settings-actions">
				<Button
					variant="primary"
					onClick={ save }
					isBusy={ saving }
					disabled={ saving }
				>
					{ __( 'Save settings', 'golive-audit' ) }
				</Button>
				{ saved && (
					<span role="status">
						{ __( 'Settings saved.', 'golive-audit' ) }
					</span>
				) }
				{ needsReload && (
					<Button
						variant="secondary"
						onClick={ () => window.location.reload() }
					>
						{ __( 'Reload to apply the language', 'golive-audit' ) }
					</Button>
				) }
			</div>
		</div>
	);
}
