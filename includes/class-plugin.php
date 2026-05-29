<?php
/**
 * Main plugin bootstrap.
 *
 * @package Baton
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads plugin components.
 */
final class Baton_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Baton_Plugin|null
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
	 * @return Baton_Plugin
	 */
	public static function instance(): Baton_Plugin {
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
	}

	/**
	 * Initialize plugin hooks.
	 */
	public function init(): void {
		if ( ! $this->abilities_available ) {
			if ( is_admin() ) {
				add_action( 'admin_menu', array( $this, 'register_missing_api_menu' ) );
				add_action( 'admin_notices', array( $this, 'render_missing_api_notice' ) );
			}
			return;
		}

		Baton_Workflow_CPT::register();
		Baton_Workflow_Abilities::register();
		Baton_Admin::register();
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	/**
	 * Plugin activation.
	 */
	public static function activate(): void {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return;
		}

		Baton_Workflow_CPT::register();
		flush_rewrite_rules();

		// Register workflow abilities after CPT exists.
		if ( did_action( 'wp_abilities_api_init' ) ) {
			Baton_Workflow_Abilities::register_all_workflows();
		}
	}

	/**
	 * Register Tools submenu when Abilities API is unavailable (notice landing page).
	 */
	public function register_missing_api_menu(): void {
		add_submenu_page(
			'tools.php',
			__( 'Baton', 'baton' ),
			__( 'Baton', 'baton' ),
			'manage_options',
			Baton_Admin::PAGE_SLUG,
			array( $this, 'render_missing_api_page' )
		);
	}

	/**
	 * Minimal Baton screen when Abilities API is missing; notice renders via admin_notices.
	 */
	public function render_missing_api_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'baton' ) );
		}

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Baton', 'baton' ) . '</h1>';
		echo '</div>';
	}

	/**
	 * Admin notice when Abilities API is missing (Tools → Baton only).
	 */
	public function render_missing_api_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $pagenow;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen routing only.
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'tools.php' !== $pagenow || Baton_Admin::PAGE_SLUG !== $page ) {
			return;
		}

		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__(
				'Baton requires WordPress 6.9+ with the Abilities API (wp_get_abilities).',
				'baton'
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
