<?php
/**
 * Frontend class
 *
 * @package WC_Email_Verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Email_Verification_Frontend {
    
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
        // Add verification to checkout
        add_action('woocommerce_checkout_billing', array($this, 'add_checkout_verification'), 25);
        
        // Add verification to registration
        add_action('woocommerce_register_form', array($this, 'add_registration_verification'));
        
        // Validate checkout
        add_action('woocommerce_checkout_process', array($this, 'validate_checkout_verification'));
        
        // Handle registration
        add_action('woocommerce_created_customer', array($this, 'handle_verified_registration'), 10, 3);
        
        // Add verification status to user profile
        add_action('show_user_profile', array($this, 'add_user_verification_status'));
        add_action('edit_user_profile', array($this, 'add_user_verification_status'));
    }
    
    /**
     * Add verification section to checkout
     */
    public function add_checkout_verification() {
        if (WC_Email_Verification::get_instance()->get_setting('enabled', 'yes') !== 'yes') {
            return;
        }
        
        if (WC_Email_Verification::get_instance()->get_setting('checkout_required', 'yes') !== 'yes') {
            return;
        }
        
        $this->render_verification_section('checkout');
        
        // Add JavaScript for checkout page
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Monitor email field
            $(document).on('input keyup change blur paste', '#billing_email', function() {
                var email = $(this).val().trim();
                console.log('Checkout email field changed:', email);
                console.log('Verification wrapper exists:', $('#wc-email-verification-wrapper').length);
                console.log('Current classes:', $('#wc-email-verification-wrapper').attr('class'));
                
                if (email && email.includes('@') && email.includes('.')) {
                    console.log('Showing checkout verification wrapper');
                    $('#wc-email-verification-wrapper').addClass('show');
                    $('#wc-email-verification-trigger').show();
                    console.log('After adding show class:', $('#wc-email-verification-wrapper').attr('class'));
                    
                    // Check if email is already verified
                    checkEmailVerificationStatus(email);
                } else {
                    console.log('Hiding checkout verification wrapper');
                    $('#wc-email-verification-wrapper').removeClass('show');
                }
            });
            
            // Disable checkout button initially
            $('button[type="submit"][name="woocommerce_checkout_place_order"]').prop('disabled', true).addClass('disabled');
        });
        
        function checkEmailVerificationStatus(email) {
            jQuery.ajax({
                url: wcEmailVerification.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_check_email_verification_status',
                    email: email,
                    nonce: wcEmailVerification.nonce
                },
                success: function(response) {
                    if (response.success && response.data.verified) {
                        // Email is already verified, show success state
                        jQuery('#wc-email-verification-wrapper').addClass('show');
                        jQuery('#wc-email-verification-trigger').hide();
                        jQuery('#wc-email-verification-code-section').hide();
                        jQuery('#wc-email-verification-success').show();
                        
                        // Enable checkout button
                        jQuery('button[type="submit"][name="woocommerce_checkout_place_order"]').prop('disabled', false).removeClass('disabled');
                        
                        // Set verification state
                        if (typeof WCEmailVerification !== 'undefined') {
                            WCEmailVerification.state.emailVerified = true;
                        }
                    }
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Add verification section to registration
     */
    public function add_registration_verification() {
        if (WC_Email_Verification::get_instance()->get_setting('enabled', 'yes') !== 'yes') {
            return;
        }
        
        if (WC_Email_Verification::get_instance()->get_setting('registration_required', 'yes') !== 'yes') {
            return;
        }
        
        $this->render_verification_section('registration');
        
        // Add JavaScript for registration page
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Monitor email field
            $(document).on('input keyup change blur paste', '#reg_email', function() {
                var email = $(this).val().trim();
                console.log('Registration email field changed:', email);
                console.log('Verification wrapper exists:', $('#wc-email-verification-wrapper').length);
                console.log('Current classes:', $('#wc-email-verification-wrapper').attr('class'));
                
                if (email && email.includes('@') && email.includes('.')) {
                    console.log('Showing registration verification wrapper');
                    $('#wc-email-verification-wrapper').addClass('show');
                    $('#wc-email-verification-trigger').show();
                    console.log('After adding show class:', $('#wc-email-verification-wrapper').attr('class'));
                } else {
                    console.log('Hiding registration verification wrapper');
                    $('#wc-email-verification-wrapper').removeClass('show');
                }
            });
            
            // Disable register button initially
            $('button[type="submit"][name="register"]').prop('disabled', true).addClass('disabled');
            
            // Check if email is already verified on page load
            var currentEmail = $('#reg_email').val().trim();
            if (currentEmail && currentEmail.includes('@') && currentEmail.includes('.')) {
                // Check if this email is already verified
                checkEmailVerificationStatus(currentEmail);
            }
        });
        
        function checkEmailVerificationStatus(email) {
            jQuery.ajax({
                url: wcEmailVerification.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_check_email_verification_status',
                    email: email,
                    nonce: wcEmailVerification.nonce
                },
                success: function(response) {
                    if (response.success && response.data.verified) {
                        // Email is already verified, show success state
                        jQuery('#wc-email-verification-wrapper').addClass('show');
                        jQuery('#wc-email-verification-trigger').hide();
                        jQuery('#wc-email-verification-code-section').hide();
                        jQuery('#wc-email-verification-success').show();
                        
                        // Enable register button
                        jQuery('button[type="submit"][name="register"]').prop('disabled', false).removeClass('disabled');
                        
                        // Set verification state
                        if (typeof WCEmailVerification !== 'undefined') {
                            WCEmailVerification.state.emailVerified = true;
                        }
                    }
                }
            });
        }
        </script>
        <?php
    }
    
    /**
     * Render verification section
     *
     * @param string $context
     */
    private function render_verification_section($context) {
        $settings = WC_Email_Verification::get_instance()->get_settings();
        $expiry_minutes = $settings['code_expiry'] ?? 10;
        $code_length = $settings['code_length'] ?? 6;
        
        ?>
        <div id="wc-email-verification-wrapper" class="wc-email-verification-wrapper" data-context="<?php echo esc_attr($context); ?>">
            <div id="wc-email-verification-trigger" class="wc-email-verification-trigger" style="display: none;">
                <div class="wc-email-verification-header">
                    <h3><?php _e('Email Verification', 'wc-email-verification'); ?></h3>
                    <p><?php _e('Verify your email address to proceed', 'wc-email-verification'); ?></p>
                </div>
                <button type="button" id="wc-send-verification-btn" class="button wc-email-verification-btn"><?php _e('Send Verification Code', 'wc-email-verification'); ?></button>
            </div>
            
            <div id="wc-email-verification-code-section" class="wc-email-verification-code-section" style="display: none;">
                <div class="wc-email-verification-code-header">
                    <h4><?php _e('Enter Verification Code', 'wc-email-verification'); ?></h4>
                    <p><?php printf(__('We sent a %d-digit code to your email address', 'wc-email-verification'), $code_length); ?></p>
                </div>
                
                <div class="wc-email-verification-code-input">
                    <input type="text" 
                           id="wc-verification-code" 
                           name="verification_code" 
                           maxlength="<?php echo esc_attr($code_length); ?>" 
                           placeholder="<?php echo str_repeat('0', $code_length); ?>"
                           class="wc-verification-code-input"
                           autocomplete="one-time-code" />
                    <button type="button" id="wc-verify-code-btn" class="button wc-email-verification-btn"><?php _e('Verify', 'wc-email-verification'); ?></button>
                </div>
                
                <div class="wc-email-verification-code-actions">
                    <button type="button" id="wc-resend-verification-btn" class="button-link">
                        <?php _e('Resend Code', 'wc-email-verification'); ?>
                    </button>
                    <span class="wc-email-verification-timer" id="wc-verification-timer" style="display: none;">
                        <?php printf(__('Resend available in %s', 'wc-email-verification'), '<span id="wc-timer-countdown">60</span>s'); ?>
                    </span>
                </div>
            </div>
            
            <div id="wc-verification-messages" class="wc-verification-messages"></div>
            
            <div id="wc-email-verification-success" class="wc-email-verification-success" style="display: none;">
                <div class="success-icon">✓</div>
                <div class="success-message">
                    <h4><?php _e('Email Verified!', 'wc-email-verification'); ?></h4>
                    <p><?php _e('Your email address has been successfully verified.', 'wc-email-verification'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Validate checkout verification
     */
    public function validate_checkout_verification() {
        if (WC_Email_Verification::get_instance()->get_setting('enabled', 'yes') !== 'yes') {
            return;
        }
        
        if (WC_Email_Verification::get_instance()->get_setting('checkout_required', 'yes') !== 'yes') {
            return;
        }
        
        if (!session_id()) {
            session_start();
        }
        
        $email = $_POST['billing_email'] ?? '';
        $verification_key = 'wc_email_verified_' . md5($email);
        
        if (!isset($_SESSION[$verification_key]) || !$_SESSION[$verification_key]) {
            wc_add_notice(
                __('Please verify your email address before placing your order.', 'wc-email-verification'),
                'error'
            );
        }
    }
    
    /**
     * Handle verified registration
     *
     * @param int $customer_id
     * @param array $new_customer_data
     * @param bool $password_generated
     */
    public function handle_verified_registration($customer_id, $new_customer_data, $password_generated) {
        if (!session_id()) {
            session_start();
        }
        
        $email = $new_customer_data['user_email'];
        $verification_key = 'wc_email_verified_' . md5($email);
        
        if (isset($_SESSION[$verification_key]) && $_SESSION[$verification_key]) {
            // Mark user as verified
            update_user_meta($customer_id, 'wc_email_verified', true);
            update_user_meta($customer_id, 'wc_email_verified_date', current_time('mysql'));
            
            // Clean up session
            unset($_SESSION[$verification_key]);
            
            // Log verification
            WC_Email_Verification_Database::log_action($email, 'user_verified', array(
                'user_id' => $customer_id
            ));
        }
    }
    
    /**
     * Add verification status to user profile
     *
     * @param WP_User $user
     */
    public function add_user_verification_status($user) {
        $is_verified = get_user_meta($user->ID, 'wc_email_verified', true);
        $verified_date = get_user_meta($user->ID, 'wc_email_verified_date', true);
        
        ?>
        <h3><?php _e('Email Verification Status', 'wc-email-verification'); ?></h3>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Email Verified', 'wc-email-verification'); ?></th>
                <td>
                    <?php if ($is_verified): ?>
                        <span style="color: green; font-weight: bold;">✓ <?php _e('Yes', 'wc-email-verification'); ?></span>
                        <?php if ($verified_date): ?>
                            <br><small><?php printf(__('Verified on: %s', 'wc-email-verification'), date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($verified_date))); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color: red; font-weight: bold;">✗ <?php _e('No', 'wc-email-verification'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * Check if email is verified
     *
     * @param string $email
     * @return bool
     */
    public static function is_email_verified($email) {
        if (!session_id()) {
            session_start();
        }
        
        $verification_key = 'wc_email_verified_' . md5($email);
        return isset($_SESSION[$verification_key]) && $_SESSION[$verification_key];
    }
    
    /**
     * Check if user email is verified
     *
     * @param int $user_id
     * @return bool
     */
    public static function is_user_email_verified($user_id) {
        return (bool) get_user_meta($user_id, 'wc_email_verified', true);
    }
}
