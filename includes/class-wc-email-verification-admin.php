<?php
/**
 * Admin class
 *
 * @package WC_Email_Verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Email_Verification_Admin {
    
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Add settings link to plugins page
        add_filter('plugin_action_links_' . WC_EMAIL_VERIFICATION_PLUGIN_BASENAME, array($this, 'add_settings_link'));
        
        // Schedule cleanup tasks
        add_action('wp', array($this, 'schedule_cleanup_tasks'));
        add_action('wc_email_verification_cleanup', array($this, 'run_cleanup_tasks'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Email Verification', 'wc-email-verification'),
            __('Email Verification', 'wc-email-verification'),
            'manage_woocommerce',
            'wc-email-verification',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('wc_email_verification_settings', 'wc_email_verification_settings', array($this, 'validate_settings'));
    }
    
    /**
     * Add settings link to plugins page
     *
     * @param array $links
     * @return array
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-email-verification') . '">' . __('Settings', 'wc-email-verification') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Display admin notices
     */
    public function admin_notices() {
        $screen = get_current_screen();
        if ($screen->id !== 'woocommerce_page_wc-email-verification') {
            return;
        }
        
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'wc-email-verification') . '</p></div>';
        }
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        $settings = WC_Email_Verification::get_instance()->get_settings();
        $stats = $this->get_verification_stats();
        
        ?>
        <div class="wrap">
            <h1><?php _e('WooCommerce Email Verification Settings', 'wc-email-verification'); ?></h1>
            
            
            <div class="wc-email-verification-admin-header">
                <div class="wc-email-verification-stats">
                    <div class="stat-box">
                        <h3><?php echo esc_html($stats['total_verifications']); ?></h3>
                        <p><?php _e('Total Verifications', 'wc-email-verification'); ?></p>
                    </div>
                    <div class="stat-box">
                        <h3><?php echo esc_html($stats['pending_verifications']); ?></h3>
                        <p><?php _e('Pending Verifications', 'wc-email-verification'); ?></p>
                    </div>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields('wc_email_verification_settings'); ?>
                
                <div class="wc-email-verification-tabs">
                    <nav class="nav-tab-wrapper">
                        <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'wc-email-verification'); ?></a>
                        <a href="#email" class="nav-tab"><?php _e('Email Settings', 'wc-email-verification'); ?></a>
                        <a href="#email-designer" class="nav-tab"><?php _e('Email Designer', 'wc-email-verification'); ?></a>
                        <a href="#security" class="nav-tab"><?php _e('Security', 'wc-email-verification'); ?></a>
                        <a href="#logs" class="nav-tab"><?php _e('Logs', 'wc-email-verification'); ?></a>
                    </nav>
                    
                    <div id="general" class="tab-content active">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Enable Email Verification', 'wc-email-verification'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wc_email_verification_settings[enabled]" value="yes" <?php checked($settings['enabled'] ?? 'yes', 'yes'); ?> />
                                        <?php _e('Enable email verification system', 'wc-email-verification'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Checkout Verification', 'wc-email-verification'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wc_email_verification_settings[checkout_required]" value="yes" <?php checked($settings['checkout_required'] ?? 'yes', 'yes'); ?> />
                                        <?php _e('Require email verification for checkout', 'wc-email-verification'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Registration Verification', 'wc-email-verification'); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="wc_email_verification_settings[registration_required]" value="yes" <?php checked($settings['registration_required'] ?? 'yes', 'yes'); ?> />
                                        <?php _e('Require email verification for registration', 'wc-email-verification'); ?>
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Code Length', 'wc-email-verification'); ?></th>
                                <td>
                                    <select name="wc_email_verification_settings[code_length]">
                                        <?php for ($i = 4; $i <= 8; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php selected($settings['code_length'] ?? 6, $i); ?>><?php echo $i; ?> <?php _e('digits', 'wc-email-verification'); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Code Expiry (minutes)', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="number" name="wc_email_verification_settings[code_expiry]" value="<?php echo esc_attr($settings['code_expiry'] ?? 10); ?>" min="1" max="60" />
                                    <p class="description"><?php _e('How long verification codes remain valid', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="email" class="tab-content">
                        <h3><?php _e('Basic Email Settings', 'wc-email-verification'); ?></h3>
                        <p class="description"><?php _e('Configure basic email settings and send test emails.', 'wc-email-verification'); ?></p>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('From Name', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="text" name="wc_email_verification_settings[from_name]" value="<?php echo esc_attr($settings['from_name'] ?? get_bloginfo('name')); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('From Email', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="email" name="wc_email_verification_settings[from_email]" value="<?php echo esc_attr($settings['from_email'] ?? get_option('admin_email')); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Email Subject', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="text" name="wc_email_verification_settings[email_subject]" value="<?php echo esc_attr($settings['email_subject'] ?? 'Your Verification Code - {site_name}'); ?>" class="large-text" />
                                    <p class="description"><?php _e('Available placeholders: {site_name}', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <h4><?php _e('Custom HTML Template', 'wc-email-verification'); ?></h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Email Template', 'wc-email-verification'); ?></th>
                                <td>
                                    <?php 
                                    $template_content = $settings['email_template'] ?? $this->get_default_email_template();
                                    // Ensure we have proper content for the editor
                                    if (empty($template_content)) {
                                        $template_content = $this->get_default_email_template();
                                    }
                                    
                                    // Try to use wp_editor, fallback to textarea if it fails
                                    if (function_exists('wp_editor')) {
                                        wp_editor($template_content, 'wc_email_verification_template', array(
                                            'textarea_name' => 'wc_email_verification_settings[email_template]',
                                            'textarea_rows' => 15,
                                            'media_buttons' => false,
                                            'teeny' => false,
                                            'quicktags' => true,
                                            'tinymce' => array(
                                                'toolbar1' => 'formatselect,bold,italic,underline,|,bullist,numlist,blockquote,|,link,unlink,|,forecolor,backcolor,|,removeformat,fullscreen',
                                                'toolbar2' => '',
                                                'content_css' => false,
                                            ),
                                            'editor_class' => 'wc-email-template-editor',
                                        ));
                                    } else {
                                        // Fallback to textarea
                                        echo '<textarea id="wc_email_verification_template" name="wc_email_verification_settings[email_template]" rows="15" cols="50" class="large-text wc-email-template-editor">' . esc_textarea($template_content) . '</textarea>';
                                    }
                                    ?>
                                    <p class="description"><?php _e('Available placeholders: {verification_code}, {expiry_time}, {site_name}, {site_url}, {header_title}, {main_heading}, {intro_text}, {code_label}, {security_notice}, {footer_text}, {primary_color}, {secondary_color}, {text_color}, {background_color}', 'wc-email-verification'); ?></p>
                                    <div style="margin-top: 10px;">
                                        <button type="button" id="preview-email-template" class="button"><?php _e('Preview Template', 'wc-email-verification'); ?></button>
                                        <button type="button" id="reset-email-template" class="button"><?php _e('Reset to Default', 'wc-email-verification'); ?></button>
                                    </div>
                                    <div id="email-template-preview" style="margin-top: 15px; padding: 15px; border: 1px solid #ddd; background: #f9f9f9; display: none;"></div>
                                </td>
                            </tr>
                        </table>
                        
                        <h4><?php _e('Test Email', 'wc-email-verification'); ?></h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Send Test Email', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="email" id="test-email" placeholder="<?php _e('Enter email address', 'wc-email-verification'); ?>" class="regular-text" />
                                    <button type="button" id="send-test-email" class="button"><?php _e('Send Test Email', 'wc-email-verification'); ?></button>
                                    <div id="test-email-result"></div>
                                    <p class="description"><?php _e('Send a test email with verification code to preview your template', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="email-designer" class="tab-content">
                        <h3><?php _e('Email Designer', 'wc-email-verification'); ?></h3>
                        <p class="description"><?php _e('Design your email verification template with colors, content, and styling. Use the "Generate Template" button to apply these settings to your email template.', 'wc-email-verification'); ?></p>
                        
                        
                        <h4><?php _e('Email Colors', 'wc-email-verification'); ?></h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Primary Color', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="color" name="wc_email_verification_settings[email_primary_color]" value="<?php echo esc_attr($settings['email_primary_color'] ?? '#0073aa'); ?>" />
                                    <p class="description"><?php _e('Header background, buttons, and verification code background', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Secondary Color', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="color" name="wc_email_verification_settings[email_secondary_color]" value="<?php echo esc_attr($settings['email_secondary_color'] ?? '#005a87'); ?>" />
                                    <p class="description"><?php _e('Header gradient and hover effects', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Text Color', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="color" name="wc_email_verification_settings[email_text_color]" value="<?php echo esc_attr($settings['email_text_color'] ?? '#333333'); ?>" />
                                    <p class="description"><?php _e('Main text color', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Background Color', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="color" name="wc_email_verification_settings[email_background_color]" value="<?php echo esc_attr($settings['email_background_color'] ?? '#f8f9fa'); ?>" />
                                    <p class="description"><?php _e('Email background color', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <h4><?php _e('Email Content', 'wc-email-verification'); ?></h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Header Title', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="text" name="wc_email_verification_settings[email_header_title]" value="<?php echo esc_attr($settings['email_header_title'] ?? 'Email Verification'); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Main Heading', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="text" name="wc_email_verification_settings[email_main_heading]" value="<?php echo esc_attr($settings['email_main_heading'] ?? 'Verify Your Email Address'); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Intro Text', 'wc-email-verification'); ?></th>
                                <td>
                                    <textarea name="wc_email_verification_settings[email_intro_text]" rows="3" cols="50" class="large-text"><?php echo esc_textarea($settings['email_intro_text'] ?? 'Thank you for registering with {site_name}. To complete your registration, please verify your email address using the code below:'); ?></textarea>
                                    <p class="description"><?php _e('Available placeholders: {site_name}', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Verification Code Label', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="text" name="wc_email_verification_settings[email_code_label]" value="<?php echo esc_attr($settings['email_code_label'] ?? 'Your Verification Code:'); ?>" class="regular-text" />
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Expiry Text', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="text" name="wc_email_verification_settings[email_expiry_text]" value="<?php echo esc_attr($settings['email_expiry_text'] ?? 'This code will expire in {expiry_time} minutes.'); ?>" class="regular-text" />
                                    <p class="description"><?php _e('Available placeholders: {expiry_time}', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Security Notice', 'wc-email-verification'); ?></th>
                                <td>
                                    <textarea name="wc_email_verification_settings[email_security_notice]" rows="3" cols="50" class="large-text"><?php echo esc_textarea($settings['email_security_notice'] ?? 'If you didn\'t request this verification code, please ignore this email. Your account security is important to us.'); ?></textarea>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php _e('Footer Text', 'wc-email-verification'); ?></th>
                                <td>
                                    <textarea name="wc_email_verification_settings[email_footer_text]" rows="2" cols="50" class="large-text"><?php echo esc_textarea($settings['email_footer_text'] ?? 'Best regards,<br>The {site_name} Team'); ?></textarea>
                                    <p class="description"><?php _e('Available placeholders: {site_name}', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <h4><?php _e('Template Actions', 'wc-email-verification'); ?></h4>
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Generate Template', 'wc-email-verification'); ?></th>
                                <td>
                                    <button type="button" id="generate-email-template" class="button button-primary"><?php _e('Generate Template from Settings Above', 'wc-email-verification'); ?></button>
                                    <p class="description"><?php _e('This will generate a new email template using your color and content settings above. It will update the HTML template in the "Email Settings" tab.', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                        </table>
                        
                        <div id="designer-preview" style="margin-top: 20px; padding: 20px; border: 1px solid #ddd; background: #f9f9f9; display: none;">
                            <h4><?php _e('Live Preview', 'wc-email-verification'); ?></h4>
                            <div id="designer-preview-content"></div>
                        </div>
                    </div>
                    
                    <div id="security" class="tab-content">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><?php _e('Rate Limiting', 'wc-email-verification'); ?></th>
                                <td>
                                    <input type="number" name="wc_email_verification_settings[rate_limit]" value="<?php echo esc_attr($settings['rate_limit'] ?? 5); ?>" min="1" max="20" />
                                    <p class="description"><?php _e('Maximum verification attempts per hour per IP/email', 'wc-email-verification'); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <div id="logs" class="tab-content">
                        <h3><?php _e('Recent Verification Activity', 'wc-email-verification'); ?></h3>
                        <?php $this->display_verification_logs(); ?>
                    </div>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                $('.nav-tab').removeClass('nav-tab-active');
                $('.tab-content').removeClass('active');
                $(this).addClass('nav-tab-active');
                $($(this).attr('href')).addClass('active');
            });
            
            // Test email
            $('#send-test-email').click(function() {
                var email = $('#test-email').val();
                if (!email) {
                    alert('<?php _e('Please enter an email address', 'wc-email-verification'); ?>');
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php _e('Sending...', 'wc-email-verification'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wc_send_test_email',
                        email: email,
                        nonce: '<?php echo wp_create_nonce('wc_email_verification_test'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#test-email-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            $('#test-email-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $('#test-email-result').html('<div class="notice notice-error"><p><?php _e('Network error. Please try again.', 'wc-email-verification'); ?></p></div>');
                    },
                    complete: function() {
                        $('#send-test-email').prop('disabled', false).text('<?php _e('Send Test Email', 'wc-email-verification'); ?>');
                    }
                });
            });
            
            // Email template preview
            $('#preview-email-template').on('click', function() {
                var template = $('textarea[name*="email_template"]').val();
                var subject = $('input[name*="email_subject"]').val();
                
                // Replace placeholders
                var preview = template
                    .replace(/{verification_code}/g, '123456')
                    .replace(/{expiry_time}/g, '10')
                    .replace(/{site_name}/g, '<?php echo esc_js(get_bloginfo('name')); ?>')
                    .replace(/{site_url}/g, '<?php echo esc_js(home_url()); ?>');
                
                $('#email-template-preview').html('<h4>Preview:</h4><div style="border: 1px solid #ccc; padding: 15px; background: white;">' + preview + '</div>').show();
            });
            
            // Reset email template
            $('#reset-email-template').on('click', function() {
                if (confirm('<?php _e('Are you sure you want to reset the email template to default?', 'wc-email-verification'); ?>')) {
                    $('textarea[name*="email_template"]').val('<?php echo esc_js($this->get_default_email_template()); ?>');
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Validate settings
     *
     * @param array $input
     * @return array
     */
    public function validate_settings($input) {
        $sanitized = array();
        
        $sanitized['enabled'] = isset($input['enabled']) ? 'yes' : 'no';
        $sanitized['checkout_required'] = isset($input['checkout_required']) ? 'yes' : 'no';
        $sanitized['registration_required'] = isset($input['registration_required']) ? 'yes' : 'no';
        $sanitized['code_length'] = absint($input['code_length'] ?? 6);
        $sanitized['code_expiry'] = absint($input['code_expiry'] ?? 10);
        $sanitized['rate_limit'] = absint($input['rate_limit'] ?? 5);
        $sanitized['from_name'] = sanitize_text_field($input['from_name'] ?? get_bloginfo('name'));
        $sanitized['from_email'] = sanitize_email($input['from_email'] ?? get_option('admin_email'));
        $sanitized['email_subject'] = sanitize_text_field($input['email_subject'] ?? '');
        $sanitized['email_template'] = wp_kses_post($input['email_template'] ?? '');
        
        // Validate code length
        if ($sanitized['code_length'] < 4 || $sanitized['code_length'] > 8) {
            $sanitized['code_length'] = 6;
        }
        
        // Validate code expiry
        if ($sanitized['code_expiry'] < 1 || $sanitized['code_expiry'] > 60) {
            $sanitized['code_expiry'] = 10;
        }
        
        // Validate rate limit
        if ($sanitized['rate_limit'] < 1 || $sanitized['rate_limit'] > 20) {
            $sanitized['rate_limit'] = 5;
        }
        
        return $sanitized;
    }
    
    /**
     * Get verification statistics
     *
     * @return array
     */
    private function get_verification_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'wc_email_verifications';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $verified = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE verified = 1");
        $pending = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE verified = 0 AND expires_at > NOW()");
        
        $success_rate = $total > 0 ? round(($verified / $total) * 100, 1) : 0;
        
        return array(
            'total_verifications' => $total,
            'verified_verifications' => $verified,
            'pending_verifications' => $pending,
            'success_rate' => $success_rate
        );
    }
    
    /**
     * Display verification logs
     */
    private function display_verification_logs() {
        global $wpdb;
        
        $logs_table = $wpdb->prefix . 'wc_email_verification_logs';
        $logs = $wpdb->get_results(
            "SELECT * FROM $logs_table ORDER BY created_at DESC LIMIT 20"
        );
        
        if (empty($logs)) {
            echo '<p>' . __('No verification activity found.', 'wc-email-verification') . '</p>';
            return;
        }
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Email', 'wc-email-verification'); ?></th>
                    <th><?php _e('Action', 'wc-email-verification'); ?></th>
                    <th><?php _e('IP Address', 'wc-email-verification'); ?></th>
                    <th><?php _e('Date', 'wc-email-verification'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo esc_html($log->email); ?></td>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $log->action))); ?></td>
                        <td><?php echo esc_html($log->ip_address); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Get default email template
     *
     * @return string
     */
    private function get_default_email_template() {
        return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: {background_color};">
    <div style="background: linear-gradient(135deg, {primary_color} 0%, {secondary_color} 100%); color: white; padding: 20px; text-align: center;">
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
     * Schedule cleanup tasks
     */
    public function schedule_cleanup_tasks() {
        if (!wp_next_scheduled('wc_email_verification_cleanup')) {
            wp_schedule_event(time(), 'hourly', 'wc_email_verification_cleanup');
        }
    }
    
    /**
     * Run cleanup tasks
     */
    public function run_cleanup_tasks() {
        WC_Email_Verification_Database::cleanup_expired_codes();
        WC_Email_Verification_Database::cleanup_old_logs();
        WC_Email_Verification_Database::cleanup_old_rate_limits();
    }
}
