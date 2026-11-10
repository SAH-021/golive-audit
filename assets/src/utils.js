/**
 * Pure helpers for the dashboard (no WordPress or React imports, so they are unit-testable).
 */

export const SEVERITY_RANK = { critical: 3, warning: 2, low: 1 };

/**
 * A failed check that has been waived is shown as "waived", not "fail".
 *
 * @param {Object} result A result from the REST API.
 * @return {string} pass | fail | waived | error | skipped
 */
export function effectiveStatus( result ) {
	return result.status === 'fail' && result.waived ? 'waived' : result.status;
}

/**
 * Lower numbers are shown first: unwaived failures by severity, then errors,
 * accepted risks, passes and skipped checks.
 *
 * @param {Object} result A result from the REST API.
 * @return {number} Sort priority.
 */
export function priority( result ) {
	const status = effectiveStatus( result );

	if ( status === 'fail' ) {
		return 10 - ( SEVERITY_RANK[ result.severity ] || 0 );
	}
	if ( status === 'error' ) {
		return 20;
	}
	if ( status === 'waived' ) {
		return 30;
	}
	return status === 'pass' ? 40 : 50;
}

/**
 * Sorts results by priority, keeping the original order within a priority.
 *
 * @param {Object[]} results Results from the REST API.
 * @return {Object[]} A new, sorted array.
 */
export function sortResults( results ) {
	return results
		.map( ( result, index ) => ( { result, index } ) )
		.sort(
			( a, b ) =>
				priority( a.result ) - priority( b.result ) || a.index - b.index
		)
		.map( ( { result } ) => result );
}

/**
 * Filters results. Every filter defaults to "all".
 *
 * @param {Object[]} results Results from the REST API.
 * @param {Object}   filters { severity, status, category }
 * @return {Object[]} The matching results.
 */
export function filterResults( results, filters = {} ) {
	const { severity = 'all', status = 'all', category = 'all' } = filters;

	return results.filter(
		( result ) =>
			( severity === 'all' || result.severity === severity ) &&
			( status === 'all' || effectiveStatus( result ) === status ) &&
			( category === 'all' || result.category === category )
	);
}

/**
 * Groups checklist rows by their group, in order of first appearance.
 *
 * @param {Object[]} rows Checklist rows from the REST API.
 * @return {Object[]} [{ group, label, rows }]
 */
export function groupChecklist( rows ) {
	const groups = [];

	rows.forEach( ( row ) => {
		let entry = groups.find( ( g ) => g.group === row.group );

		if ( ! entry ) {
			entry = { group: row.group, label: row.group_label, rows: [] };
			groups.push( entry );
		}

		entry.rows.push( row );
	} );

	return groups;
}

/**
 * Builds a link to the export endpoint, which already carries its nonce.
 *
 * @param {string} base        Export URL from the REST state.
 * @param {string} format      html | csv | json
 * @param {string} environment Optional environment override.
 * @return {string} URL.
 */
export function exportUrl( base, format, environment ) {
	const env = environment
		? `&environment=${ encodeURIComponent( environment ) }`
		: '';

	return `${ base }&format=${ encodeURIComponent( format ) }${ env }`;
}

/**
 * Only http(s) URLs may become links, so a third-party check cannot
 * inject a javascript: URL through its fix_url.
 *
 * @param {string} url Candidate URL.
 * @return {string} The URL, or an empty string when it is not safe.
 */
export function safeUrl( url ) {
	return typeof url === 'string' && /^https?:\/\//i.test( url ) ? url : '';
}
