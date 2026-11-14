import { __ } from '@wordpress/i18n';
import { Button, SelectControl } from '@wordpress/components';
import { exportUrl } from '../utils';

export default function Toolbar( {
	environments,
	environment,
	detected,
	running,
	exportBase,
	onEnvironmentChange,
	onRun,
} ) {
	const labels = {
		local: __( 'Local', 'golive-audit' ),
		development: __( 'Development', 'golive-audit' ),
		staging: __( 'Staging', 'golive-audit' ),
		production: __( 'Production', 'golive-audit' ),
	};

	const options = environments.map( ( env ) => ( {
		value: env,
		label:
			env === detected
				? `${ labels[ env ] } ${ __( '(detected)', 'golive-audit' ) }`
				: labels[ env ],
	} ) );

	return (
		<div className="golive-audit-toolbar">
			<SelectControl
				__nextHasNoMarginBottom
				label={ __( 'Audit as environment', 'golive-audit' ) }
				value={ environment }
				options={ options }
				onChange={ onEnvironmentChange }
				disabled={ running }
			/>
			<Button
				variant="primary"
				onClick={ onRun }
				isBusy={ running }
				disabled={ running }
			>
				{ running
					? __( 'Running…', 'golive-audit' )
					: __( 'Run audit again', 'golive-audit' ) }
			</Button>
			<span className="golive-audit-export">
				<Button
					variant="secondary"
					href={ exportUrl( exportBase, 'html', environment ) }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Printable report', 'golive-audit' ) }
				</Button>
				<Button
					variant="secondary"
					href={ exportUrl( exportBase, 'md', environment ) }
				>
					{ __( 'Download Markdown', 'golive-audit' ) }
				</Button>
				<Button
					variant="secondary"
					href={ exportUrl( exportBase, 'csv', environment ) }
				>
					{ __( 'Download CSV', 'golive-audit' ) }
				</Button>
				<Button
					variant="secondary"
					href={ exportUrl( exportBase, 'json', environment ) }
				>
					{ __( 'Download JSON', 'golive-audit' ) }
				</Button>
			</span>
		</div>
	);
}
