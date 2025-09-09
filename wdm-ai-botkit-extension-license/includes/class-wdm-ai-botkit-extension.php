<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://wisdmlabs.com
 * @since      1.0.0
 *
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Wdm_Ai_Botkit_Extension
 * @subpackage Wdm_Ai_Botkit_Extension/includes
 * @author     WisdmLabs <support@wisdmlabs.com>
 */
class Wdm_Ai_Botkit_Extension {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Wdm_Ai_Botkit_Extension_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'WDM_AI_BOTKIT_EXTENSION_VERSION' ) ) {
			$this->version = WDM_AI_BOTKIT_EXTENSION_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'wdm-ai-botkit-extension';

		// Always load dependencies to ensure loader is available
		$this->load_dependencies();

		// Check if AI BotKit is active before proceeding
		if ( ! Wdm_Ai_Botkit_Extension_License_Manager::is_ai_botkit_active() ) {
			// Add admin notice about missing dependency
			$this->loader->add_action( 'admin_notices', $this, 'show_missing_dependency_notice' );
			return;
		}

		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Wdm_Ai_Botkit_Extension_Loader. Orchestrates the hooks of the plugin.
	 * - Wdm_Ai_Botkit_Extension_i18n. Defines internationalization functionality.
	 * - Wdm_Ai_Botkit_Extension_Admin. Defines all hooks for the admin area.
	 * - Wdm_Ai_Botkit_Extension_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wdm-ai-botkit-extension-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wdm-ai-botkit-extension-i18n.php';

		/**
		 * The class responsible for license management and validation.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-wdm-ai-botkit-extension-license-manager.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wdm-ai-botkit-extension-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-wdm-ai-botkit-extension-public.php';

		$this->loader = new Wdm_Ai_Botkit_Extension_Loader();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Wdm_Ai_Botkit_Extension_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Wdm_Ai_Botkit_Extension_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Wdm_Ai_Botkit_Extension_Admin( $this->get_plugin_name(), $this->get_version() );

		// Initialize license manager
		$license_manager = new Wdm_Ai_Botkit_Extension_License_Manager();

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		
		// Integrate with AI BotKit admin interface using action hooks
		$this->loader->add_action( 'ai_botkit_sidebar_menu_items', $plugin_admin, 'add_extension_sidebar_menu' );
		$this->loader->add_action( 'ai_botkit_admin_tab_content', $plugin_admin, 'add_extension_tab_content' );
		
		// Register tab with AI BotKit
		$this->loader->add_filter( 'ai_botkit_admin_tabs', $plugin_admin, 'register_extension_tab' );
		
		// Add settings link to plugins page
		$this->loader->add_filter( 'plugin_action_links_' . plugin_basename( dirname( __FILE__ ) . '/../wdm-ai-botkit-extension.php' ), $plugin_admin, 'add_plugin_action_links' );
		
		// Always add AI BotKit filters - runtime license checking is done within the methods
		$this->loader->add_filter( 'ai_botkit_user_aware_context', $plugin_admin, 'wdm_ai_botkit_user_aware_context', 10, 2 );
		$this->loader->add_filter( 'ai_botkit_post_content', $plugin_admin, 'wdm_ai_botkit_post_content', 10 ,2 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Wdm_Ai_Botkit_Extension_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Wdm_Ai_Botkit_Extension_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Show admin notice when AI BotKit dependency is missing.
	 *
	 * @since     1.0.0
	 */
	public function show_missing_dependency_notice() {
		echo '<div class="notice notice-error is-dismissible">';
		echo '<p><strong>' . __( 'WDM AI BotKit Extension', 'wdm-ai-botkit-extension' ) . '</strong>: ' . 
		     __( 'This extension requires AI BotKit plugin to be installed and activated. Please install and activate AI BotKit plugin first.', 'wdm-ai-botkit-extension' ) . '</p>';
		echo '</div>';
	}

}
