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
	const {
		severity = 'all',
		status = 'all',
		category = 'all',
		search = '',
	} = filters;

	return results.filter(
		( result ) =>
			( severity === 'all' || result.severity === severity ) &&
			( status === 'all' || effectiveStatus( result ) === status ) &&
			( category === 'all' || result.category === category ) &&
			matchesSearch( result, search )
	);
}

/**
 * Case-insensitive text search over a result's title, summary and recommendation.
 *
 * @param {Object} result A result from the REST API.
 * @param {string} query  Text typed by the user; empty matches everything.
 * @return {boolean} Whether the result matches.
 */
export function matchesSearch( result, query ) {
	const needle = String( query || '' )
		.trim()
		.toLowerCase();

	if ( needle === '' ) {
		return true;
	}

	return [ result.title, result.summary, result.recommendation ]
		.join( ' ' )
		.toLowerCase()
		.includes( needle );
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

let currentLocale = 'en_US';

/**
 * Sets the locale used by localizeDigits() and formatDateTime().
 *
 * @param {string} locale Locale such as "fa_IR" or "en_US".
 */
export function setLocale( locale ) {
	currentLocale = typeof locale === 'string' && locale ? locale : 'en_US';
}

export const isPersian = () => currentLocale.toLowerCase().startsWith( 'fa' );

/**
 * Converts Latin digits to Persian ones when the dashboard speaks Persian.
 *
 * @param {string|number} value Text or number to show.
 * @return {string} The text, with Persian digits for the Persian locale.
 */
export function localizeDigits( value ) {
	const text = String( value );

	if ( ! isPersian() ) {
		return text;
	}

	return text.replace( /[0-9]/g, ( digit ) => '۰۱۲۳۴۵۶۷۸۹'[ digit ] );
}

/**
 * Formats a Unix timestamp (seconds). Persian uses the Jalali calendar.
 *
 * @param {number} seconds Unix timestamp in seconds.
 * @return {string} Date and time for display.
 */
export function formatDateTime( seconds ) {
	const date = new Date( seconds * 1000 );
	const locale = isPersian()
		? 'fa-IR-u-ca-persian'
		: currentLocale.replace( '_', '-' );

	try {
		return new Intl.DateTimeFormat( locale, {
			dateStyle: 'medium',
			timeStyle: 'short',
		} ).format( date );
	} catch ( e ) {
		return date.toISOString();
	}
}

/**
 * Compares the newest recorded run with the one before it.
 *
 * @param {Object[]} history Runs from oldest to newest.
 * @return {Object|null} { delta, newFailures, fixed } or null with fewer than two runs.
 */
export function runDelta( history ) {
	if ( ! Array.isArray( history ) || history.length < 2 ) {
		return null;
	}

	const current = history[ history.length - 1 ];
	const previous = history[ history.length - 2 ];

	return {
		delta: current.score - previous.score,
		newFailures: current.failed.filter(
			( id ) => ! previous.failed.includes( id )
		),
		fixed: previous.failed.filter( ( id ) => ! current.failed.includes( id ) ),
	};
}

/**
 * Chart coordinates for the score trend.
 *
 * @param {Object[]} history Runs from oldest to newest.
 * @param {number}   width   SVG width.
 * @param {number}   height  SVG height.
 * @param {number}   pad     Padding inside the SVG.
 * @return {Object[]} [{ x, y, entry }]
 */
export function trendPoints( history, width, height, pad = 12 ) {
	const count = history.length;
	const innerWidth = width - pad * 2;
	const innerHeight = height - pad * 2;

	return history.map( ( entry, index ) => ( {
		x: pad + ( count === 1 ? innerWidth / 2 : ( innerWidth * index ) / ( count - 1 ) ),
		y: pad + innerHeight * ( 1 - Math.max( 0, Math.min( 100, entry.score ) ) / 100 ),
		entry,
	} ) );
}

/**
 * Whether the dashboard root is laid out right to left.
 *
 * @return {boolean} True for right-to-left layouts.
 */
export function isRtlLayout() {
	if ( typeof document === 'undefined' ) {
		return false;
	}

	const root = document.getElementById( 'golive-audit-root' );

	return !! root && window.getComputedStyle( root ).direction === 'rtl';
}
