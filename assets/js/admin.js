/**
 * WooCommerce Email Verification Admin Script
 * 
 * @package WC_Email_Verification
 */

(function($) {
    'use strict';

    var WCEmailVerificationAdmin = {
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.initTabs();
        },
        
        // Bind events
        bindEvents: function() {
            var self = this;
            
            // Test email functionality
            $('#send-test-email').on('click', function(e) {
                e.preventDefault();
                self.sendTestEmail();
            });
            
            // Email template functionality
            $('#preview-email-template').on('click', function(e) {
                e.preventDefault();
                self.previewEmailTemplate();
            });
            
            $('#reset-email-template').on('click', function(e) {
                e.preventDefault();
                self.resetEmailTemplate();
            });
            
            $('#generate-email-template').on('click', function(e) {
                e.preventDefault();
                self.generateEmailTemplate();
            });
            
            // Color picker change handlers
            $('input[type="color"]').on('change', function() {
                self.updateDesignerPreview();
            });
            
            // Content change handlers for live preview
            $('#email-designer input, #email-designer textarea').on('input change', function() {
                self.updateDesignerPreview();
            });
            
            // Form validation
            $('form').on('submit', function() {
                return self.validateForm();
            });
            
            // Settings change handlers
            $('input[name*="enabled"]').on('change', function() {
                self.toggleDependentSettings();
            });
        },
        
        // Initialize tabs
        initTabs: function() {
            var self = this;
            
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                self.switchTab($(this));
            });
        },
        
        // Switch tab
        switchTab: function($tab) {
            var target = $tab.attr('href');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // Show target content
            $('.tab-content').removeClass('active');
            $(target).addClass('active');
            
            // Show live preview if we're on the email designer tab
            if (target === '#email-designer') {
                this.updateDesignerPreview();
            }
        },
        
        // Send test email
        sendTestEmail: function() {
            var self = this;
            var email = $('#test-email').val().trim();
            var $button = $('#send-test-email');
            var $result = $('#test-email-result');
            
            if (!email) {
                self.showMessage('Please enter an email address', 'error', $result);
                return;
            }
            
            if (!this.isValidEmail(email)) {
                self.showMessage('Please enter a valid email address', 'error', $result);
                return;
            }
            
            this.setButtonLoading($button, true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_send_test_email',
                    email: email,
                    nonce: $('#_wpnonce').val() || ''
                },
                success: function(response) {
                    self.setButtonLoading($button, false);
                    
                    if (response.success) {
                        self.showMessage(response.data.message, 'success', $result);
                        $('#test-email').val('');
                    } else {
                        self.showMessage(response.data.message, 'error', $result);
                    }
                },
                error: function(xhr, status, error) {
                    self.setButtonLoading($button, false);
                    self.showMessage('Network error. Please try again.', 'error', $result);
                }
            });
        },
        
        // Validate form
        validateForm: function() {
            var isValid = true;
            var errors = [];
            
            // Validate code length
            var codeLength = parseInt($('input[name*="code_length"]').val());
            if (codeLength < 4 || codeLength > 8) {
                errors.push('Code length must be between 4 and 8 digits');
                isValid = false;
            }
            
            // Validate code expiry
            var codeExpiry = parseInt($('input[name*="code_expiry"]').val());
            if (codeExpiry < 1 || codeExpiry > 60) {
                errors.push('Code expiry must be between 1 and 60 minutes');
                isValid = false;
            }
            
            // Validate rate limit
            var rateLimit = parseInt($('input[name*="rate_limit"]').val());
            if (rateLimit < 1 || rateLimit > 20) {
                errors.push('Rate limit must be between 1 and 20 attempts');
                isValid = false;
            }
            
            // Validate from email
            var fromEmail = $('input[name*="from_email"]').val().trim();
            if (fromEmail && !this.isValidEmail(fromEmail)) {
                errors.push('Please enter a valid from email address');
                isValid = false;
            }
            
            // Show errors
            if (!isValid) {
                this.showValidationErrors(errors);
            }
            
            return isValid;
        },
        
        // Show validation errors
        showValidationErrors: function(errors) {
            var errorHtml = '<div class="notice notice-error"><p><strong>Please fix the following errors:</strong></p><ul>';
            
            errors.forEach(function(error) {
                errorHtml += '<li>' + error + '</li>';
            });
            
            errorHtml += '</ul></div>';
            
            $('.wrap h1').after(errorHtml);
            
            // Scroll to top
            $('html, body').animate({
                scrollTop: 0
            }, 500);
        },
        
        // Toggle dependent settings
        toggleDependentSettings: function() {
            var enabled = $('input[name*="enabled"]').is(':checked');
            var $dependentSettings = $('input[name*="checkout_required"], input[name*="registration_required"]');
            
            if (enabled) {
                $dependentSettings.closest('tr').show();
            } else {
                $dependentSettings.closest('tr').hide();
            }
        },
        
        // Check if email is valid
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },
        
        // Show message
        showMessage: function(message, type, $container) {
            var messageClass = 'notice-' + type;
            var messageHtml = '<div class="notice ' + messageClass + '"><p>' + message + '</p></div>';
            
            $container.html(messageHtml);
            
            // Auto-hide success messages
            if (type === 'success') {
                setTimeout(function() {
                    $container.find('.notice-success').fadeOut();
                }, 5000);
            }
        },
        
        // Set button loading state
        setButtonLoading: function($button, loading) {
            if (loading) {
                $button.prop('disabled', true).addClass('loading');
            } else {
                $button.prop('disabled', false).removeClass('loading');
            }
        },
        
        // Initialize dependent settings visibility
        initDependentSettings: function() {
            this.toggleDependentSettings();
        },
        
        // Preview email template
        previewEmailTemplate: function() {
            var self = this;
            var $preview = $('#email-template-preview');
            
            // Get content from WYSIWYG editor or textarea
            var template = '';
            if (typeof tinymce !== 'undefined' && tinymce.get('wc_email_verification_template')) {
                template = tinymce.get('wc_email_verification_template').getContent();
            } else {
                template = $('#wc_email_verification_template').val();
            }
            
            if (!template) {
                self.showMessage('Please enter an email template first', 'error', $preview);
                return;
            }
            
            // Replace placeholders with sample data
            var previewTemplate = template
                .replace(/\{verification_code\}/g, '123456')
                .replace(/\{expiry_time\}/g, '10')
                .replace(/\{site_name\}/g, 'Your Site')
                .replace(/\{site_url\}/g, window.location.origin)
                .replace(/\{header_title\}/g, $('input[name*="email_header_title"]').val() || 'Email Verification')
                .replace(/\{main_heading\}/g, $('input[name*="email_main_heading"]').val() || 'Verify Your Email Address')
                .replace(/\{intro_text\}/g, $('textarea[name*="email_intro_text"]').val() || 'Thank you for registering with Your Site.')
                .replace(/\{code_label\}/g, $('input[name*="email_code_label"]').val() || 'Your Verification Code:')
                .replace(/\{security_notice\}/g, $('textarea[name*="email_security_notice"]').val() || 'Security notice text')
                .replace(/\{footer_text\}/g, $('textarea[name*="email_footer_text"]').val() || 'Best regards, The Your Site Team')
                .replace(/\{primary_color\}/g, $('input[name*="email_primary_color"]').val() || '#0073aa')
                .replace(/\{secondary_color\}/g, $('input[name*="email_secondary_color"]').val() || '#005a87')
                .replace(/\{text_color\}/g, $('input[name*="email_text_color"]').val() || '#333333')
                .replace(/\{background_color\}/g, $('input[name*="email_background_color"]').val() || '#f8f9fa');
            
            $preview.html('<h4>Email Preview:</h4><div style="border: 1px solid #ddd; padding: 20px; background: white;">' + previewTemplate + '</div>').show();
        },
        
        // Reset email template to default
        resetEmailTemplate: function() {
            var self = this;
            
            if (confirm('Are you sure you want to reset the email template to default? This will overwrite your current template.')) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wc_get_default_email_template',
                        nonce: $('#_wpnonce').val() || ''
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update WYSIWYG editor if it exists
                            if (typeof tinymce !== 'undefined' && tinymce.get('wc_email_verification_template')) {
                                tinymce.get('wc_email_verification_template').setContent(response.data.template);
                            } else {
                                // Fallback to textarea
                                $('#wc_email_verification_template').val(response.data.template);
                            }
                            self.showMessage('Email template reset to default', 'success', $('#email-template-preview'));
                        }
                    }
                });
            }
        },
        
        // Generate email template from settings
        generateEmailTemplate: function() {
            var self = this;
            var settings = {
                primary_color: $('input[name*="email_primary_color"]').val() || '#0073aa',
                secondary_color: $('input[name*="email_secondary_color"]').val() || '#005a87',
                text_color: $('input[name*="email_text_color"]').val() || '#333333',
                background_color: $('input[name*="email_background_color"]').val() || '#f8f9fa',
                header_title: $('input[name*="email_header_title"]').val() || 'Email Verification',
                main_heading: $('input[name*="email_main_heading"]').val() || 'Verify Your Email Address',
                intro_text: $('textarea[name*="email_intro_text"]').val() || 'Thank you for registering with {site_name}.',
                code_label: $('input[name*="email_code_label"]').val() || 'Your Verification Code:',
                expiry_text: $('input[name*="email_expiry_text"]').val() || 'This code will expire in {expiry_time} minutes.',
                security_notice: $('textarea[name*="email_security_notice"]').val() || 'Security notice text',
                footer_text: $('textarea[name*="email_footer_text"]').val() || 'Best regards, The {site_name} Team'
            };
            
            var template = this.buildEmailTemplate(settings);
            
            // Update WYSIWYG editor if it exists
            if (typeof tinymce !== 'undefined' && tinymce.get('wc_email_verification_template')) {
                tinymce.get('wc_email_verification_template').setContent(template);
            } else {
                // Fallback to textarea
                $('#wc_email_verification_template').val(template);
            }
            
            self.showMessage('Email template generated from your settings', 'success', $('#designer-preview'));
        },
        
        // Build email template from settings
        buildEmailTemplate: function(settings) {
            return '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: ' + settings.background_color + ';">
    <div style="background: linear-gradient(135deg, ' + settings.primary_color + ' 0%, ' + settings.secondary_color + ' 100%); color: white; padding: 20px; text-align: center;">
        <h1 style="margin: 0; font-size: 24px;">' + settings.header_title + '</h1>
    </div>
    <div style="padding: 30px 20px; background: #ffffff;">
        <h2 style="color: ' + settings.text_color + '; margin-bottom: 20px;">' + settings.main_heading + '</h2>
        <p style="color: #666; font-size: 16px; line-height: 1.6;">' + settings.intro_text + '</p>
        
        <div style="background: #f8f9fa; border: 2px solid ' + settings.primary_color + '; border-radius: 8px; padding: 20px; text-align: center; margin: 30px 0;">
            <p style="margin: 0 0 10px 0; color: ' + settings.text_color + '; font-size: 18px; font-weight: bold;">' + settings.code_label + '</p>
            <div style="background: ' + settings.primary_color + '; color: white; font-size: 32px; font-weight: bold; padding: 15px; border-radius: 4px; letter-spacing: 3px; margin: 10px 0;">{verification_code}</div>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 20px 0;">' + settings.expiry_text + '</p>
        
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; padding: 15px; margin: 20px 0;">
            <p style="margin: 0; color: #856404; font-size: 14px;"><strong>Security Notice:</strong> ' + settings.security_notice + '</p>
        </div>
        
        <p style="color: #666; font-size: 14px; margin: 30px 0 0 0;">' + settings.footer_text + '</p>
    </div>
    <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e9ecef;">
        <p style="margin: 0; color: #6c757d; font-size: 12px;">This email was sent from {site_name} | <a href="{site_url}" style="color: ' + settings.primary_color + ';">Visit our website</a></p>
    </div>
</div>';
        },
        
        // Update template preview when colors change
        updateTemplatePreview: function() {
            var $preview = $('#email-template-preview');
            if ($preview.is(':visible')) {
                this.previewEmailTemplate();
            }
        },
        
        // Update designer preview
        updateDesignerPreview: function() {
            var self = this;
            var $preview = $('#designer-preview');
            var $previewContent = $('#designer-preview-content');
            
            // Only show preview if we're on the email designer tab
            if (!$preview.is(':visible')) {
                return;
            }
            
            var settings = {
                primary_color: $('input[name*="email_primary_color"]').val() || '#0073aa',
                secondary_color: $('input[name*="email_secondary_color"]').val() || '#005a87',
                text_color: $('input[name*="email_text_color"]').val() || '#333333',
                background_color: $('input[name*="email_background_color"]').val() || '#f8f9fa',
                header_title: $('input[name*="email_header_title"]').val() || 'Email Verification',
                main_heading: $('input[name*="email_main_heading"]').val() || 'Verify Your Email Address',
                intro_text: $('textarea[name*="email_intro_text"]').val() || 'Thank you for registering with Your Site.',
                code_label: $('input[name*="email_code_label"]').val() || 'Your Verification Code:',
                expiry_text: $('input[name*="email_expiry_text"]').val() || 'This code will expire in 10 minutes.',
                security_notice: $('textarea[name*="email_security_notice"]').val() || 'Security notice text',
                footer_text: $('textarea[name*="email_footer_text"]').val() || 'Best regards, The Your Site Team'
            };
            
            var previewTemplate = this.buildEmailTemplate(settings);
            
            // Replace placeholders with sample data
            previewTemplate = previewTemplate
                .replace(/\{verification_code\}/g, '123456')
                .replace(/\{expiry_time\}/g, '10')
                .replace(/\{site_name\}/g, 'Your Site')
                .replace(/\{site_url\}/g, window.location.origin);
            
            $previewContent.html('<div style="border: 1px solid #ddd; padding: 20px; background: white;">' + previewTemplate + '</div>');
            $preview.show();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WCEmailVerificationAdmin.init();
        WCEmailVerificationAdmin.initDependentSettings();
        
        // Initialize WYSIWYG editor if it exists
        if (typeof wp !== 'undefined' && wp.editor && wp.editor.initialize) {
            // Wait a bit for the DOM to be fully ready
            setTimeout(function() {
                if ($('#wc_email_verification_template').length > 0) {
                    wp.editor.initialize('wc_email_verification_template', {
                        tinymce: {
                            toolbar1: 'formatselect,bold,italic,underline,|,bullist,numlist,blockquote,|,link,unlink,|,forecolor,backcolor,|,removeformat,fullscreen',
                            toolbar2: '',
                            content_css: false,
                        },
                        quicktags: true,
                        mediaButtons: false,
                    });
                }
            }, 100);
        }
    });

})(jQuery);
