# Installation Guide

## Quick Setup

1. **Upload the plugin** to your WordPress site:
   - Upload the `wc-email-verification` folder to `/wp-content/plugins/`
   - Or zip the folder and upload via WordPress admin

2. **Activate the plugin**:
   - Go to Plugins > Installed Plugins
   - Find "WooCommerce Email Verification" and click "Activate"

3. **Configure settings**:
   - Go to WooCommerce > Email Verification
   - Enable the plugin and set your preferences

## Troubleshooting

### Verification section not appearing?

1. **Check if WooCommerce is active**:
   - The plugin requires WooCommerce to be installed and activated

2. **Check plugin settings**:
   - Go to WooCommerce > Email Verification
   - Ensure "Enable Email Verification" is checked
   - Ensure "Checkout Verification" or "Registration Verification" is checked

3. **Enable debug mode**:
   - Add this to your `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
   - Check `/wp-content/debug.log` for error messages

4. **Check if hooks are working**:
   - Add this temporary code to your theme's `functions.php`:
   ```php
   add_action('wp_footer', function() {
       if (is_checkout() || is_account_page()) {
           echo '<div style="position: fixed; top: 10px; right: 10px; background: red; color: white; padding: 10px; z-index: 9999;">';
           echo '<h3>Debug Info:</h3>';
           
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
           } else {
               echo '<p>✗ Plugin class not found</p>';
           }
           
           echo '</div>';
       }
   });
   ```

5. **Check for JavaScript errors**:
   - Open browser developer tools (F12)
   - Go to Console tab
   - Look for any JavaScript errors
   - Check if `wcEmailVerification` object is available

6. **Check for theme conflicts**:
   - Switch to a default WordPress theme temporarily
   - Test if verification section appears
   - If it works, there's a theme conflict

## Common Issues

### "Plugin class not found"
- Plugin is not activated properly
- Try deactivating and reactivating the plugin

### "Settings not set"
- Plugin needs to be activated first
- Go to WooCommerce > Email Verification to set default settings

### JavaScript errors
- Check if jQuery is loaded
- Check if WooCommerce scripts are loaded
- Check for conflicts with other plugins

### Emails not sending
- Check WordPress email configuration
- Test with a plugin like "WP Mail SMTP"
- Check spam folder
- Use the test email function in admin

## Support

If you're still having issues, check the debug log and provide:
1. Error messages from debug.log
2. Browser console errors
3. Plugin settings screenshot
4. WordPress and WooCommerce versions
