<?php
/**
 * Contract every audit check implements.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Audit;

interface CheckInterface {

	/**
	 * Stable identifier such as "seo.search_visibility" (lowercase, dot-separated).
	 */
	public function id(): string;

	/**
	 * Human readable title.
	 */
	public function title(): string;

	/**
	 * Category slug, for example "seo", "content", "security" or "email".
	 */
	public function category(): string;

	/**
	 * Severity used when the check fails: one of the Result::SEVERITY_* values.
	 */
	public function severity(): string;

	/**
	 * Environments the check applies to, or array( '*' ) for all of them.
	 *
	 * @return string[]
	 */
	public function environments(): array;

	/**
	 * Runs the check.
	 */
	public function run( SiteContext $context ): Result;
}
