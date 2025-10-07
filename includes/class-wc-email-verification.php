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
        
        // WordPress user management hooks
        add_action('delete_user', array($this, 'cleanup_user_verification_data'));
        add_action('user_register', array($this, 'handle_wordpress_user_registration'));
        add_action('profile_update', array($this, 'handle_user_email_change'), 10, 2);
        
        // WooCommerce user management hooks
        add_action('woocommerce_delete_user', array($this, 'cleanup_user_verification_data'));
        
        // WordPress admin users page hooks
        add_action('restrict_manage_users', array($this, 'add_verification_filter'));
        add_action('pre_get_users', array($this, 'filter_users_by_verification'));
        add_filter('manage_users_columns', array($this, 'add_verification_column'));
        add_filter('manage_users_custom_column', array($this, 'show_verification_column_content'), 10, 3);
        add_filter('bulk_actions-users', array($this, 'add_bulk_verification_actions'));
        add_filter('handle_bulk_actions-users', array($this, 'handle_bulk_verification_actions'), 10, 3);
        add_action('admin_notices', array($this, 'bulk_verification_admin_notices'));
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
            
            // Add inline CSS for users page enhancements
            $custom_css = '
                .column-email_verification {
                    width: 140px;
                }
                .column-email_verification small {
                    display: block;
                    margin-top: 3px;
                    font-style: italic;
                }
                #verification_status {
                    margin-left: 10px;
                }
            ';
            wp_add_inline_style('wc-email-verification-admin', $custom_css);
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
    
    /**
     * Clean up verification data when user is deleted
     *
     * @param int $user_id
     */
    public function cleanup_user_verification_data($user_id) {
        if (!$user_id) {
            return;
        }
        
        // Get user email before deletion
        $user = get_user_by('id', $user_id);
        if (!$user || !$user->user_email) {
            return;
        }
        
        $email = $user->user_email;
        
        // Clean up database records
        WC_Email_Verification_Database::delete_verification_records($email);
        
        // Clean up verification logs for this email
        global $wpdb;
        $logs_table = $wpdb->prefix . 'wc_email_verification_logs';
        $wpdb->delete($logs_table, array('email' => $email), array('%s'));
        
        // Clean up rate limiting records
        $rate_limit_table = $wpdb->prefix . 'wc_email_verification_rate_limits';
        $wpdb->delete($rate_limit_table, array('identifier' => '%' . $email), array('%s'));
        
        // Clean up session data if session is available
        if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
            $verification_key = 'wc_email_verified_' . md5($email);
            unset($_SESSION[$verification_key]);
        }
    }
    
    /**
     * Handle WordPress user registration
     *
     * @param int $user_id
     */
    public function handle_wordpress_user_registration($user_id) {
        if (!$user_id) {
            return;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !$user->user_email) {
            return;
        }
        
        $email = $user->user_email;
        
        // Check if this email was verified in session before registration
        if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
            $verification_key = 'wc_email_verified_' . md5($email);
            
            if (isset($_SESSION[$verification_key]) && $_SESSION[$verification_key]) {
                // Mark user as verified
                update_user_meta($user_id, 'wc_email_verified', true);
                update_user_meta($user_id, 'wc_email_verified_date', current_time('mysql'));
                
                // Clean up session
                unset($_SESSION[$verification_key]);
                
                // Log verification
                WC_Email_Verification_Database::log_action($email, 'user_verified', array(
                    'user_id' => $user_id,
                    'source' => 'wordpress_registration'
                ));
            }
        }
        
        // Check if there's a verified record in database
        $record = WC_Email_Verification_Database::get_verification_record($email);
        if ($record && $record->verified == 1) {
            // Mark user as verified
            update_user_meta($user_id, 'wc_email_verified', true);
            update_user_meta($user_id, 'wc_email_verified_date', $record->verified_at);
            
            // Log verification
            WC_Email_Verification_Database::log_action($email, 'user_verified', array(
                'user_id' => $user_id,
                'source' => 'database_record'
            ));
        }
    }
    
    /**
     * Handle user email change
     *
     * @param int $user_id
     * @param WP_User $old_user_data
     */
    public function handle_user_email_change($user_id, $old_user_data) {
        if (!$user_id || !$old_user_data) {
            return;
        }
        
        $user = get_user_by('id', $user_id);
        if (!$user || !$user->user_email) {
            return;
        }
        
        $old_email = $old_user_data->user_email;
        $new_email = $user->user_email;
        
        // If email changed, we need to handle verification status
        if ($old_email !== $new_email) {
            // Get old verification status
            $was_verified = get_user_meta($user_id, 'wc_email_verified', true);
            
            if ($was_verified) {
                // User was verified with old email, transfer verification to new email
                update_user_meta($user_id, 'wc_email_verified', true);
                update_user_meta($user_id, 'wc_email_verified_date', current_time('mysql'));
                
                // Update database records
                WC_Email_Verification_Database::update_verification_email($old_email, $new_email);
                
                // Clean up old email verification data
                WC_Email_Verification_Database::delete_verification_records($old_email);
                
                // Log the email change
                WC_Email_Verification_Database::log_action($new_email, 'email_changed', array(
                    'user_id' => $user_id,
                    'old_email' => $old_email,
                    'new_email' => $new_email
                ));
            } else {
                // User wasn't verified, remove verification status for new email
                delete_user_meta($user_id, 'wc_email_verified');
                delete_user_meta($user_id, 'wc_email_verified_date');
                
                // Clean up any existing verification data for new email
                WC_Email_Verification_Database::delete_verification_records($new_email);
            }
            
            // Clean up session data for old email
            if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
                $old_verification_key = 'wc_email_verified_' . md5($old_email);
                unset($_SESSION[$old_verification_key]);
                
                if ($was_verified) {
                    // Set session for new email
                    $new_verification_key = 'wc_email_verified_' . md5($new_email);
                    $_SESSION[$new_verification_key] = true;
                }
            }
        }
    }
    
    /**
     * Add verification filter dropdown to users page
     */
    public function add_verification_filter() {
        $screen = get_current_screen();
        if ($screen->id !== 'users') {
            return;
        }
        
        $selected = isset($_GET['verification_status']) ? $_GET['verification_status'] : '';
        
        echo '<select name="verification_status" id="verification_status">';
        echo '<option value="">' . __('All verification statuses', 'wc-email-verification') . '</option>';
        echo '<option value="verified"' . selected($selected, 'verified', false) . '>' . __('Verified', 'wc-email-verification') . '</option>';
        echo '<option value="unverified"' . selected($selected, 'unverified', false) . '>' . __('Unverified', 'wc-email-verification') . '</option>';
        echo '</select>';
        
        echo '<script>
        jQuery(document).ready(function($) {
            $("#verification_status").change(function() {
                $(this).closest("form").submit();
            });
        });
        </script>';
    }
    
    /**
     * Filter users by verification status
     *
     * @param WP_User_Query $query
     */
    public function filter_users_by_verification($query) {
        if (!is_admin() || !isset($_GET['verification_status']) || empty($_GET['verification_status'])) {
            return;
        }
        
        $verification_status = $_GET['verification_status'];
        
        if ($verification_status === 'verified') {
            $query->set('meta_query', array(
                array(
                    'key' => 'wc_email_verified',
                    'value' => '1',
                    'compare' => '='
                )
            ));
        } elseif ($verification_status === 'unverified') {
            $query->set('meta_query', array(
                'relation' => 'OR',
                array(
                    'key' => 'wc_email_verified',
                    'compare' => 'NOT EXISTS'
                ),
                array(
                    'key' => 'wc_email_verified',
                    'value' => '1',
                    'compare' => '!='
                )
            ));
        }
    }
    
    /**
     * Add verification column to users table
     *
     * @param array $columns
     * @return array
     */
    public function add_verification_column($columns) {
        $columns['email_verification'] = __('Email Verification', 'wc-email-verification');
        return $columns;
    }
    
    /**
     * Show verification column content
     *
     * @param string $value
     * @param string $column_name
     * @param int $user_id
     * @return string
     */
    public function show_verification_column_content($value, $column_name, $user_id) {
        if ($column_name === 'email_verification') {
            $is_verified = get_user_meta($user_id, 'wc_email_verified', true);
            $verified_date = get_user_meta($user_id, 'wc_email_verified_date', true);
            
            if ($is_verified) {
                $status_text = '<span style="color: green; font-weight: bold;">✓ ' . __('Verified', 'wc-email-verification') . '</span>';
                if ($verified_date) {
                    $status_text .= '<br><small>' . sprintf(
                        __('Verified on: %s', 'wc-email-verification'),
                        date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($verified_date))
                    ) . '</small>';
                }
            } else {
                $status_text = '<span style="color: red; font-weight: bold;">✗ ' . __('Unverified', 'wc-email-verification') . '</span>';
            }
            
            return $status_text;
        }
        
        return $value;
    }
    
    /**
     * Add bulk verification actions
     *
     * @param array $bulk_actions
     * @return array
     */
    public function add_bulk_verification_actions($bulk_actions) {
        $bulk_actions['verify_emails'] = __('Mark as Email Verified', 'wc-email-verification');
        $bulk_actions['unverify_emails'] = __('Mark as Email Unverified', 'wc-email-verification');
        return $bulk_actions;
    }
    
    /**
     * Handle bulk verification actions
     *
     * @param string $redirect_to
     * @param string $doaction
     * @param array $user_ids
     * @return string
     */
    public function handle_bulk_verification_actions($redirect_to, $doaction, $user_ids) {
        if ($doaction === 'verify_emails') {
            $verified_count = 0;
            
            foreach ($user_ids as $user_id) {
                // Skip admin users
                if (user_can($user_id, 'manage_options')) {
                    continue;
                }
                
                $user = get_user_by('id', $user_id);
                if ($user && $user->user_email) {
                    update_user_meta($user_id, 'wc_email_verified', true);
                    update_user_meta($user_id, 'wc_email_verified_date', current_time('mysql'));
                    
                    // Log the action
                    WC_Email_Verification_Database::log_action($user->user_email, 'bulk_verified', array(
                        'user_id' => $user_id,
                        'admin_action' => true
                    ));
                    
                    $verified_count++;
                }
            }
            
            $redirect_to = add_query_arg('bulk_verified_emails', $verified_count, $redirect_to);
            
        } elseif ($doaction === 'unverify_emails') {
            $unverified_count = 0;
            
            foreach ($user_ids as $user_id) {
                // Skip admin users
                if (user_can($user_id, 'manage_options')) {
                    continue;
                }
                
                $user = get_user_by('id', $user_id);
                if ($user && $user->user_email) {
                    delete_user_meta($user_id, 'wc_email_verified');
                    delete_user_meta($user_id, 'wc_email_verified_date');
                    
                    // Clean up verification data for this email
                    WC_Email_Verification_Database::delete_verification_records($user->user_email);
                    
                    // Log the action
                    WC_Email_Verification_Database::log_action($user->user_email, 'bulk_unverified', array(
                        'user_id' => $user_id,
                        'admin_action' => true
                    ));
                    
                    $unverified_count++;
                }
            }
            
            $redirect_to = add_query_arg('bulk_unverified_emails', $unverified_count, $redirect_to);
        }
        
        return $redirect_to;
    }
    
    /**
     * Show admin notices for bulk actions
     */
    public function bulk_verification_admin_notices() {
        $screen = get_current_screen();
        if ($screen->id !== 'users') {
            return;
        }
        
        if (!empty($_REQUEST['bulk_verified_emails'])) {
            $count = intval($_REQUEST['bulk_verified_emails']);
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                _n(
                    'Email verification status updated for %d user.',
                    'Email verification status updated for %d users.',
                    $count,
                    'wc-email-verification'
                ),
                $count
            );
            echo '</p></div>';
        }
        
        if (!empty($_REQUEST['bulk_unverified_emails'])) {
            $count = intval($_REQUEST['bulk_unverified_emails']);
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                _n(
                    'Email verification status removed for %d user.',
                    'Email verification status removed for %d users.',
                    $count,
                    'wc-email-verification'
                ),
                $count
            );
            echo '</p></div>';
        }
    }
}
