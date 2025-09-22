<?php
/**
 * Plugin Name: WooCommerce Email Verification
 * Plugin URI: https://github.com/jerrybarry/woocommerce-email-verification
 * Description: Adds email verification functionality to WooCommerce checkout and registration processes.
 * Version: 1.0.0
 * Author: Jerry Barry
 * Author URI: https://github.com/jerrybarry
 * Text Domain: wc-email-verification
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WC_EMAIL_VERIFICATION_VERSION', '1.0.0');
define('WC_EMAIL_VERIFICATION_PLUGIN_FILE', __FILE__);
define('WC_EMAIL_VERIFICATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_EMAIL_VERIFICATION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_EMAIL_VERIFICATION_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'wc_email_verification_woocommerce_missing_notice');
    return;
}

/**
 * Display admin notice if WooCommerce is not active
 */
function wc_email_verification_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('WooCommerce Email Verification requires WooCommerce to be installed and active.', 'wc-email-verification'); ?></p>
    </div>
    <?php
}

// Include required files
require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification.php';
require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification-admin.php';
require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification-ajax.php';
require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification-database.php';
require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification-email.php';
require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification-frontend.php';
require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification-logger.php';

/**
 * Initialize the plugin
 */
function wc_email_verification_init() {
    // Initialize main plugin class
    WC_Email_Verification::get_instance();
}

// Hook into WordPress
add_action('plugins_loaded', 'wc_email_verification_init');

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'wc_email_verification_activate');
function wc_email_verification_activate() {
    // Create database tables
    WC_Email_Verification_Database::create_tables();
    
    // Set default options
    WC_Email_Verification::set_default_options();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'wc_email_verification_deactivate');
function wc_email_verification_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin uninstall hook
 */
register_uninstall_hook(__FILE__, 'wc_email_verification_uninstall');
function wc_email_verification_uninstall() {
    // Remove database tables
    WC_Email_Verification_Database::drop_tables();
    
    // Remove options
    delete_option('wc_email_verification_settings');
    delete_option('wc_email_verification_version');
}
