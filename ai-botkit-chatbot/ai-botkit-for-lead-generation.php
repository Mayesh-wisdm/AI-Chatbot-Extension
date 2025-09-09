<?php
/**
 * Plugin Name: AI BotKit –  AI Chatbot for WordPress
 * Plugin URI: https://aibotkit.io
 * Description: An advanced RAG-based chatbot plugin for WordPress with vector search capabilities.
 * Version: 1.0.1
 * Author: WisdmLabs
 * Author URI: https://wisdmlabs.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ai-botkit-for-lead-generation
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Plugin version and constants
define('AI_BOTKIT_VERSION', '1.0.1');
define('AI_BOTKIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AI_BOTKIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AI_BOTKIT_INCLUDES_DIR', AI_BOTKIT_PLUGIN_DIR . 'includes/');
define('AI_BOTKIT_ADMIN_DIR', AI_BOTKIT_PLUGIN_DIR . 'admin/');
define('AI_BOTKIT_PUBLIC_DIR', AI_BOTKIT_PLUGIN_DIR . 'public/');
define('AI_BOTKIT_ASSETS_URL', AI_BOTKIT_PLUGIN_URL . 'assets/');

// Vector database type is now controlled by user settings
// define('AI_BOTKIT_VECTOR_DB_TYPE', 'pinecone');

/**
 * The code that runs during plugin activation.
 */
function ai_botkit_activate_plugin() {
    require_once AI_BOTKIT_INCLUDES_DIR . 'class-ai-botkit-activator.php';
    AI_BotKit\Core\Activator::activate();
}
register_activation_hook(__FILE__, 'ai_botkit_activate_plugin');

/**
 * The code that runs during plugin deactivation.
 */
function ai_botkit_deactivate_plugin() {
    require_once AI_BOTKIT_INCLUDES_DIR . 'class-ai-botkit-deactivator.php';
    AI_BotKit\Core\Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'ai_botkit_deactivate_plugin');

/**
 * Initialize the plugin
 */
function ai_botkit_init() {
    // Initialize the plugin
    require_once AI_BOTKIT_INCLUDES_DIR . 'class-ai-botkit.php';
    $plugin = new AI_BotKit\AI_BotKit();
    $plugin->run();
}
add_action('init', 'ai_botkit_init'); 