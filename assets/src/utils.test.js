import { afterEach, describe, expect, it } from 'vitest';
import {
	effectiveStatus,
	exportUrl,
	filterResults,
	formatDateTime,
	groupChecklist,
	localizeDigits,
	matchesSearch,
	priority,
	runDelta,
	safeUrl,
	setLocale,
	sortResults,
	trendPoints,
} from './utils';

const make = ( id, status, severity, waived = false ) => ( {
	id,
	status,
	severity,
	waived,
	category: id.split( '.' )[ 0 ],
} );

describe( 'effectiveStatus', () => {
	it( 'turns a waived failure into "waived"', () => {
		expect( effectiveStatus( make( 'a.b', 'fail', 'low', true ) ) ).toBe(
			'waived'
		);
		expect( effectiveStatus( make( 'a.b', 'fail', 'low' ) ) ).toBe(
			'fail'
		);
		expect( effectiveStatus( make( 'a.b', 'pass', 'low', true ) ) ).toBe(
			'pass'
		);
	} );
} );

describe( 'sortResults', () => {
	it( 'orders by severity, then errors, waived, passes and skipped', () => {
		const sorted = sortResults( [
			make( 'a.skipped', 'skipped', 'critical' ),
			make( 'a.pass', 'pass', 'critical' ),
			make( 'a.waived', 'fail', 'critical', true ),
			make( 'a.error', 'error', 'low' ),
			make( 'a.low', 'fail', 'low' ),
			make( 'a.critical', 'fail', 'critical' ),
			make( 'a.warning', 'fail', 'warning' ),
		] );

		expect( sorted.map( ( r ) => r.id ) ).toEqual( [
			'a.critical',
			'a.warning',
			'a.low',
			'a.error',
			'a.waived',
			'a.pass',
			'a.skipped',
		] );
	} );

	it( 'keeps the original order within a priority and does not mutate', () => {
		const input = [
			make( 'a.one', 'pass', 'low' ),
			make( 'a.two', 'pass', 'low' ),
		];
		expect( sortResults( input ).map( ( r ) => r.id ) ).toEqual( [
			'a.one',
			'a.two',
		] );
		expect( input[ 0 ].id ).toBe( 'a.one' );
	} );

	it( 'ranks critical failures ahead of everything else', () => {
		expect( priority( make( 'a.b', 'fail', 'critical' ) ) ).toBeLessThan(
			priority( make( 'a.b', 'fail', 'warning' ) )
		);
	} );
} );

describe( 'filterResults', () => {
	const results = [
		make( 'seo.a', 'fail', 'critical' ),
		make( 'seo.b', 'pass', 'low' ),
		make( 'security.c', 'fail', 'warning', true ),
	];

	it( 'returns everything by default', () => {
		expect( filterResults( results ) ).toHaveLength( 3 );
	} );

	it( 'filters by severity, category and status', () => {
		expect(
			filterResults( results, { severity: 'critical' } )
		).toHaveLength( 1 );
		expect( filterResults( results, { category: 'seo' } ) ).toHaveLength(
			2
		);
		expect( filterResults( results, { status: 'fail' } ) ).toHaveLength(
			1
		);
		expect( filterResults( results, { status: 'waived' } ) ).toHaveLength(
			1
		);
	} );

	it( 'combines filters', () => {
		expect(
			filterResults( results, { category: 'seo', status: 'pass' } )
		).toHaveLength( 1 );
		expect(
			filterResults( results, { category: 'security', status: 'pass' } )
		).toHaveLength( 0 );
	} );
} );

describe( 'groupChecklist', () => {
	it( 'groups rows in order of first appearance', () => {
		const groups = groupChecklist( [
			{ id: '1', group: 'a', group_label: 'A' },
			{ id: '2', group: 'b', group_label: 'B' },
			{ id: '3', group: 'a', group_label: 'A' },
		] );

		expect( groups.map( ( g ) => g.group ) ).toEqual( [ 'a', 'b' ] );
		expect( groups[ 0 ].rows.map( ( r ) => r.id ) ).toEqual( [ '1', '3' ] );
		expect( groups[ 0 ].label ).toBe( 'A' );
	} );
} );

describe( 'exportUrl', () => {
	it( 'appends format and optional environment', () => {
		const base =
			'https://x.test/wp-admin/admin-post.php?action=a&_wpnonce=n';

		expect( exportUrl( base, 'csv', '' ) ).toBe( base + '&format=csv' );
		expect( exportUrl( base, 'html', 'staging' ) ).toBe(
			base + '&format=html&environment=staging'
		);
	} );
} );

describe( 'safeUrl', () => {
	it( 'allows http(s) only', () => {
		expect( safeUrl( 'https://x.test/wp-admin/' ) ).toBe(
			'https://x.test/wp-admin/'
		);
		expect( safeUrl( 'http://x.test' ) ).toBe( 'http://x.test' );
		expect( safeUrl( 'javascript:alert(1)' ) ).toBe( '' );
		expect( safeUrl( 'data:text/html,hi' ) ).toBe( '' );
		expect( safeUrl( '' ) ).toBe( '' );
		expect( safeUrl( undefined ) ).toBe( '' );
	} );
} );

describe( 'search', () => {
	const result = {
		title: 'Authentication keys and salts are unique',
		summary: 'Missing keys',
		recommendation: 'Generate fresh values',
		severity: 'critical',
		status: 'fail',
		waived: false,
		category: 'security',
	};

	it( 'matches title, summary and recommendation case-insensitively', () => {
		expect( matchesSearch( result, 'SALTS' ) ).toBe( true );
		expect( matchesSearch( result, 'missing' ) ).toBe( true );
		expect( matchesSearch( result, 'fresh' ) ).toBe( true );
		expect( matchesSearch( result, 'sitemap' ) ).toBe( false );
	} );

	it( 'treats an empty query as no filter and combines with other filters', () => {
		expect( matchesSearch( result, '  ' ) ).toBe( true );
		expect(
			filterResults( [ result ], { search: 'salts', severity: 'low' } )
		).toHaveLength( 0 );
		expect(
			filterResults( [ result ], { search: 'salts', severity: 'critical' } )
		).toHaveLength( 1 );
	} );
} );

describe( 'history helpers', () => {
	it( 'runDelta needs two runs and reports changes', () => {
		expect( runDelta( [] ) ).toBeNull();
		expect( runDelta( [ { score: 50, failed: [] } ] ) ).toBeNull();
		expect(
			runDelta( [
				{ score: 70, failed: [ 'a', 'b' ] },
				{ score: 65, failed: [ 'b', 'c' ] },
			] )
		).toEqual( { delta: -5, newFailures: [ 'c' ], fixed: [ 'a' ] } );
	} );

	it( 'trendPoints maps scores into the chart box', () => {
		const points = trendPoints( [ { score: 0 }, { score: 100 } ], 200, 100, 10 );

		expect( points[ 0 ] ).toMatchObject( { x: 10, y: 90 } );
		expect( points[ 1 ] ).toMatchObject( { x: 190, y: 10 } );
		expect( trendPoints( [ { score: 50 } ], 200, 100, 10 )[ 0 ].x ).toBe( 100 );
	} );
} );

describe( 'locale helpers', () => {
	afterEach( () => setLocale( 'en_US' ) );

	it( 'uses Persian digits and the Jalali calendar for fa_IR only', () => {
		expect( localizeDigits( 'Score 82/100' ) ).toBe( 'Score 82/100' );

		setLocale( 'fa_IR' );

		expect( localizeDigits( 'Score 82/100' ) ).toBe( 'Score ۸۲/۱۰۰' );
		expect( formatDateTime( 1794479400 ) ).toContain( 'آبان' );
	} );
} );
