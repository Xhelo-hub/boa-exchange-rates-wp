<?php
/**
 * Plugin Name: BoA Exchange Rates
 * Plugin URI: https://github.com/your-repo/boa-exchange-rates-wp
 * Description: Display Bank of Albania official exchange rates on your WordPress site with automatic daily updates.
 * Version: 1.2
 * Author: Your Name
 * Author URI: https://your-website.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: boa-exchange-rates
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('BOA_RATES_VERSION', '1.2');
define('BOA_RATES_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('BOA_RATES_PLUGIN_URL', plugin_dir_url(__FILE__));
define('BOA_RATES_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once BOA_RATES_PLUGIN_DIR . 'includes/class-boa-scraper.php';
require_once BOA_RATES_PLUGIN_DIR . 'includes/class-boa-admin.php';
require_once BOA_RATES_PLUGIN_DIR . 'includes/class-boa-shortcode.php';
require_once BOA_RATES_PLUGIN_DIR . 'includes/class-boa-cron.php';

/**
 * Main plugin class
 */
class BoA_Exchange_Rates {

    /**
     * Single instance
     */
    private static $instance = null;

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize components
        add_action('plugins_loaded', array($this, 'init_components'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create default options
        $default_options = array(
            'selected_currencies' => array('USD', 'EUR', 'GBP', 'CHF'),
            'display_mode' => 'table',
            'show_icons' => true,
            'show_date' => true,
            'table_style' => 'default',
            'decimal_places' => 2,
            'icon_color' => '#333333',
            'icon_size' => 24,
            'icon_style' => 'circle-flags',
            'last_update' => '',
            'last_boa_date' => '',
            'rates_data' => array(),
        );

        if (!get_option('boa_rates_options')) {
            add_option('boa_rates_options', $default_options);
        }

        // Schedule cron job
        BoA_Cron::schedule_events();

        // Fetch initial rates
        $scraper = new BoA_Scraper();
        $scraper->fetch_and_save_rates();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        BoA_Cron::clear_events();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Initialize components
     */
    public function init_components() {
        // Load text domain
        load_plugin_textdomain('boa-exchange-rates', false, dirname(BOA_RATES_PLUGIN_BASENAME) . '/languages');

        // Initialize admin
        if (is_admin()) {
            new BoA_Admin();
        }

        // Initialize shortcode
        new BoA_Shortcode();

        // Initialize cron
        new BoA_Cron();
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Iconify CDN for icons - load in header for proper initialization
        wp_enqueue_script(
            'iconify',
            'https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js',
            array(),
            '2.1.0',
            false // Load in header
        );

        wp_enqueue_style(
            'boa-rates-frontend',
            BOA_RATES_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            BOA_RATES_VERSION
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'boa-exchange-rates') === false) {
            return;
        }

        // Iconify CDN for icons - web component version
        wp_enqueue_script(
            'iconify',
            'https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js',
            array(),
            '2.1.0',
            false // Load in header
        );

        wp_enqueue_style(
            'boa-rates-admin',
            BOA_RATES_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            BOA_RATES_VERSION
        );

        wp_enqueue_script(
            'boa-rates-admin',
            BOA_RATES_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'iconify'),
            BOA_RATES_VERSION,
            true
        );

        wp_localize_script('boa-rates-admin', 'boaRatesAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('boa_rates_nonce'),
            'strings' => array(
                'refreshing' => __('Refreshing...', 'boa-exchange-rates'),
                'refreshed' => __('Rates updated successfully!', 'boa-exchange-rates'),
                'error' => __('Error updating rates. Please try again.', 'boa-exchange-rates'),
            ),
        ));
    }
}

// Initialize plugin
BoA_Exchange_Rates::get_instance();
