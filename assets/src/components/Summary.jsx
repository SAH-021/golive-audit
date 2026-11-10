import { __ } from '@wordpress/i18n';

export default function Summary( { summary } ) {
	const verdicts = {
		ready: __( 'Ready to launch', 'golive-audit' ),
		attention: __( 'Needs attention', 'golive-audit' ),
		blocked: __( 'Launch blocked', 'golive-audit' ),
	};

	const counts = [
		[ 'blockers', __( 'Blockers', 'golive-audit' ) ],
		[ 'fail', __( 'Failed', 'golive-audit' ) ],
		[ 'waived', __( 'Accepted risks', 'golive-audit' ) ],
		[ 'pass', __( 'Passed', 'golive-audit' ) ],
		[ 'skipped', __( 'Not applicable', 'golive-audit' ) ],
		[ 'error', __( 'Could not run', 'golive-audit' ) ],
	];

	return (
		<section className="golive-audit-summary">
			<h2 className="screen-reader-text">
				{ __( 'Summary', 'golive-audit' ) }
			</h2>
			<div className={ `golive-audit-verdict is-${ summary.verdict }` }>
				<p className="golive-audit-score">
					<span className="screen-reader-text">
						{ __( 'Readiness score:', 'golive-audit' ) }
					</span>
					<strong>{ summary.score }</strong>
					<span aria-hidden="true">/100</span>
				</p>
				<p className="golive-audit-verdict-label">
					{ verdicts[ summary.verdict ] }
				</p>
			</div>
			<ul className="golive-audit-counts">
				{ counts.map( ( [ key, label ] ) => (
					<li
						key={ key }
						className={
							summary[ key ] > 0 ? `has-${ key }` : undefined
						}
					>
						<strong>{ summary[ key ] }</strong> { label }
					</li>
				) ) }
			</ul>
		</section>
	);
}
