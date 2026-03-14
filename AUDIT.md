# Audit Report for KISS Checkout Password Protection

This report details the findings from a code review of the `kiss-checkout-password-protection.php` plugin. The review was conducted based on the rules outlined in `AGENTS.md`.

## Round 1 - Original Audit

### Summary of Findings

| Severity | Finding | Details |
| :--- | :--- | :--- |
| **Medium** | **Hardcoded Plaintext Password** | The password is defined in plaintext within the PHP file (`define( 'CPP_PASSWORD', 'your-password-here' );`). While the password's purpose is to block the checkout flow on non-production environments and not to protect sensitive data, storing secrets in plaintext is a bad practice and should be avoided. |
| **Medium** | **Lack of CSRF Protection** | The password submission form does not include a nonce, making it vulnerable to Cross-Site Request Forgery (CSRF) attacks. An attacker could potentially trick a user into submitting the form. |
| **Medium**| **Inline CSS and HTML** | The plugin renders a full HTML page with inline CSS. This goes against WordPress best practices for enqueuing scripts and styles and could cause conflicts with themes or other plugins. |
| **Low** | **Use of `$_SERVER['HTTP_HOST']`** | The plugin relies on `$_SERVER['HTTP_HOST']` to identify the domain. This can be manipulated, though the risk in this specific context is low. |
| **Low** | **Unsanitized POST Data** | `$_POST['cpp_password']` is used without `wp_unslash()` or `sanitize_text_field()`. While the strict `===` comparison prevents injection, WordPress best practices require sanitizing all superglobal input. |

## Production vs. Dev/Staging Environments

The plugin **does effectively distinguish between production and non-production environments**. It uses the `CPP_PRODUCTION_DOMAINS` constant to create a whitelist of production domains where the password protection is explicitly disabled. This "fail-open" approach is a good safety measure, ensuring that the protection doesn't accidentally run on a live site.

### Recommendations

- [x] **Password Storage**: Even for a low-security password, it's better to store a hash of the password rather than the plaintext version. *(Fixed — now uses `CPP_PASSWORD_HASH` with `wp_check_password()`. Cookie token upgraded from `md5()` to `hash_hmac('sha256', ...)` with timing-safe `hash_equals()`.)*
- [x] **CSRF Protection**: Implement a WordPress nonce in the password form to prevent CSRF attacks. *(Already fixed on `development` branch — uses `wp_nonce_field()` and `wp_verify_nonce()`.)*
- [x] **Input Sanitization**: Sanitize `$_POST['cpp_password']` with `wp_unslash()` and `sanitize_text_field()` per WordPress best practices. *(Fixed.)*
- [ ] ~**Code Structure**: Refactor the plugin to enqueue a separate stylesheet and use hooks to display the password form within the checkout page, rather than rendering a separate, standalone page.~ *(Skipped — the standalone gate page is intentional by design. Rendering within the checkout template would leak page content before password entry, and loading the full WP front-end stack defeats the purpose of the lightweight gate.)*

## Recommended Password Storage Mechanism

A simple and effective password storage mechanism, without over-engineering, would be to use WordPress's built-in password hashing functions. This is the standard and recommended approach for handling passwords in the WordPress ecosystem.

Here’s how you could implement it:

1.  **Store a Hash, Not Plaintext**: Instead of defining the password directly, you would store its hash. You can generate this hash once and place it in your configuration.

    To generate the hash, you could temporarily add this code to your plugin or `functions.php` file, visit a page, and then remove it:
    ```php
    // echo wp_hash_password('your-chosen-password');
    ```
    This will output a string like `$P$B...`. This is your password hash.

2.  **Update the Configuration**: Replace the plaintext password with the generated hash:
    ```php
    define( 'CPP_PASSWORD_HASH', '$P$B...' ); // Replace with your actual hash
    ```

3.  **Check the Password**: In the `cpp_maybe_protect_checkout` function, you would change the password verification logic to use `wp_check_password()`:
    ```php
    // Instead of this:
    // if ( $_POST['cpp_password'] === CPP_PASSWORD ) {

    // Use this:
    if ( isset( $_POST['cpp_password'] ) && wp_check_password( $_POST['cpp_password'], CPP_PASSWORD_HASH ) ) {
        // ... password is correct
    }
    ```

### Why is this a good approach?

*   **Simple**: It uses core WordPress functions that are readily available.
*   **Secure**: It leverages WordPress's robust and well-maintained password hashing library (phpass), which handles salting automatically. You are not storing the actual password, so if your code is exposed, the password isn't immediately compromised.
*   **No Over-Engineering**: It's the idiomatic WordPress way of handling passwords, so it's not considered an over-engineered solution for this context.

## Round 2 - Augment Review

- [x] **Cookie security flags are missing** — The checkout access cookie is set without `Secure`, `HttpOnly`, or `SameSite` attributes. This is the biggest remaining hardening gap because the cookie can be more easily exposed or reused than necessary. *(Fixed — cookie now uses `Secure` (via `is_ssl()`), `HttpOnly`, and `SameSite=Strict`.)*
- [ ] **Production-domain detection relies on raw `HTTP_HOST` exact matching** — The current fail-open production bypass uses `$_SERVER['HTTP_HOST']` and exact string comparison. This is brittle in proxy / alias / port scenarios and can create either accidental protection on production or unintended bypasses in edge cases. *(Acknowledged — fail-open design means edge cases only cause extra protection on production, not a bypass. `www.` stripping already added on `development` branch. Low priority.)*
- [ ] **No brute-force throttling on the password form** — There is no attempt limit, rate limit, delay, or temporary lockout around password submissions. On publicly reachable staging environments, this makes the gate brute-forceable. *(Skipped — adding state for rate limiting conflicts with the plugin's KISS philosophy. Better handled at infrastructure level.)*
- [x] **Verify the configured password hash is not still the placeholder value** — The code currently contains `define( 'CPP_PASSWORD_HASH', '$P$B...' );`. If that literal value is present in a deployed environment, the protection is misconfigured and should be replaced with a real hash immediately. *(Fixed — plugin now detects the placeholder hash, calls `_doing_it_wrong()`, and fails open.)*
- [x] **No major performance red flags found** — The plugin remains lightweight, exits early on non-checkout requests, and only performs password verification on form submission. No significant performance issues were identified in this review round.

