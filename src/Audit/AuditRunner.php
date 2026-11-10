<?php
/**
 * Runs the registered checks and turns their results into a report.
 *
 * A check that throws never aborts the audit: it becomes an "error" result,
 * which is deliberately different from a "fail" (a problem with the site).
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Audit;

use InvalidArgumentException;
use Throwable;

final class AuditRunner {

	public const ENVIRONMENTS = array( 'local', 'development', 'staging', 'production' );

	private CheckRegistry $registry;
	private string $plugin_version;

	public function __construct( CheckRegistry $registry, string $plugin_version = '' ) {
		$this->registry       = $registry;
		$this->plugin_version = $plugin_version;
	}

	/**
	 * @param SiteContext $context     Site to audit.
	 * @param string|null $environment Profile override; null uses the context's own.
	 * @throws InvalidArgumentException For an unknown environment.
	 */
	public function run( SiteContext $context, ?string $environment = null ): Report {
		if ( null !== $environment ) {
			$this->assert_environment( $environment );
			$context = new EnvironmentOverride( $context, $environment );
		}

		$environment = $context->environment();
		$results     = array();

		foreach ( $this->registry->all() as $check ) {
			if ( ! $this->applies_to( $check, $environment ) ) {
				$results[] = Result::skipped(
					$check,
					sprintf(
						/* translators: %s: environment name, e.g. "local". */
						__( 'Not applicable to the "%s" environment.', 'golive-audit' ),
						$environment
					)
				);
				continue;
			}

			try {
				$results[] = $check->run( $context );
			} catch ( Throwable $e ) {
				$results[] = Result::error( $check, $this->short_class_name( $e ) );
			}
		}

		return new Report( $environment, $results, time(), $this->plugin_version );
	}

	private function applies_to( CheckInterface $check, string $environment ): bool {
		$environments = $check->environments();

		return in_array( '*', $environments, true ) || in_array( $environment, $environments, true );
	}

	private function assert_environment( string $environment ): void {
		if ( ! in_array( $environment, self::ENVIRONMENTS, true ) ) {
			throw new InvalidArgumentException(
				esc_html(
					sprintf(
						'Unknown environment "%s". Expected one of: %s.',
						$environment,
						implode( ', ', self::ENVIRONMENTS )
					)
				)
			);
		}
	}

	private function short_class_name( Throwable $e ): string {
		$parts = explode( '\\', get_class( $e ) );

		return (string) end( $parts );
	}
}
