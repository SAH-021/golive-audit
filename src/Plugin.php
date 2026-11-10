<?php
/**
 * Plugin bootstrap: wires the engine to WordPress.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit;

use GoliveAudit\Admin\AdminPage;
use GoliveAudit\Admin\ExportHandler;
use GoliveAudit\Audit\AuditRunner;
use GoliveAudit\Audit\AuditService;
use GoliveAudit\Audit\CheckInterface;
use GoliveAudit\Audit\CheckRegistry;
use GoliveAudit\Audit\Report;
use GoliveAudit\Checklist\ChecklistStore;
use GoliveAudit\Checks;
use GoliveAudit\Cli\AuditCommand;
use GoliveAudit\Rest\Controller;
use GoliveAudit\Storage\OptionStore;
use GoliveAudit\Storage\SettingsStore;
use GoliveAudit\Storage\WaiverStore;
use GoliveAudit\Storage\WpOptionStore;
use GoliveAudit\Support\WpSiteContext;

final class Plugin {

	private static ?Plugin $instance = null;

	private ?CheckRegistry $registry   = null;
	private ?OptionStore $option_store = null;
	private ?WaiverStore $waivers      = null;
	private ?SettingsStore $settings   = null;
	private ?ChecklistStore $checklist = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		add_action(
			'rest_api_init',
			function (): void {
				( new Controller( $this ) )->register_routes();
			}
		);

		if ( is_admin() ) {
			( new AdminPage() )->register();
			( new ExportHandler( $this ) )->register();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'golive-audit', AuditCommand::class );
		}
	}

	/**
	 * The check registry. Built lazily so other plugins have loaded and can
	 * add checks through the `golive_audit_register_checks` action.
	 */
	public function registry(): CheckRegistry {
		if ( null === $this->registry ) {
			$registry = new CheckRegistry();

			foreach ( $this->default_checks() as $check ) {
				$registry->register( $check );
			}

			/**
			 * Lets other plugins register their own checks.
			 *
			 * @param CheckRegistry $registry The registry to add checks to.
			 */
			do_action( 'golive_audit_register_checks', $registry );

			$this->registry = $registry;
		}

		return $this->registry;
	}

	public function waivers(): WaiverStore {
		if ( null === $this->waivers ) {
			$this->waivers = new WaiverStore( $this->option_store() );
		}

		return $this->waivers;
	}

	public function settings(): SettingsStore {
		if ( null === $this->settings ) {
			$this->settings = new SettingsStore( $this->option_store() );
		}

		return $this->settings;
	}

	public function checklist(): ChecklistStore {
		if ( null === $this->checklist ) {
			$this->checklist = new ChecklistStore( $this->option_store() );
		}

		return $this->checklist;
	}

	/**
	 * Runs every applicable check against this site and applies stored waivers.
	 *
	 * @param string|null $environment Optional environment override.
	 */
	public function run_audit( ?string $environment = null ): Report {
		$runner  = new AuditRunner( $this->registry(), GOLIVE_AUDIT_VERSION );
		$service = new AuditService( $runner, $this->waivers() );

		return $service->run( new WpSiteContext(), $environment );
	}

	/**
	 * The bundled checks, in display order.
	 *
	 * @return CheckInterface[]
	 */
	public function default_checks(): array {
		return array(
			new Checks\NoindexCheck(),
			new Checks\UrlConsistencyCheck(),
			new Checks\RobotsTxtCheck(),
			new Checks\PermalinksCheck(),
			new Checks\SitemapCheck(),
			new Checks\SiteIdentityCheck(),
			new Checks\SiteIconCheck(),
			new Checks\FrontPageCheck(),
			new Checks\DefaultContentCheck(),
			new Checks\CommentDefaultsCheck(),
			new Checks\PrivacyPolicyCheck(),
			new Checks\SaltsCheck(),
			new Checks\OpenRegistrationCheck(),
			new Checks\AdminUsersCheck(),
			new Checks\FileEditorCheck(),
			new Checks\DebugLogCheck(),
			new Checks\AdminEmailCheck(),
			new Checks\MailTransportCheck(),
			new Checks\TimezoneCheck(),
		);
	}

	private function option_store(): OptionStore {
		if ( null === $this->option_store ) {
			$this->option_store = new WpOptionStore();
		}

		return $this->option_store;
	}
}
