# Changelog

## [1.1.0] - 2026-03-13

### Security
- Password is now stored as a hash (`CPP_PASSWORD_HASH`) and verified with `wp_check_password()` instead of plaintext comparison.
- Cookie token upgraded from `md5()` to `hash_hmac('sha256', ...)` with timing-safe `hash_equals()`.
- Cookie now set with `Secure` (via `is_ssl()`), `HttpOnly`, and `SameSite=Strict` flags.
- Added CSRF protection via `wp_nonce_field()` / `wp_verify_nonce()`.
- POST input sanitized with `wp_unslash()` and `sanitize_text_field()`.
- Plugin detects placeholder password hash and fails open with a `_doing_it_wrong()` notice.

## [1.0.0] - Initial release

- Password-protects WooCommerce checkout on non-production environments.
- Fail-open design: production domains are never protected.
- Admin bypass via `current_user_can('manage_options')`.
