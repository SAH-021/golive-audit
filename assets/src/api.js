import apiFetch from '@wordpress/api-fetch';

const BASE = '/golive-audit/v1';

export const getState = () => apiFetch( { path: `${ BASE }/state` } );

export const runAudit = ( environment ) =>
	apiFetch( {
		path: `${ BASE }/audit`,
		method: 'POST',
		data: environment ? { environment } : {},
	} );

export const setWaiver = ( id, reason ) =>
	apiFetch( {
		path: `${ BASE }/waivers/${ id }`,
		method: 'PUT',
		data: { reason },
	} );

export const removeWaiver = ( id ) =>
	apiFetch( { path: `${ BASE }/waivers/${ id }`, method: 'DELETE' } );

export const setChecklistItem = ( item, state ) =>
	apiFetch( {
		path: `${ BASE }/checklist/${ item }`,
		method: 'PUT',
		data: { state },
	} );

export const saveSettings = ( settings ) =>
	apiFetch( { path: `${ BASE }/settings`, method: 'POST', data: settings } );

export const getHistory = () => apiFetch( { path: `${ BASE }/history` } );

export const clearHistory = () =>
	apiFetch( { path: `${ BASE }/history`, method: 'DELETE' } );
