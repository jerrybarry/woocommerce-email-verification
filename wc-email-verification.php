<?php
/**
 * Plugin Name: WooCommerce Email Verification
 * Plugin URI: https://github.com/jerrybarry/woocommerce-email-verification
 * Description: Adds email verification functionality to WooCommerce checkout and registration processes.
 * Version: 1.2.2
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
define('WC_EMAIL_VERIFICATION_VERSION', '1.2.2');
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
add_action('plugins_loaded', 'wc_email_verification_init');

/**
 * Register text domain for Polylang
 */
add_action('init', function() {
    if (function_exists('pll_register_string')) {
        // Register strings for Polylang with proper grouping
        $strings = array(
            // Frontend UI strings
            'Email Verification' => 'Email Verification',
            'Verify your email address to proceed' => 'Verify your email address to proceed',
            'Send Verification Code' => 'Send Verification Code',
            'Enter Verification Code' => 'Enter Verification Code',
            'We sent a %d-digit code to your email address' => 'We sent a %d-digit code to your email address',
            'Verify' => 'Verify',
            'Resend Code' => 'Resend Code',
            'Resend available in %s' => 'Resend available in %s',
            'Email Verified!' => 'Email Verified!',
            'Your email address has been successfully verified.' => 'Your email address has been successfully verified.',
            
            // Error and success messages
            'Please enter a valid email address.' => 'Please enter a valid email address.',
            'Verification code sent to your email!' => 'Verification code sent to your email!',
            'Email verified successfully!' => 'Email verified successfully!',
            'Invalid or expired verification code.' => 'Invalid or expired verification code.',
            'Network error. Please try again.' => 'Network error. Please try again.',
            'Please verify your email address before proceeding.' => 'Please verify your email address before proceeding.',
            'Please enter the verification code.' => 'Please enter the verification code.',
            'This email address has already been verified.' => 'This email address has already been verified.',
            'Too many verification attempts. Please try again later.' => 'Too many verification attempts. Please try again later.',
            'Security check failed.' => 'Security check failed.',
            'Database error occurred.' => 'Database error occurred.',
            'Failed to send email. Please try again.' => 'Failed to send email. Please try again.',
            
            // Email template strings
            'Verify Your Email Address' => 'Verify Your Email Address',
            'Thank you for registering with %s. To complete your registration, please verify your email address using the code below:' => 'Thank you for registering with %s. To complete your registration, please verify your email address using the code below:',
            'Your Verification Code:' => 'Your Verification Code:',
            'This code will expire in %s minutes.' => 'This code will expire in %s minutes.',
            'Security Notice:' => 'Security Notice:',
            'If you didn\'t request this verification code, please ignore this email. Your account security is important to us.' => 'If you didn\'t request this verification code, please ignore this email. Your account security is important to us.',
            'Best regards,<br>The %s Team' => 'Best regards,<br>The %s Team',
            'This email was sent from %s | %s' => 'This email was sent from %s | %s',
            'Visit our website' => 'Visit our website',
            
            // Admin strings
            'Settings saved successfully!' => 'Settings saved successfully!',
            'Enable Email Verification' => 'Enable Email Verification',
            'Enable email verification system' => 'Enable email verification system',
            'Checkout Verification' => 'Checkout Verification',
            'Require email verification for checkout' => 'Require email verification for checkout',
            'Registration Verification' => 'Registration Verification',
            'Require email verification for registration' => 'Require email verification for registration',
            'Code Length' => 'Code Length',
            'Number of digits in verification code' => 'Number of digits in verification code',
            'Code Expiry (minutes)' => 'Code Expiry (minutes)',
            'How long verification codes remain valid' => 'How long verification codes remain valid',
            'Rate Limit' => 'Rate Limit',
            'Maximum verification attempts per hour' => 'Maximum verification attempts per hour',
            'Email Subject' => 'Email Subject',
            'Subject line for verification emails' => 'Subject line for verification emails',
            'Email Template' => 'Email Template',
            'HTML template for verification emails' => 'HTML template for verification emails',
            'General' => 'General',
            'Email Settings' => 'Email Settings',
            'Security' => 'Security',
            'Logs' => 'Logs',
            'Total Verifications' => 'Total Verifications',
            'Pending Verifications' => 'Pending Verifications',
            'Success Rate' => 'Success Rate'
        );
        
        foreach ($strings as $name => $string) {
            // Determine if multiline based on string content
            $multiline = (strpos($string, '<br>') !== false || strpos($string, '%s') !== false || strlen($string) > 50);
            
            pll_register_string($name, $string, 'wc-email-verification', $multiline);
        }
    }
}, 20);

/**
 * Helper function to translate strings with Polylang
 * Falls back to WordPress __() if Polylang is not available
 */
function wc_email_verification_translate($string, $context = 'wc-email-verification') {
    if (function_exists('pll__')) {
        return pll__($string);
    }
    return __($string, $context);
}

/**
 * Helper function to echo translated strings with Polylang
 * Falls back to WordPress _e() if Polylang is not available
 */
function wc_email_verification_translate_e($string, $context = 'wc-email-verification') {
    if (function_exists('pll_e')) {
        pll_e($string);
    } else {
        _e($string, $context);
    }
}

/**
 * Debug function to check if text domain is loaded (remove in production)
 */
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_notices', function() {
        if (current_user_can('manage_options')) {
            $loaded = __("Email Verification", 'wc-email-verification');
            if ($loaded === "Email Verification") {
                echo '<div class="notice notice-info"><p>WC Email Verification: Text domain loaded successfully. Strings available for translation.</p></div>';
            }
        }
    });
}

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, 'wc_email_verification_activate');
function wc_email_verification_activate() {
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
