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
        $template = $settings['email_template'] ?? $this->get_default_email_template();
        
        // Replace placeholders
        $content = str_replace(
            array('{verification_code}', '{expiry_time}', '{site_name}', '{site_url}'),
            array($verification_code, $expiry_minutes, get_bloginfo('name'), home_url()),
            $template
        );
        
        // Wrap in HTML template
        return $this->wrap_in_html_template($content);
    }
    
    /**
     * Get default email template
     *
     * @return string
     */
    private function get_default_email_template() {
        return sprintf(
            '<div style="text-align: center; padding: 20px; font-family: Arial, sans-serif;">
                <h2 style="color: #333; margin-bottom: 20px;">%s</h2>
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;">
                    <p style="font-size: 18px; margin: 0 0 10px 0;">%s</p>
                    <div style="background: #007cba; color: white; font-size: 24px; font-weight: bold; padding: 15px; border-radius: 4px; letter-spacing: 3px; margin: 15px 0;">%s</div>
                </div>
                <p style="color: #666; font-size: 14px; margin: 20px 0;">%s</p>
                <p style="color: #999; font-size: 12px; margin: 30px 0 0 0;">%s</p>
            </div>',
            __('Email Verification', 'wc-email-verification'),
            __('Your verification code is:', 'wc-email-verification'),
            '{verification_code}',
            sprintf(__('This code will expire in %s minutes.', 'wc-email-verification'), '{expiry_time}'),
            __('If you didn\'t request this code, please ignore this email.', 'wc-email-verification')
        );
    }
    
    /**
     * Wrap content in HTML template
     *
     * @param string $content
     * @return string
     */
    private function wrap_in_html_template($content) {
        $settings = WC_Email_Verification::get_instance()->get_settings();
        $from_name = $settings['from_name'] ?? get_bloginfo('name');
        
        return sprintf(
            '<!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>%s</title>
                %s
            </head>
            <body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;">
                <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div style="background: linear-gradient(135deg, #007cba 0%%, #005a87 100%%); padding: 30px; text-align: center;">
                        <h1 style="color: white; margin: 0; font-size: 24px;">%s</h1>
                    </div>
                    <div style="padding: 40px 30px;">
                        %s
                    </div>
                    <div style="background-color: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef;">
                        <p style="margin: 0; color: #6c757d; font-size: 12px;">
                            %s | %s
                        </p>
                    </div>
                </div>
            </body>
            </html>',
            __('Email Verification', 'wc-email-verification'),
            $this->get_email_styles(),
            $from_name,
            $content,
            sprintf(__('This email was sent from %s', 'wc-email-verification'), get_bloginfo('name')),
            sprintf(__('If you have any questions, please contact us at %s', 'wc-email-verification'), get_option('admin_email'))
        );
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
