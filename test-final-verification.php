<?php
/**
 * Final test script for email verification
 * Add this to your theme's functions.php temporarily
 */

// Add test info to verify everything is working
add_action('wp_footer', 'add_final_verification_test');

function add_final_verification_test() {
    if (is_checkout() || is_account_page() || (isset($_GET['action']) && $_GET['action'] === 'register')) {
        ?>
        <div style="position: fixed; top: 10px; left: 10px; background: #28a745; color: white; padding: 10px; z-index: 9999; border-radius: 4px; max-width: 300px; font-size: 12px;">
            <h4 style="margin: 0 0 10px 0;">✅ Verification Test</h4>
            <div id="verification-status">
                <p>Status: <span id="status-text">Checking...</span></p>
                <p>Email: <span id="email-text">-</span></p>
                <p>Verified: <span id="verified-text">-</span></p>
                <p>Button: <span id="button-text">-</span></p>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            function updateStatus() {
                var email = $('#billing_email').val() || $('#reg_email').val() || '';
                var isVerified = false;
                
                // Check if verification success is visible
                if ($('#wc-email-verification-success').is(':visible')) {
                    isVerified = true;
                }
                
                // Check if buttons are enabled
                var checkoutBtn = $('button[type="submit"][name="woocommerce_checkout_place_order"]');
                var registerBtn = $('button[type="submit"][name="register"]');
                var buttonsEnabled = !checkoutBtn.prop('disabled') || !registerBtn.prop('disabled');
                
                $('#email-text').text(email || 'None');
                $('#verified-text').text(isVerified ? 'Yes' : 'No');
                $('#button-text').text(buttonsEnabled ? 'Enabled' : 'Disabled');
                $('#status-text').text('Monitoring...');
            }
            
            // Update status every second
            setInterval(updateStatus, 1000);
            
            // Initial update
            updateStatus();
            
            // Update on email change
            $(document).on('input', '#billing_email, #reg_email', function() {
                setTimeout(updateStatus, 100);
            });
        });
        </script>
        <?php
    }
}
