<?php
/**
 * Plugin Name:       Ability Workflows
 * Description:       Create and run admin workflows from registered WordPress Abilities API abilities.
 * Version:           0.2.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Nik McLaughlin
 * Text Domain:       ability-workflows
 *
 * @package AbilityWorkflows
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABILITY_WORKFLOWS_VERSION', '0.2.0' );
define( 'ABILITY_WORKFLOWS_FILE', __FILE__ );
define( 'ABILITY_WORKFLOWS_DIR', plugin_dir_path( __FILE__ ) );
define( 'ABILITY_WORKFLOWS_URL', plugin_dir_url( __FILE__ ) );

require_once ABILITY_WORKFLOWS_DIR . 'includes/class-ability-catalog.php';
require_once ABILITY_WORKFLOWS_DIR . 'includes/class-input-mapper.php';
require_once ABILITY_WORKFLOWS_DIR . 'includes/class-workflow-runner.php';
require_once ABILITY_WORKFLOWS_DIR . 'includes/class-workflow-cpt.php';
require_once ABILITY_WORKFLOWS_DIR . 'includes/admin/class-admin.php';
require_once ABILITY_WORKFLOWS_DIR . 'includes/class-plugin.php';

Ability_Workflows_Plugin::instance();
