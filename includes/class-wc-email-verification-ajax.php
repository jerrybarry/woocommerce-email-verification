<?php
/**
 * AJAX handler class
 *
 * @package WC_Email_Verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Email_Verification_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Send verification code
        add_action('wp_ajax_wc_send_verification_code', array($this, 'send_verification_code'));
        add_action('wp_ajax_nopriv_wc_send_verification_code', array($this, 'send_verification_code'));
        
        // Verify code
        add_action('wp_ajax_wc_verify_email_code', array($this, 'verify_email_code'));
        add_action('wp_ajax_nopriv_wc_verify_email_code', array($this, 'verify_email_code'));
        
        // Resend code
        add_action('wp_ajax_wc_resend_verification_code', array($this, 'resend_verification_code'));
        add_action('wp_ajax_nopriv_wc_resend_verification_code', array($this, 'resend_verification_code'));
        
        // Test email (admin only)
        add_action('wp_ajax_wc_send_test_email', array($this, 'send_test_email'));
        
        // Check email verification status
        add_action('wp_ajax_wc_check_email_verification_status', array($this, 'check_email_verification_status'));
        add_action('wp_ajax_nopriv_wc_check_email_verification_status', array($this, 'check_email_verification_status'));
        
        // Get default email template
        add_action('wp_ajax_wc_get_default_email_template', array($this, 'get_default_email_template'));
        
    }
    
    /**
     * Send verification code
     */
    public function send_verification_code() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'wc_email_verification_nonce')) {
                throw new Exception(__('Security check failed.', 'wc-email-verification'));
            }
            
            $email = sanitize_email($_POST['email']);
            
            if (!is_email($email)) {
                throw new Exception(__('Please enter a valid email address.', 'wc-email-verification'));
            }
            
            // Check if email is already verified
            // Session should already be started by the main plugin file
            if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
                $verification_key = 'wc_email_verified_' . md5($email);
                if (isset($_SESSION[$verification_key]) && $_SESSION[$verification_key]) {
                    throw new Exception(__('This email address has already been verified.', 'wc-email-verification'));
                }
            }
            
            // Check database for recent verification
            $record = WC_Email_Verification_Database::get_verification_record($email);
            if ($record && $record->verified == 1) {
                // Mark as verified in session
                if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
                    $_SESSION[$verification_key] = true;
                }
                throw new Exception(__('This email address has already been verified.', 'wc-email-verification'));
            }
            
            // Check if this email belongs to an existing user and if they're already verified
            $user = get_user_by('email', $email);
            if ($user && $user->ID) {
                $is_user_verified = get_user_meta($user->ID, 'wc_email_verified', true);
                if ($is_user_verified) {
                    // Mark as verified in session
                    if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
                        $_SESSION[$verification_key] = true;
                    }
                    throw new Exception(__('This email address has already been verified.', 'wc-email-verification'));
                }
            }
            
            // Check rate limiting
            $identifier = $this->get_client_ip() . '_' . $email;
            $rate_limit = WC_Email_Verification::get_instance()->get_setting('rate_limit', 5);
            
            if (!WC_Email_Verification_Database::check_rate_limit($identifier, 'send_code', $rate_limit, 1)) {
                throw new Exception(__('Too many verification attempts. Please try again later.', 'wc-email-verification'));
            }
            
            // Generate verification code
            $code_length = WC_Email_Verification::get_instance()->get_setting('code_length', 6);
            $verification_code = $this->generate_verification_code($code_length);
            
            // Calculate expiry time
            $expiry_minutes = WC_Email_Verification::get_instance()->get_setting('code_expiry', 10);
            $expires_at = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));
            
            // Delete old codes for this email
            WC_Email_Verification_Database::delete_verification_records($email);
            
            // Insert new verification record
            $result = WC_Email_Verification_Database::insert_verification_record(array(
                'email' => $email,
                'verification_code' => $verification_code,
                'expires_at' => $expires_at
            ));
            
            if ($result === false) {
                throw new Exception(__('Database error occurred.', 'wc-email-verification'));
            }
            
            // Send email
            $email_handler = new WC_Email_Verification_Email();
            $email_sent = $email_handler->send_verification_email($email, $verification_code, $expiry_minutes);
            
            if (!$email_sent) {
                throw new Exception(__('Failed to send email. Please try again.', 'wc-email-verification'));
            }
            
            // Log action
            WC_Email_Verification_Database::log_action($email, 'code_sent', array(
                'code_length' => $code_length,
                'expiry_minutes' => $expiry_minutes
            ));
            
            wp_send_json_success(array(
                'message' => __('Verification code sent to your email!', 'wc-email-verification'),
                'expiry_minutes' => $expiry_minutes
            ));
            
        } catch (Exception $e) {
            // Log error
            WC_Email_Verification_Database::log_action(
                isset($email) ? $email : '',
                'send_code_error',
                array('error' => $e->getMessage())
            );
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Verify email code
     */
    public function verify_email_code() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'wc_email_verification_nonce')) {
                throw new Exception(__('Security check failed.', 'wc-email-verification'));
            }
            
            $email = sanitize_email($_POST['email']);
            $code = sanitize_text_field($_POST['code']);
            
            if (!is_email($email) || empty($code)) {
                throw new Exception(__('Invalid email or verification code.', 'wc-email-verification'));
            }
            
            // Check if email is already verified
            // Session should already be started by the main plugin file
            if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
                $verification_key = 'wc_email_verified_' . md5($email);
                if (isset($_SESSION[$verification_key]) && $_SESSION[$verification_key]) {
                    throw new Exception(__('This email address has already been verified.', 'wc-email-verification'));
                }
            }
            
            // Check database for recent verification
            $existing_record = WC_Email_Verification_Database::get_verification_record($email);
            if ($existing_record && $existing_record->verified == 1) {
                throw new Exception(__('This email address has already been verified.', 'wc-email-verification'));
            }
            
            // Check if this email belongs to an existing user and if they're already verified
            $user = get_user_by('email', $email);
            if ($user && $user->ID) {
                $is_user_verified = get_user_meta($user->ID, 'wc_email_verified', true);
                if ($is_user_verified) {
                    throw new Exception(__('This email address has already been verified.', 'wc-email-verification'));
                }
            }
            
            // Check rate limiting
            $identifier = $this->get_client_ip() . '_' . $email;
            $rate_limit = WC_Email_Verification::get_instance()->get_setting('rate_limit', 5);
            
            if (!WC_Email_Verification_Database::check_rate_limit($identifier, 'verify_code', $rate_limit, 1)) {
                throw new Exception(__('Too many verification attempts. Please try again later.', 'wc-email-verification'));
            }
            
            // Get verification record
            $record = WC_Email_Verification_Database::get_verification_record($email, $code);
            
            if (!$record) {
                // Increment attempts for existing record
                $existing_record = WC_Email_Verification_Database::get_verification_record($email);
                if ($existing_record) {
                    WC_Email_Verification_Database::update_verification_record(
                        $existing_record->id,
                        array('attempts' => $existing_record->attempts + 1)
                    );
                }
                
                throw new Exception(__('Invalid or expired verification code.', 'wc-email-verification'));
            }
            
            // Mark as verified
            WC_Email_Verification_Database::update_verification_record(
                $record->id,
                array(
                    'verified' => 1,
                    'verified_at' => current_time('mysql')
                )
            );
            
            // Store verification status in session
            // Session should already be started by the main plugin file
            if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
                $_SESSION['wc_email_verified_' . md5($email)] = true;
            }
            
            // Log action
            WC_Email_Verification_Database::log_action($email, 'code_verified', array(
                'attempts' => $record->attempts + 1
            ));
            
            wp_send_json_success(array(
                'message' => __('Email verified successfully!', 'wc-email-verification')
            ));
            
        } catch (Exception $e) {
            // Log error
            WC_Email_Verification_Database::log_action(
                isset($email) ? $email : '',
                'verify_code_error',
                array('error' => $e->getMessage())
            );
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Resend verification code
     */
    public function resend_verification_code() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'wc_email_verification_nonce')) {
                throw new Exception(__('Security check failed.', 'wc-email-verification'));
            }
            
            $email = sanitize_email($_POST['email']);
            
            if (!is_email($email)) {
                throw new Exception(__('Please enter a valid email address.', 'wc-email-verification'));
            }
            
            // Check if email is already verified
            // Session should already be started by the main plugin file
            if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
                $verification_key = 'wc_email_verified_' . md5($email);
                if (isset($_SESSION[$verification_key]) && $_SESSION[$verification_key]) {
                    throw new Exception(__('This email address has already been verified.', 'wc-email-verification'));
                }
            }
            
            // Check if this email belongs to an existing user and if they're already verified
            $user = get_user_by('email', $email);
            if ($user && $user->ID) {
                $is_user_verified = get_user_meta($user->ID, 'wc_email_verified', true);
                if ($is_user_verified) {
                    throw new Exception(__('This email address has already been verified.', 'wc-email-verification'));
                }
            }

            // Check if there's an existing unverified record
            $existing_record = WC_Email_Verification_Database::get_verification_record($email);
            
            if (!$existing_record) {
                throw new Exception(__('No pending verification found for this email.', 'wc-email-verification'));
            }
            
            // Double check the existing record is not already verified
            if ($existing_record && $existing_record->verified == 1) {
                throw new Exception(__('This email address has already been verified.', 'wc-email-verification'));
            }
            
            // Check rate limiting for resend
            $identifier = WC_Email_Verification_Database::get_client_ip() . '_' . $email;
            if (!WC_Email_Verification_Database::check_rate_limit($identifier, 'resend_code', 3, 1)) {
                throw new Exception(__('Too many resend attempts. Please try again later.', 'wc-email-verification'));
            }
            
            // Generate new code
            $code_length = WC_Email_Verification::get_instance()->get_setting('code_length', 6);
            $verification_code = $this->generate_verification_code($code_length);
            
            // Calculate new expiry time
            $expiry_minutes = WC_Email_Verification::get_instance()->get_setting('code_expiry', 10);
            $expires_at = date('Y-m-d H:i:s', time() + ($expiry_minutes * 60));
            
            // Update existing record
            WC_Email_Verification_Database::update_verification_record(
                $existing_record->id,
                array(
                    'verification_code' => $verification_code,
                    'expires_at' => $expires_at,
                    'attempts' => 0
                )
            );
            
            // Send email
            $email_handler = new WC_Email_Verification_Email();
            $email_sent = $email_handler->send_verification_email($email, $verification_code, $expiry_minutes);
            
            if (!$email_sent) {
                throw new Exception(__('Failed to send email. Please try again.', 'wc-email-verification'));
            }
            
            // Log action
            WC_Email_Verification_Database::log_action($email, 'code_resent', array(
                'code_length' => $code_length,
                'expiry_minutes' => $expiry_minutes
            ));
            
            wp_send_json_success(array(
                'message' => __('New verification code sent to your email!', 'wc-email-verification'),
                'expiry_minutes' => $expiry_minutes
            ));
            
        } catch (Exception $e) {
            // Log error
            WC_Email_Verification_Database::log_action(
                isset($email) ? $email : '',
                'resend_code_error',
                array('error' => $e->getMessage())
            );
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Check email verification status
     */
    public function check_email_verification_status() {
        try {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'wc_email_verification_nonce')) {
                throw new Exception(__('Security check failed.', 'wc-email-verification'));
            }
            
            $email = sanitize_email($_POST['email']);
            
            if (!is_email($email)) {
                throw new Exception(__('Invalid email address.', 'wc-email-verification'));
            }
            
            // Check if email is verified in session
            // Session should already be started by the main plugin file
            $is_verified = false;
            if (function_exists('wc_woo_email_verification_session_available') && wc_woo_email_verification_session_available()) {
                $verification_key = 'wc_email_verified_' . md5($email);
                $is_verified = isset($_SESSION[$verification_key]) && $_SESSION[$verification_key];
            }
            
            // Check database for recent verification
            if (!$is_verified) {
                $record = WC_Email_Verification_Database::get_verification_record($email);
                $is_verified = $record && $record->verified == 1;
            }
            
            // Also check if this email belongs to an existing user and if they're verified
            if (!$is_verified) {
                $user = get_user_by('email', $email);
                if ($user && $user->ID) {
                    $is_verified = (bool) get_user_meta($user->ID, 'wc_email_verified', true);
                }
            }
            
            wp_send_json_success(array(
                'verified' => $is_verified,
                'message' => $is_verified ? __('Email is already verified.', 'wc-email-verification') : __('Email not verified.', 'wc-email-verification')
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }
    
    /**
     * Send test email (admin only)
     */
    public function send_test_email() {
        // Check if user is admin
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wc-email-verification')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_email_verification_test')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-email-verification')));
        }
        
        $email = sanitize_email($_POST['email']);
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'wc-email-verification')));
        }
        
        // Send test email
        $email_handler = new WC_Email_Verification_Email();
        $sent = $email_handler->send_test_email($email);
        
        if ($sent) {
            wp_send_json_success(array('message' => __('Test email sent successfully!', 'wc-email-verification')));
        } else {
            wp_send_json_error(array('message' => __('Failed to send test email.', 'wc-email-verification')));
        }
    }
    
    /**
     * Get client IP address
     *
     * @return string
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Get default email template
     */
    public function get_default_email_template() {
        // Check if user is admin
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'wc-email-verification')));
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_email_verification_test')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wc-email-verification')));
        }
        
        $default_template = WC_Email_Verification::get_instance()->get_default_email_template();
        
        wp_send_json_success(array('template' => $default_template));
    }
    
    
    /**
     * Generate verification code
     *
     * @param int $length
     * @return string
     */
    private function generate_verification_code($length = 6) {
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= wp_rand(0, 9);
        }
        return $code;
    }
}
