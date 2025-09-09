<?php

/**
 * Fired during plugin activation
 *
 * @link       https://wisdmlabs.com
 * @since      1.0.0
 *
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/includes
 * @author     WisdmLabs <support@wisdmlabs.com>
 */
class Wdm_Ai_Botkit_Extension_Activator {

	/**
	 * Check dependencies and activate the plugin.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {
		// Check if AI BotKit plugin is active
		if (!Wdm_Ai_Botkit_Extension_License_Manager::is_ai_botkit_active()) {
			// Deactivate the plugin
			deactivate_plugins(plugin_basename(__FILE__));
			
			// Show error message
			wp_die(
				__('WDM AI BotKit Extension requires AI BotKit plugin to be installed and activated. Please install and activate AI BotKit plugin first.', 'wdm-ai-botkit-extension'),
				__('Plugin Activation Error', 'wdm-ai-botkit-extension'),
				array(
					'response' => 200,
					'back_link' => true,
				)
			);
		}
	}

}
