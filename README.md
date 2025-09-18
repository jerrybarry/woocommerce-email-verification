# WooCommerce Email Verification Plugin

A comprehensive email verification plugin for WooCommerce that adds email verification functionality to checkout and registration processes.

## Features

### Core Functionality
- **Email Verification for Checkout**: Require email verification before customers can complete their purchase
- **Email Verification for Registration**: Require email verification for new user registrations
- **Customizable Verification Codes**: Configurable code length (4-8 digits) and expiry time (1-60 minutes)
- **Rate Limiting**: Prevent abuse with configurable rate limiting per IP/email
- **Session Management**: Maintain verification status across page loads

### Security Features
- **Rate Limiting**: Configurable attempts per hour per IP/email combination
- **Nonce Verification**: CSRF protection for all AJAX requests
- **Input Validation**: Comprehensive validation and sanitization
- **IP Tracking**: Track verification attempts by IP address
- **Attempt Logging**: Log all verification attempts for monitoring

### User Experience
- **Responsive Design**: Mobile-first design that works on all devices
- **Real-time Validation**: Instant feedback on email format and verification status
- **Loading States**: Visual feedback during verification processes
- **Auto-formatting**: Automatic formatting of verification code input
- **Resend Functionality**: Ability to resend verification codes with cooldown timer
- **Accessibility**: WCAG compliant with proper focus management and screen reader support

### Admin Features
- **Comprehensive Settings**: Full control over verification behavior
- **Email Customization**: Customizable email templates with placeholder support
- **Statistics Dashboard**: View verification statistics and success rates
- **Activity Logs**: Monitor verification attempts and troubleshoot issues
- **Test Email Function**: Send test emails to verify configuration
- **Database Management**: Automatic cleanup of expired codes and old logs

### Email Features
- **HTML Email Templates**: Beautiful, responsive email templates
- **Placeholder Support**: Dynamic content with placeholders for personalization
- **Customizable Sender**: Configure from name and email address
- **Mobile Optimized**: Emails look great on all devices
- **Branding Support**: Customize emails to match your brand

## Installation

1. Upload the plugin files to `/wp-content/plugins/wc-email-verification/` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Email Verification to configure settings
4. Ensure WooCommerce is installed and activated

## Configuration

### General Settings
- **Enable Email Verification**: Toggle the entire verification system
- **Checkout Verification**: Require verification for checkout process
- **Registration Verification**: Require verification for user registration
- **Code Length**: Set verification code length (4-8 digits)
- **Code Expiry**: Set how long codes remain valid (1-60 minutes)

### Email Settings
- **From Name**: Name that appears in verification emails
- **From Email**: Email address that sends verification emails
- **Email Subject**: Subject line for verification emails
- **Email Template**: HTML template for verification emails

### Security Settings
- **Rate Limiting**: Maximum verification attempts per hour per IP/email

## Email Template Placeholders

The email template supports the following placeholders:

- `{verification_code}` - The 6-digit verification code
- `{expiry_time}` - How many minutes until the code expires
- `{site_name}` - Your website name
- `{site_url}` - Your website URL

## Hooks and Filters

### Actions
- `wc_email_verification_before_send_code` - Before sending verification code
- `wc_email_verification_after_send_code` - After sending verification code
- `wc_email_verification_before_verify_code` - Before verifying code
- `wc_email_verification_after_verify_code` - After verifying code

### Filters
- `wc_email_verification_email_subject` - Modify email subject
- `wc_email_verification_email_template` - Modify email template
- `wc_email_verification_code_length` - Modify code length
- `wc_email_verification_code_expiry` - Modify code expiry time

## Database Tables

The plugin creates three database tables:

- `wp_wc_email_verifications` - Stores verification codes and status
- `wp_wc_email_verification_logs` - Logs all verification activities
- `wp_wc_email_verification_rate_limits` - Tracks rate limiting data

## API Functions

### Check Verification Status
```php
// Check if email is verified in current session
WC_Email_Verification_Frontend::is_email_verified($email);

// Check if user's email is verified
WC_Email_Verification_Frontend::is_user_email_verified($user_id);
```

### Logging
```php
// Log custom messages
WC_Email_Verification_Logger::info('Custom message', $context);
WC_Email_Verification_Logger::error('Error message', $context);
```

## Troubleshooting

### Common Issues

1. **Emails not sending**
   - Check your WordPress email configuration
   - Verify SMTP settings if using SMTP plugin
   - Check spam folder
   - Use the test email function in admin

2. **Verification not working**
   - Ensure JavaScript is enabled
   - Check browser console for errors
   - Verify nonce is being generated correctly
   - Check rate limiting settings

3. **Database errors**
   - Ensure database user has CREATE TABLE permissions
   - Check for plugin conflicts
   - Verify WordPress database is accessible

### Debug Mode

Enable WordPress debug mode to see detailed error messages:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## Requirements

- WordPress 5.0 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Changelog

### Version 1.0.0
- Initial release
- Email verification for checkout and registration
- Admin settings page
- Rate limiting and security features
- Responsive design
- Comprehensive logging

## Support

For support, feature requests, or bug reports, please contact the plugin developer.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed with ❤️ for the WooCommerce community.
