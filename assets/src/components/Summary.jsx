import { __, sprintf } from '@wordpress/i18n';
import { localizeDigits } from '../utils';

export default function Summary( { summary, delta } ) {
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
					<strong>{ localizeDigits( summary.score ) }</strong>
					<span aria-hidden="true">/{ localizeDigits( 100 ) }</span>
				</p>
				<p className="golive-audit-verdict-label">
					{ verdicts[ summary.verdict ] }
				</p>
				{ delta && delta.delta !== 0 && (
					<p
						className={ `golive-audit-delta ${
							delta.delta > 0 ? 'is-up' : 'is-down'
						}` }
					>
						{ localizeDigits(
							sprintf(
								/* translators: %s: signed change in the readiness score, e.g. +5 or -3. */
								__( '%s since the previous audit', 'golive-audit' ),
								`${ delta.delta > 0 ? '+' : '' }${ delta.delta }`
							)
						) }
					</p>
				) }
			</div>
			<ul className="golive-audit-counts">
				{ counts.map( ( [ key, label ] ) => (
					<li
						key={ key }
						className={
							summary[ key ] > 0 ? `has-${ key }` : undefined
						}
					>
						<strong>{ localizeDigits( summary[ key ] ) }</strong>{ ' ' }
						{ label }
					</li>
				) ) }
			</ul>
		</section>
	);
}
