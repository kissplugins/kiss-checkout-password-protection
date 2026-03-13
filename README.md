# Checkout Password Protection

A lightweight WordPress plugin that password-protects the WooCommerce checkout page on non-production environments. It is designed to travel with site clones so that staging and development copies are automatically protected, while production is never affected.

## Features

- **Fails open**: Production domains are never blocked, even if the plugin is misconfigured.
- **Admin bypass**: Logged-in administrators can always access checkout without a password.
- **Cookie-based session**: Visitors only need to enter the password once per browser session.
- **Zero dependencies** beyond WooCommerce (already required).
- **Single-file plugin**: Easy to deploy, audit, and remove.

## Requirements

- WordPress 5.0+
- WooCommerce (declared as a required plugin)

## Installation

1. Upload `checkout-password-protection.php` to your site's `wp-content/plugins/checkout-password-protection/` directory (create the folder if needed).
2. Activate the plugin from **WP Admin → Plugins**.
3. Edit the two configuration constants at the top of the file (see [Configuration](#configuration)).

## Configuration

Open `checkout-password-protection.php` and update the two constants near the top of the file:

```php
define( 'CPP_PASSWORD', 'your-password-here' );
define( 'CPP_PRODUCTION_DOMAINS', 'example.com,www.example.com' );
```

| Constant | Description |
|---|---|
| `CPP_PASSWORD` | The password non-admin visitors must enter to access checkout. |
| `CPP_PRODUCTION_DOMAINS` | Comma-separated list of production domain names. These domains are **never** protected. |

> **Tip:** Because the plugin file travels with database/file clones, simply set your real production domains in `CPP_PRODUCTION_DOMAINS` and every cloned environment will be protected automatically without any extra steps.

## How It Works

The plugin hooks into `template_redirect` and applies the following logic on every request to the checkout page:

1. **Production domain?** → Allow through (no protection).
2. **Logged-in administrator?** → Allow through (bypass).
3. **Valid password cookie present?** → Allow through.
4. **Password submitted and correct?** → Set cookie, redirect back to checkout.
5. **Otherwise** → Display a minimal password form and halt further output.

The thank-you / order-received page is never protected, so completed orders always display correctly.

## Emergency Disable

Use any one of the following methods to immediately disable the plugin:

- **WP Admin**: Deactivate from **Plugins → Installed Plugins**.
- **WP-CLI**: `wp plugin deactivate checkout-password-protection`
- **SFTP/SSH**: Rename the plugin folder (e.g. `checkout-password-protection_OFF`).

## Security Notes

- Passwords are **not** stored in plain text in cookies. The cookie value is an MD5 hash of the password combined with the site's WordPress salt.
- This plugin is intended as a lightweight deterrent for non-production environments, **not** as a replacement for network-level access controls (e.g. HTTP Basic Auth or IP allowlisting) on highly sensitive staging sites.

## License

This plugin is licensed under the [GNU General Public License v2.0](LICENSE) or later.
