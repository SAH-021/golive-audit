import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	SelectControl,
	TextControl,
	TextareaControl,
} from '@wordpress/components';

export default function SettingsPanel( { settings, presets, onSave } ) {
	const [ form, setForm ] = useState( settings );
	const [ saving, setSaving ] = useState( false );
	const [ saved, setSaved ] = useState( false );

	const update = ( key ) => ( value ) => {
		setSaved( false );
		setForm( ( current ) => ( { ...current, [ key ]: value } ) );
	};

	const save = async () => {
		setSaving( true );
		try {
			await onSave( form );
			setSaved( true );
		} finally {
			setSaving( false );
		}
	};

	return (
		<div className="golive-audit-settings">
			<p className="description">
				{ __(
					'These details appear on the printable report and in the JSON export.',
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
				options={ Object.entries( presets ).map(
					( [ value, label ] ) => ( {
						value,
						label,
					} )
				) }
				onChange={ update( 'preset' ) }
			/>
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
			</div>
		</div>
	);
}
