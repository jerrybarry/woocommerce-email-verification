<?php
/**
 * Main plugin class
 *
 * @package WC_Email_Verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Email_Verification {
    
    /**
     * Plugin instance
     *
     * @var WC_Email_Verification
     */
    private static $instance = null;
    
    /**
     * Plugin settings
     *
     * @var array
     */
    private $settings = array();
    
    /**
     * Get plugin instance
     *
     * @return WC_Email_Verification
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
        $this->load_settings();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Initialize other classes
        add_action('init', array($this, 'init_classes'));
        
        // Check for plugin updates
        add_action('init', array($this, 'check_version'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Load text domain
        load_plugin_textdomain('wc-email-verification', false, dirname(plugin_basename(WC_EMAIL_VERIFICATION_PLUGIN_FILE)) . '/languages');
        
        // Start session if not already started
        if (!session_id()) {
            session_start();
        }
    }
    
    /**
     * Initialize other classes
     */
    public function init_classes() {
        // Initialize admin
        if (is_admin()) {
            new WC_Email_Verification_Admin();
        }
        
        // Initialize AJAX handlers
        new WC_Email_Verification_Ajax();
        
        // Initialize frontend
        new WC_Email_Verification_Frontend();
        
        // Initialize email handler
        new WC_Email_Verification_Email();
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        // Check if we're on checkout, account page, or any page with WooCommerce forms
        if (is_checkout() || is_account_page() || is_wc_endpoint_url('lost-password') || 
            (function_exists('is_woocommerce') && is_woocommerce()) ||
            (isset($_GET['action']) && $_GET['action'] === 'register') ||
            (is_page() && (strpos(get_permalink(), 'register') !== false || strpos(get_permalink(), 'my-account') !== false))) {
            wp_enqueue_script(
                'wc-email-verification',
                WC_EMAIL_VERIFICATION_PLUGIN_URL . 'assets/js/email-verification.js',
                array('jquery', 'wc-checkout'),
                WC_EMAIL_VERIFICATION_VERSION,
                true
            );
            
            wp_enqueue_style(
                'wc-email-verification',
                WC_EMAIL_VERIFICATION_PLUGIN_URL . 'assets/css/email-verification.css',
                array(),
                WC_EMAIL_VERIFICATION_VERSION
            );
            
            // Localize script
            wp_localize_script('wc-email-verification', 'wcEmailVerification', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_email_verification_nonce'),
                'settings' => $this->settings,
                'messages' => array(
                    'invalidEmail' => __('Please enter a valid email address.', 'wc-email-verification'),
                    'codeSent' => __('Verification code sent to your email!', 'wc-email-verification'),
                    'codeVerified' => __('Email verified successfully!', 'wc-email-verification'),
                    'invalidCode' => __('Invalid or expired verification code.', 'wc-email-verification'),
                    'networkError' => __('Network error. Please try again.', 'wc-email-verification'),
                    'verifyEmailFirst' => __('Please verify your email address before proceeding.', 'wc-email-verification'),
                    'enterCode' => __('Please enter the verification code.', 'wc-email-verification'),
                )
            ));
        }
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'wc-email-verification') !== false) {
            wp_enqueue_script(
                'wc-email-verification-admin',
                WC_EMAIL_VERIFICATION_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                WC_EMAIL_VERIFICATION_VERSION,
                true
            );
            
            wp_enqueue_style(
                'wc-email-verification-admin',
                WC_EMAIL_VERIFICATION_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                WC_EMAIL_VERIFICATION_VERSION
            );
        }
    }
    
    /**
     * Load plugin settings
     */
    private function load_settings() {
        $this->settings = get_option('wc_email_verification_settings', array());
    }
    
    /**
     * Get plugin settings
     *
     * @return array
     */
    public function get_settings() {
        return $this->settings;
    }
    
    /**
     * Get specific setting value
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get_setting($key, $default = '') {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Set default options
     */
    public static function set_default_options() {
        $default_settings = array(
            'enabled' => 'yes',
            'checkout_required' => 'yes',
            'registration_required' => 'yes',
            'code_expiry' => 10, // minutes
            'code_length' => 6,
            'rate_limit' => 5, // attempts per hour
            'email_subject' => __('Your Verification Code - {site_name}', 'wc-email-verification'),
            'email_template' => __('Your verification code is: <strong>{verification_code}</strong><br><br>This code will expire in {expiry_time} minutes.<br><br>If you didn\'t request this code, please ignore this email.', 'wc-email-verification'),
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
        );
        
        add_option('wc_email_verification_settings', $default_settings);
        add_option('wc_email_verification_version', WC_EMAIL_VERIFICATION_VERSION);
    }
    
    /**
     * Check for plugin version updates
     */
    public function check_version() {
        $installed_version = get_option('wc_email_verification_version');
        
        if ($installed_version !== WC_EMAIL_VERIFICATION_VERSION) {
            $this->update_plugin();
            update_option('wc_email_verification_version', WC_EMAIL_VERIFICATION_VERSION);
        }
    }
    
    /**
     * Update plugin
     */
    private function update_plugin() {
        // Update database if needed
        WC_Email_Verification_Database::update_tables();
        
        // Update settings if needed
        $this->update_settings();
    }
    
    /**
     * Update settings for new version
     */
    private function update_settings() {
        $current_settings = get_option('wc_email_verification_settings', array());
        $default_settings = array(
            'enabled' => 'yes',
            'checkout_required' => 'yes',
            'registration_required' => 'yes',
            'code_expiry' => 10,
            'code_length' => 6,
            'rate_limit' => 5,
            'email_subject' => __('Your Verification Code - {site_name}', 'wc-email-verification'),
            'email_template' => __('Your verification code is: <strong>{verification_code}</strong><br><br>This code will expire in {expiry_time} minutes.<br><br>If you didn\'t request this code, please ignore this email.', 'wc-email-verification'),
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
        );
        
        $updated_settings = array_merge($default_settings, $current_settings);
        update_option('wc_email_verification_settings', $updated_settings);
    }
}
