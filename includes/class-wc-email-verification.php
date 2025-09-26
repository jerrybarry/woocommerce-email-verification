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
        // Load text domain at proper time
        add_action('init', array($this, 'load_textdomain'), 1);
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Initialize other classes
        add_action('init', array($this, 'init_classes'));
        
        // Check for plugin updates
        add_action('init', array($this, 'check_version'));
        
        // Block login for unverified users
        add_filter('wp_authenticate_user', array($this, 'check_user_verification_on_login'), 10, 2);
    }
    
    /**
     * Load text domain
     */
    public function load_textdomain() {
        // Use the safe helper function
        if (function_exists('wc_woo_email_verification_load_textdomain')) {
            wc_woo_email_verification_load_textdomain();
        }
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Session is already started by the main plugin file
        // This method can be used for other initialization if needed
    }
    
    /**
     * Initialize other classes
     */
    public function init_classes() {
        try {
            // Initialize admin
            if (is_admin() && class_exists('WC_Email_Verification_Admin')) {
                new WC_Email_Verification_Admin();
            }
            
            // Initialize AJAX handlers
            if (class_exists('WC_Email_Verification_Ajax')) {
                new WC_Email_Verification_Ajax();
            }
            
            // Initialize frontend
            if (class_exists('WC_Email_Verification_Frontend')) {
                new WC_Email_Verification_Frontend();
            }
            
            // Initialize email handler
            if (class_exists('WC_Email_Verification_Email')) {
                new WC_Email_Verification_Email();
            }
        } catch (Exception $e) {
            // Log error but don't break the site
            error_log('WC Email Verification: Error initializing classes - ' . $e->getMessage());
        }
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
            
            // Ensure text domain is loaded before using translation functions
            if (function_exists('wc_woo_email_verification_load_textdomain')) {
                wc_woo_email_verification_load_textdomain();
            }
            
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
            // Ensure text domain is loaded before using translation functions
            if (function_exists('wc_woo_email_verification_load_textdomain')) {
                wc_woo_email_verification_load_textdomain();
            }
            
            // Enqueue WordPress editor scripts and styles for WYSIWYG
            wp_enqueue_editor();
            
            wp_enqueue_script(
                'wc-email-verification-admin',
                WC_EMAIL_VERIFICATION_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery', 'wp-editor'),
                WC_EMAIL_VERIFICATION_VERSION,
                true
            );
            
            // Add inline script to ensure ajaxurl is available
            wp_add_inline_script('wc-email-verification-admin', 'var ajaxurl = "' . admin_url('admin-ajax.php') . '";', 'before');
            
            wp_enqueue_style(
                'wc-email-verification-admin',
                WC_EMAIL_VERIFICATION_PLUGIN_URL . 'assets/css/admin.css',
                array('wp-admin', 'dashicons'),
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
            'email_subject' => 'Your Verification Code - {site_name}',
            'email_template' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <div style="background: #0073aa; color: white; padding: 20px; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">Email Verification</h1>
    </div>
    <div style="padding: 30px 20px;">
        <h2 style="color: #333; margin-bottom: 20px;">Verify Your Email Address</h2>
        <p style="color: #666; font-size: 16px; line-height: 1.6;">Thank you for registering with {site_name}. To complete your registration, please verify your email address using the code below:</p>
        
        <div style="background: #f8f9fa; border: 2px solid #0073aa; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;">
            <p style="margin: 0 0 10px 0; color: #333; font-size: 18px; font-weight: bold;">Your Verification Code:</p>
            <div style="background: #0073aa; color: white; font-size: 32px; font-weight: bold; padding: 15px; border-radius: 4px; letter-spacing: 3px; margin: 10px 0;">{verification_code}</div>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 20px 0;">This code will expire in <strong>{expiry_time} minutes</strong>.</p>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; color: #856404; font-size: 14px;"><strong>Security Notice:</strong> If you didn\'t request this verification code, please ignore this email. Your account security is important to us.</p>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 30px 0 0 0;">Best regards,<br>The {site_name} Team</p>
    </div>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef;">
        <p style="margin: 0; color: #6c757d; font-size: 12px;">This email was sent from {site_name} | <a href="{site_url}" style="color: #0073aa;">Visit our website</a></p>
    </div>
</div>',
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
            'email_subject' => 'Your Verification Code - {site_name}',
            'email_template' => '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <div style="background: #0073aa; color: white; padding: 20px; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">Email Verification</h1>
    </div>
    <div style="padding: 30px 20px;">
        <h2 style="color: #333; margin-bottom: 20px;">Verify Your Email Address</h2>
        <p style="color: #666; font-size: 16px; line-height: 1.6;">Thank you for registering with {site_name}. To complete your registration, please verify your email address using the code below:</p>
        
        <div style="background: #f8f9fa; border: 2px solid #0073aa; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;">
            <p style="margin: 0 0 10px 0; color: #333; font-size: 18px; font-weight: bold;">Your Verification Code:</p>
            <div style="background: #0073aa; color: white; font-size: 32px; font-weight: bold; padding: 15px; border-radius: 4px; letter-spacing: 3px; margin: 10px 0;">{verification_code}</div>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 20px 0;">This code will expire in <strong>{expiry_time} minutes</strong>.</p>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; color: #856404; font-size: 14px;"><strong>Security Notice:</strong> If you didn\'t request this verification code, please ignore this email. Your account security is important to us.</p>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 30px 0 0 0;">Best regards,<br>The {site_name} Team</p>
    </div>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef;">
        <p style="margin: 0; color: #6c757d; font-size: 12px;">This email was sent from {site_name} | <a href="{site_url}" style="color: #0073aa;">Visit our website</a></p>
    </div>
</div>',
            'from_name' => get_bloginfo('name'),
            'from_email' => get_option('admin_email'),
        );
        
        $updated_settings = array_merge($default_settings, $current_settings);
        update_option('wc_email_verification_settings', $updated_settings);
    }
    
    /**
     * Get default email template
     *
     * @return string
     */
    public function get_default_email_template() {
        return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: {background_color};">
    <div style="background: linear-gradient(135deg, {primary_color} 0%, {secondary_color} 100%); color: white; padding: 20px; text-align: center;">
        {email_logo}
        <h1 style="margin: 0; font-size: 24px;">{header_title}</h1>
    </div>
    <div style="padding: 30px 20px; background: #ffffff;">
        <h2 style="color: {text_color}; margin-bottom: 20px;">{main_heading}</h2>
        <p style="color: #666; font-size: 16px; line-height: 1.6;">{intro_text}</p>
        
        <div style="background: #f8f9fa; border: 2px solid {primary_color}; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;">
            <p style="margin: 0 0 10px 0; color: {text_color}; font-size: 18px; font-weight: bold;">{code_label}</p>
            <div style="background: {primary_color}; color: white; font-size: 32px; font-weight: bold; padding: 15px; border-radius: 4px; letter-spacing: 3px; margin: 10px 0;">{verification_code}</div>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 20px 0;">{expiry_text}</p>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; color: #856404; font-size: 14px;"><strong>Security Notice:</strong> {security_notice}</p>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 30px 0 0 0;">{footer_text}</p>
    </div>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef;">
        <p style="margin: 0; color: #6c757d; font-size: 12px;">This email was sent from {site_name} | <a href="{site_url}" style="color: {primary_color};">Visit our website</a></p>
    </div>
</div>';
    }
    
    /**
     * Check user verification status on login
     *
     * @param WP_User $user
     * @param string $password
     * @return WP_User|WP_Error
     */
    public function check_user_verification_on_login($user, $password) {
        // Skip verification check for admin users
        if (is_wp_error($user) || $user->ID === 0) {
            return $user;
        }
        
        // Skip check if plugin is disabled
        if ($this->get_setting('enabled', 'yes') !== 'yes') {
            return $user;
        }
        
        // Only check for regular users, not admin users with manage_options capability  
        if (user_can($user, 'manage_options')) {
            return $user;
        }
        
        // Get verification status from user meta
        $is_verified = get_user_meta($user->ID, 'wc_email_verified', true);
        
        // If user is not verified, deny login
        if (!$is_verified) {
            // Allow temporary bypass during verification
            // Also allow admins to bypass this if they have certain capabilities
            
            // Create WP_Error with custom message
            $error = new WP_Error(
                'email_not_verified',
                sprintf(
                    __('Your email address has not been verified. Please check your inbox for the verification email or contact support. %s', 'wc-email-verification'),
                    '<a href="' . wp_lostpassword_url() . '">' . __('Reset your password', 'wc-email-verification') . '</a>'
                )
            );
            
            return $error;
        }
        
        return $user;
    }
}
