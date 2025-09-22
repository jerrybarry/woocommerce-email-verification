# Translation Guide

This directory contains translation files for the WooCommerce Email Verification plugin.

## Files

- `wc-email-verification.pot` - Translation template file containing all translatable strings
- `README.md` - This file

## How to Translate

### Using Polylang

1. Install and activate Polylang plugin
2. Go to **Languages** → **String Translations**
3. Select the domain `wc-email-verification`
4. Translate all the strings shown

### Using Loco Translate

1. Install and activate Loco Translate plugin
2. Go to **Loco Translate** → **Plugins** → **WooCommerce Email Verification**
3. Click **New Language** and select your language
4. Translate all the strings

### Manual Translation

1. Copy `wc-email-verification.pot` to `wc-email-verification-{locale}.po`
2. Replace `{locale}` with your language code (e.g., `es_ES`, `fr_FR`, `de_DE`)
3. Translate all `msgstr ""` entries
4. Save the file as `wc-email-verification-{locale}.po`
5. Place it in the `languages/` directory

## Translation Coverage

The plugin includes translations for:

- **Frontend Interface**: All user-facing text on checkout and registration pages
- **Admin Interface**: All admin settings and configuration text
- **Email Templates**: All email content including subject lines and body text
- **JavaScript Messages**: All AJAX response messages and user notifications
- **Error Messages**: All error and success messages

## Important Notes

- All strings use the text domain `wc-email-verification`
- Some strings contain placeholders like `{site_name}`, `{verification_code}`, etc. - keep these placeholders intact
- HTML tags in email templates should be preserved
- Use `sprintf()` placeholders (`%s`, `%d`) correctly in your translations

## Support

For translation support, please contact the plugin author or create an issue on the plugin's repository.
