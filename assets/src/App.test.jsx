// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	cleanup,
	render,
	screen,
	waitFor,
	within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import App from './App';
import * as api from './api';
import state from './test/state.fixture.json';
import report from './test/report.fixture.json';

// Responses captured from a real site by the REST API (see docs/testing.md).
vi.mock( './api' );

const clone = ( value ) => JSON.parse( JSON.stringify( value ) );

beforeEach( () => {
	globalThis.ResizeObserver = class {
		observe() {}
		unobserve() {}
		disconnect() {}
	};
	window.matchMedia =
		window.matchMedia ||
		( () => ( {
			matches: false,
			addEventListener() {},
			removeEventListener() {},
			addListener() {},
			removeListener() {},
		} ) );

	api.getState.mockResolvedValue( clone( state ) );
	api.runAudit.mockResolvedValue( clone( report ) );
	api.setWaiver.mockResolvedValue( {
		waivers: { 'seo.permalinks': { reason: 'ok' } },
	} );
	api.removeWaiver.mockResolvedValue( { waivers: {} } );
	api.setChecklistItem.mockImplementation( async ( id, value ) => {
		const next = clone( state );
		next.checklist = next.checklist.map( ( row ) =>
			row.id === id ? { ...row, state: value } : row
		);
		next.checklist_progress = {
			...next.checklist_progress,
			done: value === 'done' ? 1 : 0,
		};
		return {
			checklist: next.checklist,
			checklist_progress: next.checklist_progress,
		};
	} );
} );

afterEach( () => {
	cleanup();
	vi.clearAllMocks();
} );

describe( 'dashboard', () => {
	it( 'loads state, runs the audit and shows the summary and failures first', async () => {
		render( <App /> );

		expect( await screen.findByText( 'Launch blocked' ) ).toBeTruthy();
		expect( api.runAudit ).toHaveBeenCalledWith( state.environment );

		const rows = screen.getAllByRole( 'row' ).slice( 1 );
		expect( rows ).toHaveLength( report.results.length );

		// The critical failure is sorted to the top, passes to the bottom.
		expect( within( rows[ 0 ] ).getByText( 'Failed' ) ).toBeTruthy();
		expect(
			within( rows[ rows.length - 1 ] ).getByText( 'Passed' )
		).toBeTruthy();
	} );

	it( 'shows the recommendation and a fix link for a failed check', async () => {
		render( <App /> );
		await screen.findByText( 'Launch blocked' );

		const failing = report.results.find(
			( r ) => r.id === 'seo.permalinks'
		);
		expect( screen.getByText( failing.recommendation ) ).toBeTruthy();
		expect(
			screen
				.getByRole( 'link', { name: `Fix: ${ failing.title }` } )
				.getAttribute( 'href' )
		).toBe( failing.fix_url );
	} );

	it( 'filters by status', async () => {
		const user = userEvent.setup();
		render( <App /> );
		await screen.findByText( 'Launch blocked' );

		await user.selectOptions( screen.getByLabelText( 'Status' ), 'pass' );

		const rows = screen.getAllByRole( 'row' ).slice( 1 );
		expect( rows ).toHaveLength( report.summary.pass );
		rows.forEach( ( row ) =>
			expect( within( row ).getByText( 'Passed' ) ).toBeTruthy()
		);
	} );

	it( 'accepts a risk with a reason and re-runs the audit', async () => {
		const user = userEvent.setup();
		render( <App /> );
		await screen.findByText( 'Launch blocked' );
		const target = report.results.find(
			( r ) => r.id === 'seo.permalinks'
		);

		await user.click(
			screen.getByRole( 'button', {
				name: `Accept risk: ${ target.title }`,
			} )
		);

		const dialog = await screen.findByRole( 'dialog' );
		const submit = within( dialog ).getByRole( 'button', {
			name: 'Accept risk',
		} );
		expect(
			submit.hasAttribute( 'disabled' ) ||
				submit.getAttribute( 'aria-disabled' ) === 'true'
		).toBe( true );

		await user.type(
			within( dialog ).getByLabelText( 'Why is this acceptable?' ),
			'Client keeps plain URLs'
		);
		await user.click( submit );

		await waitFor( () =>
			expect( api.setWaiver ).toHaveBeenCalledWith(
				'seo.permalinks',
				'Client keeps plain URLs'
			)
		);
		await waitFor( () =>
			expect( api.runAudit ).toHaveBeenCalledTimes( 2 )
		);
	} );

	it( 'marks a checklist item done', async () => {
		const user = userEvent.setup();
		render( <App /> );
		await screen.findByText( 'Launch blocked' );

		await user.click(
			screen.getByRole( 'tab', { name: /Handoff checklist/ } )
		);
		const first = state.checklist[ 0 ];
		await user.selectOptions(
			await screen.findByLabelText( first.label ),
			'done'
		);

		await waitFor( () =>
			expect( api.setChecklistItem ).toHaveBeenCalledWith(
				first.id,
				'done'
			)
		);
	} );

	it( 'shows an error notice when the state cannot be loaded', async () => {
		api.getState.mockRejectedValue( {
			message: 'Sorry, you are not allowed to do that.',
		} );
		render( <App /> );

		expect(
			await screen.findByText( 'Sorry, you are not allowed to do that.' )
		).toBeTruthy();
	} );

	it( 'never renders a javascript: fix link from a third-party check', async () => {
		const hostile = clone( report );
		hostile.results[ 0 ].fix_url = 'javascript:alert(1)';
		api.runAudit.mockResolvedValue( hostile );

		render( <App /> );
		await screen.findByText( 'Launch blocked' );

		const hrefs = screen
			.queryAllByRole( 'link' )
			.map( ( a ) => a.getAttribute( 'href' ) );
		expect(
			hrefs.some( ( href ) => /^javascript:/i.test( href || '' ) )
		).toBe( false );
	} );
} );
