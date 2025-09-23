# Polylang Integration Guide

This document explains how the WooCommerce Email Verification plugin integrates with Polylang for multilingual support.

## Overview

The plugin is fully compatible with [Polylang](https://polylang.pro/) and provides comprehensive translation support for all user-facing strings, admin interface, and email templates.

## Features

- ✅ **Frontend Translation**: All checkout and registration page strings
- ✅ **Admin Translation**: All settings and configuration text
- ✅ **Email Translation**: Complete email template translation
- ✅ **JavaScript Translation**: AJAX messages and notifications
- ✅ **Automatic String Registration**: Strings are automatically registered with Polylang
- ✅ **Fallback Support**: Works with or without Polylang installed

## Setup Instructions

### 1. Install Polylang

1. Install and activate the Polylang plugin
2. Configure your languages in **Languages** → **Languages**
3. Set up language switcher if desired

### 2. Access String Translations

1. Go to **Languages** → **String Translations**
2. Select the domain `wc-email-verification`
3. All plugin strings will be listed and ready for translation

### 3. Translate Strings

1. **Frontend Strings**: Translate user-facing text on checkout/registration pages
2. **Admin Strings**: Translate admin interface labels and descriptions
3. **Email Strings**: Translate email templates and subject lines
4. **Error Messages**: Translate all error and success messages

## String Categories

### Frontend UI Strings
- Email Verification
- Verify your email address to proceed
- Send Verification Code
- Enter Verification Code
- Verify
- Resend Code
- Email Verified!

### Email Template Strings
- Verify Your Email Address
- Thank you for registering with %s...
- Your Verification Code:
- This code will expire in %s minutes
- Security Notice:
- Best regards,<br>The %s Team

### Admin Interface Strings
- Enable Email Verification
- Checkout Verification
- Registration Verification
- Code Length
- Code Expiry (minutes)
- Rate Limit
- Email Subject
- Email Template

### Error & Success Messages
- Please enter a valid email address
- Verification code sent to your email!
- Invalid or expired verification code
- Network error. Please try again
- Too many verification attempts

## Technical Details

### String Registration

The plugin automatically registers all strings with Polylang using:

```php
pll_register_string($name, $string, 'wc-email-verification', $multiline);
```

### Helper Functions

The plugin provides helper functions for translation:

```php
// Translate a string
wc_email_verification_translate('Email Verification');

// Echo a translated string
wc_email_verification_translate_e('Email Verification');
```

These functions automatically fall back to WordPress `__()` and `_e()` if Polylang is not available.

### Multiline Support

Long strings and HTML content are automatically detected and registered as multiline fields in Polylang for better translation experience.

## Testing Translations

### 1. Frontend Testing
1. Switch to a translated language
2. Go to checkout or registration page
3. Enter an email address
4. Verify all text appears in the correct language

### 2. Email Testing
1. Use the "Send Test Email" feature in admin
2. Check that email content appears in the correct language
3. Verify placeholders like `{site_name}` and `{verification_code}` work correctly

### 3. Admin Testing
1. Switch to admin language
2. Go to **WooCommerce** → **Email Verification**
3. Verify all labels and descriptions are translated

## Troubleshooting

### Strings Not Appearing in Polylang

1. **Deactivate and reactivate** the plugin
2. Check that Polylang is active and configured
3. Look for the debug notice (if WP_DEBUG is enabled)
4. Clear any caching plugins

### Translations Not Working

1. Verify strings are translated in Polylang
2. Check that the correct language is selected
3. Clear browser cache
4. Test with different browsers

### Email Translations Not Working

1. Ensure email template strings are translated
2. Check that placeholders are preserved
3. Test with different email clients
4. Verify HTML formatting is maintained

## Advanced Usage

### Custom String Registration

To add custom strings to the plugin's translation system:

```php
add_action('init', function() {
    if (function_exists('pll_register_string')) {
        pll_register_string('Custom String Name', 'Your custom string', 'wc-email-verification');
    }
});
```

### Conditional Translation

For conditional translations based on context:

```php
$message = wc_email_verification_translate('Email Verification');
if (is_checkout()) {
    $message .= ' - ' . wc_email_verification_translate('Checkout');
}
```

## Support

For translation support or issues:

1. Check the plugin's documentation
2. Review Polylang's documentation
3. Contact plugin support
4. Create an issue on the plugin repository

## Changelog

- **v1.2.2**: Enhanced Polylang integration with proper string registration
- **v1.2.1**: Fixed text domain loading for Polylang compatibility
- **v1.2.0**: Initial internationalization support
