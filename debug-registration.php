<?php
/**
 * Debug script specifically for registration page
 * Add this to your theme's functions.php temporarily
 */

// Add debug info to registration page
add_action('wp_footer', 'debug_registration_verification');

function debug_registration_verification() {
    // Only on registration page
    if (is_account_page() || (isset($_GET['action']) && $_GET['action'] === 'register')) {
        echo '<div style="position: fixed; top: 10px; left: 10px; background: blue; color: white; padding: 10px; z-index: 9999; max-width: 300px;">';
        echo '<h3>Registration Debug:</h3>';
        
        // Check if verification section exists
        if (strpos(get_the_content(), 'wc-email-verification-wrapper') !== false) {
            echo '<p>✓ Verification section found in content</p>';
        } else {
            echo '<p>✗ Verification section NOT found in content</p>';
        }
        
        // Check if JavaScript is loaded
        echo '<p>jQuery loaded: ' . (wp_script_is('jquery', 'done') ? 'Yes' : 'No') . '</p>';
        
        // Check if our script is loaded
        global $wp_scripts;
        if (isset($wp_scripts->registered['wc-email-verification'])) {
            echo '<p>✓ Our script is registered</p>';
        } else {
            echo '<p>✗ Our script is NOT registered</p>';
        }
        
        // Check if email field exists
        echo '<script>
        jQuery(document).ready(function($) {
            if ($("#reg_email").length > 0) {
                $("body").append("<p style=\"color: white;\">✓ Email field found: " + $("#reg_email").length + "</p>");
            } else {
                $("body").append("<p style=\"color: red;\">✗ Email field NOT found</p>");
            }
            
            if ($("#wc-email-verification-trigger").length > 0) {
                $("body").append("<p style=\"color: white;\">✓ Verification trigger found: " + $("#wc-email-verification-trigger").length + "</p>");
                $("body").append("<p style=\"color: white;\">Trigger display: " + $("#wc-email-verification-trigger").css("display") + "</p>");
            } else {
                $("body").append("<p style=\"color: red;\">✗ Verification trigger NOT found</p>");
            }
        });
        </script>';
        
        echo '</div>';
    }
}
