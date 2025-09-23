<?php
/**
 * Email handler class
 *
 * @package WC_Email_Verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Email_Verification_Email {
    
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
        // Add custom email template
        add_filter('woocommerce_email_styles', array($this, 'add_email_styles'));
    }
    
    /**
     * Send verification email
     *
     * @param string $email
     * @param string $verification_code
     * @param int $expiry_minutes
     * @return bool
     */
    public function send_verification_email($email, $verification_code, $expiry_minutes) {
        $settings = WC_Email_Verification::get_instance()->get_settings();
        
        // Get email subject
        $subject = $this->get_email_subject();
        
        // Get email content
        $message = $this->get_email_content($verification_code, $expiry_minutes);
        
        // Use WooCommerce email system if available
        if (class_exists('WC_Emails')) {
            $wc_emails = WC_Emails::instance();
            
            // Set email content type to HTML
            add_filter('wp_mail_content_type', function() {
                return 'text/html';
            });
            
            // Send using WordPress default mail
            $sent = wp_mail($email, $subject, $message);
            
            // Remove the filter
            remove_filter('wp_mail_content_type', function() {
                return 'text/html';
            });
        } else {
            // Fallback to basic wp_mail
            $headers = $this->get_email_headers();
            $sent = wp_mail($email, $subject, $message, $headers);
        }
        
        if ($sent) {
            // Log successful email send
            WC_Email_Verification_Database::log_action($email, 'email_sent', array(
                'subject' => $subject,
                'expiry_minutes' => $expiry_minutes
            ));
        }
        
        return $sent;
    }
    
    /**
     * Get email subject
     *
     * @return string
     */
    private function get_email_subject() {
        $settings = WC_Email_Verification::get_instance()->get_settings();
        $subject = $settings['email_subject'] ?? __('Your Verification Code - {site_name}', 'wc-email-verification');
        
        // Replace placeholders
        $subject = str_replace('{site_name}', get_bloginfo('name'), $subject);
        
        return $subject;
    }
    
    /**
     * Get email content
     *
     * @param string $verification_code
     * @param int $expiry_minutes
     * @return string
     */
    private function get_email_content($verification_code, $expiry_minutes) {
        $settings = WC_Email_Verification::get_instance()->get_settings();
        $template = $settings['email_template'] ?? $this->get_fallback_template();
        
        // Get customizable content settings
        $header_title = $settings['email_header_title'] ?? 'Email Verification';
        $main_heading = $settings['email_main_heading'] ?? 'Verify Your Email Address';
        $intro_text = $settings['email_intro_text'] ?? 'Thank you for registering with {site_name}. To complete your registration, please verify your email address using the code below:';
        $code_label = $settings['email_code_label'] ?? 'Your Verification Code:';
        $expiry_text = $settings['email_expiry_text'] ?? 'This code will expire in {expiry_time} minutes.';
        $security_notice = $settings['email_security_notice'] ?? 'If you didn\'t request this verification code, please ignore this email. Your account security is important to us.';
        $footer_text = $settings['email_footer_text'] ?? 'Best regards,<br>The {site_name} Team';
        
        // Replace all placeholders
        $content = str_replace(
            array(
                '{verification_code}', 
                '{expiry_time}', 
                '{site_name}', 
                '{site_url}',
                '{header_title}',
                '{main_heading}',
                '{intro_text}',
                '{code_label}',
                '{security_notice}',
                '{footer_text}'
            ),
            array(
                $verification_code, 
                $expiry_minutes, 
                get_bloginfo('name'), 
                home_url(),
                $header_title,
                $main_heading,
                $intro_text,
                $code_label,
                $security_notice,
                $footer_text
            ),
            $template
        );
        
        // Apply custom colors if template doesn't have them
        if (strpos($content, '{primary_color}') !== false || strpos($content, '{secondary_color}') !== false) {
            $primary_color = $settings['email_primary_color'] ?? '#0073aa';
            $secondary_color = $settings['email_secondary_color'] ?? '#005a87';
            $text_color = $settings['email_text_color'] ?? '#333333';
            $background_color = $settings['email_background_color'] ?? '#f8f9fa';
            
            $content = str_replace(
                array('{primary_color}', '{secondary_color}', '{text_color}', '{background_color}'),
                array($primary_color, $secondary_color, $text_color, $background_color),
                $content
            );
        }
        
        return $content;
    }
    
    /**
     * Get default email template
     *
     * @return string
     */
    private function get_default_email_template() {
        $settings = WC_Email_Verification::get_instance()->get_settings();
        return $settings['email_template'] ?? $this->get_fallback_template();
    }
    
    /**
     * Get fallback email template
     *
     * @return string
     */
    private function get_fallback_template() {
        return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: #ffffff;">
    <div style="background: #0073aa; color: white; padding: 20px; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">' . __('Email Verification', 'wc-email-verification') . '</h1>
    </div>
    <div style="padding: 30px 20px;">
        <h2 style="color: #333; margin-bottom: 20px;">' . __('Verify Your Email Address', 'wc-email-verification') . '</h2>
        <p style="color: #666; font-size: 16px; line-height: 1.6;">' . sprintf(__('Thank you for registering with %s. To complete your registration, please verify your email address using the code below:', 'wc-email-verification'), '{site_name}') . '</p>
        
        <div style="background: #f8f9fa; border: 2px solid #0073aa; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;">
            <p style="margin: 0 0 10px 0; color: #333; font-size: 18px; font-weight: bold;">' . __('Your Verification Code:', 'wc-email-verification') . '</p>
            <div style="background: #0073aa; color: white; font-size: 32px; font-weight: bold; padding: 15px; border-radius: 4px; letter-spacing: 3px; margin: 10px 0;">{verification_code}</div>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 20px 0;">' . sprintf(__('This code will expire in %s minutes.', 'wc-email-verification'), '<strong>{expiry_time}</strong>') . '</p>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; color: #856404; font-size: 14px;"><strong>' . __('Security Notice:', 'wc-email-verification') . '</strong> ' . __('If you didn\'t request this verification code, please ignore this email. Your account security is important to us.', 'wc-email-verification') . '</p>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 30px 0 0 0;">' . sprintf(__('Best regards,<br>The %s Team', 'wc-email-verification'), '{site_name}') . '</p>
    </div>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef;">
        <p style="margin: 0; color: #6c757d; font-size: 12px;">' . sprintf(__('This email was sent from %s | %s', 'wc-email-verification'), '{site_name}', '<a href="{site_url}" style="color: #0073aa;">' . __('Visit our website', 'wc-email-verification') . '</a>') . '</p>
    </div>
</div>';
    }
    
    
    /**
     * Get email styles
     *
     * @return string
     */
    private function get_email_styles() {
        return '<style>
            .verification-code {
                background: #007cba;
                color: white;
                font-size: 24px;
                font-weight: bold;
                padding: 15px 30px;
                border-radius: 8px;
                letter-spacing: 3px;
                display: inline-block;
                margin: 20px 0;
                box-shadow: 0 2px 10px rgba(0,124,186,0.3);
            }
            .verification-info {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
                border-left: 4px solid #007cba;
                margin: 20px 0;
            }
            .warning-text {
                color: #dc3545;
                font-weight: bold;
            }
            @media only screen and (max-width: 600px) {
                .verification-code {
                    font-size: 20px;
                    padding: 12px 20px;
                }
            }
        </style>';
    }
    
    /**
     * Add email styles to WooCommerce emails
     *
     * @param string $styles
     * @return string
     */
    public function add_email_styles($styles) {
        $styles .= $this->get_email_styles();
        return $styles;
    }
    
    /**
     * Get email headers
     *
     * @return array
     */
    private function get_email_headers() {
        $settings = WC_Email_Verification::get_instance()->get_settings();
        $from_name = $settings['from_name'] ?? get_bloginfo('name');
        $from_email = $settings['from_email'] ?? get_option('admin_email');
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $from_email
        );
        
        return $headers;
    }
    
    /**
     * Send test email
     *
     * @param string $email
     * @return bool
     */
    public function send_test_email($email) {
        $test_code = '123456';
        $expiry_minutes = 10;
        
        return $this->send_verification_email($email, $test_code, $expiry_minutes);
    }
}
