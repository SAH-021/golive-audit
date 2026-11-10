<?php
/**
 * Human readable category names. The category of a check is the first segment of its ID.
 *
 * @package GoliveAudit
 */

namespace GoliveAudit\Audit;

final class Categories {

	/**
	 * @return array<string, string> Category slug => label.
	 */
	public static function labels(): array {
		return array(
			'seo'      => __( 'SEO & visibility', 'golive-audit' ),
			'env'      => __( 'Environment & URLs', 'golive-audit' ),
			'content'  => __( 'Content', 'golive-audit' ),
			'legal'    => __( 'Privacy & legal', 'golive-audit' ),
			'security' => __( 'Security basics', 'golive-audit' ),
			'email'    => __( 'Email', 'golive-audit' ),
			'locale'   => __( 'Language & region', 'golive-audit' ),
		);
	}
}
