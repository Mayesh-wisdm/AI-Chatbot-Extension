<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://wisdmlabs.com
 * @since             1.0.0
 * @package           Wdm_KnowVault_Extension
 *
 * @wordpress-plugin
 * Plugin Name:       WDM KnowVault Extension for LearnDash
 * Plugin URI:        https://www.wisdmlabs.com
 * Description:       Adds User Awareness for LearnDash enrolled courses with integrated licensing system for KnowVault
 * Version:           1.0.2
 * Author:            WisdmLabs
 * Author URI:        https://wisdmlabs.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wdm-knowvault-extension
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'WDM_KNOWVAULT_EXTENSION_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wdm-ai-botkit-extension-activator.php
 */
function activate_wdm_ai_botkit_extension() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wdm-ai-botkit-extension-activator.php';
	Wdm_Ai_Botkit_Extension_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wdm-ai-botkit-extension-deactivator.php
 */
function deactivate_wdm_ai_botkit_extension() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wdm-ai-botkit-extension-deactivator.php';
	Wdm_Ai_Botkit_Extension_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wdm_ai_botkit_extension' );
register_deactivation_hook( __FILE__, 'deactivate_wdm_ai_botkit_extension' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wdm-ai-botkit-extension.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wdm_ai_botkit_extension() {

	$plugin = new Wdm_Ai_Botkit_Extension();
	$plugin->run();

}
run_wdm_ai_botkit_extension();
