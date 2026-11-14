<?php
/**
 * REST API: golive-audit/v1.
 *
 * Every route requires the manage_options capability. The audit is read-only;
 * the only things written are waivers, checklist progress and settings.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Rest;

use GoliveAudit\Audit\AuditRunner;
use GoliveAudit\Audit\Categories;
use GoliveAudit\Checklist\Catalog;
use GoliveAudit\Checklist\ChecklistStore;
use GoliveAudit\Cron\ScheduledAudit;
use GoliveAudit\Plugin;
use GoliveAudit\Storage\SettingsStore;
use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Controller {

	public const NAMESPACE = 'golive-audit/v1';

	private const LOCK_KEY = 'golive_audit_running';
	private const LOCK_TTL = 30;

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register_routes(): void {
		$id_pattern = '(?P<id>[a-z0-9_]+(?:\.[a-z0-9_]+)*)';

		register_rest_route(
			self::NAMESPACE,
			'/state',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_state' ),
				'permission_callback' => array( $this, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/audit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_audit' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'environment' => array(
						'type'              => 'string',
						'enum'              => AuditRunner::ENVIRONMENTS,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/history',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_history' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'clear_history' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/waivers/' . $id_pattern,
			array(
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'set_waiver' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'reason' => array(
							'type'              => 'string',
							'required'          => true,
							'minLength'         => 1,
							'maxLength'         => 500,
							'validate_callback' => 'rest_validate_request_arg',
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'remove_waiver' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/checklist/(?P<item>[a-z0-9_]+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'set_checklist_item' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'state' => array(
						'type'     => 'string',
						'required' => true,
						'enum'     => ChecklistStore::STATES,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_settings' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args'                => array(
					'agency_name' => array(
						'type'              => 'string',
						'maxLength'         => 100,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'client_name' => array(
						'type'              => 'string',
						'maxLength'         => 100,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'note'        => array(
						'type'              => 'string',
						'maxLength'         => 1000,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
					'preset'      => array(
						'type' => 'string',
						'enum' => Catalog::PRESETS,
					),
					'language'    => array(
						'type' => 'string',
						'enum' => SettingsStore::LANGUAGES,
					),
					'schedule'    => array(
						'type' => 'string',
						'enum' => SettingsStore::SCHEDULES,
					),
					'alert_email' => array(
						'type'              => 'string',
						'maxLength'         => 254,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'sanitize_email',
					),
				),
			)
		);
	}

	/**
	 * Capability gate for every route. A nonce is checked by core's REST layer
	 * for cookie authentication, but it never replaces this capability check.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new WP_Error(
			'golive_audit_forbidden',
			__( 'You are not allowed to use Golive Audit.', 'golive-audit' ),
			array( 'status' => rest_authorization_required_code() )
		);
	}

	public function get_state(): WP_REST_Response {
		return new WP_REST_Response( $this->state() );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_audit( WP_REST_Request $request ) {
		if ( get_transient( self::LOCK_KEY ) ) {
			return new WP_Error(
				'golive_audit_busy',
				__( 'An audit is already running. Try again in a few seconds.', 'golive-audit' ),
				array( 'status' => 429 )
			);
		}

		set_transient( self::LOCK_KEY, 1, self::LOCK_TTL );

		try {
			$environment = $request->get_param( 'environment' );
			$report      = $this->plugin->run_audit( is_string( $environment ) && '' !== $environment ? $environment : null, true );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'golive_audit_invalid_environment', __( 'Unknown environment.', 'golive-audit' ), array( 'status' => 400 ) );
		} finally {
			delete_transient( self::LOCK_KEY );
		}

		return new WP_REST_Response( array_merge( $report->to_array(), array( 'history' => $this->plugin->history()->all() ) ) );
	}

	public function get_history(): WP_REST_Response {
		return new WP_REST_Response( array( 'history' => $this->plugin->history()->all() ) );
	}

	public function clear_history(): WP_REST_Response {
		$this->plugin->history()->clear();

		return new WP_REST_Response( array( 'history' => array() ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_waiver( WP_REST_Request $request ) {
		$id = (string) $request['id'];

		if ( null === $this->plugin->registry()->get( $id ) ) {
			return $this->not_found();
		}

		try {
			$this->plugin->waivers()->set( $id, (string) $request['reason'] );
		} catch ( InvalidArgumentException $e ) {
			return new WP_Error( 'golive_audit_invalid_waiver', __( 'A waiver needs a reason.', 'golive-audit' ), array( 'status' => 400 ) );
		}

		return new WP_REST_Response( array( 'waivers' => $this->waiver_map() ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function remove_waiver( WP_REST_Request $request ) {
		$id = (string) $request['id'];

		if ( null === $this->plugin->registry()->get( $id ) ) {
			return $this->not_found();
		}

		$this->plugin->waivers()->remove( $id );

		return new WP_REST_Response( array( 'waivers' => $this->waiver_map() ) );
	}

	/**
	 * @return WP_REST_Response|WP_Error
	 */
	public function set_checklist_item( WP_REST_Request $request ) {
		try {
			$this->plugin->checklist()->set( (string) $request['item'], (string) $request['state'] );
		} catch ( InvalidArgumentException $e ) {
			return $this->not_found();
		}

		return new WP_REST_Response( $this->checklist_payload() );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$input = array();

		foreach ( array( 'agency_name', 'client_name', 'note', 'preset', 'language', 'schedule', 'alert_email' ) as $key ) {
			if ( null !== $request->get_param( $key ) ) {
				$input[ $key ] = (string) $request->get_param( $key );
			}
		}

		$settings = $this->plugin->settings()->update( $input );

		ScheduledAudit::sync( $settings['schedule'] );

		return new WP_REST_Response(
			array_merge(
				array(
					'settings' => $settings,
					'next_run' => $this->next_run(),
				),
				$this->checklist_payload()
			)
		);
	}

	/**
	 * Everything the dashboard needs except the audit results themselves.
	 *
	 * @return array<string, mixed>
	 */
	private function state(): array {
		$checks = array();

		foreach ( $this->plugin->registry()->all() as $check ) {
			$checks[] = array(
				'id'           => $check->id(),
				'title'        => $check->title(),
				'category'     => $check->category(),
				'severity'     => $check->severity(),
				'environments' => $check->environments(),
			);
		}

		return array_merge(
			array(
				'version'      => GOLIVE_AUDIT_VERSION,
				'environment'  => ( new \GoliveAudit\Support\WpSiteContext() )->environment(),
				'locale'       => $this->plugin->effective_locale(),
				'environments' => AuditRunner::ENVIRONMENTS,
				'categories'   => Categories::labels(),
				'checks'       => $checks,
				'waivers'      => $this->waiver_map(),
				'settings'     => $this->plugin->settings()->get(),
				'presets'      => Catalog::preset_labels(),
				'languages'    => array(
					'auto' => __( 'Site default', 'golive-audit' ),
					'en'   => 'English',
					'fa'   => 'فارسی',
				),
				'schedules'    => array(
					'off'    => __( 'Off', 'golive-audit' ),
					'daily'  => __( 'Daily', 'golive-audit' ),
					'weekly' => __( 'Weekly', 'golive-audit' ),
				),
				'next_run'     => $this->next_run(),
				'history'      => $this->plugin->history()->all(),
				'export_url'   => $this->export_url(),
			),
			$this->checklist_payload()
		);
	}

	/**
	 * Unix time of the next scheduled audit, or 0 when none is scheduled.
	 */
	private function next_run(): int {
		$next = wp_next_scheduled( ScheduledAudit::HOOK );

		return false === $next ? 0 : (int) $next;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function checklist_payload(): array {
		$preset = $this->plugin->settings()->get()['preset'];

		return array(
			'checklist'          => $this->plugin->checklist()->rows( $preset ),
			'checklist_progress' => $this->plugin->checklist()->progress( $preset ),
		);
	}

	/**
	 * Base URL of the export endpoint, already carrying its nonce. The dashboard
	 * appends &format=...&environment=... when building links.
	 */
	private function export_url(): string {
		return add_query_arg(
			array(
				'action'   => 'golive_audit_export',
				'_wpnonce' => wp_create_nonce( 'golive_audit_export' ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Waivers keyed by check ID. An empty list is returned as an object so the
	 * JSON shape is always {} and never [].
	 *
	 * @return array<string, mixed>|\stdClass
	 */
	private function waiver_map() {
		$waivers = $this->plugin->waivers()->all();

		return array() === $waivers ? new \stdClass() : $waivers;
	}

	private function not_found(): WP_Error {
		return new WP_Error( 'golive_audit_not_found', __( 'Unknown check or checklist item.', 'golive-audit' ), array( 'status' => 404 ) );
	}
}
