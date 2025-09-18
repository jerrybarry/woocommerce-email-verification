<?php
/**
 * Test script to verify email verification functionality
 * Add this to your theme's functions.php temporarily
 */

// Add test button to verify functionality
add_action('wp_footer', 'add_verification_test_button');

function add_verification_test_button() {
    if (is_checkout() || is_account_page() || (isset($_GET['action']) && $_GET['action'] === 'register')) {
        ?>
        <div style="position: fixed; bottom: 10px; right: 10px; background: #0073aa; color: white; padding: 10px; z-index: 9999; border-radius: 4px;">
            <h4 style="margin: 0 0 10px 0;">Verification Test</h4>
            <button type="button" id="test-show-verification" style="background: white; color: #0073aa; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-right: 5px;">
                Show Verification
            </button>
            <button type="button" id="test-hide-verification" style="background: white; color: #0073aa; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer; margin-right: 5px;">
                Hide Verification
            </button>
            <button type="button" id="test-enable-buttons" style="background: white; color: #0073aa; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                Enable Buttons
            </button>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-show-verification').click(function() {
                $('#wc-email-verification-wrapper').show();
                console.log('Test: Showing verification section');
            });
            
            $('#test-hide-verification').click(function() {
                $('#wc-email-verification-wrapper').hide();
                console.log('Test: Hiding verification section');
            });
            
            $('#test-enable-buttons').click(function() {
                $('button[type="submit"]').prop('disabled', false).removeClass('disabled');
                console.log('Test: Enabling all submit buttons');
            });
        });
        </script>
        <?php
    }
}
