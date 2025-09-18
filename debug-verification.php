<?php
/**
 * Debug script to test email verification functionality
 * Add this to your theme's functions.php temporarily to debug
 */

// Add this to your theme's functions.php to test if the verification section appears
add_action('wp_footer', 'debug_verification_section');

function debug_verification_section() {
    if (is_checkout() || is_account_page()) {
        echo '<div style="position: fixed; top: 10px; right: 10px; background: red; color: white; padding: 10px; z-index: 9999;">';
        echo '<h3>Debug Info:</h3>';
        
        // Check if plugin is active
        if (class_exists('WC_Email_Verification')) {
            echo '<p>✓ Plugin class exists</p>';
            
            $instance = WC_Email_Verification::get_instance();
            $settings = $instance->get_settings();
            
            echo '<p>Settings:</p>';
            echo '<ul>';
            echo '<li>Enabled: ' . ($settings['enabled'] ?? 'not set') . '</li>';
            echo '<li>Checkout Required: ' . ($settings['checkout_required'] ?? 'not set') . '</li>';
            echo '<li>Registration Required: ' . ($settings['registration_required'] ?? 'not set') . '</li>';
            echo '</ul>';
            
            // Check if hooks are registered
            global $wp_filter;
            $checkout_hook = isset($wp_filter['woocommerce_checkout_billing']) ? 'Yes' : 'No';
            $registration_hook = isset($wp_filter['woocommerce_register_form']) ? 'Yes' : 'No';
            
            echo '<p>Hooks registered:</p>';
            echo '<ul>';
            echo '<li>Checkout: ' . $checkout_hook . '</li>';
            echo '<li>Registration: ' . $registration_hook . '</li>';
            echo '</ul>';
            
        } else {
            echo '<p>✗ Plugin class not found</p>';
        }
        
        echo '</div>';
    }
}
