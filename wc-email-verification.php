<?php
/**
 * Plugin Name: WooCommerce Email Verification
 * Plugin URI: https://github.com/jerrybarry/woocommerce-email-verification
 * Description: Adds email verification functionality to WooCommerce checkout and registration processes.
 * Version: 1.3.0
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

// Check for plugin conflicts
if (function_exists('wc_email_verification_woocommerce_missing_notice')) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p><strong>Plugin Conflict Detected:</strong> Another email verification plugin is active. Please deactivate the other plugin to avoid conflicts.</p></div>';
    });
    return;
}

// Define plugin constants
define('WC_EMAIL_VERIFICATION_VERSION', '1.2.2');
define('WC_EMAIL_VERIFICATION_PLUGIN_FILE', __FILE__);
define('WC_EMAIL_VERIFICATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_EMAIL_VERIFICATION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_EMAIL_VERIFICATION_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Start session safely
 */
function wc_woo_email_verification_start_session() {
    if (!session_id() && !headers_sent()) {
        session_start();
    }
}

/**
 * Check if session is available
 */
function wc_woo_email_verification_session_available() {
    return session_id() !== '';
}

/**
 * Safely load text domain
 */
function wc_woo_email_verification_load_textdomain() {
    if (!is_textdomain_loaded('wc-email-verification')) {
        load_plugin_textdomain('wc-email-verification', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

// Start session early to avoid header issues
add_action('init', 'wc_woo_email_verification_start_session', 1);

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', 'wc_woo_email_verification_woocommerce_missing_notice');
    return;
}

/**
 * Display admin notice if WooCommerce is not active
 */
function wc_woo_email_verification_woocommerce_missing_notice() {
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
function wc_woo_email_verification_init() {
    try {
        // Check if main class exists
        if (!class_exists('WC_Email_Verification')) {
            error_log('WC Email Verification: Main class not found');
            return;
        }
        
    // Initialize main plugin class
    WC_Email_Verification::get_instance();
        
    } catch (Exception $e) {
        error_log('WC Email Verification: Initialization error - ' . $e->getMessage());
        
        // Show admin notice if in admin area
        if (is_admin()) {
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>WC Email Verification Error: ' . esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
}

// Hook into WordPress
add_action('plugins_loaded', 'wc_woo_email_verification_init');

// Fallback text domain loading
add_action('init', 'wc_woo_email_verification_load_textdomain', 5);



/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'wc_woo_email_verification_activate');
function wc_woo_email_verification_activate() {
    try {
        // Ensure classes are loaded
        if (!class_exists('WC_Email_Verification_Database')) {
            require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification-database.php';
        }
        
        if (!class_exists('WC_Email_Verification')) {
            require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification.php';
        }
        
    // Create database tables
        if (class_exists('WC_Email_Verification_Database')) {
    WC_Email_Verification_Database::create_tables();
        }
    
    // Set default options
        if (class_exists('WC_Email_Verification')) {
    WC_Email_Verification::set_default_options();
        }
    
    // Flush rewrite rules
    flush_rewrite_rules();
        
    } catch (Exception $e) {
        // Log the error
        error_log('WC Email Verification Activation Error: ' . $e->getMessage());
        
        // Deactivate the plugin to prevent further issues
        deactivate_plugins(plugin_basename(__FILE__));
        
        // Show error message
        wp_die(
            'WooCommerce Email Verification plugin activation failed: ' . $e->getMessage() . 
            '<br><br>Please check your error logs and try again.',
            'Plugin Activation Error',
            array('back_link' => true)
        );
    }
}

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, 'wc_woo_email_verification_deactivate');
function wc_woo_email_verification_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Plugin uninstall hook
 */
register_uninstall_hook(__FILE__, 'wc_woo_email_verification_uninstall');
function wc_woo_email_verification_uninstall() {
    // Ensure classes are loaded
    if (!class_exists('WC_Email_Verification_Database')) {
        require_once WC_EMAIL_VERIFICATION_PLUGIN_DIR . 'includes/class-wc-email-verification-database.php';
    }
    
    // Remove database tables
    WC_Email_Verification_Database::drop_tables();
    
    // Remove options
    delete_option('wc_email_verification_settings');
    delete_option('wc_email_verification_version');
}
