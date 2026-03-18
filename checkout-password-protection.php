<?php
/**
 * Plugin Name: KISS Checkout Password Protection
 * Description: Password-protects WooCommerce checkout on non-production environments. Fails open — production domains are never protected. Admins bypass automatically.
 * Version: 1.0.1
 * Author: KISS Plugins | Hypercart
 * License: GPL v2
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 *
 * SETUP: Edit the two constants below (CPP_PASSWORD_HASH and CPP_PRODUCTION_DOMAINS).
 *        This file travels with site clones, so cloned environments are automatically protected.
 *
 * BEHAVIOR:
 *   - If current domain is in CPP_PRODUCTION_DOMAINS → no protection (production)
 *   - If visitor is a logged-in administrator         → no protection (bypass)
 *   - Otherwise                                       → password form on checkout
 *
 * EMERGENCY DISABLE (any one of these):
 *   - Deactivate from WP Admin → Plugins
 *   - WP-CLI: wp plugin deactivate checkout-password-protection
 *   - SFTP: rename the plugin folder
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
|--------------------------------------------------------------------------
| Configuration — edit these values
|--------------------------------------------------------------------------
|
| CPP_PASSWORD_HASH:      A wp_hash_password() hash of the password visitors must enter.
|                         Generate with: echo wp_hash_password('your-chosen-password');
| CPP_PRODUCTION_DOMAINS: Comma-separated list of production domains (exact match).
|                         These domains are NEVER protected.
|
*/
define( 'CPP_PASSWORD_HASH', '$P$BxHa9NlCa.ciwlBfIRInqYdrhaw78R0' );
define( 'CPP_PRODUCTION_DOMAINS', 'binoidcbd.com,bloomzhemp.com,binoid.com' );

add_action( 'template_redirect', 'cpp_maybe_protect_checkout' );

function cpp_maybe_protect_checkout() {
    // Only protect the checkout page
    if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
        return;
    }

    // Don't protect order-received (thank you) or order-pay endpoints
    if ( is_wc_endpoint_url( 'order-received' ) || is_wc_endpoint_url( 'order-pay' ) ) {
        return;
    }

    // Production domains are never protected (fail open)
    $current_host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( trim( $_SERVER['HTTP_HOST'] ) ) : '';
    $current_host = preg_replace( '/^www\./', '', $current_host );
    $production_domains = array_map( function( $d ) {
        return preg_replace( '/^www\./', '', strtolower( trim( $d ) ) );
    }, explode( ',', CPP_PRODUCTION_DOMAINS ) );

    if ( in_array( $current_host, $production_domains, true ) ) {
        return;
    }

    // Logged-in administrators bypass
    if ( current_user_can( 'manage_options' ) ) {
        return;
    }

    // Bail out if the password hash is still the placeholder value
    if ( CPP_PASSWORD_HASH === '$P$B...' ) {
        _doing_it_wrong( __FUNCTION__, 'CPP_PASSWORD_HASH is still the placeholder value. Generate a real hash — see plugin instructions.', '1.0.1' );
        return;
    }

    // Check for valid password cookie
    $cookie_name  = 'cpp_checkout_access';
    $cookie_token = hash_hmac( 'sha256', CPP_PASSWORD_HASH, wp_salt() );
    if ( isset( $_COOKIE[ $cookie_name ] ) && hash_equals( $cookie_token, $_COOKIE[ $cookie_name ] ) ) {
        return;
    }

    // Handle password form submission
    if ( isset( $_POST['cpp_password'] ) && isset( $_POST['_cpp_nonce'] )
         && wp_verify_nonce( $_POST['_cpp_nonce'], 'cpp_checkout_password' ) ) {
        $submitted = sanitize_text_field( wp_unslash( $_POST['cpp_password'] ) );

        if ( wp_check_password( $submitted, CPP_PASSWORD_HASH ) ) {
            setcookie( $cookie_name, $cookie_token, [
                'expires'  => 0,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly'  => true,
                'samesite' => 'Strict',
            ] );
            wp_safe_redirect( wc_get_checkout_url() );
            exit;
        }
        $error = true;
    }

    // Show password form
    cpp_render_password_form( ! empty( $error ) );
    exit;
}

function cpp_render_password_form( $has_error = false ) {
    $site_name = get_bloginfo( 'name' );
    ?>
    <!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
        <meta charset="<?php bloginfo( 'charset' ); ?>">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Checkout Protected — <?php echo esc_html( $site_name ); ?></title>
        <style>
            * { box-sizing: border-box; margin: 0; padding: 0; }
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f0f1; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
            .cpp-form { background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.1); max-width: 400px; width: 90%; }
            .cpp-form h1 { font-size: 1.25rem; margin-bottom: .5rem; }
            .cpp-form p { color: #666; font-size: .875rem; margin-bottom: 1.5rem; }
            .cpp-form input[type="password"] { width: 100%; padding: .625rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 1rem; }
            .cpp-form button { width: 100%; padding: .625rem; background: #2271b1; color: #fff; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; }
            .cpp-form button:hover { background: #135e96; }
            .cpp-error { color: #d63638; font-size: .875rem; margin-bottom: 1rem; }
        </style>
    </head>
    <body>
        <div class="cpp-form">
            <h1>Checkout Protected</h1>
            <p>This is a non-production environment. Enter the password to access checkout.</p>
            <?php if ( $has_error ) : ?>
                <p class="cpp-error">Incorrect password. Please try again.</p>
            <?php endif; ?>
            <form method="post">
                <?php wp_nonce_field( 'cpp_checkout_password', '_cpp_nonce' ); ?>
                <input type="password" name="cpp_password" placeholder="Password" autofocus>
                <button type="submit">Access Checkout</button>
            </form>
        </div>
    </body>
    </html>
    <?php
}
