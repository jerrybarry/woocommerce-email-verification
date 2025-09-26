/**
 * WooCommerce Email Verification Frontend Script
 * 
 * @package WC_Email_Verification
 */

(function($) {
    'use strict';

    var WCEmailVerification = {
        
        // Configuration
        config: {
            ajaxUrl: wcEmailVerification.ajaxUrl,
            nonce: wcEmailVerification.nonce,
            settings: wcEmailVerification.settings,
            messages: wcEmailVerification.messages
        },
        
        // State
        state: {
            emailVerified: false,
            currentEmail: '',
            verificationTimer: null,
            resendCooldown: 60 // seconds
        },
        
        // Initialize
        init: function() {
            this.bindEvents();
            this.monitorEmailField();
            this.setupInitialState();
            
            // Initialize submit button state
            this.updateSubmitButtonState();
        },
        
        // Bind events
        bindEvents: function() {
            var self = this;
            
            // Open popup
            $(document).on('click', '#wc-open-verification-popup', function(e) {
                e.preventDefault();
                self.openVerificationPopup();
            });
            
            // Close popup
            $(document).on('click', '#wc-close-verification-popup, #wc-close-verification-popup-success, .wc-email-verification-popup-overlay', function(e) {
                e.preventDefault();
                self.closeVerificationPopup();
            });
            
            // Send verification code
            $(document).on('click', '#wc-send-verification-btn', function(e) {
                e.preventDefault();
                self.sendVerificationCode();
            });
            
            // Verify code
            $(document).on('click', '#wc-verify-code-btn', function(e) {
                e.preventDefault();
                self.verifyCode();
            });
            
            // Resend code
            $(document).on('click', '#wc-resend-verification-btn', function(e) {
                e.preventDefault();
                self.resendVerificationCode();
            });
            
            // Back to step 1
            $(document).on('click', '#wc-back-to-step-1', function(e) {
                e.preventDefault();
                self.goToStep(1);
            });
            
            // Enter key on verification code input
            $(document).on('keypress', '#wc-verification-code', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.verifyCode();
                }
            });
            
            // Escape key to close popup
            $(document).on('keydown', function(e) {
                if (e.keyCode === 27 && $('#wc-email-verification-popup').is(':visible')) {
                    self.closeVerificationPopup();
                }
            });
            
            // Block form submission if not verified
            $(document).on('submit', 'form.checkout, form.woocommerce-form-register', function(e) {
                if (!self.state.emailVerified) {
                    e.preventDefault();
                    self.openVerificationPopup();
                    return false;
                }
            });
            
            // Auto-format verification code input
            $(document).on('input', '#wc-verification-code', function() {
                var value = $(this).val().replace(/\D/g, ''); // Remove non-digits
                $(this).val(value);
            });
        },
        
        // Monitor email field changes
        monitorEmailField: function() {
            var self = this;
            
            // Monitor email field changes
            $(document).on('input keyup change blur paste', '#billing_email, #reg_email', function() {
                setTimeout(function() {
                    self.handleEmailChange();
                }, 100);
            });
            
            // More aggressive monitoring for dynamic content
            setInterval(function() {
                var currentEmail = self.getCurrentEmail();
                if (currentEmail !== self.state.currentEmail) {
                    self.handleEmailChange();
                }
            }, 1000);
        },
        
        // Setup initial state
        setupInitialState: function() {
            var self = this;
            
            // Initial check with multiple attempts
            setTimeout(function() {
                self.handleEmailChange();
            }, 500);
            
            setTimeout(function() {
                self.handleEmailChange();
            }, 1000);
            
            setTimeout(function() {
                self.handleEmailChange();
            }, 2000);
        },
        
        // Get current email value
        getCurrentEmail: function() {
            var email = $('#billing_email').val() || $('#reg_email').val() || '';
            return email.trim();
        },
        
        // Handle email field change
        handleEmailChange: function() {
            var email = this.getCurrentEmail();
            
            if (email !== this.state.currentEmail) {
                this.state.currentEmail = email;
                this.state.emailVerified = false;
                this.resetVerificationState();
                
                // Check if email is already verified
                if (email && this.isValidEmail(email)) {
                    this.checkEmailVerificationStatus(email);
                }
            }
            
            this.toggleVerificationButton();
        },
        
        // Toggle verification button visibility
        toggleVerificationButton: function() {
            var email = this.getCurrentEmail();
            var isValidEmail = this.isValidEmail(email);
            
            if (isValidEmail) {
                $('#wc-email-verification-wrapper').addClass('show');
                $('#wc-email-verification-trigger').show();
            } else {
                $('#wc-email-verification-wrapper').removeClass('show');
                $('#wc-email-verification-trigger').hide();
                $('#wc-email-verification-code-section').hide();
                this.clearMessages();
            }
            
            // Update submit button state
            this.updateSubmitButtonState();
        },
        
        // Check if email is valid
        isValidEmail: function(email) {
            if (!email || email.length < 5) return false;
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email) && email.length > 5;
        },
        
        // Reset verification state
        resetVerificationState: function() {
            $('#wc-email-verification-code-section').hide();
            $('#wc-email-verification-success').hide();
            this.clearMessages();
            this.clearTimer();
        },
        
        // Send verification code
        sendVerificationCode: function() {
            var self = this;
            var email = this.getCurrentEmail();
            var button = $('#wc-send-verification-btn');
            
            if (!email) {
                this.showPopupMessage(this.config.messages.invalidEmail, 'error');
                return;
            }
            
            this.setButtonLoading(button, true);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_send_verification_code',
                    email: email,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.setButtonLoading(button, false);
                    
                    if (response.success) {
                        self.showPopupMessage(response.data.message, 'success');
                        self.goToStep(2);
                        self.startResendTimer();
                    } else {
                        self.showPopupMessage(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.setButtonLoading(button, false);
                    self.showPopupMessage(self.config.messages.networkError, 'error');
                }
            });
        },
        
        // Verify code
        verifyCode: function() {
            var self = this;
            var email = this.getCurrentEmail();
            var code = $('#wc-verification-code').val().trim();
            var button = $('#wc-verify-code-btn');
            
            if (!code) {
                this.showPopupMessage(this.config.messages.enterCode, 'error');
                $('#wc-verification-code').focus();
                return;
            }
            
            this.setButtonLoading(button, true);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_verify_email_code',
                    email: email,
                    code: code,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.setButtonLoading(button, false);
                    
                    if (response.success) {
                        self.state.emailVerified = true;
                        self.goToStep(3);
                        self.clearTimer();
                        self.updateSubmitButtonState();
                        
                        // Close popup after 2 seconds
                        setTimeout(function() {
                            self.closeVerificationPopup();
                            self.showSuccessState();
                        }, 2000);
                    } else {
                        self.showPopupMessage(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.setButtonLoading(button, false);
                    self.showPopupMessage(self.config.messages.networkError, 'error');
                }
            });
        },
        
        // Resend verification code
        resendVerificationCode: function() {
            var self = this;
            var email = this.getCurrentEmail();
            var button = $('#wc-resend-verification-btn');
            
            if (!email) {
                this.showPopupMessage(this.config.messages.invalidEmail, 'error');
                return;
            }
            
            this.setButtonLoading(button, true);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_resend_verification_code',
                    email: email,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    self.setButtonLoading(button, false);
                    
                    if (response.success) {
                        self.showPopupMessage(response.data.message, 'success');
                        self.startResendTimer();
                    } else {
                        self.showPopupMessage(response.data.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    self.setButtonLoading(button, false);
                    self.showPopupMessage(self.config.messages.networkError, 'error');
                }
            });
        },
        
        // Show success state
        showSuccessState: function() {
            $('#wc-email-verification-trigger').hide();
            $('#wc-email-verification-code-section').hide();
            $('#wc-email-verification-success').show();
            this.clearMessages();
            
            // Enable submit button
            this.updateSubmitButtonState();
        },
        
        // Show message
        showMessage: function(message, type) {
            var messageClass = 'wc-verification-message-' + type;
            var messageHtml = '<div class="wc-verification-message ' + messageClass + '">' + message + '</div>';
            
            $('#wc-verification-messages').html(messageHtml);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $('#wc-verification-messages .wc-verification-message-success').fadeOut();
                }, 5000);
            }
        },
        
        // Clear messages
        clearMessages: function() {
            $('#wc-verification-messages').empty();
        },
        
        // Set button loading state
        setButtonLoading: function(button, loading) {
            if (loading) {
                button.prop('disabled', true);
                var originalText = button.text();
                button.data('original-text', originalText);
                button.text('Sending...');
            } else {
                button.prop('disabled', false);
                var originalText = button.data('original-text');
                if (originalText) {
                    button.text(originalText);
                }
            }
        },
        
        // Start resend timer
        startResendTimer: function() {
            var self = this;
            var countdown = this.state.resendCooldown;
            
            $('#wc-resend-verification-btn').hide();
            $('#wc-verification-timer').show();
            
            this.state.verificationTimer = setInterval(function() {
                countdown--;
                $('#wc-timer-countdown').text(countdown);
                
                if (countdown <= 0) {
                    self.clearTimer();
                }
            }, 1000);
        },
        
        // Clear timer
        clearTimer: function() {
            if (this.state.verificationTimer) {
                clearInterval(this.state.verificationTimer);
                this.state.verificationTimer = null;
            }
            
            $('#wc-resend-verification-btn').show();
            $('#wc-verification-timer').hide();
        },
        
        // Update submit button state
        updateSubmitButtonState: function() {
            var $checkoutBtn = $('button[type="submit"][name="woocommerce_checkout_place_order"]');
            var $registerBtn = $('button[type="submit"][name="register"]');
            
            if (this.state.emailVerified) {
                // Enable buttons
                $checkoutBtn.prop('disabled', false).removeClass('disabled');
                $registerBtn.prop('disabled', false).removeClass('disabled');
            } else {
                // Disable buttons
                $checkoutBtn.prop('disabled', true).addClass('disabled');
                $registerBtn.prop('disabled', true).addClass('disabled');
            }
        },
        
        // Check email verification status
        checkEmailVerificationStatus: function(email) {
            var self = this;
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wc_check_email_verification_status',
                    email: email,
                    nonce: this.config.nonce
                },
                success: function(response) {
                    if (response.success && response.data.verified) {
                        // Email is already verified
                        self.state.emailVerified = true;
                        $('#wc-email-verification-wrapper').addClass('show');
                        
                        // If popup is open, show success step
                        if ($('#wc-email-verification-popup').is(':visible')) {
                            self.goToStep(3);
                            self.closeVerificationPopup();
                        }
                        
                        self.showSuccessState();
                        self.updateSubmitButtonState();
                    }
                },
                error: function(xhr, status, error) {
                    // Silent error handling
                }
            });
        },
        
        // Scroll to verification section
        scrollToVerification: function() {
            $('html, body').animate({
                scrollTop: $('#wc-email-verification-wrapper').offset().top - 100
            }, 500);
        },
        
        // Open verification popup
        openVerificationPopup: function() {
            var email = this.getCurrentEmail();
            if (!email || !this.isValidEmail(email)) {
                this.showPopupMessage(this.config.messages.invalidEmail, 'error');
                return;
            }
            
            // Update email display in popup
            $('#wc-popup-email-display').text(email);
            
            // Reset popup state
            this.resetPopupState();
            
            // Show popup with proper animation
            $('#wc-email-verification-popup').show().addClass('show');
            $('body').addClass('wc-email-verification-popup-open');
            
            // Check if email is already verified
            this.checkEmailVerificationStatus(email);
        },
        
        // Close verification popup
        closeVerificationPopup: function() {
            var self = this;
            $('#wc-email-verification-popup').removeClass('show');
            $('body').removeClass('wc-email-verification-popup-open');
            
            // Hide popup after animation
            setTimeout(function() {
                $('#wc-email-verification-popup').hide();
            }, 300);
            
            this.clearPopupMessages();
        },
        
        // Reset popup state
        resetPopupState: function() {
            this.goToStep(1);
            $('#wc-verification-code').val('');
            this.clearPopupMessages();
            this.clearTimer();
        },
        
        // Go to specific step
        goToStep: function(step) {
            $('.wc-verification-step').removeClass('active');
            $('#wc-verification-step-' + step).addClass('active');
            
            if (step === 2) {
                setTimeout(function() {
                    $('#wc-verification-code').focus();
                }, 300);
            }
        },
        
        // Show popup message
        showPopupMessage: function(message, type) {
            var messageClass = 'wc-verification-message-' + type;
            var messageHtml = '<div class="wc-verification-message ' + messageClass + '">' + message + '</div>';
            
            $('#wc-popup-verification-messages').html(messageHtml);
            
            // Auto-hide success messages after 5 seconds
            if (type === 'success') {
                setTimeout(function() {
                    $('#wc-popup-verification-messages .wc-verification-message-success').fadeOut();
                }, 5000);
            }
        },
        
        // Clear popup messages
        clearPopupMessages: function() {
            $('#wc-popup-verification-messages').empty();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        WCEmailVerification.init();
    });

})(jQuery);
