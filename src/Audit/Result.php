<?php
/**
 * Immutable outcome of a single check.
 *
 * Status says what happened; severity says how bad a failure is:
 * - pass:    the check succeeded.
 * - fail:    the check ran and found a problem.
 * - error:   the check itself could not complete (not a site problem).
 * - skipped: the check does not apply to the audited environment.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Audit;

final class Result {

	public const STATUS_PASS    = 'pass';
	public const STATUS_FAIL    = 'fail';
	public const STATUS_ERROR   = 'error';
	public const STATUS_SKIPPED = 'skipped';

	public const SEVERITY_CRITICAL = 'critical';
	public const SEVERITY_WARNING  = 'warning';
	public const SEVERITY_LOW      = 'low';

	private const SEVERITY_RANK = array(
		self::SEVERITY_LOW      => 1,
		self::SEVERITY_WARNING  => 2,
		self::SEVERITY_CRITICAL => 3,
	);

	private const SEVERITY_WEIGHT = array(
		self::SEVERITY_LOW      => 1,
		self::SEVERITY_WARNING  => 3,
		self::SEVERITY_CRITICAL => 10,
	);

	private string $id;
	private string $title;
	private string $category;
	private string $severity;
	private string $status;
	private string $summary;
	private string $recommendation;
	private string $fix_url;
	private bool $waived         = false;
	private string $waive_reason = '';

	private function __construct( CheckInterface $check, string $status, string $summary, string $recommendation, string $fix_url ) {
		$this->id             = $check->id();
		$this->title          = $check->title();
		$this->category       = $check->category();
		$this->severity       = $check->severity();
		$this->status         = $status;
		$this->summary        = $summary;
		$this->recommendation = $recommendation;
		$this->fix_url        = $fix_url;
	}

	public static function pass( CheckInterface $check, string $summary ): self {
		return new self( $check, self::STATUS_PASS, $summary, '', '' );
	}

	public static function fail( CheckInterface $check, string $summary, string $recommendation, string $fix_url = '' ): self {
		return new self( $check, self::STATUS_FAIL, $summary, $recommendation, $fix_url );
	}

	public static function skipped( CheckInterface $check, string $reason ): self {
		return new self( $check, self::STATUS_SKIPPED, $reason, '', '' );
	}

	/**
	 * Builds an error result. Only a short code is kept: exception messages can
	 * contain server paths, which must never reach a report.
	 */
	public static function error( CheckInterface $check, string $code ): self {
		$summary = sprintf(
			/* translators: %s: short error code. */
			__( 'This check could not be completed (%s).', 'golive-audit' ),
			$code
		);

		return new self( $check, self::STATUS_ERROR, $summary, '', '' );
	}

	/**
	 * Numeric rank of a severity; 0 for unknown values.
	 */
	public static function severity_rank( string $severity ): int {
		return self::SEVERITY_RANK[ $severity ] ?? 0;
	}

	/**
	 * Weight of this check in the readiness score, derived from its severity.
	 */
	public function weight(): int {
		return self::SEVERITY_WEIGHT[ $this->severity ] ?? 0;
	}

	/**
	 * Returns a copy marked as an accepted risk.
	 */
	public function with_waiver( string $reason ): self {
		$copy               = clone $this;
		$copy->waived       = true;
		$copy->waive_reason = $reason;

		return $copy;
	}

	public function id(): string {
		return $this->id;
	}

	public function status(): string {
		return $this->status;
	}

	public function severity(): string {
		return $this->severity;
	}

	public function is_waived(): bool {
		return $this->waived;
	}

	/**
	 * Whether this is an unwaived critical failure.
	 */
	public function blocks_launch(): bool {
		return $this->fails_at_or_above( self::SEVERITY_CRITICAL );
	}

	/**
	 * Whether this is an unwaived failure with at least the given severity.
	 */
	public function fails_at_or_above( string $severity ): bool {
		return self::STATUS_FAIL === $this->status
			&& ! $this->waived
			&& self::severity_rank( $this->severity ) >= self::severity_rank( $severity );
	}

	/**
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'             => $this->id,
			'title'          => $this->title,
			'category'       => $this->category,
			'severity'       => $this->severity,
			'status'         => $this->status,
			'summary'        => $this->summary,
			'recommendation' => $this->recommendation,
			'fix_url'        => $this->fix_url,
			'waived'         => $this->waived,
			'waive_reason'   => $this->waive_reason,
		);
	}
}
