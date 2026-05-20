<?php
/**
 * Main plugin bootstrap.
 *
 * @package AbilityWorkflows
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin components.
 */
final class Ability_Workflows_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Ability_Workflows_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Whether the Abilities API is available.
	 *
	 * @var bool
	 */
	private $abilities_available = false;

	/**
	 * Get singleton instance.
	 *
	 * @return Ability_Workflows_Plugin
	 */
	public static function instance(): Ability_Workflows_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->abilities_available = function_exists( 'wp_get_abilities' );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
		register_activation_hook( ABILITY_WORKFLOWS_FILE, array( $this, 'activate' ) );
	}

	/**
	 * Initialize plugin hooks.
	 */
	public function init(): void {
		if ( ! $this->abilities_available ) {
			add_action( 'admin_notices', array( $this, 'render_missing_api_notice' ) );
			return;
		}

		Ability_Workflows_CPT::register();
		Ability_Workflows_Admin::register();
	}

	/**
	 * Plugin activation.
	 */
	public function activate(): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}

		Ability_Workflows_CPT::register();
		flush_rewrite_rules();
	}

	/**
	 * Admin notice when Abilities API is missing.
	 */
	public function render_missing_api_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Ability Workflows requires WordPress 6.9+ with the Abilities API (wp_get_abilities).',
				'ability-workflows'
			)
		);
	}

	/**
	 * Whether abilities are available.
	 *
	 * @return bool
	 */
	public function is_abilities_available(): bool {
		return $this->abilities_available;
	}
}
