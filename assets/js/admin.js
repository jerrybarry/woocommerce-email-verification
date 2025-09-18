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
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WCEmailVerificationAdmin.init();
        WCEmailVerificationAdmin.initDependentSettings();
    });

})(jQuery);
