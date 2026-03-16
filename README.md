# KISS Checkout Password Protection

Lightweight WooCommerce checkout protection for non-production WordPress environments.

## What it does

This plugin blocks access to the WooCommerce checkout page on non-production domains until a visitor enters a shared password. It is designed for cloned, staging, and development environments where checkout should stay hidden from normal visitors.

The plugin intentionally **fails open on production**. If the current host matches a configured production domain, checkout is not protected.

## Features

- Protects the WooCommerce checkout page with a password gate
- Automatically bypasses protection on configured production domains
- Automatically bypasses protection for logged-in administrators
- Skips WooCommerce `order-received` and `order-pay` endpoints
- Uses a WordPress password hash instead of storing a plaintext password
- Uses a nonce on form submission and hardened cookie flags

## Requirements

- WordPress
- WooCommerce

## Configuration

Edit these constants in `checkout-password-protection.php`:

- `CPP_PASSWORD_HASH` — a `wp_hash_password()` hash for the checkout password
- `CPP_PRODUCTION_DOMAINS` — comma-separated production domains that should never be protected

Example hash generation:

```php
echo wp_hash_password( 'your-chosen-password' );
```

Replace the placeholder value in `CPP_PASSWORD_HASH` with the generated hash before using the plugin.

## Behavior summary

Checkout protection is skipped when:

1. The current request is not for checkout
2. The request is for `order-received` or `order-pay`
3. The current host matches a configured production domain
4. The current user can `manage_options`

Otherwise, visitors must enter the configured password to continue to checkout.

## Emergency disable

- Deactivate the plugin in WordPress admin
- Run `wp plugin deactivate checkout-password-protection`
- Rename the plugin folder over SFTP/SSH

## Version

Current version: `1.0.1`

## License

Licensed under **GNU General Public License v2.0**. See `LICENSE` or `license.txt`.