<?php
/*
Plugin Name: Microsoft Entra SSO for YOURLS
Plugin URI: https://it.telkomuniversity.ac.id/
Description: Secure Microsoft Entra ID SSO for YOURLS with configurable domain validation and AuthMgrPlus role integration.
Version: 1.4.3
Author: Konten Telu
Author URI: https://it.telkomuniversity.ac.id/
License: GPL-3.0-or-later
*/

if ( ! defined( 'YOURLS_ABSPATH' ) ) {
    die();
}

define( 'TELU_ENTRA_SSO_VERSION', '1.4.3' );
define( 'TELU_ENTRA_AUTH_COOKIE', '__Host-TelUEntraAuth' );
define( 'TELU_ENTRA_FLOW_COOKIE', '__Host-TelUEntraFlow' );
define( 'TELU_ENTRA_JWKS_OPTION', 'telu_entra_sso_jwks_v1' );
define( 'TELU_ENTRA_TENANT_OPTION', 'telu_entra_sso_tenant_id_v1' );
define( 'TELU_ENTRA_CLIENT_OPTION', 'telu_entra_sso_client_id_v1' );
define( 'TELU_ENTRA_ENABLED_OPTION', 'telu_entra_sso_enabled_v1' );
define( 'TELU_ENTRA_TEST_OPTION', 'telu_entra_sso_last_test_v1' );
define( 'TELU_ENTRA_SESSION_OPTION', 'telu_entra_sso_session_lifetime_v1' );
define( 'TELU_ENTRA_GROUPS_OPTION', 'telu_entra_sso_allowed_groups_v1' );
define( 'TELU_ENTRA_ROLES_OPTION', 'telu_entra_sso_allowed_roles_v1' );
define( 'TELU_ENTRA_AUDIT_OPTION', 'telu_entra_sso_audit_v1' );
define( 'TELU_ENTRA_ADMINS_OPTION', 'telu_entra_sso_admin_emails_v1' );
define( 'TELU_ENTRA_EDITORS_OPTION', 'telu_entra_sso_editor_emails_v1' );
define( 'TELU_ENTRA_HOMEPAGE_OPTION', 'telu_entra_sso_homepage_hook_v1' );

yourls_add_filter( 'shunt_is_valid_user', 'telu_entra_authenticate', 8 );
yourls_add_filter( 'logout_link', 'telu_entra_display_name_in_header', 20 );
yourls_add_action( 'pre_load_template', 'telu_entra_protect_homepage', 1 );
yourls_add_action( 'logout', 'telu_entra_logout' );
yourls_add_action( 'login_form_bottom', 'telu_entra_local_login_microsoft_button' );

if ( function_exists( 'yourls_register_plugin_page' ) ) {
    yourls_register_plugin_page(
        'telu_entra_sso',
        'Microsoft SSO',
        'telu_entra_settings_page'
    );
}

/**
 * Get a plugin setting from a constant, environment variable, or safe DB option.
 * Client Secret is deliberately never read from or stored in the database.
 */
function telu_entra_config( $name, $default = null ) {
    $key = 'TELU_ENTRA_' . $name;

    if ( defined( $key ) ) {
        return constant( $key );
    }

    $environment = getenv( $key );
    if ( $environment !== false && $environment !== '' ) {
        return $environment;
    }

    $database_options = array(
        'TENANT_ID' => TELU_ENTRA_TENANT_OPTION,
        'CLIENT_ID' => TELU_ENTRA_CLIENT_OPTION,
        'SESSION_LIFETIME' => TELU_ENTRA_SESSION_OPTION,
        'ALLOWED_GROUP_IDS' => TELU_ENTRA_GROUPS_OPTION,
        'ALLOWED_APP_ROLES' => TELU_ENTRA_ROLES_OPTION,
        'ADMIN_EMAILS' => TELU_ENTRA_ADMINS_OPTION,
        'EDITOR_EMAILS' => TELU_ENTRA_EDITORS_OPTION,
    );
    if ( isset( $database_options[ $name ] ) ) {
        $stored = yourls_get_option( $database_options[ $name ] );
        if ( is_string( $stored ) && $stored !== '' ) {
            return $stored;
        }
    }

    return $default;
}

function telu_entra_config_is_locked( $name ) {
    $key = 'TELU_ENTRA_' . $name;
    $environment = getenv( $key );
    return defined( $key ) || ( $environment !== false && $environment !== '' );
}

function telu_entra_is_enabled() {
    if ( telu_entra_config_is_locked( 'ENABLED' ) ) {
        return filter_var( telu_entra_config( 'ENABLED', false ), FILTER_VALIDATE_BOOLEAN );
    }
    return yourls_get_option( TELU_ENTRA_ENABLED_OPTION ) === '1';
}

function telu_entra_secret_fingerprint() {
    $secret = (string) telu_entra_config( 'CLIENT_SECRET', '' );
    if ( $secret === '' || ! defined( 'YOURLS_COOKIEKEY' ) ) {
        return '';
    }
    return hash_hmac( 'sha256', $secret, (string) YOURLS_COOKIEKEY );
}

function telu_entra_authmgr_available() {
    return function_exists( 'amp_have_capability' );
}

function telu_entra_audit( $event, $email = '', $detail = '' ) {
    $raw = yourls_get_option( TELU_ENTRA_AUDIT_OPTION );
    $entries = is_string( $raw ) ? json_decode( $raw, true ) : array();
    if ( ! is_array( $entries ) ) {
        $entries = array();
    }
    array_unshift( $entries, array(
        'time'   => time(),
        'event'  => substr( preg_replace( '/[^a-z0-9_-]/i', '', (string) $event ), 0, 40 ),
        'email'  => strtolower( trim( (string) $email ) ),
        'detail' => substr( trim( (string) $detail ), 0, 240 ),
    ) );
    $entries = array_slice( $entries, 0, 100 );
    yourls_update_option( TELU_ENTRA_AUDIT_OPTION, json_encode( $entries, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

/**
 * Return configuration problems. An empty array means SSO is ready.
 */
function telu_entra_configuration_errors() {
    $errors = array();
    $tenant = strtolower( trim( (string) telu_entra_config( 'TENANT_ID', '' ) ) );
    $client = trim( (string) telu_entra_config( 'CLIENT_ID', '' ) );
    $secret = trim( (string) telu_entra_config( 'CLIENT_SECRET', '' ) );
    $root   = strtolower( trim( (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', 'telkomuniversity.ac.id' ) ) );

    if ( ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $tenant ) ) {
        $errors[] = 'TELU_ENTRA_TENANT_ID harus berupa Tenant ID (GUID) Microsoft Entra.';
    }

    if ( ! preg_match( '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $client ) ) {
        $errors[] = 'TELU_ENTRA_CLIENT_ID belum diisi dengan benar.';
    }

    if ( strlen( $secret ) < 16 ) {
        $errors[] = 'TELU_ENTRA_CLIENT_SECRET belum diisi atau terlalu pendek.';
    }

    if ( ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $root ) ) {
        $errors[] = 'TELU_ENTRA_ALLOWED_ROOT_DOMAIN tidak valid.';
    }

    if (
        ! defined( 'YOURLS_COOKIEKEY' ) ||
        strlen( (string) YOURLS_COOKIEKEY ) < 32 ||
        stripos( (string) YOURLS_COOKIEKEY, 'modify this text' ) !== false
    ) {
        $errors[] = 'YOURLS_COOKIEKEY harus diganti dengan nilai acak minimal 32 karakter.';
    }

    if ( ! extension_loaded( 'curl' ) ) {
        $errors[] = 'Ekstensi PHP cURL belum aktif.';
    }

    if ( ! extension_loaded( 'openssl' ) ) {
        $errors[] = 'Ekstensi PHP OpenSSL belum aktif.';
    }

    if ( ! defined( 'YOURLS_PRIVATE' ) || YOURLS_PRIVATE !== true ) {
        $errors[] = 'YOURLS_PRIVATE harus bernilai true agar pembuatan shortlink tidak tersedia untuk publik.';
    }

    $admins = telu_entra_email_list_setting( 'ADMIN_EMAILS' );
    if ( empty( $admins ) ) {
        $errors[] = 'Minimal satu TELU_ENTRA_ADMIN_EMAILS wajib ditetapkan untuk administrasi dan recovery.';
    } else {
        foreach ( $admins as $admin_email ) {
            if ( ! telu_entra_email_is_allowed( $admin_email ) ) {
                $errors[] = 'Email administrator tidak valid atau berada di luar domain Telkom University: ' . $admin_email;
            }
        }
    }

    if ( ! telu_entra_authmgr_available() ) {
        $errors[] = 'AuthMgrPlus wajib aktif agar setiap pengguna hanya mengelola shortlink miliknya.';
    }

    foreach ( telu_entra_list_setting( 'ALLOWED_GROUP_IDS' ) as $group_id ) {
        if ( ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', strtolower( $group_id ) ) ) {
            $errors[] = 'Allowed Group ID bukan GUID yang valid: ' . $group_id;
        }
    }

    return $errors;
}

/**
 * Main authentication filter. Admin access requires Entra; API link creation is denied.
 */
function telu_entra_authenticate( $pre ) {
    if ( function_exists( 'yourls_is_installing' ) && yourls_is_installing() ) {
        return $pre;
    }

    // Test callbacks must work while enforcement is disabled.
    if ( ( isset( $_GET['code'] ) || isset( $_GET['error'] ) ) && ! empty( $_COOKIE[ TELU_ENTRA_FLOW_COOKIE ] ) ) {
        telu_entra_handle_callback();
        exit;
    }

    if ( ! telu_entra_is_enabled() ) {
        return $pre;
    }

    // Browser-based Entra authentication cannot safely authorize API requests.
    // Keep read-only API operations available, but never allow API shortlink creation.
    if ( yourls_is_API() ) {
        $action = isset( $_REQUEST['action'] ) ? strtolower( trim( (string) $_REQUEST['action'] ) ) : '';
        if ( $action === 'shorturl' ) {
            telu_entra_api_forbidden();
        }
        return $pre;
    }

    $configuration_errors = telu_entra_configuration_errors();
    if ( ! empty( $configuration_errors ) ) {
        telu_entra_error_page( implode( ' ', $configuration_errors ), 503, false );
    }

    // Always let YOURLS process its signed logout request.
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'logout' ) {
        return $pre;
    }

    // Local login is disabled by default. It must be deliberately enabled in config
    // for emergency recovery, and should be disabled again immediately afterwards.
    $local_recovery = filter_var( telu_entra_config( 'ALLOW_LOCAL_RECOVERY', false ), FILTER_VALIDATE_BOOLEAN );
    if ( $local_recovery && (
        isset( $_GET['telu_local_login'] ) ||
        isset( $_REQUEST['username'], $_REQUEST['password'] )
    ) ) {
        return $pre;
    }

    // Only preserve local administrator sessions while emergency recovery is enabled.
    if ( $local_recovery && isset( $_COOKIE[ yourls_cookie_name() ] ) && yourls_check_auth_cookie() === true ) {
        return $pre;
    }

    // Microsoft sends either a code or an OAuth error back to the registered URI.
    if ( isset( $_GET['code'] ) || isset( $_GET['error'] ) ) {
        telu_entra_handle_callback();
        exit;
    }

    $identity = telu_entra_read_identity_cookie();
    if ( is_array( $identity ) ) {
        yourls_set_user( $identity['email'] );
        telu_entra_assign_authmgr_role( $identity['email'] );
        return true;
    }

    telu_entra_begin_login();
    exit;
}

/**
 * Require the same Entra session on the root homepage while leaving short URLs public.
 * YOURLS passes an empty request here for the site root, and the keyword for /abc123.
 */
function telu_entra_protect_homepage( $request ) {
    if ( trim( (string) $request, '/' ) !== '' ) {
        return;
    }

    $last_seen = (int) yourls_get_option( TELU_ENTRA_HOMEPAGE_OPTION );
    if ( time() - $last_seen > 3600 ) {
        yourls_update_option( TELU_ENTRA_HOMEPAGE_OPTION, (string) time() );
    }

    if ( ! telu_entra_is_enabled() ) {
        return;
    }

    $valid = yourls_is_valid_user();
    if ( $valid !== true ) {
        telu_entra_error_page( 'Login Microsoft diperlukan untuk membuka halaman pembuatan shortlink.', 401 );
    }
}

/**
 * Start Microsoft Authorization Code flow with PKCE, state and nonce.
 */
function telu_entra_begin_login( $purpose = 'login', $return_to = null ) {
    if ( headers_sent() ) {
        telu_entra_error_page( 'Login Microsoft tidak dapat dimulai karena header HTTP sudah terkirim.', 500 );
    }

    $tenant  = strtolower( trim( (string) telu_entra_config( 'TENANT_ID' ) ) );
    $client  = trim( (string) telu_entra_config( 'CLIENT_ID' ) );
    $state   = telu_entra_base64url_encode( random_bytes( 32 ) );
    $nonce   = telu_entra_base64url_encode( random_bytes( 32 ) );
    $verifier = telu_entra_base64url_encode( random_bytes( 64 ) );
    $challenge = telu_entra_base64url_encode( hash( 'sha256', $verifier, true ) );

    $flow = array(
        'state'      => $state,
        'nonce'      => $nonce,
        'verifier'   => $verifier,
        'created_at' => time(),
        'return_to'  => $return_to === null ? telu_entra_current_admin_path() : telu_entra_safe_return_path( $return_to ),
        'purpose'    => $purpose === 'test' ? 'test' : 'login',
    );

    $flow_container = telu_entra_read_signed_cookie( TELU_ENTRA_FLOW_COOKIE );
    $flows = is_array( $flow_container ) && isset( $flow_container['flows'] ) && is_array( $flow_container['flows'] ) ? $flow_container['flows'] : array();
    foreach ( $flows as $flow_state => $stored_flow ) {
        if ( ! is_array( $stored_flow ) || empty( $stored_flow['created_at'] ) || time() - (int) $stored_flow['created_at'] > 600 ) {
            unset( $flows[ $flow_state ] );
        }
    }
    $flows[ $state ] = $flow;
    $flows = array_slice( $flows, -5, null, true );
    telu_entra_set_signed_cookie( TELU_ENTRA_FLOW_COOKIE, array( 'flows' => $flows ), time() + 600 );

    $parameters = array(
        'client_id'             => $client,
        'response_type'         => 'code',
        'redirect_uri'          => telu_entra_redirect_uri(),
        'response_mode'         => 'query',
        'scope'                 => 'openid profile email',
        'state'                 => $state,
        'nonce'                 => $nonce,
        'code_challenge'        => $challenge,
        'code_challenge_method' => 'S256',
        'domain_hint'           => (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', 'telkomuniversity.ac.id' ),
    );

    $authorization_url = 'https://login.microsoftonline.com/' . rawurlencode( $tenant ) .
        '/oauth2/v2.0/authorize?' . http_build_query( $parameters, '', '&', PHP_QUERY_RFC3986 );

    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Pragma: no-cache' );
    header( 'Location: ' . $authorization_url, true, 302 );
}

/**
 * Validate callback, exchange the code, validate the ID token and issue our session cookie.
 */
function telu_entra_handle_callback() {
    $flow_container = telu_entra_read_signed_cookie( TELU_ENTRA_FLOW_COOKIE );
    $returned_state = isset( $_GET['state'] ) ? (string) $_GET['state'] : '';
    if ( is_array( $flow_container ) && isset( $flow_container['flows'] ) && is_array( $flow_container['flows'] ) ) {
        $flow = isset( $flow_container['flows'][ $returned_state ] ) ? $flow_container['flows'][ $returned_state ] : null;
    } else {
        // Backward compatibility with a flow started by version 1.3.x.
        $flow = $flow_container;
    }
    $GLOBALS['telu_entra_test_in_progress'] = is_array( $flow ) && isset( $flow['purpose'] ) && $flow['purpose'] === 'test';

    if ( ! is_array( $flow ) || empty( $flow['state'] ) || empty( $flow['nonce'] ) || empty( $flow['verifier'] ) ) {
        telu_entra_error_page( 'Sesi login sudah kedaluwarsa. Silakan mulai kembali.', 400 );
    }

    if ( time() - (int) $flow['created_at'] > 600 ) {
        telu_entra_error_page( 'Sesi login sudah kedaluwarsa. Silakan mulai kembali.', 400 );
    }

    if ( ! hash_equals( (string) $flow['state'], $returned_state ) ) {
        telu_entra_error_page( 'State OAuth tidak valid.', 400 );
    }

    // The response belongs to this browser flow; it is now safe to consume it.
    if ( is_array( $flow_container ) && isset( $flow_container['flows'] ) && is_array( $flow_container['flows'] ) ) {
        unset( $flow_container['flows'][ $returned_state ] );
        if ( empty( $flow_container['flows'] ) ) {
            telu_entra_clear_cookie( TELU_ENTRA_FLOW_COOKIE );
        } else {
            telu_entra_set_signed_cookie( TELU_ENTRA_FLOW_COOKIE, $flow_container, time() + 600 );
        }
    } else {
        telu_entra_clear_cookie( TELU_ENTRA_FLOW_COOKIE );
    }

    if ( isset( $_GET['error'] ) ) {
        telu_entra_error_page( 'Login Microsoft dibatalkan atau ditolak.', 401 );
    }

    $code = isset( $_GET['code'] ) ? (string) $_GET['code'] : '';
    if ( $code === '' || strlen( $code ) > 8192 ) {
        telu_entra_error_page( 'Kode otorisasi Microsoft tidak valid.', 400 );
    }

    $tokens = telu_entra_exchange_code( $code, (string) $flow['verifier'] );
    if ( empty( $tokens['id_token'] ) || ! is_string( $tokens['id_token'] ) ) {
        telu_entra_error_page( 'Microsoft tidak mengirimkan ID token.', 502 );
    }

    $claims = telu_entra_verify_id_token( $tokens['id_token'], (string) $flow['nonce'] );
    $email  = telu_entra_email_from_claims( $claims );

    if ( ! telu_entra_email_is_allowed( $email ) ) {
        telu_entra_error_page( 'Email Microsoft ini tidak termasuk domain Telkom University yang diizinkan.', 403 );
    }

    if ( ! telu_entra_claims_are_allowed( $claims ) ) {
        telu_entra_error_page( 'Akun tidak memiliki Entra Group atau App Role yang diwajibkan.', 403 );
    }

    if ( isset( $flow['purpose'] ) && $flow['purpose'] === 'test' ) {
        yourls_update_option( TELU_ENTRA_TEST_OPTION, json_encode( array(
            'success' => true,
            'email'   => $email,
            'time'    => time(),
            'tenant'  => strtolower( trim( (string) telu_entra_config( 'TENANT_ID', '' ) ) ),
            'client'  => strtolower( trim( (string) telu_entra_config( 'CLIENT_ID', '' ) ) ),
            'secret'  => telu_entra_secret_fingerprint(),
        ), JSON_UNESCAPED_SLASHES ) );
        telu_entra_audit( 'test_success', $email, 'Login Microsoft dan validasi token berhasil.' );

        $return_to = isset( $flow['return_to'] ) ? telu_entra_safe_return_path( $flow['return_to'] ) : '/admin/';
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Location: ' . $return_to, true, 302 );
        exit;
    }

    $identity = array(
        'email'     => $email,
        'name'      => telu_entra_display_name_from_claims( $claims, $email ),
        'sub'       => (string) $claims['sub'],
        'tid'       => strtolower( (string) $claims['tid'] ),
        'issued_at' => time(),
        'expires'   => time() + telu_entra_session_lifetime(),
    );

    telu_entra_set_signed_cookie( TELU_ENTRA_AUTH_COOKIE, $identity, $identity['expires'] );
    telu_entra_assign_authmgr_role( $email );
    telu_entra_audit( 'login_success', $email, 'Sesi SSO diterbitkan.' );

    $return_to = isset( $flow['return_to'] ) ? telu_entra_safe_return_path( $flow['return_to'] ) : '/admin/';
    header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
    header( 'Location: ' . $return_to, true, 302 );
}

/**
 * Exchange an authorization code at the tenant-specific Microsoft token endpoint.
 */
function telu_entra_exchange_code( $code, $verifier ) {
    $tenant = strtolower( trim( (string) telu_entra_config( 'TENANT_ID' ) ) );
    $url    = 'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/oauth2/v2.0/token';
    $body   = array(
        'client_id'     => trim( (string) telu_entra_config( 'CLIENT_ID' ) ),
        'client_secret' => (string) telu_entra_config( 'CLIENT_SECRET' ),
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => telu_entra_redirect_uri(),
        'code_verifier' => $verifier,
        'scope'         => 'openid profile email',
    );

    return telu_entra_http_json( $url, $body );
}

/**
 * Verify RS256 signature and security-critical claims in an Entra ID token.
 */
function telu_entra_verify_id_token( $jwt, $expected_nonce ) {
    $parts = explode( '.', $jwt );
    if ( count( $parts ) !== 3 ) {
        telu_entra_error_page( 'Format ID token Microsoft tidak valid.', 401 );
    }

    $header_json  = telu_entra_base64url_decode( $parts[0] );
    $payload_json = telu_entra_base64url_decode( $parts[1] );
    $signature    = telu_entra_base64url_decode( $parts[2] );
    $header       = json_decode( $header_json, true );
    $claims       = json_decode( $payload_json, true );

    if ( ! is_array( $header ) || ! is_array( $claims ) || $signature === false ) {
        telu_entra_error_page( 'ID token Microsoft tidak dapat dibaca.', 401 );
    }

    if ( ! isset( $header['alg'], $header['kid'] ) || $header['alg'] !== 'RS256' || ! is_string( $header['kid'] ) ) {
        telu_entra_error_page( 'Algoritma atau kunci ID token tidak diizinkan.', 401 );
    }

    $jwk = telu_entra_find_jwk( $header['kid'], false );
    if ( ! is_array( $jwk ) ) {
        $jwk = telu_entra_find_jwk( $header['kid'], true );
    }

    if ( ! is_array( $jwk ) || ! isset( $jwk['n'], $jwk['e'] ) || ( isset( $jwk['kty'] ) && $jwk['kty'] !== 'RSA' ) ) {
        telu_entra_error_page( 'Kunci publik Microsoft tidak ditemukan.', 401 );
    }

    $public_key = telu_entra_jwk_to_pem( $jwk );
    $verified   = openssl_verify( $parts[0] . '.' . $parts[1], $signature, $public_key, OPENSSL_ALGO_SHA256 );
    if ( $verified !== 1 ) {
        telu_entra_error_page( 'Tanda tangan ID token Microsoft tidak valid.', 401 );
    }

    $now     = time();
    $leeway  = 120;
    $tenant  = strtolower( trim( (string) telu_entra_config( 'TENANT_ID' ) ) );
    $client  = strtolower( trim( (string) telu_entra_config( 'CLIENT_ID' ) ) );
    $issuer  = 'https://login.microsoftonline.com/' . $tenant . '/v2.0';

    if ( empty( $claims['iss'] ) || ! hash_equals( strtolower( rtrim( $issuer, '/' ) ), strtolower( rtrim( (string) $claims['iss'], '/' ) ) ) ) {
        telu_entra_error_page( 'Issuer ID token Microsoft tidak sesuai tenant.', 401 );
    }

    if ( empty( $claims['tid'] ) || ! hash_equals( $tenant, strtolower( (string) $claims['tid'] ) ) ) {
        telu_entra_error_page( 'Tenant ID pada token tidak diizinkan.', 403 );
    }

    $audiences = isset( $claims['aud'] ) ? (array) $claims['aud'] : array();
    $audiences = array_map( 'strtolower', array_map( 'strval', $audiences ) );
    if ( ! in_array( $client, $audiences, true ) ) {
        telu_entra_error_page( 'Audience ID token tidak sesuai aplikasi.', 401 );
    }

    if ( empty( $claims['sub'] ) || ! is_string( $claims['sub'] ) ) {
        telu_entra_error_page( 'Subject ID token tidak tersedia.', 401 );
    }

    if ( empty( $claims['nonce'] ) || ! hash_equals( $expected_nonce, (string) $claims['nonce'] ) ) {
        telu_entra_error_page( 'Nonce ID token tidak valid.', 401 );
    }

    if ( ! isset( $claims['exp'] ) || (int) $claims['exp'] < $now - $leeway ) {
        telu_entra_error_page( 'ID token Microsoft sudah kedaluwarsa.', 401 );
    }

    if ( isset( $claims['nbf'] ) && (int) $claims['nbf'] > $now + $leeway ) {
        telu_entra_error_page( 'ID token Microsoft belum berlaku.', 401 );
    }

    if ( isset( $claims['iat'] ) && (int) $claims['iat'] > $now + $leeway ) {
        telu_entra_error_page( 'Waktu penerbitan ID token tidak valid.', 401 );
    }

    return $claims;
}

/**
 * Fetch and cache Microsoft JWKS. Force refresh handles key rotation.
 */
function telu_entra_find_jwk( $kid, $force_refresh ) {
    $cached = yourls_get_option( TELU_ENTRA_JWKS_OPTION );
    $data   = is_string( $cached ) ? json_decode( $cached, true ) : null;

    if (
        $force_refresh ||
        ! is_array( $data ) ||
        empty( $data['expires'] ) ||
        (int) $data['expires'] < time() ||
        empty( $data['keys'] )
    ) {
        $tenant = strtolower( trim( (string) telu_entra_config( 'TENANT_ID' ) ) );
        $url = 'https://login.microsoftonline.com/' . rawurlencode( $tenant ) . '/discovery/v2.0/keys';
        $jwks = telu_entra_http_json( $url );

        if ( empty( $jwks['keys'] ) || ! is_array( $jwks['keys'] ) ) {
            telu_entra_error_page( 'Daftar kunci publik Microsoft tidak valid.', 502 );
        }

        $data = array(
            'expires' => time() + 21600,
            'keys'    => $jwks['keys'],
        );
        yourls_update_option( TELU_ENTRA_JWKS_OPTION, json_encode( $data ) );
    }

    foreach ( $data['keys'] as $key ) {
        if ( isset( $key['kid'] ) && is_string( $key['kid'] ) && hash_equals( $key['kid'], $kid ) ) {
            return $key;
        }
    }

    return null;
}

/**
 * Convert an RSA JWK into a PEM SubjectPublicKeyInfo public key.
 */
function telu_entra_jwk_to_pem( $jwk ) {
    $modulus  = telu_entra_base64url_decode( $jwk['n'] );
    $exponent = telu_entra_base64url_decode( $jwk['e'] );

    if ( $modulus === false || $exponent === false || $modulus === '' || $exponent === '' ) {
        telu_entra_error_page( 'Material kunci publik Microsoft tidak valid.', 502 );
    }

    $rsa_public_key = telu_entra_der_sequence(
        telu_entra_der_integer( $modulus ) . telu_entra_der_integer( $exponent )
    );

    $rsa_algorithm_identifier = hex2bin( '300d06092a864886f70d0101010500' );
    $subject_public_key_info = telu_entra_der_sequence(
        $rsa_algorithm_identifier . "\x03" . telu_entra_der_length( strlen( $rsa_public_key ) + 1 ) . "\x00" . $rsa_public_key
    );

    return "-----BEGIN PUBLIC KEY-----\n" .
        chunk_split( base64_encode( $subject_public_key_info ), 64, "\n" ) .
        "-----END PUBLIC KEY-----\n";
}

function telu_entra_der_integer( $value ) {
    $value = ltrim( $value, "\x00" );
    if ( $value === '' ) {
        $value = "\x00";
    }
    if ( ord( $value[0] ) > 0x7f ) {
        $value = "\x00" . $value;
    }
    return "\x02" . telu_entra_der_length( strlen( $value ) ) . $value;
}

function telu_entra_der_sequence( $value ) {
    return "\x30" . telu_entra_der_length( strlen( $value ) ) . $value;
}

function telu_entra_der_length( $length ) {
    if ( $length < 128 ) {
        return chr( $length );
    }

    $encoded = '';
    while ( $length > 0 ) {
        $encoded = chr( $length & 0xff ) . $encoded;
        $length >>= 8;
    }

    return chr( 0x80 | strlen( $encoded ) ) . $encoded;
}

/**
 * Extract a usable institutional email from verified ID-token claims.
 */
function telu_entra_email_from_claims( $claims ) {
    foreach ( array( 'preferred_username', 'email', 'upn' ) as $claim ) {
        if ( ! empty( $claims[ $claim ] ) && is_string( $claims[ $claim ] ) ) {
            $email = strtolower( trim( $claims[ $claim ] ) );
            if ( filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
                return $email;
            }
        }
    }

    telu_entra_error_page( 'Microsoft tidak memberikan alamat email yang dapat digunakan.', 403 );
}

/**
 * Use the verified OIDC name claim for display only; email remains the identity key.
 */
function telu_entra_display_name_from_claims( $claims, $fallback ) {
    $name = isset( $claims['name'] ) && is_string( $claims['name'] ) ? trim( $claims['name'] ) : '';
    $name = preg_replace( '/[\x00-\x1F\x7F]/u', '', $name );
    if ( ! is_string( $name ) || $name === '' ) {
        return (string) $fallback;
    }
    return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 120, 'UTF-8' ) : substr( $name, 0, 120 );
}

/**
 * Change only the visible YOURLS greeting; ownership and permissions keep using email.
 */
function telu_entra_display_name_in_header( $logout_link ) {
    $identity = telu_entra_read_identity_cookie();
    if ( ! is_array( $identity ) || empty( $identity['name'] ) ) {
        return $logout_link;
    }

    $display_name = '<strong>' . telu_entra_escape( $identity['name'] ) . '</strong>';
    return preg_replace_callback(
        '/<strong>.*?<\/strong>/s',
        function() use ( $display_name ) {
            return $display_name;
        },
        (string) $logout_link,
        1
    );
}

/**
 * Allow the exact root domain and any true subdomain, never look-alike suffixes.
 */
function telu_entra_email_is_allowed( $email ) {
    $email = strtolower( trim( (string) $email ) );
    if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
        return false;
    }

    $position = strrpos( $email, '@' );
    $domain   = substr( $email, $position + 1 );
    $root     = strtolower( trim( (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', 'telkomuniversity.ac.id' ), '.' ) );

    return $domain === $root || telu_entra_string_ends_with( $domain, '.' . $root );
}

function telu_entra_string_ends_with( $haystack, $needle ) {
    if ( $needle === '' ) {
        return true;
    }
    return substr( $haystack, -strlen( $needle ) ) === $needle;
}

/**
 * Give every Microsoft user a default Contributor role in AuthMgrPlus.
 * Optional admin/editor allowlists can elevate selected institutional emails.
 */
function telu_entra_assign_authmgr_role( $email ) {
    global $amp_role_assignment;

    if ( ! is_array( $amp_role_assignment ) ) {
        $amp_role_assignment = array();
    }

    $email  = strtolower( trim( (string) $email ) );
    $admins = telu_entra_email_list_setting( 'ADMIN_EMAILS' );
    $editors = telu_entra_email_list_setting( 'EDITOR_EMAILS' );

    if ( in_array( $email, $admins, true ) ) {
        $role = 'administrator';
    } elseif ( in_array( $email, $editors, true ) ) {
        $role = 'editor';
    } else {
        $role = 'contributor';
    }

    if ( ! isset( $amp_role_assignment[ $role ] ) || ! is_array( $amp_role_assignment[ $role ] ) ) {
        $amp_role_assignment[ $role ] = array();
    }

    if ( ! in_array( $email, array_map( 'strtolower', $amp_role_assignment[ $role ] ), true ) ) {
        $amp_role_assignment[ $role ][] = $email;
    }
}

function telu_entra_email_list_setting( $name ) {
    $value = telu_entra_config( $name, array() );
    if ( is_string( $value ) ) {
        $value = preg_split( '/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY );
    }
    if ( ! is_array( $value ) ) {
        return array();
    }
    return array_values( array_unique( array_map( 'strtolower', array_map( 'trim', $value ) ) ) );
}

function telu_entra_list_setting( $name ) {
    $value = telu_entra_config( $name, array() );
    if ( is_string( $value ) ) {
        $decoded = json_decode( $value, true );
        $value = is_array( $decoded ) ? $decoded : preg_split( '/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY );
    }
    if ( ! is_array( $value ) ) {
        return array();
    }
    return array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $value ) ) ) ) );
}

function telu_entra_normalize_csv( $value ) {
    $items = preg_split( '/[\s,]+/', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
    if ( ! is_array( $items ) ) {
        return array();
    }
    return array_values( array_unique( array_map( 'trim', $items ) ) );
}

/**
 * Optional authorization constraints. If configured, each configured category
 * must have at least one match in the verified ID token.
 */
function telu_entra_claims_are_allowed( $claims ) {
    $allowed_groups = array_map( 'strtolower', telu_entra_list_setting( 'ALLOWED_GROUP_IDS' ) );
    $allowed_roles = array_map( 'strtolower', telu_entra_list_setting( 'ALLOWED_APP_ROLES' ) );
    $token_groups = isset( $claims['groups'] ) && is_array( $claims['groups'] ) ? array_map( 'strtolower', array_map( 'strval', $claims['groups'] ) ) : array();
    $token_roles = isset( $claims['roles'] ) && is_array( $claims['roles'] ) ? array_map( 'strtolower', array_map( 'strval', $claims['roles'] ) ) : array();

    if ( ! empty( $allowed_groups ) && empty( array_intersect( $allowed_groups, $token_groups ) ) ) {
        return false;
    }
    if ( ! empty( $allowed_roles ) && empty( array_intersect( $allowed_roles, $token_roles ) ) ) {
        return false;
    }
    return true;
}

/**
 * Read, validate and return our signed session identity.
 */
function telu_entra_read_identity_cookie() {
    $identity = telu_entra_read_signed_cookie( TELU_ENTRA_AUTH_COOKIE );
    if ( ! is_array( $identity ) || empty( $identity['email'] ) || empty( $identity['sub'] ) || empty( $identity['tid'] ) ) {
        return null;
    }

    if ( empty( $identity['expires'] ) || (int) $identity['expires'] < time() ) {
        telu_entra_clear_cookie( TELU_ENTRA_AUTH_COOKIE );
        return null;
    }

    $tenant = strtolower( trim( (string) telu_entra_config( 'TENANT_ID' ) ) );
    if ( ! hash_equals( $tenant, strtolower( (string) $identity['tid'] ) ) ) {
        telu_entra_clear_cookie( TELU_ENTRA_AUTH_COOKIE );
        return null;
    }

    if ( ! telu_entra_email_is_allowed( $identity['email'] ) ) {
        telu_entra_clear_cookie( TELU_ENTRA_AUTH_COOKIE );
        return null;
    }

    $identity['email'] = strtolower( $identity['email'] );
    return $identity;
}

function telu_entra_set_signed_cookie( $name, $payload, $expires ) {
    $json      = json_encode( $payload, JSON_UNESCAPED_SLASHES );
    $encoded   = telu_entra_base64url_encode( $json );
    $signature = telu_entra_base64url_encode( hash_hmac( 'sha256', $encoded, telu_entra_hmac_key(), true ) );
    $value     = $encoded . '.' . $signature;

    setcookie(
        $name,
        $value,
        array(
            'expires'  => (int) $expires,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        )
    );
    $_COOKIE[ $name ] = $value;
}

function telu_entra_read_signed_cookie( $name ) {
    if ( empty( $_COOKIE[ $name ] ) || ! is_string( $_COOKIE[ $name ] ) || strlen( $_COOKIE[ $name ] ) > 8192 ) {
        return null;
    }

    $parts = explode( '.', $_COOKIE[ $name ] );
    if ( count( $parts ) !== 2 ) {
        return null;
    }

    $expected = telu_entra_base64url_encode( hash_hmac( 'sha256', $parts[0], telu_entra_hmac_key(), true ) );
    if ( ! hash_equals( $expected, $parts[1] ) ) {
        return null;
    }

    $decoded = telu_entra_base64url_decode( $parts[0] );
    $payload = json_decode( $decoded, true );
    return is_array( $payload ) ? $payload : null;
}

function telu_entra_hmac_key() {
    return hash( 'sha256', (string) YOURLS_COOKIEKEY . "\x00" . (string) telu_entra_config( 'CLIENT_SECRET' ), true );
}

function telu_entra_clear_cookie( $name ) {
    setcookie(
        $name,
        '',
        array(
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        )
    );
    unset( $_COOKIE[ $name ] );
}

function telu_entra_base64url_encode( $value ) {
    return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
}

function telu_entra_base64url_decode( $value ) {
    if ( ! is_string( $value ) || ! preg_match( '/^[A-Za-z0-9_-]*$/', $value ) ) {
        return false;
    }
    $padding = strlen( $value ) % 4;
    if ( $padding ) {
        $value .= str_repeat( '=', 4 - $padding );
    }
    return base64_decode( strtr( $value, '-_', '+/' ), true );
}

/**
 * Minimal HTTPS JSON client. Only Microsoft login.microsoftonline.com is allowed.
 */
function telu_entra_http_json( $url, $post_fields = null ) {
    $parts = parse_url( $url );
    if (
        ! is_array( $parts ) ||
        empty( $parts['scheme'] ) || strtolower( $parts['scheme'] ) !== 'https' ||
        empty( $parts['host'] ) || strtolower( $parts['host'] ) !== 'login.microsoftonline.com'
    ) {
        telu_entra_error_page( 'Endpoint Microsoft tidak diizinkan.', 500 );
    }

    $handle = curl_init( $url );
    $options = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => array( 'Accept: application/json' ),
        CURLOPT_USERAGENT      => 'TelU-YOURLS-Entra-SSO/' . TELU_ENTRA_SSO_VERSION,
    );

    if ( is_array( $post_fields ) ) {
        $options[ CURLOPT_POST ] = true;
        $options[ CURLOPT_POSTFIELDS ] = http_build_query( $post_fields, '', '&', PHP_QUERY_RFC3986 );
        $options[ CURLOPT_HTTPHEADER ][] = 'Content-Type: application/x-www-form-urlencoded';
    }

    curl_setopt_array( $handle, $options );
    $response = curl_exec( $handle );
    $status   = (int) curl_getinfo( $handle, CURLINFO_HTTP_CODE );
    $error    = curl_error( $handle );
    curl_close( $handle );

    if ( $response === false || $error !== '' || $status < 200 || $status >= 300 || strlen( $response ) > 1048576 ) {
        telu_entra_error_page( 'Tidak dapat berkomunikasi dengan Microsoft Entra ID.', 502 );
    }

    $decoded = json_decode( $response, true );
    if ( ! is_array( $decoded ) ) {
        telu_entra_error_page( 'Respons Microsoft Entra ID tidak valid.', 502 );
    }

    return $decoded;
}

/**
 * Reject API-based shortlink creation because it has no interactive Entra session.
 */
function telu_entra_api_forbidden() {
    if ( ! headers_sent() ) {
        http_response_code( 403 );
        header( 'Content-Type: application/json; charset=UTF-8' );
        header( 'Cache-Control: no-store' );
    }

    echo json_encode( array(
        'status'    => 'fail',
        'code'      => 'error:entra_required',
        'message'   => 'Pembuatan shortlink wajib melalui admin dan login Microsoft Entra Telkom University.',
        'errorCode' => '403',
    ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    exit;
}

function telu_entra_redirect_uri() {
    $configured = trim( (string) telu_entra_config( 'REDIRECT_URI', '' ) );
    if ( $configured !== '' ) {
        return rtrim( $configured, '/' ) . '/';
    }
    return rtrim( YOURLS_SITE, '/' ) . '/admin/';
}

function telu_entra_session_lifetime() {
    $lifetime = (int) telu_entra_config( 'SESSION_LIFETIME', 28800 );
    return max( 900, min( $lifetime, 86400 ) );
}

function telu_entra_current_admin_path() {
    $request = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/admin/';
    return telu_entra_safe_return_path( $request );
}

function telu_entra_safe_return_path( $path ) {
    $path = (string) $path;
    if ( $path === '' || substr( $path, 0, 1 ) !== '/' || substr( $path, 0, 2 ) === '//' ) {
        return '/admin/';
    }

    $parsed = parse_url( $path );
    if ( ! is_array( $parsed ) || ! isset( $parsed['path'] ) ) {
        return '/admin/';
    }

    $site_path = (string) parse_url( YOURLS_SITE, PHP_URL_PATH );
    $site_path = '/' . trim( $site_path, '/' );
    $site_path = $site_path === '/' ? '/' : $site_path . '/';
    $admin_path = rtrim( $site_path, '/' ) . '/admin';
    $is_homepage = rtrim( $parsed['path'], '/' ) === rtrim( $site_path, '/' );
    $is_admin = $parsed['path'] === $admin_path || strpos( $parsed['path'], $admin_path . '/' ) === 0;

    if ( ! $is_homepage && ! $is_admin ) {
        return rtrim( $site_path, '/' ) . '/admin/';
    }

    $safe = $parsed['path'];
    if ( ! empty( $parsed['query'] ) ) {
        $safe .= '?' . $parsed['query'];
    }
    return $safe;
}

/**
 * Clear our cookie. Optional Microsoft logout can be enabled in config.
 */
function telu_entra_logout() {
    $identity = telu_entra_read_identity_cookie();
    if ( is_array( $identity ) && isset( $identity['email'] ) ) {
        telu_entra_audit( 'logout', $identity['email'], 'Sesi plugin dihapus.' );
    }
    telu_entra_clear_cookie( TELU_ENTRA_AUTH_COOKIE );
    telu_entra_clear_cookie( TELU_ENTRA_FLOW_COOKIE );

    $microsoft_logout = filter_var( telu_entra_config( 'LOGOUT_MICROSOFT', false ), FILTER_VALIDATE_BOOLEAN );
    if ( is_array( $identity ) && $microsoft_logout && ! headers_sent() ) {
        $tenant = strtolower( trim( (string) telu_entra_config( 'TENANT_ID' ) ) );
        $after  = trim( (string) telu_entra_config( 'POST_LOGOUT_REDIRECT_URI', YOURLS_SITE ) );
        $url = 'https://login.microsoftonline.com/' . rawurlencode( $tenant ) .
            '/oauth2/v2.0/logout?post_logout_redirect_uri=' . rawurlencode( $after );
        header( 'Location: ' . $url, true, 302 );
        exit;
    }
}

/**
 * Show a way back to Microsoft on the local recovery login screen.
 */
function telu_entra_local_login_microsoft_button() {
    if ( ! empty( telu_entra_configuration_errors() ) ) {
        return;
    }

    $url = rtrim( YOURLS_SITE, '/' ) . '/admin/';
    echo '<p><a class="button" style="display:block;text-align:center" href="' . telu_entra_escape( $url ) . '">Login dengan Microsoft</a></p>';
}

/**
 * Settings and diagnostic page. Client Secret is never accepted or rendered.
 */
function telu_entra_settings_page() {
    $notice = '';
    $notice_error = '';

    $is_post = isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST';
    if ( $is_post && (
        isset( $_POST['telu_entra_save'] ) ||
        isset( $_POST['telu_entra_test'] ) ||
        isset( $_POST['telu_entra_toggle'] ) ||
        isset( $_POST['telu_entra_reset'] )
    ) ) {
        $nonce = isset( $_POST['nonce'] ) ? (string) $_POST['nonce'] : '';
        yourls_verify_nonce( 'telu_entra_settings', $nonce );
    }

    if ( $is_post && isset( $_POST['telu_entra_save'] ) ) {
        $submitted_tenant = strtolower( trim( isset( $_POST['telu_entra_tenant_id'] ) ? (string) $_POST['telu_entra_tenant_id'] : '' ) );
        $submitted_client = strtolower( trim( isset( $_POST['telu_entra_client_id'] ) ? (string) $_POST['telu_entra_client_id'] : '' ) );
        $submitted_session = isset( $_POST['telu_entra_session_lifetime'] ) ? (int) $_POST['telu_entra_session_lifetime'] : 28800;
        $submitted_groups = implode( ',', telu_entra_normalize_csv( isset( $_POST['telu_entra_allowed_groups'] ) ? (string) $_POST['telu_entra_allowed_groups'] : '' ) );
        $submitted_roles = implode( ',', telu_entra_normalize_csv( isset( $_POST['telu_entra_allowed_roles'] ) ? (string) $_POST['telu_entra_allowed_roles'] : '' ) );
        $submitted_admins = implode( ',', array_map( 'strtolower', telu_entra_normalize_csv( isset( $_POST['telu_entra_admin_emails'] ) ? (string) $_POST['telu_entra_admin_emails'] : '' ) ) );
        $submitted_editors = implode( ',', array_map( 'strtolower', telu_entra_normalize_csv( isset( $_POST['telu_entra_editor_emails'] ) ? (string) $_POST['telu_entra_editor_emails'] : '' ) ) );
        $invalid_role_email = '';
        foreach ( array_merge( telu_entra_normalize_csv( $submitted_admins ), telu_entra_normalize_csv( $submitted_editors ) ) as $role_email ) {
            if ( ! telu_entra_email_is_allowed( $role_email ) ) {
                $invalid_role_email = $role_email;
                break;
            }
        }
        $guid = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/';

        if ( ! preg_match( $guid, $submitted_tenant ) || ! preg_match( $guid, $submitted_client ) ) {
            $notice_error = 'Tenant ID dan Client ID harus berupa GUID Microsoft yang valid.';
        } elseif ( $submitted_session < 900 || $submitted_session > 86400 ) {
            $notice_error = 'Durasi sesi harus antara 900 dan 86400 detik.';
        } elseif ( $submitted_admins === '' ) {
            $notice_error = 'Minimal satu email administrator Telkom University wajib diisi.';
        } elseif ( $invalid_role_email !== '' ) {
            $notice_error = 'Email role tidak valid atau di luar domain Telkom University: ' . $invalid_role_email;
        } else {
            if ( ! telu_entra_config_is_locked( 'TENANT_ID' ) ) {
                yourls_update_option( TELU_ENTRA_TENANT_OPTION, $submitted_tenant );
            }
            if ( ! telu_entra_config_is_locked( 'CLIENT_ID' ) ) {
                yourls_update_option( TELU_ENTRA_CLIENT_OPTION, $submitted_client );
            }
            if ( ! telu_entra_config_is_locked( 'SESSION_LIFETIME' ) ) {
                yourls_update_option( TELU_ENTRA_SESSION_OPTION, (string) $submitted_session );
            }
            if ( ! telu_entra_config_is_locked( 'ALLOWED_GROUP_IDS' ) ) {
                yourls_update_option( TELU_ENTRA_GROUPS_OPTION, $submitted_groups );
            }
            if ( ! telu_entra_config_is_locked( 'ALLOWED_APP_ROLES' ) ) {
                yourls_update_option( TELU_ENTRA_ROLES_OPTION, $submitted_roles );
            }
            if ( ! telu_entra_config_is_locked( 'ADMIN_EMAILS' ) ) {
                yourls_update_option( TELU_ENTRA_ADMINS_OPTION, $submitted_admins );
            }
            if ( ! telu_entra_config_is_locked( 'EDITOR_EMAILS' ) ) {
                yourls_update_option( TELU_ENTRA_EDITORS_OPTION, $submitted_editors );
            }
            yourls_update_option( TELU_ENTRA_TEST_OPTION, '' );
            $notice = 'Tenant ID dan Client ID berhasil disimpan.';
        }
    }

    if ( $is_post && isset( $_POST['telu_entra_test'] ) ) {
        $test_errors = telu_entra_configuration_errors();
        if ( ! empty( $test_errors ) ) {
            $notice_error = 'Tes belum dapat dimulai: ' . implode( ' ', $test_errors );
        } else {
            $settings_url = rtrim( YOURLS_SITE, '/' ) . '/admin/plugins.php?page=telu_entra_sso';
            telu_entra_begin_login( 'test', $settings_url );
            exit;
        }
    }

    if ( $is_post && isset( $_POST['telu_entra_toggle'] ) ) {
        if ( telu_entra_config_is_locked( 'ENABLED' ) ) {
            $notice_error = 'Status enable dikunci oleh TELU_ENTRA_ENABLED di config atau environment.';
        } elseif ( telu_entra_is_enabled() ) {
            yourls_update_option( TELU_ENTRA_ENABLED_OPTION, '0' );
            telu_entra_clear_cookie( TELU_ENTRA_AUTH_COOKIE );
            $notice = 'Microsoft SSO dinonaktifkan. Homepage kembali mengikuti autentikasi bawaan YOURLS.';
        } else {
            $enable_errors = telu_entra_configuration_errors();
            if ( ! empty( $enable_errors ) ) {
                $notice_error = 'SSO belum dapat diaktifkan: ' . implode( ' ', $enable_errors );
            } else {
                $test_raw = yourls_get_option( TELU_ENTRA_TEST_OPTION );
                $test = is_string( $test_raw ) ? json_decode( $test_raw, true ) : null;
                $current_tenant = strtolower( trim( (string) telu_entra_config( 'TENANT_ID', '' ) ) );
                $current_client = strtolower( trim( (string) telu_entra_config( 'CLIENT_ID', '' ) ) );
                $test_matches = is_array( $test ) && ! empty( $test['success'] ) &&
                    isset( $test['tenant'], $test['client'] ) &&
                    hash_equals( $current_tenant, (string) $test['tenant'] ) &&
                    hash_equals( $current_client, (string) $test['client'] ) &&
                    isset( $test['secret'] ) &&
                    hash_equals( telu_entra_secret_fingerprint(), (string) $test['secret'] );

                if ( ! $test_matches ) {
                    $notice_error = 'Jalankan Tes Login Microsoft hingga berhasil sebelum mengaktifkan SSO.';
                } else {
                    yourls_update_option( TELU_ENTRA_ENABLED_OPTION, '1' );
                    $notice = 'Microsoft SSO berhasil diaktifkan.';
                }
            }
        }
    }

    if ( $is_post && isset( $_POST['telu_entra_reset'] ) ) {
        if ( telu_entra_is_enabled() ) {
            $notice_error = 'Nonaktifkan SSO terlebih dahulu sebelum mereset konfigurasi.';
        } else {
            foreach ( array(
                TELU_ENTRA_TENANT_OPTION,
                TELU_ENTRA_CLIENT_OPTION,
                TELU_ENTRA_SESSION_OPTION,
                TELU_ENTRA_GROUPS_OPTION,
                TELU_ENTRA_ROLES_OPTION,
                TELU_ENTRA_ADMINS_OPTION,
                TELU_ENTRA_EDITORS_OPTION,
                TELU_ENTRA_TEST_OPTION,
                TELU_ENTRA_JWKS_OPTION,
            ) as $option_name ) {
                yourls_update_option( $option_name, '' );
            }
            telu_entra_clear_cookie( TELU_ENTRA_AUTH_COOKIE );
            telu_entra_clear_cookie( TELU_ENTRA_FLOW_COOKIE );
            $notice = 'Konfigurasi non-rahasia, hasil tes, dan cache JWKS telah direset. Client Secret di config tidak diubah.';
        }
    }

    $errors = telu_entra_configuration_errors();
    $tenant = trim( (string) telu_entra_config( 'TENANT_ID', '' ) );
    $client = trim( (string) telu_entra_config( 'CLIENT_ID', '' ) );
    $root   = trim( (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', 'telkomuniversity.ac.id' ) );
    $session_lifetime = telu_entra_session_lifetime();
    $allowed_groups = implode( ', ', telu_entra_list_setting( 'ALLOWED_GROUP_IDS' ) );
    $allowed_roles = implode( ', ', telu_entra_list_setting( 'ALLOWED_APP_ROLES' ) );
    $admin_emails = implode( ', ', telu_entra_email_list_setting( 'ADMIN_EMAILS' ) );
    $editor_emails = implode( ', ', telu_entra_email_list_setting( 'EDITOR_EMAILS' ) );
    $local_recovery = filter_var( telu_entra_config( 'ALLOW_LOCAL_RECOVERY', false ), FILTER_VALIDATE_BOOLEAN );
    $local_url = rtrim( YOURLS_SITE, '/' ) . '/admin/?telu_local_login=1';
    $authmgr = telu_entra_authmgr_available();
    $enabled = telu_entra_is_enabled();
    $last_test_raw = yourls_get_option( TELU_ENTRA_TEST_OPTION );
    $last_test = is_string( $last_test_raw ) ? json_decode( $last_test_raw, true ) : null;
    $homepage_seen = (int) yourls_get_option( TELU_ENTRA_HOMEPAGE_OPTION );

    echo '<h2>Microsoft Entra SSO</h2>';
    echo '<p>Versi plugin: <strong>' . telu_entra_escape( TELU_ENTRA_SSO_VERSION ) . '</strong></p>';

    if ( $notice !== '' ) {
        echo '<div style="border-left:4px solid #167c2f;padding:8px 14px;background:#effaf2"><strong>' . telu_entra_escape( $notice ) . '</strong></div>';
    }
    if ( $notice_error !== '' ) {
        echo '<div style="border-left:4px solid #b32d2e;padding:8px 14px;background:#fff3f3"><strong>' . telu_entra_escape( $notice_error ) . '</strong></div>';
    }

    if ( empty( $errors ) ) {
        echo '<p style="color:#167c2f"><strong>Konfigurasi siap digunakan.</strong></p>';
    } else {
        echo '<div style="border-left:4px solid #b32d2e;padding:8px 14px;background:#fff3f3"><strong>Konfigurasi belum lengkap:</strong><ul>';
        foreach ( $errors as $error ) {
            echo '<li>' . telu_entra_escape( $error ) . '</li>';
        }
        echo '</ul></div>';
    }

    echo '<h3 style="margin-top:24px">Pengaturan Microsoft Entra</h3>';
    echo '<p>Masukkan dua ID dari App Registration. Client Secret tetap disimpan hanya di <code>user/config.php</code> atau environment server.</p>';
    echo '<form method="post"><table style="max-width:900px">';
    echo '<tr><th style="text-align:left;padding:8px;width:220px"><label for="telu_entra_tenant_id">Tenant ID</label></th><td style="padding:8px"><input type="text" id="telu_entra_tenant_id" name="telu_entra_tenant_id" value="' . telu_entra_escape( $tenant ) . '" style="width:420px" pattern="[A-Fa-f0-9-]{36}" required' . ( telu_entra_config_is_locked( 'TENANT_ID' ) ? ' readonly' : '' ) . '></td></tr>';
    echo '<tr><th style="text-align:left;padding:8px"><label for="telu_entra_client_id">Client ID</label></th><td style="padding:8px"><input type="text" id="telu_entra_client_id" name="telu_entra_client_id" value="' . telu_entra_escape( $client ) . '" style="width:420px" pattern="[A-Fa-f0-9-]{36}" required' . ( telu_entra_config_is_locked( 'CLIENT_ID' ) ? ' readonly' : '' ) . '></td></tr>';
    echo '<tr><th style="text-align:left;padding:8px"><label for="telu_entra_session_lifetime">Durasi sesi</label></th><td style="padding:8px"><input type="number" id="telu_entra_session_lifetime" name="telu_entra_session_lifetime" value="' . telu_entra_escape( $session_lifetime ) . '" min="900" max="86400" required' . ( telu_entra_config_is_locked( 'SESSION_LIFETIME' ) ? ' readonly' : '' ) . '> detik</td></tr>';
    echo '<tr><th style="text-align:left;padding:8px"><label for="telu_entra_admin_emails">Email Administrator</label></th><td style="padding:8px"><textarea id="telu_entra_admin_emails" name="telu_entra_admin_emails" rows="2" style="width:420px" required' . ( telu_entra_config_is_locked( 'ADMIN_EMAILS' ) ? ' readonly' : '' ) . '>' . telu_entra_escape( $admin_emails ) . '</textarea><br><small>Wajib, pisahkan dengan koma. Minimal satu akun untuk mengelola SSO.</small></td></tr>';
    echo '<tr><th style="text-align:left;padding:8px"><label for="telu_entra_editor_emails">Email Editor</label></th><td style="padding:8px"><textarea id="telu_entra_editor_emails" name="telu_entra_editor_emails" rows="2" style="width:420px"' . ( telu_entra_config_is_locked( 'EDITOR_EMAILS' ) ? ' readonly' : '' ) . '>' . telu_entra_escape( $editor_emails ) . '</textarea><br><small>Opsional, pisahkan dengan koma.</small></td></tr>';
    echo '<tr><th style="text-align:left;padding:8px"><label for="telu_entra_allowed_groups">Allowed Group IDs</label></th><td style="padding:8px"><textarea id="telu_entra_allowed_groups" name="telu_entra_allowed_groups" rows="2" style="width:420px"' . ( telu_entra_config_is_locked( 'ALLOWED_GROUP_IDS' ) ? ' readonly' : '' ) . '>' . telu_entra_escape( $allowed_groups ) . '</textarea><br><small>Opsional, pisahkan dengan koma; memerlukan groups claim.</small></td></tr>';
    echo '<tr><th style="text-align:left;padding:8px"><label for="telu_entra_allowed_roles">Allowed App Roles</label></th><td style="padding:8px"><textarea id="telu_entra_allowed_roles" name="telu_entra_allowed_roles" rows="2" style="width:420px"' . ( telu_entra_config_is_locked( 'ALLOWED_APP_ROLES' ) ? ' readonly' : '' ) . '>' . telu_entra_escape( $allowed_roles ) . '</textarea><br><small>Opsional. Jika Group dan Role diisi, keduanya wajib cocok.</small></td></tr>';
    echo '<tr><th></th><td style="padding:8px">';
    yourls_nonce_field( 'telu_entra_settings' );
    echo '<button type="submit" class="button primary" name="telu_entra_save" value="1">Simpan Pengaturan</button></td></tr></table></form>';

    echo '<h3 style="margin-top:24px">Status</h3><table class="tblSorter" style="margin-top:10px;max-width:900px">';
    telu_entra_status_row( 'Microsoft SSO', $enabled ? 'AKTIF' : 'NONAKTIF' );
    telu_entra_status_row( 'Tenant ID', $tenant !== '' ? $tenant : 'Belum diisi' );
    telu_entra_status_row( 'Client ID', $client !== '' ? $client : 'Belum diisi' );
    telu_entra_status_row( 'Client Secret', strlen( (string) telu_entra_config( 'CLIENT_SECRET', '' ) ) >= 16 ? 'Terpasang (disembunyikan)' : 'Belum diisi' );
    telu_entra_status_row( 'Redirect URI', telu_entra_redirect_uri() );
    telu_entra_status_row( 'Domain yang diizinkan', $root . ' dan seluruh subdomainnya' );
    telu_entra_status_row( 'Role bawaan Microsoft', 'Contributor' );
    telu_entra_status_row( 'AuthMgrPlus', $authmgr ? 'Aktif/terdeteksi' : 'WAJIB — belum terdeteksi' );
    telu_entra_status_row( 'Login lokal darurat', $local_recovery ? 'AKTIF: ' . $local_url : 'Nonaktif (aman)' );
    telu_entra_status_row( 'Pembuatan via API', $enabled ? 'Diblokir; wajib melalui login Microsoft' : 'Mengikuti konfigurasi bawaan YOURLS' );
    telu_entra_status_row( 'Hook homepage', $homepage_seen > 0 ? 'Terdeteksi pada ' . date( 'Y-m-d H:i:s', $homepage_seen ) : 'Belum terdeteksi — buka homepage sekali lalu muat ulang halaman ini' );
    $last_test_current = is_array( $last_test ) && isset( $last_test['tenant'], $last_test['client'], $last_test['secret'] ) &&
        hash_equals( strtolower( $tenant ), (string) $last_test['tenant'] ) &&
        hash_equals( strtolower( $client ), (string) $last_test['client'] ) &&
        hash_equals( telu_entra_secret_fingerprint(), (string) $last_test['secret'] );
    if ( is_array( $last_test ) && ! empty( $last_test['success'] ) && $last_test_current ) {
        $tested_at = isset( $last_test['time'] ) ? date( 'Y-m-d H:i:s', (int) $last_test['time'] ) : '-';
        $tested_email = isset( $last_test['email'] ) ? (string) $last_test['email'] : '-';
        telu_entra_status_row( 'Tes login terakhir', 'Berhasil: ' . $tested_email . ' (' . $tested_at . ')' );
    } elseif ( is_array( $last_test ) && ! empty( $last_test['success'] ) && ! $last_test_current ) {
        telu_entra_status_row( 'Tes login terakhir', 'KEDALUWARSA — konfigurasi atau Client Secret berubah; jalankan tes ulang' );
    } elseif ( is_array( $last_test ) && isset( $last_test['success'] ) && ! $last_test['success'] ) {
        $tested_at = isset( $last_test['time'] ) ? date( 'Y-m-d H:i:s', (int) $last_test['time'] ) : '-';
        $tested_error = isset( $last_test['error'] ) ? (string) $last_test['error'] : 'Tidak diketahui';
        telu_entra_status_row( 'Tes login terakhir', 'GAGAL (' . $tested_at . '): ' . $tested_error );
    } else {
        telu_entra_status_row( 'Tes login terakhir', 'Belum pernah berhasil' );
    }
    echo '</table>';

    echo '<div style="display:flex;gap:10px;margin-top:18px;flex-wrap:wrap">';
    echo '<form method="post">';
    yourls_nonce_field( 'telu_entra_settings' );
    echo '<button type="submit" class="button" name="telu_entra_test" value="1">Tes Login Microsoft</button></form>';
    echo '<form method="post">';
    yourls_nonce_field( 'telu_entra_settings' );
    echo '<button type="submit" class="button ' . ( $enabled ? '' : 'primary' ) . '" name="telu_entra_toggle" value="1"' . ( telu_entra_config_is_locked( 'ENABLED' ) ? ' disabled' : '' ) . '>' . ( $enabled ? 'Nonaktifkan SSO' : 'Aktifkan SSO' ) . '</button></form>';
    echo '<form method="post" onsubmit="return confirm(\'Reset konfigurasi non-rahasia dan hasil tes?\')">';
    yourls_nonce_field( 'telu_entra_settings' );
    echo '<button type="submit" class="button" name="telu_entra_reset" value="1"' . ( $enabled ? ' disabled' : '' ) . '>Reset Konfigurasi</button></form>';
    echo '</div>';

    $audit_raw = yourls_get_option( TELU_ENTRA_AUDIT_OPTION );
    $audit_entries = is_string( $audit_raw ) ? json_decode( $audit_raw, true ) : array();
    if ( is_array( $audit_entries ) && ! empty( $audit_entries ) ) {
        echo '<h3 style="margin-top:24px">Audit login terbaru</h3><table class="tblSorter" style="max-width:900px"><thead><tr><th>Waktu</th><th>Event</th><th>Email</th><th>Detail</th></tr></thead><tbody>';
        foreach ( array_slice( $audit_entries, 0, 10 ) as $entry ) {
            echo '<tr><td>' . telu_entra_escape( isset( $entry['time'] ) ? date( 'Y-m-d H:i:s', (int) $entry['time'] ) : '-' ) . '</td><td>' . telu_entra_escape( isset( $entry['event'] ) ? $entry['event'] : '-' ) . '</td><td>' . telu_entra_escape( isset( $entry['email'] ) ? $entry['email'] : '-' ) . '</td><td>' . telu_entra_escape( isset( $entry['detail'] ) ? $entry['detail'] : '-' ) . '</td></tr>';
        }
        echo '</tbody></table><p><small>Maksimum 100 entri; token, secret, IP, dan user-agent tidak dicatat.</small></p>';
    }

    echo '<h3 style="margin-top:24px">Konfigurasi rahasia di user/config.php</h3>';
    echo '<pre style="padding:14px;background:#f5f5f5;overflow:auto">' . telu_entra_escape(
        "define( 'TELU_ENTRA_CLIENT_SECRET', 'ISI-LANGSUNG-DI-SERVER' );\n" .
        "define( 'TELU_ENTRA_ALLOWED_ROOT_DOMAIN', 'telkomuniversity.ac.id' );\n" .
        "define( 'TELU_ENTRA_ALLOW_LOCAL_RECOVERY', false );"
    ) . '</pre>';
}

function telu_entra_status_row( $label, $value ) {
    echo '<tr><th style="text-align:left;padding:8px;width:220px">' . telu_entra_escape( $label ) . '</th>' .
        '<td style="padding:8px">' . telu_entra_escape( $value ) . '</td></tr>';
}

function telu_entra_escape( $value ) {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function telu_entra_error_page( $message, $status, $allow_retry = true ) {
    if ( ! empty( $GLOBALS['telu_entra_test_in_progress'] ) ) {
        yourls_update_option( TELU_ENTRA_TEST_OPTION, json_encode( array(
            'success' => false,
            'time'    => time(),
            'error'   => substr( (string) $message, 0, 240 ),
            'tenant'  => strtolower( trim( (string) telu_entra_config( 'TENANT_ID', '' ) ) ),
            'client'  => strtolower( trim( (string) telu_entra_config( 'CLIENT_ID', '' ) ) ),
            'secret'  => telu_entra_secret_fingerprint(),
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
        telu_entra_audit( 'test_failed', '', (string) $message );
        $GLOBALS['telu_entra_test_in_progress'] = false;
    }
    if ( ! headers_sent() ) {
        http_response_code( (int) $status );
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
    }

    $retry = rtrim( YOURLS_SITE, '/' ) . '/admin/';
    $local_recovery = filter_var( telu_entra_config( 'ALLOW_LOCAL_RECOVERY', false ), FILTER_VALIDATE_BOOLEAN );
    $local = rtrim( YOURLS_SITE, '/' ) . '/admin/?telu_local_login=1';
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Microsoft SSO</title><style>body{font-family:Arial,sans-serif;background:#f3f7fa;margin:0;padding:32px;color:#1f2937}.box{max-width:620px;margin:8vh auto;background:#fff;padding:28px;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.08)}a{display:inline-block;margin:8px 10px 0 0;padding:10px 14px;border-radius:7px;background:#1675b8;color:#fff;text-decoration:none}.secondary{background:#64748b}</style></head><body><div class="box">';
    echo '<h1>Login Microsoft gagal</h1><p>' . telu_entra_escape( $message ) . '</p>';
    if ( $allow_retry ) {
        echo '<a href="' . telu_entra_escape( $retry ) . '">Coba lagi</a>';
    }
    if ( $local_recovery ) {
        echo '<a class="secondary" href="' . telu_entra_escape( $local ) . '">Login admin lokal darurat</a>';
    }
    echo '</div></body></html>';
    exit;
}
