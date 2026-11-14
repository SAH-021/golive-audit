<?php
/**
 * Scheduled audits (WP-Cron): records history and emails an alert when new problems appear.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Cron;

use GoliveAudit\Notify\AlertMessage;
use GoliveAudit\Plugin;
use GoliveAudit\Storage\HistoryStore;

final class ScheduledAudit {

	public const HOOK = 'golive_audit_scheduled_run';

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function register(): void {
		add_action( self::HOOK, array( $this, 'run' ) );
	}

	/**
	 * Cron callback: audits the site as its detected environment, stores the result and sends an alert if needed.
	 */
	public function run(): void {
		$settings = $this->plugin->settings()->get();

		if ( 'off' === $settings['schedule'] ) {
			return;
		}

		$before   = $this->plugin->history()->all();
		$previous = array() === $before ? null : end( $before );
		$report   = $this->plugin->run_audit( null, true );
		$after    = $this->plugin->history()->all();
		$current  = end( $after );

		if ( false === $current ) {
			return;
		}

		$message = AlertMessage::build(
			$report,
			HistoryStore::diff( $previous, $current ),
			null === $previous,
			array(
				'site_name'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'site_url'      => home_url(),
				'dashboard_url' => admin_url( 'tools.php?page=golive-audit' ),
			)
		);

		if ( null === $message ) {
			return;
		}

		$to = '' !== $settings['alert_email'] ? $settings['alert_email'] : (string) get_option( 'admin_email' );

		if ( '' !== $to ) {
			wp_mail( $to, $message['subject'], $message['body'] );
		}
	}

	/**
	 * Makes the scheduled event match the setting ("off", "daily" or "weekly").
	 */
	public static function sync( string $schedule ): void {
		$next = wp_next_scheduled( self::HOOK );

		if ( 'off' === $schedule ) {
			if ( false !== $next ) {
				wp_clear_scheduled_hook( self::HOOK );
			}

			return;
		}

		if ( false !== $next && wp_get_schedule( self::HOOK ) === $schedule ) {
			return;
		}

		wp_clear_scheduled_hook( self::HOOK );
		wp_schedule_event( time() + HOUR_IN_SECONDS, $schedule, self::HOOK );
	}

	/**
	 * Removes the scheduled event (used on deactivation).
	 */
	public static function clear(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}
}
