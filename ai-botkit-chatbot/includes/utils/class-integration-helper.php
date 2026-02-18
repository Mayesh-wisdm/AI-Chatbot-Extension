<?php
/**
 * Integration Helper
 *
 * Centralized utility for checking third-party plugin integrations.
 *
 * @package AI_BotKit
 * @subpackage Utils
 * @since 2.0.4
 */

namespace AI_BotKit\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration_Helper class
 *
 * Provides centralized methods for checking if third-party plugins are active.
 * This eliminates code duplication across multiple classes.
 */
class Integration_Helper {

	/**
	 * Check if LearnDash LMS is active
	 *
	 * @return bool True if LearnDash is active, false otherwise.
	 */
	public static function is_learndash_active(): bool {
		return defined( 'LEARNDASH_VERSION' );
	}

	/**
	 * Check if WooCommerce is active
	 *
	 * @return bool True if WooCommerce is active, false otherwise.
	 */
	public static function is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Get LearnDash version
	 *
	 * @return string|null LearnDash version if active, null otherwise.
	 */
	public static function get_learndash_version(): ?string {
		return self::is_learndash_active() ? LEARNDASH_VERSION : null;
	}

	/**
	 * Get WooCommerce version
	 *
	 * @return string|null WooCommerce version if active, null otherwise.
	 */
	public static function get_woocommerce_version(): ?string {
		if ( ! self::is_woocommerce_active() ) {
			return null;
		}

		return defined( 'WC_VERSION' ) ? WC_VERSION : null;
	}

	/**
	 * Get list of active integrations
	 *
	 * @return array Array of active integration names.
	 */
	public static function get_active_integrations(): array {
		$integrations = array();

		if ( self::is_learndash_active() ) {
			$integrations[] = 'learndash';
		}

		if ( self::is_woocommerce_active() ) {
			$integrations[] = 'woocommerce';
		}

		return $integrations;
	}

	/**
	 * Check if any integrations are active
	 *
	 * @return bool True if at least one integration is active.
	 */
	public static function has_any_integration(): bool {
		return self::is_learndash_active() || self::is_woocommerce_active();
	}
}
