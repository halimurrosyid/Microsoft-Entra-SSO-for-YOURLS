<?php
/*
Plugin Name: Microsoft Entra SSO & Theme for YOURLS
Plugin URI: https://github.com/halimurrosyid/Microsoft-Entra-SSO-for-YOURLS
Description: Secure Microsoft Entra ID SSO with integrated Telkom University branding theme, gateway popup, and AuthMgrPlus role management.
Version: 2.3.2
Author: Konten Telu
Author URI: https://github.com/halimurrosyid/Microsoft-Entra-SSO-for-YOURLS
License: GPL-3.0-or-later
*/

if ( ! defined( 'YOURLS_ABSPATH' ) ) {
    die();
}

define( 'TELU_ENTRA_SSO_VERSION', '2.3.2' );
define( 'TELU_YOURLS_THEME_VERSION', '2.3.2' );
define( 'TELU_YOURLS_THEME_OPTION', 'telu_yourls_theme_settings_v1' );
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
define( 'TELU_ENTRA_DOMAIN_OPTION', 'telu_entra_sso_allowed_root_domain_v1' );

yourls_add_filter( 'shunt_is_valid_user', 'telu_entra_authenticate', 8 );
yourls_add_filter( 'shunt_add_new_link', 'telu_entra_validate_custom_keyword', 5, 4 );
yourls_add_filter( 'sanitize_string', 'telu_entra_preserve_safe_custom_keyword', 20, 3 );
yourls_add_filter( 'logout_link', 'telu_entra_display_name_in_header', 20 );
yourls_add_action( 'pre_load_template', 'telu_entra_protect_homepage', 1 );
yourls_add_action( 'logout', 'telu_entra_logout' );
yourls_add_action( 'login_form_bottom', 'telu_entra_local_login_microsoft_button' );
yourls_add_action( 'auth_successful', 'telu_entra_harden_authmgr_roles', 1 );
yourls_add_action( 'auth_successful', 'telu_entra_reconcile_current_user_ownership', 2 );
yourls_add_filter( 'admin_list_where', 'telu_entra_strict_owner_list_where', 99 );
yourls_add_filter( 'get_db_stats', 'telu_entra_strict_owner_db_stats', 99 );
yourls_add_filter( 'api_url_stats', 'telu_entra_strict_owner_api_stats', 99 );
yourls_add_filter( 'admin_links', 'telu_entra_role_based_admin_links', 99 );
yourls_add_filter( 'admin_sublinks', 'telu_entra_role_based_admin_sublinks', 99 );
yourls_add_filter( 'shunt_option_core_version_checks', 'telu_entra_filter_core_version_checks', 99 );
yourls_add_filter( 'get_option_core_version_checks', 'telu_entra_filter_core_version_checks', 99 );
yourls_add_filter( 'shunt_maybe_check_core_version', 'telu_entra_shunt_maybe_check_core_version', 99 );
yourls_add_action( 'pre_yourls_infos', 'telu_entra_strict_owner_info_access', 1 );
yourls_add_action( 'plugins_loaded', 'telu_entra_migrate_legacy_domain', 1 );
yourls_add_action( 'plugins_loaded', 'telu_entra_enforce_root_homepage_gate', 1 );
yourls_add_action( 'plugins_loaded', 'telu_entra_authenticate_public_creation', 5 );
yourls_add_action( 'auth_successful', 'telu_entra_restrict_administrator_pages', 20 );
yourls_add_action( 'insert_link', 'telu_entra_restore_owner_before_authmgr', 1 );
yourls_add_action( 'insert_link', 'telu_entra_verify_public_creation_owner', 99 );

// Presentation / Theme hooks
yourls_add_action( 'html_head', 'telu_yourls_theme_assets', 99 );
yourls_add_action( 'html_logo', 'telu_yourls_theme_header', 99 );
yourls_add_filter( 'bodyclass', 'telu_yourls_theme_body_class', 99 );
yourls_add_filter( 'html_title', 'telu_yourls_theme_title', 99 );
yourls_add_filter( 'html_footer_text', 'telu_yourls_theme_footer', 99 );
yourls_add_filter( 'help_link', 'telu_yourls_theme_role_based_help_link', 99 );
yourls_add_action( 'plugins_loaded', 'telu_yourls_theme_public_buffer_early', 99 );
yourls_add_action( 'pre_load_template', 'telu_yourls_theme_public_buffer', 99 );

if ( function_exists( 'yourls_register_plugin_page' ) ) {
    yourls_register_plugin_page(
        'telu_entra_sso',
        'Microsoft SSO',
        'telu_entra_settings_page'
    );
    yourls_register_plugin_page(
        'telu_yourls_theme',
        'Pengaturan Theme',
        'telu_yourls_theme_settings_page'
    );
}

/**
 * Preserve user-entered custom keywords without changing generated keywords.
 *
 * YOURLS passes both its sanitized value and the original keyword through the
 * sanitize_string filter. Rebuild only values explicitly restricted for add or
 * edit, using URL-path-safe characters. The global conversion charset remains
 * untouched, so automatic base-36/base-62 generation cannot change.
 */
function telu_entra_preserve_safe_custom_keyword( $valid, $keyword, $restrict_to_shorturl_charset = false ) {
    if ( $restrict_to_shorturl_charset !== true || ! is_string( $keyword ) ) {
        return $valid;
    }

    $preserved = preg_replace( '/[^0-9A-Za-z_-]/', '', $keyword );
    return substr( (string) $preserved, 0, 199 );
}

/**
 * Validate URL scheme, prevent self-redirect loops, protect reserved keywords,
 * and ensure custom keywords strictly conform to allowed safe characters.
 */
function telu_entra_validate_custom_keyword( $pre, $url, $keyword = '', $title = '' ) {
    if ( function_exists( 'yourls_shunt_default' ) && yourls_shunt_default() !== $pre ) {
        return $pre;
    }

    // 1. Validasi protokol / skema URL: hanya izinkan http:// dan https://
    $scheme = strtolower( (string) parse_url( (string) $url, PHP_URL_SCHEME ) );
    if ( $scheme !== '' && ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
        return array(
            'status'     => 'fail',
            'code'       => 'error:invalid-scheme',
            'message'    => 'Tautan harus menggunakan protokol yang aman (http:// atau https://).',
            'errorCode'  => '400',
            'statusCode' => '400',
        );
    }

    // 2. Anti Self-Redirect: cegah tautan mengarah kembali ke domain penyingkat ini
    $url_host = strtolower( (string) parse_url( (string) $url, PHP_URL_HOST ) );
    $site_host = defined( 'YOURLS_SITE' ) ? strtolower( (string) parse_url( (string) YOURLS_SITE, PHP_URL_HOST ) ) : '';
    if ( $url_host !== '' && ( $url_host === $site_host || $url_host === 's.telkomuniversity.ac.id' ) ) {
        return array(
            'status'     => 'fail',
            'code'       => 'error:self-redirect',
            'message'    => 'Tautan tujuan tidak boleh mengarah ke domain s.telkomuniversity.ac.id untuk mencegah loop redirection.',
            'errorCode'  => '400',
            'statusCode' => '400',
        );
    }

    // 3. Validasi custom keyword jika diisi
    if ( is_string( $keyword ) && $keyword !== '' ) {
        $reserved_words = array(
            'admin', 'api', 'login', 'logout', 'dashboard', 'assets', 'plugins',
            'pages', 'tools', 'stats', 'index', 'result', 'readme', 'license',
            'user', 'sample-public-front-page', 'yourls-loader', 'yourls-api',
            'yourls-admin', 'yourls-go', 'yourls-infos', 'qr'
        );
        if ( in_array( strtolower( trim( $keyword ) ), $reserved_words, true ) ) {
            return array(
                'status'     => 'fail',
                'code'       => 'error:keyword-reserved',
                'message'    => 'Kata kunci "' . htmlspecialchars( $keyword, ENT_QUOTES, 'UTF-8' ) . '" dicadangkan oleh sistem dan tidak dapat digunakan.',
                'errorCode'  => '400',
                'statusCode' => '400',
            );
        }

        if ( strlen( $keyword ) <= 199 && preg_match( '/^[0-9A-Za-z_-]+$/D', $keyword ) === 1 ) {
            return $pre;
        }

        return array(
            'status'     => 'fail',
            'code'       => 'error:keyword-format',
            'message'    => 'Custom keyword hanya boleh berisi angka, huruf besar/kecil, tanda hubung (-), dan garis bawah (_).',
            'errorCode'  => '400',
            'statusCode' => '400',
        );
    }

    return $pre;
}

/**
 * Get a plugin setting from a constant, environment variable, or safe DB option.
 * Client Secret is deliberately never read from or stored in the database.
 */
function telu_entra_config( $name, $default = null ) {
    $key = 'YOURLS_ENTRA_' . $name;
    $legacy_key = 'TELU_ENTRA_' . $name;

    if ( defined( $key ) ) {
        return constant( $key );
    }

    $environment = getenv( $key );
    if ( $environment !== false && $environment !== '' ) {
        return $environment;
    }

    // Backward compatibility with releases up to 1.5.0.
    if ( defined( $legacy_key ) ) {
        return constant( $legacy_key );
    }
    $legacy_environment = getenv( $legacy_key );
    if ( $legacy_environment !== false && $legacy_environment !== '' ) {
        return $legacy_environment;
    }

    $database_options = array(
        'TENANT_ID' => TELU_ENTRA_TENANT_OPTION,
        'CLIENT_ID' => TELU_ENTRA_CLIENT_OPTION,
        'SESSION_LIFETIME' => TELU_ENTRA_SESSION_OPTION,
        'ALLOWED_GROUP_IDS' => TELU_ENTRA_GROUPS_OPTION,
        'ALLOWED_APP_ROLES' => TELU_ENTRA_ROLES_OPTION,
        'ADMIN_EMAILS' => TELU_ENTRA_ADMINS_OPTION,
        'EDITOR_EMAILS' => TELU_ENTRA_EDITORS_OPTION,
        'ALLOWED_ROOT_DOMAIN' => TELU_ENTRA_DOMAIN_OPTION,
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
    $key = 'YOURLS_ENTRA_' . $name;
    $legacy_key = 'TELU_ENTRA_' . $name;
    $environment = getenv( $key );
    $legacy_environment = getenv( $legacy_key );
    return defined( $key ) || ( $environment !== false && $environment !== '' ) ||
        defined( $legacy_key ) || ( $legacy_environment !== false && $legacy_environment !== '' );
}

/**
 * Persist the old 1.x domain once so removing the legacy constant after an
 * upgrade does not unexpectedly lock out the organization.
 */
function telu_entra_migrate_legacy_domain() {
    if ( defined( 'YOURLS_ENTRA_ALLOWED_ROOT_DOMAIN' ) || ! defined( 'TELU_ENTRA_ALLOWED_ROOT_DOMAIN' ) ) {
        return;
    }
    $stored = yourls_get_option( TELU_ENTRA_DOMAIN_OPTION );
    $legacy = strtolower( trim( (string) constant( 'TELU_ENTRA_ALLOWED_ROOT_DOMAIN' ), " .\t\n\r\0\x0B" ) );
    if ( ( ! is_string( $stored ) || $stored === '' ) && filter_var( $legacy, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
        yourls_update_option( TELU_ENTRA_DOMAIN_OPTION, $legacy );
    }
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

/**
 * Bind issued sessions to the current application and authorization policy.
 * Changing Client ID, tenant, domain, groups, or roles forces a fresh login.
 */
function telu_entra_policy_fingerprint() {
    $groups = array_map( 'strtolower', telu_entra_list_setting( 'ALLOWED_GROUP_IDS' ) );
    $roles  = array_map( 'strtolower', telu_entra_list_setting( 'ALLOWED_APP_ROLES' ) );
    sort( $groups, SORT_STRING );
    sort( $roles, SORT_STRING );

    return hash( 'sha256', implode( "\n", array(
        strtolower( trim( (string) telu_entra_config( 'TENANT_ID', '' ) ) ),
        strtolower( trim( (string) telu_entra_config( 'CLIENT_ID', '' ) ) ),
        strtolower( trim( (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', '' ) ) ),
        implode( ',', $groups ),
        implode( ',', $roles ),
    ) ) );
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
    $root   = strtolower( trim( (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', '' ) ) );

    if ( ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/', $tenant ) ) {
        $errors[] = 'YOURLS_ENTRA_TENANT_ID harus berupa Tenant ID (GUID) Microsoft Entra.';
    }

    if ( ! preg_match( '/^[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12}$/', $client ) ) {
        $errors[] = 'YOURLS_ENTRA_CLIENT_ID belum diisi dengan benar.';
    }

    if ( strlen( $secret ) < 16 ) {
        $errors[] = 'YOURLS_ENTRA_CLIENT_SECRET belum diisi atau terlalu pendek.';
    }

    if ( ! preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $root ) ) {
        $errors[] = 'Domain email organisasi belum diisi atau tidak valid.';
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
        $errors[] = 'Minimal satu email Administrator wajib ditetapkan untuk administrasi dan recovery.';
    } else {
        foreach ( $admins as $admin_email ) {
            if ( ! telu_entra_email_is_allowed( $admin_email ) ) {
                $errors[] = 'Email administrator tidak valid atau berada di luar domain organisasi: ' . $admin_email;
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

    telu_entra_migrate_legacy_domain();

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

    // Check Entra configuration only after the deliberately enabled recovery
    // path, otherwise a missing domain/secret could also lock out local admins.
    $configuration_errors = telu_entra_configuration_errors();
    if ( ! empty( $configuration_errors ) ) {
        telu_entra_error_page( implode( ' ', $configuration_errors ), 503, false );
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

    $return_to = isset( $_REQUEST['return_to'] ) ? telu_entra_safe_return_path( $_REQUEST['return_to'] ) : null;

    if ( isset( $_GET['telu_sso_direct'] ) ) {
        telu_entra_begin_login( 'login', $return_to );
        exit;
    }

    telu_entra_render_gateway_page( '', '', $return_to );
    exit;
}

/**
 * Detect whether the incoming HTTP request is for the site's root homepage or public front page.
 */
function telu_entra_is_root_homepage_request() {
    if ( empty( $_SERVER['REQUEST_URI'] ) ) {
        return false;
    }

    $raw_path = parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    if ( $raw_path === null || $raw_path === false ) {
        return false;
    }

    $site_path = defined( 'YOURLS_SITE' ) ? parse_url( (string) YOURLS_SITE, PHP_URL_PATH ) : '/';
    $site_root = '/' . trim( (string) $site_path, '/' );
    $request_path = '/' . trim( (string) $raw_path, '/' );

    $site_root_clean = rtrim( $site_root, '/' );
    $allowed_roots = array(
        $site_root_clean === '' ? '/' : $site_root_clean,
        ( $site_root_clean === '' ? '' : $site_root_clean ) . '/index.php',
        ( $site_root_clean === '' ? '' : $site_root_clean ) . '/sample-public-front-page.php',
    );

    return in_array( $request_path, $allowed_roots, true );
}

/**
 * Enforce that accessing the root homepage when not logged in displays ONLY the pop-up modal.
 * Only after a valid Microsoft 365 login can the user access the link generator form or dashboard.
 */
function telu_entra_enforce_root_homepage_gate() {
    if ( function_exists( 'yourls_is_installing' ) && yourls_is_installing() ) {
        return;
    }

    if ( ! telu_entra_is_enabled() ) {
        return;
    }

    if ( ! telu_entra_is_root_homepage_request() ) {
        return;
    }

    // Handle explicit public logout
    if ( isset( $_GET['telu_logout'] ) || ( isset( $_GET['action'] ) && $_GET['action'] === 'telu_logout' ) ) {
        telu_entra_logout();
        telu_entra_clear_cookie( TELU_ENTRA_AUTH_COOKIE );
        telu_entra_clear_cookie( TELU_ENTRA_FLOW_COOKIE );
        if ( function_exists( 'yourls_cookie_name' ) ) {
            setcookie( yourls_cookie_name(), '', time() - 3600, '/' );
        }
        header( 'Location: ' . rtrim( (string) YOURLS_SITE, '/' ) . '/', true, 302 );
        exit;
    }

    // Allow Microsoft OAuth authorization code and error callbacks
    if ( isset( $_GET['code'] ) || isset( $_GET['error'] ) ) {
        return;
    }

    // Allow emergency local administrator recovery login if explicitly enabled
    $local_recovery = filter_var( telu_entra_config( 'ALLOW_LOCAL_RECOVERY', false ), FILTER_VALIDATE_BOOLEAN );
    if ( $local_recovery && (
        isset( $_GET['telu_local_login'] ) ||
        isset( $_REQUEST['username'], $_REQUEST['password'] )
    ) ) {
        return;
    }

    // Check if the visitor already has an active, valid Microsoft Entra session
    $identity = telu_entra_read_identity_cookie();
    if ( is_array( $identity ) && ! empty( $identity['email'] ) && telu_entra_email_is_allowed( $identity['email'] ) ) {
        $email = strtolower( trim( (string) $identity['email'] ) );
        if ( function_exists( 'yourls_set_user' ) && ! defined( 'YOURLS_USER' ) ) {
            yourls_set_user( $email );
        }
        telu_entra_assign_authmgr_role( $email );
        return;
    }

    // Direct SSO button click redirects straight to Microsoft login
    if ( isset( $_GET['telu_sso_direct'] ) ) {
        telu_entra_begin_login( 'login', '/' );
        exit;
    }

    // Visitor is unauthenticated: discard any buffered output so no background page or form renders
    while ( ob_get_level() > 0 ) {
        @ob_end_clean();
    }

    // Render ONLY the clean pop-up modal and terminate execution
    telu_entra_render_gateway_page( '', '', '/' );
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

    $identity = telu_entra_read_identity_cookie();
    if ( is_array( $identity ) && ! empty( $identity['email'] ) && telu_entra_email_is_allowed( $identity['email'] ) ) {
        return;
    }

    while ( ob_get_level() > 0 ) {
        @ob_end_clean();
    }

    telu_entra_render_gateway_page( '', '', '/' );
    exit;
}

/**
 * Public frontend forms commonly POST to result.php without calling
 * yourls_is_valid_user(). Restore the verified Entra identity before the
 * frontend calls yourls_add_new_link(), so AuthMgrPlus can record its owner.
 */
function telu_entra_authenticate_public_creation() {
    if ( ! telu_entra_is_enabled() || ! telu_entra_is_public_creation_request() ) {
        return;
    }

    $identity = telu_entra_read_identity_cookie();
    if ( ! is_array( $identity ) || empty( $identity['email'] ) ) {
        telu_entra_error_page( 'Sesi Microsoft diperlukan untuk membuat shortlink.', 401 );
    }

    if ( ! telu_entra_email_is_allowed( $identity['email'] ) ) {
        telu_entra_domain_error_page( $identity['email'] );
    }

    $email = strtolower( trim( (string) $identity['email'] ) );
    $GLOBALS['telu_entra_public_creation_email'] = $email;

    if ( function_exists( 'yourls_set_user' ) && ! defined( 'YOURLS_USER' ) ) {
        yourls_set_user( $email );
    }
    telu_entra_assign_authmgr_role( $email );
}

/**
 * Compare AuthMgrPlus owner values as normalized Entra email identities.
 *
 * AuthMgrPlus 2.3.1 performs an exact, case-sensitive PHP comparison when it
 * authorizes edit/delete actions. Older rows can contain the same email with
 * different letter casing or surrounding whitespace, which makes a user's own
 * URL appear unmanageable even though the database lookup still finds it.
 */
function telu_entra_owner_identity_matches( $owner, $email ) {
    if ( ! is_string( $owner ) || ! is_string( $email ) ) {
        return false;
    }

    $owner = strtolower( trim( $owner ) );
    $email = strtolower( trim( $email ) );

    return $owner !== '' && $email !== '' && hash_equals( $email, $owner );
}

/**
 * Normalize only rows that already belong to the signed-in Entra identity.
 *
 * This runs before AuthMgrPlus' default-priority auth_successful callback, so
 * both action-button visibility and AJAX edit/delete authorization see the
 * exact YOURLS_USER value. It never claims anonymous rows and never transfers
 * a row whose normalized owner differs from the authenticated email.
 */
function telu_entra_reconcile_current_user_ownership() {
    static $reconciled = false;

    if ( $reconciled || ! telu_entra_is_enabled() || ! defined( 'YOURLS_USER' ) ) {
        return;
    }
    $reconciled = true;

    $identity = telu_entra_read_identity_cookie();
    if ( ! is_array( $identity ) || empty( $identity['email'] ) || ! telu_entra_email_is_allowed( $identity['email'] ) ) {
        return;
    }

    $email = strtolower( trim( (string) $identity['email'] ) );
    if ( ! telu_entra_owner_identity_matches( (string) YOURLS_USER, $email ) ) {
        return;
    }

    global $ydb;
    if ( ! is_object( $ydb ) || ! defined( 'YOURLS_DB_TABLE_URL' ) ) {
        return;
    }

    $sql = "UPDATE `" . YOURLS_DB_TABLE_URL . "` SET `user` = :telu_entra_exact_owner "
         . "WHERE `user` IS NOT NULL AND LOWER(TRIM(`user`)) = :telu_entra_normalized_owner";
    $affected = $ydb->fetchAffected( $sql, array(
        'telu_entra_exact_owner'      => $email,
        'telu_entra_normalized_owner' => $email,
    ) );

    if ( (int) $affected > 0 ) {
        telu_entra_audit( 'owner_identity_normalized', $email, (int) $affected . ' URL(s)' );
    }
}

function telu_entra_is_public_creation_request() {
    if ( empty( $_SERVER['REQUEST_URI'] ) || empty( $_SERVER['REQUEST_METHOD'] ) ) {
        return false;
    }

    $path = parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    $site_path = defined( 'YOURLS_SITE' ) ? parse_url( (string) YOURLS_SITE, PHP_URL_PATH ) : '/';
    $expected = rtrim( '/' . trim( (string) $site_path, '/' ), '/' ) . '/result.php';
    $method = strtoupper( (string) $_SERVER['REQUEST_METHOD'] );

    return rtrim( '/' . trim( (string) $path, '/' ), '/' ) === rtrim( $expected, '/' )
        && in_array( $method, array( 'POST', 'GET' ), true )
        && isset( $_REQUEST['url'] )
        && trim( (string) $_REQUEST['url'] ) !== '';
}

/**
 * Defensive ordering guard: this runs before AuthMgrPlus' default-priority
 * insert_link callback. It does not write to the database itself.
 */
function telu_entra_restore_owner_before_authmgr( $actions ) {
    $email = isset( $GLOBALS['telu_entra_public_creation_email'] )
        ? strtolower( trim( (string) $GLOBALS['telu_entra_public_creation_email'] ) )
        : '';

    if ( $email !== '' && ! defined( 'YOURLS_USER' ) && function_exists( 'yourls_set_user' ) ) {
        yourls_set_user( $email );
    } elseif ( ! defined( 'YOURLS_USER' ) && telu_entra_is_enabled() ) {
        $identity = telu_entra_read_identity_cookie();
        if ( is_array( $identity ) && ! empty( $identity['email'] ) && telu_entra_email_is_allowed( $identity['email'] ) ) {
            if ( function_exists( 'yourls_set_user' ) ) {
                yourls_set_user( strtolower( trim( (string) $identity['email'] ) ) );
            }
            telu_entra_assign_authmgr_role( $identity['email'] );
        }
    }
    return $actions;
}

/**
 * AuthMgrPlus normally writes ownership from YOURLS_USER. Public frontend
 * scripts do not all execute the normal YOURLS authentication path, so verify
 * the owner after a successful insert and repair only this authenticated
 * result.php request with a parameterized update.
 */
function telu_entra_verify_public_creation_owner( $actions ) {
    $email = isset( $GLOBALS['telu_entra_public_creation_email'] )
        ? strtolower( trim( (string) $GLOBALS['telu_entra_public_creation_email'] ) )
        : '';

    if ( $email === '' || ! is_array( $actions ) || empty( $actions[0] ) || empty( $actions[2] ) ) {
        return $actions;
    }

    $keyword = (string) $actions[2];
    $owner = telu_entra_get_keyword_owner( $keyword );
    if ( telu_entra_owner_identity_matches( $owner, $email ) ) {
        telu_entra_audit( 'homepage_link_created', $email, $keyword );
        return $actions;
    }

    global $ydb;
    if ( ! is_object( $ydb ) || ! defined( 'YOURLS_DB_TABLE_URL' ) ) {
        telu_entra_audit( 'homepage_owner_failed', $email, 'Database unavailable for ' . $keyword );
        return $actions;
    }

    $sql = "UPDATE `" . YOURLS_DB_TABLE_URL . "` SET `user` = :telu_entra_user WHERE `keyword` = :telu_entra_keyword";
    $ydb->fetchAffected( $sql, array(
        'telu_entra_user'    => $email,
        'telu_entra_keyword' => $keyword,
    ) );

    $owner = telu_entra_get_keyword_owner( $keyword );
    if ( telu_entra_owner_identity_matches( $owner, $email ) ) {
        telu_entra_audit( 'homepage_owner_repaired', $email, $keyword );
    } else {
        telu_entra_audit( 'homepage_owner_failed', $email, $keyword );
    }

    return $actions;
}

/**
 * Start Microsoft Authorization Code flow with PKCE, state and nonce.
 */
function telu_entra_begin_login( $purpose = 'login', $return_to = null, $login_hint = '' ) {
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
        'domain_hint'           => (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', '' ),
        'prompt'                => 'select_account',
    );

    if ( $login_hint !== '' && filter_var( $login_hint, FILTER_VALIDATE_EMAIL ) ) {
        $parameters['login_hint'] = $login_hint;
    }

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
        telu_entra_domain_error_page( $email );
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
            'domain'  => strtolower( trim( (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', '' ) ) ),
            'secret'  => telu_entra_secret_fingerprint(),
            'policy'  => telu_entra_policy_fingerprint(),
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
        'policy'    => telu_entra_policy_fingerprint(),
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
    if ( ! telu_entra_is_enabled() ) {
        return $logout_link;
    }
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
    return telu_entra_email_matches_domain( $email, telu_entra_config( 'ALLOWED_ROOT_DOMAIN', '' ) );
}

function telu_entra_email_matches_domain( $email, $allowed_root_domain ) {
    $email = strtolower( trim( (string) $email ) );
    if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
        return false;
    }

    $position = strrpos( $email, '@' );
    $domain   = substr( $email, $position + 1 );
    $root     = strtolower( trim( (string) $allowed_root_domain, '.' ) );

    if ( $root === '' || ! filter_var( $root, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
        return false;
    }

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

    // Normalize AuthMgrPlus role keys before adding a dynamic Entra user. This
    // prevents duplicate Administrator/administrator keys from overwriting
    // assignments when AuthMgrPlus performs its own normalization later.
    if ( telu_entra_authmgr_available() && function_exists( 'amp_env_check' ) ) {
        amp_env_check();
    }

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

    telu_entra_harden_authmgr_roles();
}

/**
 * AuthMgrPlus normally lets Editors see every URL and lets non-admin users see
 * anonymous legacy URLs. Keep elevated cross-user access exclusive to the
 * Administrator role; Editor and Contributor remain owners of their own URLs.
 */
function telu_entra_harden_authmgr_roles() {
    global $amp_role_capabilities;

    if ( ! telu_entra_is_enabled() || ! telu_entra_authmgr_available() || ! function_exists( 'amp_env_check' ) ) {
        return;
    }

    amp_env_check();
    if ( ! is_array( $amp_role_capabilities ) ) {
        return;
    }

    $admin_only = array( 'ViewAll', 'ManageAnonURL', 'ManageUsrsURL' );
    foreach ( $amp_role_capabilities as $role => $capabilities ) {
        if ( strtolower( (string) $role ) === 'administrator' || ! is_array( $capabilities ) ) {
            continue;
        }
        $amp_role_capabilities[ $role ] = array_values( array_diff( $capabilities, $admin_only ) );
    }
}

function telu_entra_current_user_is_administrator() {
    global $amp_role_assignment;

    $user = defined( 'YOURLS_USER' ) ? strtolower( trim( (string) YOURLS_USER ) ) : '';
    if ( $user === '' || ! is_array( $amp_role_assignment ) ) {
        return false;
    }

    foreach ( $amp_role_assignment as $role => $users ) {
        if ( strtolower( (string) $role ) !== 'administrator' || ! is_array( $users ) ) {
            continue;
        }
        return in_array( $user, array_map( 'strtolower', array_map( 'strval', $users ) ), true );
    }

    return false;
}

/**
 * Keep administrative navigation and sensitive diagnostic pages exclusive to
 * the AuthMgrPlus Administrator role. The request guard also covers direct URLs.
 */
function telu_entra_role_based_admin_links( $links ) {
    if ( ! telu_entra_is_enabled() || telu_entra_current_user_is_administrator() || ! is_array( $links ) ) {
        return $links;
    }
    foreach ( array( 'tools', 'plugins' ) as $key ) {
        unset( $links[ $key ] );
    }
    return $links;
}

function telu_entra_role_based_admin_sublinks( $links ) {
    if ( ! telu_entra_is_enabled() || telu_entra_current_user_is_administrator() || ! is_array( $links ) ) {
        return $links;
    }
    unset( $links['plugins'] );
    foreach ( $links as $group => $items ) {
        if ( ! is_array( $items ) ) {
            continue;
        }
        foreach ( $items as $key => $item ) {
            $serialized = strtolower( is_array( $item ) ? implode( ' ', array_map( 'strval', $item ) ) : (string) $item );
            if ( strpos( strtolower( (string) $key ), 'telu_entra_sso' ) !== false || strpos( $serialized, 'microsoft sso' ) !== false ) {
                unset( $links[ $group ][ $key ] );
            }
        }
    }
    return $links;
}

function telu_entra_restrict_administrator_pages() {
    if ( ! telu_entra_is_enabled() || telu_entra_current_user_is_administrator() || empty( $_SERVER['REQUEST_URI'] ) ) {
        return;
    }
    $path = parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH );
    $page = isset( $_GET['page'] ) ? (string) $_GET['page'] : '';
    $restricted = preg_match( '#/admin/(?:tools|plugins)\.php$#i', (string) $path ) || $page === 'telu_entra_sso';
    if ( ! $restricted ) {
        return;
    }
    if ( function_exists( 'yourls_redirect' ) && function_exists( 'yourls_admin_url' ) ) {
        yourls_redirect( yourls_admin_url( '?access=denied' ), 302 );
    }
    exit;
}

/**
 * Suppress core version checks and upgrade notifications for non-administrator roles.
 * Only Super Admin (Administrator) users can see available updates.
 */
function telu_entra_filter_core_version_checks( $value ) {
    if (
        function_exists( 'telu_entra_is_enabled' ) &&
        telu_entra_is_enabled() &&
        function_exists( 'telu_entra_current_user_is_administrator' ) &&
        ! telu_entra_current_user_is_administrator()
    ) {
        return false;
    }
    return $value;
}

function telu_entra_shunt_maybe_check_core_version( $pre ) {
    if (
        function_exists( 'telu_entra_is_enabled' ) &&
        telu_entra_is_enabled() &&
        function_exists( 'telu_entra_current_user_is_administrator' ) &&
        ! telu_entra_current_user_is_administrator()
    ) {
        return false;
    }
    return $pre;
}

function telu_entra_strict_owner_list_where( $where ) {
    if ( ! telu_entra_is_enabled() || telu_entra_current_user_is_administrator() || ! is_array( $where ) ) {
        return $where;
    }

    $sql = isset( $where['sql'] ) ? (string) $where['sql'] : '';
    $sql = preg_replace(
        '/\s+AND\s+\(\s*`user`\s*=\s*:user\s+OR\s+`user`\s+IS\s+NULL\s*\)\s*/i',
        ' ',
        $sql
    );
    $where['sql'] = $sql . ' AND (`user` = :telu_entra_owner) ';
    if ( ! isset( $where['binds'] ) || ! is_array( $where['binds'] ) ) {
        $where['binds'] = array();
    }
    unset( $where['binds']['user'] );
    $where['binds']['telu_entra_owner'] = defined( 'YOURLS_USER' ) ? (string) YOURLS_USER : '';

    return $where;
}

function telu_entra_strict_owner_db_stats( $return, $where ) {
    if ( ! telu_entra_is_enabled() || telu_entra_current_user_is_administrator() ) {
        return $return;
    }

    global $ydb;
    if ( ! is_object( $ydb ) || ! defined( 'YOURLS_DB_TABLE_URL' ) ) {
        return $return;
    }

    $where = telu_entra_strict_owner_list_where( is_array( $where ) ? $where : array() );
    $sql = "SELECT COUNT(keyword) AS count, SUM(clicks) AS sum FROM `" . YOURLS_DB_TABLE_URL . "` WHERE 1=1 " . $where['sql'];
    $totals = $ydb->fetchObject( $sql, $where['binds'] );

    return array(
        'total_links'  => isset( $totals->count ) ? (int) $totals->count : 0,
        'total_clicks' => isset( $totals->sum ) ? (int) $totals->sum : 0,
    );
}

function telu_entra_get_keyword_owner( $keyword ) {
    $keyword = is_array( $keyword ) ? (string) reset( $keyword ) : (string) $keyword;
    $keyword = preg_replace( '/\++$/', '', (string) $keyword );
    $keyword = trim( $keyword );
    if ( $keyword === '' ) {
        return null;
    }

    // 1. Direct authoritative lookup in YOURLS database (table yourls_url)
    global $ydb;
    if ( is_object( $ydb ) && defined( 'YOURLS_DB_TABLE_URL' ) ) {
        try {
            $sql = "SELECT `user` FROM `" . YOURLS_DB_TABLE_URL . "` WHERE `keyword` = :telu_keyword LIMIT 1";
            $row = $ydb->fetchObject( $sql, array( 'telu_keyword' => $keyword ) );
            if ( $row && isset( $row->user ) && $row->user !== null && trim( (string) $row->user ) !== '' ) {
                return trim( (string) $row->user );
            }
        } catch ( Exception $e ) {
            // DB fallback
        }
    }

    // 2. AuthMgrPlus helper fallback (or test mock)
    if ( function_exists( 'amp_keyword_owner' ) ) {
        $owner = amp_keyword_owner( $keyword );
        if ( $owner !== null && trim( (string) $owner ) !== '' ) {
            return trim( (string) $owner );
        }
    }

    return null;
}

function telu_entra_current_user_email() {
    if ( defined( 'YOURLS_USER' ) && YOURLS_USER !== '' ) {
        return strtolower( trim( (string) YOURLS_USER ) );
    }
    if ( function_exists( 'telu_entra_read_identity_cookie' ) ) {
        $identity = telu_entra_read_identity_cookie();
        if ( is_array( $identity ) && ! empty( $identity['email'] ) ) {
            return strtolower( trim( (string) $identity['email'] ) );
        }
    }
    return '';
}

function telu_entra_current_user_owns_keyword( $keyword ) {
    if ( telu_entra_current_user_is_administrator() ) {
        return true;
    }

    $current_user = telu_entra_current_user_email();
    if ( $current_user === '' ) {
        return false;
    }

    $owner = telu_entra_get_keyword_owner( $keyword );
    if ( $owner === null ) {
        return false;
    }

    return telu_entra_owner_identity_matches( $owner, $current_user );
}

function telu_entra_strict_owner_api_stats( $return, $shorturl ) {
    if ( ! telu_entra_is_enabled() ) {
        return $return;
    }
    $keyword = str_replace( YOURLS_SITE . '/', '', (string) $shorturl );
    $keyword = function_exists( 'yourls_sanitize_string' ) ? yourls_sanitize_string( $keyword ) : $keyword;
    if ( ! telu_entra_current_user_owns_keyword( $keyword ) ) {
        return array(
            'simple'    => 'URL is owned by another user',
            'message'   => 'URL is owned by another user',
            'errorCode' => 403,
        );
    }
    return $return;
}

function telu_entra_strict_owner_info_access( $keyword ) {
    $keyword = is_array( $keyword ) ? (string) reset( $keyword ) : (string) $keyword;
    $keyword = preg_replace( '/\++$/', '', (string) $keyword );
    $keyword = function_exists( 'yourls_sanitize_keyword' ) ? yourls_sanitize_keyword( $keyword ) : trim( $keyword );

    if ( ! telu_entra_is_enabled() || ! yourls_is_private() || telu_entra_current_user_owns_keyword( $keyword ) ) {
        return;
    }

    yourls_redirect( yourls_admin_url( '?access=denied' ), 302 );
    exit;
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

    if ( empty( $identity['policy'] ) || ! hash_equals( telu_entra_policy_fingerprint(), (string) $identity['policy'] ) ) {
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
        CURLOPT_USERAGENT      => 'YOURLS-Microsoft-Entra-SSO/' . TELU_ENTRA_SSO_VERSION,
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
        'message'   => 'Pembuatan shortlink wajib melalui antarmuka web dan login Microsoft Entra organisasi.',
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
    if ( ! telu_entra_current_user_is_administrator() ) {
        if ( function_exists( 'yourls_add_notice' ) ) {
            yourls_add_notice( 'Access Denied' );
        }
        return;
    }

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
        $submitted_domain = strtolower( trim( isset( $_POST['telu_entra_allowed_root_domain'] ) ? (string) $_POST['telu_entra_allowed_root_domain'] : '', " .\t\n\r\0\x0B" ) );
        $submitted_session = isset( $_POST['telu_entra_session_lifetime'] ) ? (int) $_POST['telu_entra_session_lifetime'] : 28800;
        $submitted_groups = implode( ',', telu_entra_normalize_csv( isset( $_POST['telu_entra_allowed_groups'] ) ? (string) $_POST['telu_entra_allowed_groups'] : '' ) );
        $submitted_roles = implode( ',', telu_entra_normalize_csv( isset( $_POST['telu_entra_allowed_roles'] ) ? (string) $_POST['telu_entra_allowed_roles'] : '' ) );
        $submitted_admins = implode( ',', array_map( 'strtolower', telu_entra_normalize_csv( isset( $_POST['telu_entra_admin_emails'] ) ? (string) $_POST['telu_entra_admin_emails'] : '' ) ) );
        $submitted_editors = implode( ',', array_map( 'strtolower', telu_entra_normalize_csv( isset( $_POST['telu_entra_editor_emails'] ) ? (string) $_POST['telu_entra_editor_emails'] : '' ) ) );
        $invalid_role_email = '';
        foreach ( array_merge( telu_entra_normalize_csv( $submitted_admins ), telu_entra_normalize_csv( $submitted_editors ) ) as $role_email ) {
            if ( ! telu_entra_email_matches_domain( $role_email, $submitted_domain ) ) {
                $invalid_role_email = $role_email;
                break;
            }
        }
        $guid = '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/';

        if ( ! preg_match( $guid, $submitted_tenant ) || ! preg_match( $guid, $submitted_client ) ) {
            $notice_error = 'Tenant ID dan Client ID harus berupa GUID Microsoft yang valid.';
        } elseif ( ! filter_var( $submitted_domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
            $notice_error = 'Domain email organisasi harus berupa nama domain yang valid, misalnya example.org.';
        } elseif ( $submitted_session < 900 || $submitted_session > 86400 ) {
            $notice_error = 'Durasi sesi harus antara 900 dan 86400 detik.';
        } elseif ( $submitted_admins === '' ) {
            $notice_error = 'Minimal satu email administrator organisasi wajib diisi.';
        } elseif ( $invalid_role_email !== '' ) {
            $notice_error = 'Email role tidak valid atau di luar domain organisasi: ' . $invalid_role_email;
        } else {
            if ( ! telu_entra_config_is_locked( 'TENANT_ID' ) ) {
                yourls_update_option( TELU_ENTRA_TENANT_OPTION, $submitted_tenant );
            }
            if ( ! telu_entra_config_is_locked( 'CLIENT_ID' ) ) {
                yourls_update_option( TELU_ENTRA_CLIENT_OPTION, $submitted_client );
            }
            if ( ! telu_entra_config_is_locked( 'ALLOWED_ROOT_DOMAIN' ) ) {
                yourls_update_option( TELU_ENTRA_DOMAIN_OPTION, $submitted_domain );
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
            $notice_error = 'Status enable dikunci oleh YOURLS_ENTRA_ENABLED di config atau environment.';
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
                $current_domain = strtolower( trim( (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', '' ) ) );
                $test_matches = is_array( $test ) && ! empty( $test['success'] ) &&
                    isset( $test['tenant'], $test['client'], $test['domain'], $test['policy'] ) &&
                    hash_equals( $current_tenant, (string) $test['tenant'] ) &&
                    hash_equals( $current_client, (string) $test['client'] ) &&
                    hash_equals( $current_domain, (string) $test['domain'] ) &&
                    isset( $test['secret'] ) &&
                    hash_equals( telu_entra_secret_fingerprint(), (string) $test['secret'] ) &&
                    hash_equals( telu_entra_policy_fingerprint(), (string) $test['policy'] );

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
                TELU_ENTRA_DOMAIN_OPTION,
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
    $root   = trim( (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', '' ) );
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
    echo '<tr><th style="text-align:left;padding:8px"><label for="telu_entra_allowed_root_domain">Domain email organisasi</label></th><td style="padding:8px"><input type="text" id="telu_entra_allowed_root_domain" name="telu_entra_allowed_root_domain" value="' . telu_entra_escape( $root ) . '" style="width:420px" placeholder="example.org" required' . ( telu_entra_config_is_locked( 'ALLOWED_ROOT_DOMAIN' ) ? ' readonly' : '' ) . '><br><small>Domain utama tanpa <code>@</code>; seluruh subdomainnya otomatis diizinkan.</small></td></tr>';
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
    $last_test_current = is_array( $last_test ) && isset( $last_test['tenant'], $last_test['client'], $last_test['domain'], $last_test['secret'], $last_test['policy'] ) &&
        hash_equals( strtolower( $tenant ), (string) $last_test['tenant'] ) &&
        hash_equals( strtolower( $client ), (string) $last_test['client'] ) &&
        hash_equals( strtolower( $root ), (string) $last_test['domain'] ) &&
        hash_equals( telu_entra_secret_fingerprint(), (string) $last_test['secret'] ) &&
        hash_equals( telu_entra_policy_fingerprint(), (string) $last_test['policy'] );
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
        echo '<h3 style="margin-top:28px">Audit Login & Aktivitas Keamanan Terbaru</h3>';
        echo '<table class="tblSorter" style="max-width:900px;width:100%;border-collapse:collapse;margin-top:8px"><thead><tr><th style="padding:10px;text-align:left">Waktu</th><th style="padding:10px;text-align:left">Event</th><th style="padding:10px;text-align:left">Email Sivitas</th><th style="padding:10px;text-align:left">Keterangan / Detail</th></tr></thead><tbody>';
        foreach ( array_slice( $audit_entries, 0, 25 ) as $entry ) {
            $evt = isset( $entry['event'] ) ? (string) $entry['event'] : '-';
            $badge_bg = '#e2e8f0';
            $badge_fg = '#334155';
            if ( in_array( $evt, array( 'login_success', 'test_success', 'homepage_link_created' ), true ) ) {
                $badge_bg = '#dcfce7';
                $badge_fg = '#166534';
            } elseif ( in_array( $evt, array( 'login_denied_domain', 'test_failed', 'login_denied_group', 'login_denied_role' ), true ) ) {
                $badge_bg = '#fee2e2';
                $badge_fg = '#991b1b';
            } elseif ( in_array( $evt, array( 'logout' ), true ) ) {
                $badge_bg = '#fef3c7';
                $badge_fg = '#92400e';
            }
            $badge = '<span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;background:' . $badge_bg . ';color:' . $badge_fg . '">' . telu_entra_escape( $evt ) . '</span>';
            echo '<tr style="border-bottom:1px solid #f1f5f9"><td style="padding:8px 10px;font-size:12px;color:#64748b;white-space:nowrap">' . telu_entra_escape( isset( $entry['time'] ) ? date( 'Y-m-d H:i:s', (int) $entry['time'] ) : '-' ) . '</td><td style="padding:8px 10px">' . $badge . '</td><td style="padding:8px 10px;font-weight:600">' . telu_entra_escape( isset( $entry['email'] ) ? $entry['email'] : '-' ) . '</td><td style="padding:8px 10px;font-size:12px;color:#475569">' . telu_entra_escape( isset( $entry['detail'] ) ? $entry['detail'] : '-' ) . '</td></tr>';
        }
        echo '</tbody></table><p style="margin-top:8px"><small style="color:#64748b">Menampilkan 25 riwayat aktivitas terakhir dari database log terproteksi.</small></p>';
    }

    echo '<h3 style="margin-top:24px">Konfigurasi rahasia di user/config.php</h3>';
    echo '<pre style="padding:14px;background:#f5f5f5;overflow:auto">' . telu_entra_escape(
        "define( 'YOURLS_ENTRA_CLIENT_SECRET', 'ISI-LANGSUNG-DI-SERVER' );\n" .
        "define( 'YOURLS_ENTRA_ALLOW_LOCAL_RECOVERY', false );"
    ) . '</pre>';
}

function telu_entra_status_row( $label, $value ) {
    echo '<tr><th style="text-align:left;padding:8px;width:220px">' . telu_entra_escape( $label ) . '</th>' .
        '<td style="padding:8px">' . telu_entra_escape( $value ) . '</td></tr>';
}

function telu_entra_escape( $value ) {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function telu_entra_render_gateway_page( $submitted_email = '', $error_message = '', $return_to = null ) {
    while ( ob_get_level() > 0 ) {
        @ob_end_clean();
    }

    if ( ! headers_sent() ) {
        http_response_code( $error_message !== '' ? 403 : 200 );
        header( 'Content-Type: text/html; charset=UTF-8' );
        header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
        header( 'Pragma: no-cache' );
    }

    $action_url     = rtrim( (string) YOURLS_SITE, '/' ) . '/admin/';
    $direct_url     = $action_url . '?telu_sso_direct=1' . ( $return_to ? '&return_to=' . rawurlencode( $return_to ) : '' );
    $local_recovery = filter_var( telu_entra_config( 'ALLOW_LOCAL_RECOVERY', false ), FILTER_VALIDATE_BOOLEAN );
    $local_url      = $action_url . '?telu_local_login=1';
    $allowed_domain = (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', 'telkomuniversity.ac.id' );
    if ( $allowed_domain === '' ) {
        $allowed_domain = 'telkomuniversity.ac.id';
    }
    $logo_url  = function_exists( 'telu_yourls_theme_url' ) ? telu_yourls_theme_url( 'assets/telkom-university-logo.png' ) : ( function_exists( 'yourls_plugin_url' ) ? yourls_plugin_url( dirname( __FILE__ ) ) . '/assets/telkom-university-logo.png' : 'https://b856188.assetcdn.net/2.0/856188/wp-content/uploads/2022/02/logo3-e1511767184374.png?lossy=2&strip=1&webp=1&size=120x0' );
    $site_host = defined( 'YOURLS_SITE' ) ? parse_url( (string) YOURLS_SITE, PHP_URL_HOST ) : 's.telkomuniversity.ac.id';

    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>Layanan Short Link - Telkom University</title>';
    echo '<style>';
    echo '*{box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#0f172a;background:radial-gradient(circle at 50% 25%, #1e293b 0%, #0f172a 100%);margin:0;min-height:100vh;min-height:100dvh;display:flex;align-items:center;justify-content:center;padding:16px;color:#1e293b;line-height:1.5}';
    echo '.popup-card{max-width:440px;width:100%;background:#fff;border-radius:24px;box-shadow:0 25px 50px -12px rgba(0,0,0,.6);border:1px solid #334155;padding:32px 28px;text-align:center;position:relative;overflow:hidden}';
    echo '.accent-bar{position:absolute;top:0;left:0;right:0;height:6px;background:#b72025}';
    echo '.logo-wrap{margin-bottom:18px;display:flex;justify-content:center}';
    echo '.logo-img{height:54px;max-width:100%;object-fit:contain}';
    echo 'h1{font-size:21px;font-weight:700;color:#0f172a;margin:0 0 2px}';
    echo '.subtitle{font-size:12px;font-weight:700;color:#b72025;text-transform:uppercase;letter-spacing:.6px;margin-bottom:18px}';
    echo '.info-box{background:#fef2f2;border:1px solid #fee2e2;border-radius:14px;padding:14px 16px;text-align:left;font-size:12px;color:#475569;margin-bottom:24px;line-height:1.5}';
    echo '.info-title{display:flex;align-items:center;gap:6px;font-weight:700;color:#991b1b;margin-bottom:4px;font-size:12px}';
    echo '.info-sub{font-size:11px;color:#b91c1c;margin-top:6px;border-top:1px solid #fecaca;padding-top:6px}';
    echo '.btn-login{display:flex;align-items:center;justify-content:center;gap:10px;width:100%;padding:14px 20px;background:#b72025;color:#fff;font-size:14px;font-weight:600;border-radius:14px;text-decoration:none;box-shadow:0 6px 18px rgba(183,32,37,.25);transition:background .15s,transform .15s}';
    echo '.btn-login:hover{background:#93171b;transform:translateY(-1px);box-shadow:0 8px 22px rgba(183,32,37,.35)}';
    echo '.recovery-link{display:inline-block;margin-top:14px;font-size:12px;color:#94a3b8;text-decoration:none}';
    echo '.recovery-link:hover{color:#cbd5e1}';
    echo '.footer-copy{margin-top:20px;font-size:11px;color:#94a3b8}';
    echo '@media (max-width:480px){body{padding:12px}.popup-card{padding:24px 18px;border-radius:20px}.logo-img{height:46px}h1{font-size:19px}.info-box{padding:12px 14px;margin-bottom:18px}.btn-login{padding:13px 16px;font-size:13px}}';
    echo '</style></head><body>';
    echo '<div class="popup-card">';
    echo '<div class="accent-bar"></div>';
    echo '<div class="logo-wrap"><img class="logo-img" src="' . telu_entra_escape( $logo_url ) . '" alt="Telkom University"></div>';
    echo '<h1>Layanan Short Link</h1>';
    echo '<div class="subtitle">' . telu_entra_escape( $site_host ) . '</div>';
    echo '<div class="info-box">';
    echo '<div class="info-title"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>Khusus Civitas Academica</span></div>';
    echo '<p style="margin:0 0 4px">Layanan ini hanya dapat diakses menggunakan akun resmi Microsoft 365 Telkom University (<strong>@' . telu_entra_escape( $allowed_domain ) . '</strong> atau <strong>@student.' . telu_entra_escape( $allowed_domain ) . '</strong>).</p>';
    echo '<div class="info-sub">*Akun pribadi (seperti @outlook.com atau @gmail.com) otomatis ditolak oleh sistem.</div>';
    echo '</div>';
    echo '<a class="btn-login" href="' . telu_entra_escape( $direct_url ) . '">';
    echo '<svg width="18" height="18" viewBox="0 0 23 23"><path fill="#f35325" d="M1 1h10v10H1z"/><path fill="#81bc06" d="M12 1h10v10H12z"/><path fill="#05a6f0" d="M1 12h10v10H1z"/><path fill="#ffba08" d="M12 12h10v10H12z"/></svg>';
    echo '<span>Masuk dengan Microsoft 365</span>';
    echo '<svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>';
    echo '</a>';
    if ( $local_recovery ) {
        echo '<div><a class="recovery-link" href="' . telu_entra_escape( $local_url ) . '">Login Admin Lokal Darurat</a></div>';
    }
    echo '<div class="footer-copy">&copy; ' . date( 'Y' ) . ' Direktorat Pusat Teknologi Informasi &bull; Telkom University</div>';
    echo '</div></body></html>';
    exit;
}

function telu_entra_domain_error_page( $email ) {
    $root_domain = (string) telu_entra_config( 'ALLOWED_ROOT_DOMAIN', 'telkomuniversity.ac.id' );
    if ( $root_domain === '' ) {
        $root_domain = 'telkomuniversity.ac.id';
    }

    $email_safe = is_string( $email ) ? strtolower( trim( $email ) ) : '';
    telu_entra_audit( 'login_denied_domain', $email_safe, 'Domain email bukan civitas Telkom University.' );

    $title = 'Akses Khusus Telkom University';
    $message = "Layanan penyingkat tautan ini hanya dapat diakses menggunakan akun email resmi Telkom University (@" . $root_domain . ").";

    telu_entra_error_page(
        $message,
        403,
        true,
        $title,
        array(
            'type'           => 'invalid_domain',
            'email'          => $email_safe,
            'allowed_domain' => $root_domain,
        )
    );
}

function telu_entra_error_page( $message, $status, $allow_retry = true, $title = 'Login Microsoft gagal', $details = array() ) {
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

    $retry = rtrim( (string) YOURLS_SITE, '/' ) . '/admin/';
    $home = defined( 'YOURLS_SITE' ) ? rtrim( (string) YOURLS_SITE, '/' ) . '/' : '/';
    $local_recovery = filter_var( telu_entra_config( 'ALLOW_LOCAL_RECOVERY', false ), FILTER_VALIDATE_BOOLEAN );
    $local = rtrim( (string) YOURLS_SITE, '/' ) . '/admin/?telu_local_login=1';

    $is_invalid_domain = is_array( $details ) && isset( $details['type'] ) && $details['type'] === 'invalid_domain';
    $attempted_email   = $is_invalid_domain && isset( $details['email'] ) ? (string) $details['email'] : '';
    $allowed_domain    = $is_invalid_domain && isset( $details['allowed_domain'] ) ? (string) $details['allowed_domain'] : 'telkomuniversity.ac.id';

    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . telu_entra_escape( $title ) . ' - Telkom University</title>';
    echo '<style>';
    echo 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:#f4f6f8;margin:0;padding:24px;color:#1e293b;line-height:1.6}';
    echo '.box{max-width:620px;margin:6vh auto;background:#fff;padding:32px;border-radius:12px;box-shadow:0 10px 25px rgba(0,0,0,.06);border-top:5px solid #b72025}';
    echo 'h1{font-size:22px;margin:0 0 16px;color:#b72025}';
    echo '.alert-box{background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:14px 16px;border-radius:8px;margin-bottom:18px;font-size:14px}';
    echo '.alert-box strong{color:#7f1d1d}';
    echo '.account-info{background:#f8fafc;border:1px solid #e2e8f0;padding:12px 16px;border-radius:8px;margin:16px 0;font-size:14px}';
    echo '.account-info span{display:block;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.5px;margin-bottom:3px}';
    echo '.steps{margin:8px 0 16px;padding-left:20px;font-size:14px;color:#334155}';
    echo '.steps li{margin-bottom:8px}';
    echo '.actions{margin-top:24px;display:flex;flex-wrap:wrap;gap:10px}';
    echo 'a.btn{display:inline-block;padding:10px 18px;border-radius:6px;background:#b72025;color:#fff;text-decoration:none;font-weight:600;font-size:14px;transition:background .15s}';
    echo 'a.btn:hover{background:#93171b}';
    echo 'a.btn-secondary{background:#64748b}';
    echo 'a.btn-secondary:hover{background:#475569}';
    echo 'a.btn-outline{background:transparent;border:1px solid #cbd5e1;color:#475569}';
    echo 'a.btn-outline:hover{background:#f1f5f9;color:#1e293b}';
    echo '</style></head><body>';
    echo '<div class="box">';
    echo '<h1>' . telu_entra_escape( $title ) . '</h1>';

    if ( $is_invalid_domain ) {
        echo '<div class="alert-box">';
        echo '<strong>Akses Dibatasi:</strong> Layanan penyingkat tautan (Short Link) ini hanya diperuntukkan bagi civitas academica Telkom University.';
        echo '</div>';

        if ( $attempted_email !== '' ) {
            echo '<div class="account-info">';
            echo '<span>Akun yang terdeteksi:</span>';
            echo '<strong style="color:#b72025">' . telu_entra_escape( $attempted_email ) . '</strong>';
            echo '</div>';
        }

        echo '<p style="font-size:14px;color:#334155;margin:12px 0">';
        echo 'Hanya alamat email dengan domain <strong>@' . telu_entra_escape( $allowed_domain ) . '</strong> serta subdomain resminya (seperti <em>@student.' . telu_entra_escape( $allowed_domain ) . '</em>) yang dapat digunakan untuk masuk.';
        echo '</p>';

        echo '<div style="margin:16px 0;font-size:13px;color:#64748b">';
        echo '<strong style="color:#334155;display:block;margin-bottom:6px">Petunjuk untuk Masuk:</strong>';
        echo '<ol class="steps">';
        echo '<li>Jika browser Anda otomatis terhubung dengan akun Microsoft pribadi (seperti Outlook/Hotmail/Gmail) atau akun instansi lain, pastikan Anda <strong>keluar (sign out)</strong> terlebih dahulu dari akun tersebut, atau gunakan <strong>jendela penyamaran (Incognito / InPrivate)</strong>.</li>';
        echo '<li>Klik tombol di bawah ini lalu pilih atau masukkan akun email resmi Microsoft 365 / Office 365 Telkom University Anda.</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="actions">';
        echo '<a class="btn" href="' . telu_entra_escape( $retry ) . '">Ganti Akun &amp; Masuk dengan Email TelU</a>';
        echo '<a class="btn btn-outline" href="' . telu_entra_escape( $home ) . '">Kembali ke Beranda</a>';
        if ( $local_recovery ) {
            echo '<a class="btn btn-secondary" href="' . telu_entra_escape( $local ) . '">Login Admin Lokal</a>';
        }
        echo '</div>';
    } else {
        echo '<p>' . telu_entra_escape( $message ) . '</p>';
        echo '<div class="actions">';
        if ( $allow_retry ) {
            echo '<a class="btn" href="' . telu_entra_escape( $retry ) . '">Coba lagi</a>';
        }
        if ( $local_recovery ) {
            echo '<a class="btn btn-secondary" href="' . telu_entra_escape( $local ) . '">Login admin lokal darurat</a>';
        }
        echo '</div>';
    }

    echo '</div></body></html>';
    exit;
}

// =========================================================================
// Telkom University Theme & Presentation Functions
// =========================================================================

if ( ! function_exists( 'telu_yourls_theme_settings' ) ) {
    function telu_yourls_theme_settings() {
        $defaults = array(
            'service_name'      => 'Short Link',
            'organization_name' => 'Telkom University',
            'primary_color'     => '#b72025',
            'show_greeting'     => '1',
            'show_dashboard'    => '1',
            'replace_favicon'   => '1',
        );
        $stored = function_exists( 'yourls_get_option' ) ? yourls_get_option( TELU_YOURLS_THEME_OPTION ) : array();
        if ( is_string( $stored ) ) {
            $decoded = json_decode( $stored, true );
            $stored = is_array( $decoded ) ? $decoded : array();
        }
        $settings = array_merge( $defaults, is_array( $stored ) ? array_intersect_key( $stored, $defaults ) : array() );
        $settings['service_name'] = telu_yourls_theme_clean_label( $settings['service_name'], $defaults['service_name'] );
        $settings['organization_name'] = telu_yourls_theme_clean_label( $settings['organization_name'], $defaults['organization_name'] );
        if ( ! preg_match( '/^#[0-9a-f]{6}$/i', (string) $settings['primary_color'] ) ) {
            $settings['primary_color'] = $defaults['primary_color'];
        }
        $settings['primary_color'] = strtolower( $settings['primary_color'] );
        foreach ( array( 'show_greeting', 'show_dashboard', 'replace_favicon' ) as $flag ) {
            $settings[ $flag ] = $settings[ $flag ] === '1' ? '1' : '0';
        }
        return $settings;
    }
}

if ( ! function_exists( 'telu_yourls_theme_can_manage' ) ) {
    function telu_yourls_theme_can_manage() {
        if ( function_exists( 'telu_entra_current_user_is_administrator' ) ) {
            return telu_entra_current_user_is_administrator();
        }
        return defined( 'YOURLS_USER' ) && YOURLS_USER !== '';
    }
}

if ( ! function_exists( 'telu_yourls_theme_role_based_help_link' ) ) {
    function telu_yourls_theme_role_based_help_link( $help_link ) {
        if (
            function_exists( 'telu_entra_is_enabled' ) &&
            telu_entra_is_enabled() &&
            function_exists( 'telu_entra_current_user_is_administrator' ) &&
            ! telu_entra_current_user_is_administrator()
        ) {
            return '';
        }
        return $help_link;
    }
}

if ( ! function_exists( 'telu_yourls_theme_url' ) ) {
    function telu_yourls_theme_url( $path = '' ) {
        $base = function_exists( 'yourls_plugin_url' ) ? yourls_plugin_url( dirname( __FILE__ ) ) : '';
        return rtrim( $base, '/' ) . '/' . ltrim( (string) $path, '/' );
    }
}

if ( ! function_exists( 'telu_yourls_theme_assets' ) ) {
    function telu_yourls_theme_assets( $context = '' ) {
        $settings = telu_yourls_theme_settings();
        $stylesheet = telu_yourls_theme_url( 'assets/theme.css' );
        if ( $stylesheet === '' ) {
            return;
        }
        echo '<link rel="stylesheet" href="' . telu_yourls_theme_escape( $stylesheet ) . '?v=' . TELU_YOURLS_THEME_VERSION . '" type="text/css" media="screen" data-telu-theme-assets="1">' . "\n";
        echo '<style>:root{--telu-primary:' . telu_yourls_theme_escape( $settings['primary_color'] ) . ';--telu-primary-dark:' . telu_yourls_theme_escape( telu_yourls_theme_darken_color( $settings['primary_color'] ) ) . '}</style>' . "\n";
        if ( $settings['replace_favicon'] === '1' ) {
            echo telu_yourls_theme_favicon_tags();
        }
        echo '<meta name="theme-color" content="' . telu_yourls_theme_escape( $settings['primary_color'] ) . '">' . "\n";
        telu_yourls_theme_dom_script();
    }
}

if ( ! function_exists( 'telu_yourls_theme_favicon_tags' ) ) {
    function telu_yourls_theme_favicon_tags() {
        $favicon = telu_yourls_theme_escape( telu_yourls_theme_url( 'assets/favicon.png' ) ) . '?v=' . TELU_YOURLS_THEME_VERSION;
        return '<link rel="icon" type="image/png" sizes="32x32" href="' . $favicon . '" data-telu-favicon="1">' . "\n" .
            '<link rel="icon" type="image/png" sizes="192x192" href="' . $favicon . '" data-telu-favicon="1">' . "\n" .
            '<link rel="shortcut icon" type="image/png" href="' . $favicon . '" data-telu-favicon="1">' . "\n" .
            '<link rel="apple-touch-icon" sizes="512x512" href="' . $favicon . '" data-telu-favicon="1">' . "\n";
    }
}

if ( ! function_exists( 'telu_yourls_theme_header' ) ) {
    function telu_yourls_theme_header() {
        $settings = telu_yourls_theme_settings();
        $logo = telu_yourls_theme_url( 'assets/telkom-university-logo.png' );
        $home = defined( 'YOURLS_SITE' ) ? rtrim( YOURLS_SITE, '/' ) . '/' : '/';

        echo '<div class="telu-brand-header" role="presentation">';
        echo '<a class="telu-brand-link" href="' . telu_yourls_theme_escape( $home ) . '" aria-label="' . telu_yourls_theme_escape( $settings['organization_name'] . ' ' . $settings['service_name'] ) . '">';
        echo '<span class="telu-brand-logo-wrap"><img class="telu-brand-logo" src="' . telu_yourls_theme_escape( $logo ) . '" alt="' . telu_yourls_theme_escape( $settings['organization_name'] ) . '"></span>';
        echo '<span class="telu-brand-copy"><strong>' . telu_yourls_theme_escape( $settings['service_name'] ) . '</strong><small>' . telu_yourls_theme_escape( $settings['organization_name'] ) . '</small></span>';
        echo '</a></div>';
    }
}

if ( ! function_exists( 'telu_yourls_theme_body_class' ) ) {
    function telu_yourls_theme_body_class( $classes ) {
        $role_class = '';
        if (
            function_exists( 'telu_entra_is_enabled' ) &&
            telu_entra_is_enabled() &&
            function_exists( 'telu_entra_current_user_is_administrator' )
        ) {
            $role_class = telu_entra_current_user_is_administrator() ? ' telu-role-administrator' : ' telu-role-user';
        }
        return trim( (string) $classes . ' telu-professional-theme' . $role_class . ' ' );
    }
}

if ( ! function_exists( 'telu_yourls_theme_title' ) ) {
    function telu_yourls_theme_title( $title, $context = '' ) {
        $settings = telu_yourls_theme_settings();
        $labels = array(
            'index'   => 'Kelola Short Link',
            'login'   => 'Login',
            'plugins' => 'Plugin',
            'tools'   => 'Peralatan',
            'infos'   => 'Statistik Short Link',
        );
        $label = isset( $labels[ $context ] ) ? $labels[ $context ] : trim( strip_tags( (string) $title ) );
        return ( $label !== '' ? $label . ' — ' : '' ) . $settings['organization_name'] . ' ' . $settings['service_name'];
    }
}

if ( ! function_exists( 'telu_yourls_theme_footer' ) ) {
    function telu_yourls_theme_footer( $footer ) {
        $settings = telu_yourls_theme_settings();
        return '&copy; ' . date( 'Y' ) . ' Direktorat Pusat Teknologi Informasi &middot; ' . telu_yourls_theme_escape( $settings['organization_name'] );
    }
}

if ( ! function_exists( 'telu_yourls_theme_darken_color' ) ) {
    function telu_yourls_theme_darken_color( $color ) {
        if ( ! preg_match( '/^#[0-9a-f]{6}$/i', (string) $color ) ) {
            return '#8f171c';
        }
        $r = max( 0, hexdec( substr( $color, 1, 2 ) ) - 38 );
        $g = max( 0, hexdec( substr( $color, 3, 2 ) ) - 38 );
        $b = max( 0, hexdec( substr( $color, 5, 2 ) ) - 38 );
        return sprintf( '#%02x%02x%02x', $r, $g, $b );
    }
}

if ( ! function_exists( 'telu_yourls_theme_escape' ) ) {
    function telu_yourls_theme_escape( $value ) {
        if ( function_exists( 'yourls_esc_attr' ) ) {
            return yourls_esc_attr( (string) $value );
        }
        return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
    }
}

if ( ! function_exists( 'telu_yourls_theme_public_buffer' ) ) {
    function telu_yourls_theme_public_buffer( $request ) {
        static $started = false;
        if ( is_array( $request ) ) {
            $request = reset( $request );
        }
        if ( trim( (string) $request, '/' ) !== '' || $started ) {
            return;
        }
        $started = true;
        ob_start( 'telu_yourls_theme_inject_public_assets' );
    }
}

if ( ! function_exists( 'telu_yourls_theme_public_buffer_early' ) ) {
    function telu_yourls_theme_public_buffer_early() {
        if ( empty( $_SERVER['REQUEST_URI'] ) ) {
            return;
        }

        $request_path = parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH );
        $site_path = defined( 'YOURLS_SITE' ) ? parse_url( (string) YOURLS_SITE, PHP_URL_PATH ) : '/';
        $request_path = '/' . trim( (string) $request_path, '/' );
        $site_path = '/' . trim( (string) $site_path, '/' );

        $root_path = rtrim( $site_path, '/' );
        $allowed_paths = array( $root_path, $root_path . '/result.php' );
        if ( ! in_array( rtrim( $request_path, '/' ), $allowed_paths, true ) ) {
            return;
        }

        telu_yourls_theme_public_buffer( '' );
    }
}

if ( ! function_exists( 'telu_yourls_theme_inject_public_assets' ) ) {
    function telu_yourls_theme_inject_public_assets( $html ) {
        if ( stripos( (string) $html, '</head>' ) === false ) {
            return $html;
        }

        if ( stripos( (string) $html, 'data-telu-theme-assets="1"' ) !== false ) {
            return $html;
        }

        $settings = telu_yourls_theme_settings();
        $asset = '<link rel="stylesheet" href="' . telu_yourls_theme_escape( telu_yourls_theme_url( 'assets/theme.css' ) ) . '?v=' . TELU_YOURLS_THEME_VERSION . '" type="text/css" media="screen" data-telu-theme-assets="1">';
        $asset .= '<style>:root{--telu-primary:' . telu_yourls_theme_escape( $settings['primary_color'] ) . ';--telu-primary-dark:' . telu_yourls_theme_escape( telu_yourls_theme_darken_color( $settings['primary_color'] ) ) . '}</style>';
        if ( $settings['replace_favicon'] === '1' ) {
            $asset .= telu_yourls_theme_favicon_tags();
        }
        $asset .= '<meta name="theme-color" content="' . telu_yourls_theme_escape( $settings['primary_color'] ) . '">';
        $asset .= telu_yourls_theme_dom_script( false );

        $html = preg_replace_callback(
            '/<\/head>/i',
            function () use ( $asset ) {
                return $asset . '</head>';
            },
            (string) $html,
            1
        );

        $html = preg_replace_callback(
            '/<body(\s+[^>]*)?>/i',
            function ( $matches ) {
                $attrs = isset( $matches[1] ) ? $matches[1] : '';
                if ( preg_match( '/\bclass\s*=\s*["\']([^"\']*)["\']/i', $attrs, $class_match ) ) {
                    $existing = trim( $class_match[1] );
                    $to_add = array();
                    if ( strpos( $existing, 'telu-professional-theme' ) === false ) {
                        $to_add[] = 'telu-professional-theme';
                    }
                    if ( strpos( $existing, 'telu-public-interface' ) === false ) {
                        $to_add[] = 'telu-public-interface';
                    }
                    if ( ! empty( $to_add ) ) {
                        $new_class_val = trim( $existing . ' ' . implode( ' ', $to_add ) );
                        $new_attrs = preg_replace( '/\bclass\s*=\s*["\'][^"\']*["\']/i', 'class="' . $new_class_val . '"', $attrs );
                    } else {
                        $new_attrs = $attrs;
                    }
                } else {
                    $new_attrs = $attrs . ' class="telu-professional-theme telu-public-interface"';
                }
                return '<body' . $new_attrs . '>';
            },
            (string) $html,
            1
        );

        return $html;
    }
}

if ( ! function_exists( 'telu_yourls_theme_dom_script' ) ) {
    function telu_yourls_theme_dom_script( $echo = true ) {
        $settings = telu_yourls_theme_settings();
        $logo = telu_yourls_theme_url( 'assets/telkom-university-logo.png' );
        $site = defined( 'YOURLS_SITE' ) ? rtrim( YOURLS_SITE, '/' ) : '';
        $name = '';
        if (
            function_exists( 'telu_entra_is_enabled' ) &&
            telu_entra_is_enabled() &&
            function_exists( 'telu_entra_read_identity_cookie' )
        ) {
            $identity = telu_entra_read_identity_cookie();
            if ( is_array( $identity ) && ! empty( $identity['name'] ) ) {
                $name = trim( (string) $identity['name'] );
            }
        }
        if ( $name === '' && defined( 'YOURLS_USER' ) ) {
            $name = trim( (string) YOURLS_USER );
        }
        $role = '';
        if (
            function_exists( 'telu_entra_is_enabled' ) &&
            telu_entra_is_enabled() &&
            function_exists( 'telu_entra_current_user_is_administrator' )
        ) {
            $role = telu_entra_current_user_is_administrator() ? 'administrator' : 'user';
        }
        $data = json_encode( array(
            'logo'           => $logo,
            'favicon'        => telu_yourls_theme_url( 'assets/favicon.png' ) . '?v=' . TELU_YOURLS_THEME_VERSION,
            'service'        => $settings['service_name'],
            'org'            => $settings['organization_name'],
            'greeting'       => $settings['show_greeting'] === '1',
            'show_dashboard' => $settings['show_dashboard'] === '1',
            'role'           => $role,
            'name'           => $name,
            'home'           => $site . '/',
            'dashboard'      => $site . '/admin/',
            'logout'         => $site . '/?telu_logout=1',
        ), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );

        $script = '<script>(function(c){' .
            'function onReady(fn){if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",fn)}else{fn()}}' .
            'function isHttpUrl(u){return typeof u==="string"&&(u.indexOf("http://")===0||u.indexOf("https://")===0)}' .
            'function attachCopyAndQr(){' .
                'document.querySelectorAll(".input-with-copy > .telu-action-group, .form-item > .telu-action-group").forEach(function(el){el.remove()});' .
                'var shortInp=document.querySelector("#shorturl,input.shorturl,input[name=\\"shorturl\\"],#copylink");' .
                'var statsInp=document.querySelector("#stats,input.stats,input[name=stats]");' .
                'var halves=document.querySelector(".halves");' .
                'if(!halves&&shortInp&&statsInp&&!document.querySelector(".telu-result-grid")){' .
                    'var shortCol=shortInp.closest(".half-width,.form-item,p")||shortInp.parentElement;' .
                    'var statsCol=statsInp.closest(".half-width,.form-item,p")||statsInp.parentElement;' .
                    'if(shortCol&&statsCol&&shortCol!==statsCol&&shortCol.parentNode===statsCol.parentNode){' .
                        'var grid=document.createElement("div");' .
                        'grid.className="telu-result-grid";' .
                        'shortCol.parentNode.insertBefore(grid,shortCol);' .
                        'grid.appendChild(shortCol);' .
                        'grid.appendChild(statsCol);' .
                    '}' .
                '}' .
                'document.querySelectorAll("#shorturl,#stats,#origurl,#longurl,#copylink,input.shorturl").forEach(function(inp){' .
                    'if(!inp.hasAttribute("data-telu-autoselect")){' .
                        'inp.setAttribute("data-telu-autoselect","1");' .
                        'inp.addEventListener("click",function(){this.select()});' .
                    '}' .
                '});' .
                'var targets=document.querySelectorAll("#copylink,input.shorturl,input[name=\\"shorturl\\"],#shorturl,a.shorturl");' .
                'targets.forEach(function(target){' .
                    'var url=(target.value||target.getAttribute("value")||target.href||target.textContent||"").trim();' .
                    'if(!isHttpUrl(url))return;' .
                    'var container=target.closest(".content,.result,#output,.output,#copybox,.share,#shareboxes,.telu-public-form-card,#content,main,body");' .
                    'if(!container||container.querySelector(".telu-action-group"))return;' .
                    'var group=document.createElement("div");' .
                    'group.className="telu-action-group";' .
                    'group.innerHTML=\'<div class="telu-copy-row">' .
                        '<button type="button" class="telu-btn-copy" data-url="\'+encodeURI(url)+\'"><svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"/></svg><span>Salin Tautan</span></button>' .
                        '<a class="telu-btn-visit" href="\'+encodeURI(url)+\'" target="_blank" rel="noopener"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg><span>Buka Tautan</span></a>' .
                        '<button type="button" class="telu-btn-toggle-qr"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg><span>QR Code</span></button>' .
                    '</div>' .
                    '<div class="telu-qr-panel" style="display:none">' .
                        '<div class="telu-qr-card">' .
                            '<img class="telu-qr-image" src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&margin=8&data=\'+encodeURIComponent(url)+\'" alt="QR Code Telkom University" width="140" height="140">' .
                            '<div class="telu-qr-meta">' .
                                '<strong>QR Code Resmi Telkom University</strong>' .
                                '<p>Gunakan untuk materi poster, presentasi, atau media cetak.</p>' .
                                '<button type="button" class="telu-btn-download-qr" data-url="\'+encodeURI(url)+\'"><svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg><span>Unduh Gambar PNG</span></button>' .
                            '</div>' .
                        '</div>' .
                    '</div>\';' .
                    'var copyBtn=group.querySelector(".telu-btn-copy");' .
                    'if(copyBtn){copyBtn.addEventListener("click",function(){var u=copyBtn.getAttribute("data-url");var fb=function(){var s=copyBtn.querySelector("span");copyBtn.classList.add("telu-copied");if(s)s.textContent="✓ Tersalin ke Clipboard!";setTimeout(function(){copyBtn.classList.remove("telu-copied");if(s)s.textContent="Salin Tautan"},2000)};if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(u).then(fb).catch(function(){var inp=document.createElement("input");inp.value=u;document.body.appendChild(inp);inp.select();document.execCommand("copy");document.body.removeChild(inp);fb()})}else{var inp=document.createElement("input");inp.value=u;document.body.appendChild(inp);inp.select();document.execCommand("copy");document.body.removeChild(inp);fb()}})}' .
                    'var qrBtn=group.querySelector(".telu-btn-toggle-qr");' .
                    'var qrPanel=group.querySelector(".telu-qr-panel");' .
                    'if(qrBtn&&qrPanel){qrBtn.addEventListener("click",function(){var isH=qrPanel.style.display==="none";qrPanel.style.display=isH?"block":"none";qrBtn.classList.toggle("active",isH)})}' .
                    'var dlBtn=group.querySelector(".telu-btn-download-qr");' .
                    'if(dlBtn){dlBtn.addEventListener("click",function(e){e.preventDefault();var u=dlBtn.getAttribute("data-url");var btnSpan=dlBtn.querySelector("span");var oldText=btnSpan?btnSpan.textContent:"Unduh Gambar PNG";if(btnSpan)btnSpan.textContent="Mengunduh...";var qrImgUrl="https://api.qrserver.com/v1/create-qr-code/?size=600x600&margin=16&data="+encodeURIComponent(u);fetch(qrImgUrl).then(function(res){return res.blob()}).then(function(blob){var blobUrl=URL.createObjectURL(blob);var a=document.createElement("a");a.href=blobUrl;var segs=u.split("/").filter(Boolean);var slug=segs[segs.length-1]||"shortlink";a.download="qrcode-telkom-"+slug+".png";document.body.appendChild(a);a.click();document.body.removeChild(a);setTimeout(function(){URL.revokeObjectURL(blobUrl)},1000);if(btnSpan)btnSpan.textContent="✓ Terunduh!";setTimeout(function(){if(btnSpan)btnSpan.textContent=oldText},2000)}).catch(function(){window.open(qrImgUrl,"_blank");if(btnSpan)btnSpan.textContent=oldText})})}' .
                    'var anchor=document.querySelector(".halves,.telu-result-grid");' .
                    'if(anchor){if(anchor.nextSibling){anchor.parentNode.insertBefore(group,anchor.nextSibling)}else{anchor.parentNode.appendChild(group)}}' .
                    'else{var insertRef=target.closest(".input-with-copy,.form-item,p")||target;if(insertRef.nextSibling){insertRef.parentNode.insertBefore(group,insertRef.nextSibling)}else{insertRef.parentNode.appendChild(group)}}' .
                '});' .
                'window.teluAttachCopyAndQr=attachCopyAndQr;' .
            '}' .
            'function initTheme(){' .
                'var b=document.body,p=location.pathname.toLowerCase();' .
                'b.classList.add("telu-professional-theme");' .
                'var isInfos=b.id==="infos"||b.classList.contains("infos")||p.indexOf("+")!==-1||p.indexOf("yourls-infos.php")!==-1;' .
                'var isAdmin=p.indexOf("/admin")!==-1||b.id==="index"||b.id==="tools"||b.id==="plugins";' .
                'var hasBrandHeader=!!document.querySelector(".telu-brand-header");' .
                'if(isInfos)b.classList.add("telu-page-infos");' .
                'if(p.indexOf("/admin/index")!==-1||/\\/admin\\/?$/.test(p))b.classList.add("telu-page-index");' .
                'if(p.indexOf("/admin/tools")!==-1)b.classList.add("telu-page-tools");' .
                'if(p.indexOf("/admin/plugins")!==-1)b.classList.add("telu-page-plugins");' .
                'if(/\\/result\\.php$/.test(p))b.classList.add("telu-public-result");' .
                'var isPublic=!isAdmin&&!isInfos&&!hasBrandHeader&&(p==="/"||/^\\/(index\\.php)?$/.test(p)||/\\/result\\.php$/.test(p)||!!document.querySelector("#shorturl,#copylink,.halves"));' .
                'if(isPublic)b.classList.add("telu-public-interface");' .
                'else b.classList.remove("telu-public-interface");' .
                'if(isInfos||isAdmin||hasBrandHeader){document.querySelectorAll(".telu-public-brand").forEach(function(el){el.remove()})}' .
                'var au=document.querySelector("#add-url");if(au&&!au.getAttribute("placeholder"))au.setAttribute("placeholder","https://");' .
                'var ak=document.querySelector("#add-keyword");if(ak&&!ak.getAttribute("placeholder"))ak.setAttribute("placeholder","alias-kustom (opsional)");' .
                'var fk=document.querySelector("#filter_keyword");if(fk&&!fk.getAttribute("placeholder"))fk.setAttribute("placeholder","Kata kunci...");' .
                'if(c.role){b.classList.remove("telu-role-user","telu-role-administrator");b.classList.add("telu-role-"+c.role)}' .
                'document.querySelectorAll("img[src*=\\"yourls-logo\\"],img[alt=\\"YOURLS\\"],#yourls-logo,#logo img").forEach(function(img){var box=img.closest("h1,#logo");(box||img).classList.add("telu-hide-original-brand")});' .
                'var lg=document.querySelector("#admin_menu_logout_link a");if(lg){if(!lg.textContent.trim()||lg.textContent.trim().toLowerCase()==="logout")lg.textContent="Keluar";lg.setAttribute("title","Keluar dari akun");}' .
                'var ll=document.querySelector("#admin_menu_logout_link");if(ll){ll.childNodes.forEach(function(n){if(n.nodeType===Node.TEXT_NODE&&n.textContent){n.textContent=n.textContent.replace(/[()]/g," ").replace(/\\s{2,}/g," ")}})}' .
                'if(b.classList.contains("telu-page-index")){var mi=document.querySelector("#admin_menu_admin_link a");if(mi)mi.classList.add("active");}' .
                'else if(b.classList.contains("telu-page-plugins")){var mi=document.querySelector("#admin_menu_plugins_link a");if(mi)mi.classList.add("active");}' .
                'else if(b.classList.contains("telu-page-tools")){var mi=document.querySelector("#admin_menu_tools_link a");if(mi)mi.classList.add("active");}' .
                'if(b.classList.contains("telu-public-interface")&&!isInfos&&!isAdmin&&!document.querySelector(".telu-brand-header")){' .
                    'var legacy=document.querySelector("header,#header,.header");' .
                    'if(legacy&&/url shortener|yourls/i.test(legacy.textContent))legacy.classList.add("telu-hide-old-public-header");' .
                    'var host=document.querySelector("#container,.container,#wrap")||b;' .
                    'if(!document.querySelector(".telu-public-brand")){' .
                        'var brand=document.createElement("section");' .
                        'brand.className="telu-public-brand";' .
                        'brand.innerHTML="<a class=\\"telu-public-identity\\" href=\\""+c.home+"\\"><img src=\\""+c.logo+"\\" alt=\\"Telkom University\\"><span><strong>"+(c.service||"Short Link")+"</strong><small>"+(c.org||"Telkom University")+"</small></span></a><nav><span class=\\"telu-public-greeting\\">Halo, <b></b></span><a class=\\"telu-dashboard-link\\" href=\\""+c.dashboard+"\\">Dashboard Saya</a><a class=\\"telu-logout-link\\" href=\\""+c.logout+"\\" title=\\"Keluar dari akun\\"><svg width=\\"14\\" height=\\"14\\" fill=\\"none\\" stroke=\\"currentColor\\" viewBox=\\"0 0 24 24\\"><path stroke-linecap=\\"round\\" stroke-linejoin=\\"round\\" stroke-width=\\"2\\" d=\\"M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1\\"/></svg><span>Keluar</span></a></nav>";' .
                        'var bName=brand.querySelector("b");if(bName)bName.textContent=c.name||"Pengguna";' .
                        'var gr=brand.querySelector(".telu-public-greeting");if(gr&&!c.greeting)gr.hidden=true;' .
                        'var db=brand.querySelector(".telu-dashboard-link");if(db&&!c.show_dashboard)db.hidden=true;' .
                        'host.insertBefore(brand,host.firstChild);' .
                    '}' .
                    'var form=document.querySelector("form");' .
                    'if(form&&!form.closest(".telu-public-form-card")){' .
                        'var card=document.createElement("div");' .
                        'card.className="telu-public-form-card";' .
                        'form.parentNode.insertBefore(card,form);' .
                        'card.appendChild(form);' .
                    '}' .
                    'var foot=document.querySelector("footer,#footer,.footer");' .
                    'if(foot)foot.innerHTML="<p>&copy; "+new Date().getFullYear()+" Direktorat Pusat Teknologi Informasi &middot; "+(c.org||"Telkom University")+"</p>";' .
                    'document.querySelectorAll(".bookmarklet,#bookmarklet,.bookmarklets,#bookmarklets,a[href*=\\"javascript:(function()\\"],a[href*=\\"bookmarklet\\"]").forEach(function(el){var bx=el.closest(".card,.panel,.box,section,div:not(#container):not(#wrap):not(body)");if(bx&&!bx.querySelector("form")&&!bx.classList.contains("telu-public-brand")){bx.style.display="none";bx.remove()}else{el.style.display="none";el.remove()}});' .
                    'document.querySelectorAll("h1,h2,h3,h4,h5").forEach(function(h){if(/bookmarklet/i.test(h.textContent)){var bx=h.closest(".card,.panel,.box,section,div:not(#container):not(#wrap):not(body)");if(bx&&!bx.querySelector("form")&&!bx.classList.contains("telu-public-brand")){bx.style.display="none";bx.remove()}else{var nx=h.nextElementSibling;while(nx&&!/^H[1-6]$/.test(nx.tagName)&&!nx.querySelector("form")&&!nx.classList.contains("telu-public-brand")&&!nx.classList.contains("telu-public-form-card")){var rm=nx;nx=nx.nextElementSibling;rm.style.display="none";rm.remove()}h.style.display="none";h.remove()}}});' .
                '}' .
                'if(b.classList.contains("telu-public-result")){' .
                    'document.querySelectorAll("h1,h2,h3,h4,h5").forEach(function(h){if(/^qr\\s*code$/i.test(h.textContent.trim())&&!h.closest(".telu-qr-panel")){var nx=h.nextElementSibling;while(nx&&!/^H[1-6]$/.test(nx.tagName)&&!nx.querySelector("form")){var rm=nx;nx=nx.nextElementSibling;rm.remove()}h.remove()}});' .
                    'document.querySelectorAll("#qr_code,.qr_code,img[src*=\\".qr\\"],img[src*=\\"qr_code\\"]").forEach(function(img){if(!img.classList.contains("telu-qr-image")){var p=img.closest("p,div,section");if(p&&!p.closest(".telu-qr-panel")&&!p.querySelector("form,input")){p.remove()}else{img.remove()}}});' .
                    'var hs=document.querySelectorAll("h1,h2,h3");for(var i=0;i<hs.length;i++){if(hs[i].textContent.trim().toLowerCase()!=="share")continue;var n=hs[i].nextElementSibling;while(n&&!/^H[1-3]$/.test(n.tagName)){if(n.matches&&n.matches("a"))n.classList.add("telu-social-button");if(n.querySelectorAll)n.querySelectorAll("a").forEach(function(a){a.classList.add("telu-social-button")});n=n.nextElementSibling}break}' .
                '}' .
                'if(b.classList.contains("telu-role-user")){' .
                    'document.querySelectorAll("#admin_menu a").forEach(function(a){var text=a.textContent.trim().toLowerCase(),href=(a.getAttribute("href")||"").toLowerCase();if(/^(help|bantuan)$/.test(text)||/\\/readme\\.html(?:[?#]|$)/.test(href)){var item=a.closest("li,span")||a;item.style.display="none";(item||a).hidden=true;item.remove()}});' .
                    'document.querySelectorAll(".notice,div.notice,p").forEach(function(el){if(/YOURLS\\s+version.*(?:available|update)/i.test(el.textContent)||el.querySelector("a[href*=\\"yourls.org\\"]")){var bNotice=el.closest(".notice")||el;bNotice.style.display="none";bNotice.remove()}});' .
                '}' .
                'attachCopyAndQr();' .
                'var box=document.querySelector("#shareboxes");' .
                'function refreshShare(){' .
                    'var field=box?box.querySelector("#copylink,input[name=shorturl],input.shorturl"):null,' .
                    'val=field?(field.value||field.getAttribute("value")||"").trim():"";' .
                    'if(box)box.classList.toggle("telu-empty-shareboxes",!isHttpUrl(val));' .
                    'attachCopyAndQr();' .
                '}' .
                'refreshShare();' .
                'if(box){box.addEventListener("input",refreshShare);box.addEventListener("change",refreshShare);}' .
                'if(window.jQuery){window.jQuery(document).ajaxComplete(function(){window.setTimeout(refreshShare,0)});}' .
                'var obsRoot=box||document.querySelector("#wrap,#container")||document.body;' .
                'if(window.MutationObserver&&obsRoot){new MutationObserver(refreshShare).observe(obsRoot,{childList:true,subtree:true,attributes:true,characterData:true});}' .
            '}' .
            'onReady(initTheme);' .
        '})(' . $data . ');</script>';

        if ( $settings['replace_favicon'] === '1' ) {
            $script .= '<script>(function(){function rf(){document.querySelectorAll("link[rel~=icon]").forEach(function(i){if(!i.hasAttribute("data-telu-favicon"))i.remove()});var i=document.querySelector("link[data-telu-favicon]");if(!i){i=document.createElement("link");i.rel="icon";i.type="image/png";i.sizes="32x32";i.setAttribute("data-telu-favicon","1")}i.href=' . json_encode( telu_yourls_theme_url( 'assets/favicon.png' ) . '?v=' . TELU_YOURLS_THEME_VERSION, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';document.head.appendChild(i)};if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",rf)}else{rf()}})();</script>';
        }

        if ( $echo ) {
            echo $script . "\n";
        }
        return $script;
    }
}

if ( ! function_exists( 'telu_yourls_theme_clean_label' ) ) {
    function telu_yourls_theme_clean_label( $value, $fallback ) {
        $value = trim( strip_tags( (string) $value ) );
        $value = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );
        if ( $value === '' ) {
            return $fallback;
        }
        return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 80, 'UTF-8' ) : substr( $value, 0, 80 );
    }
}

if ( ! function_exists( 'telu_yourls_theme_settings_page' ) ) {
    function telu_yourls_theme_settings_page() {
        if ( ! telu_yourls_theme_can_manage() ) {
            if ( function_exists( 'yourls_add_notice' ) ) {
                yourls_add_notice( 'Access Denied' );
            }
            return;
        }

        $settings = telu_yourls_theme_settings();
        $saved = false;
        if ( isset( $_SERVER['REQUEST_METHOD'] ) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['telu_theme_save'] ) ) {
            $nonce = isset( $_POST['nonce'] ) ? (string) $_POST['nonce'] : '';
            yourls_verify_nonce( 'telu_yourls_theme_settings', $nonce );

            $color = isset( $_POST['primary_color'] ) ? strtolower( trim( (string) $_POST['primary_color'] ) ) : '#b72025';
            if ( ! preg_match( '/^#[0-9a-f]{6}$/', $color ) ) {
                $color = '#b72025';
            }
            $settings = array(
                'service_name'      => telu_yourls_theme_clean_label( isset( $_POST['service_name'] ) ? $_POST['service_name'] : '', 'Short Link' ),
                'organization_name' => telu_yourls_theme_clean_label( isset( $_POST['organization_name'] ) ? $_POST['organization_name'] : '', 'Telkom University' ),
                'primary_color'     => $color,
                'show_greeting'     => isset( $_POST['show_greeting'] ) ? '1' : '0',
                'show_dashboard'    => isset( $_POST['show_dashboard'] ) ? '1' : '0',
                'replace_favicon'   => isset( $_POST['replace_favicon'] ) ? '1' : '0',
            );
            yourls_update_option( TELU_YOURLS_THEME_OPTION, json_encode( $settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
            $saved = true;
        }

        echo '<div class="telu-theme-settings">';
        echo '<h2>Pengaturan Theme Telkom University</h2>';
        echo '<p>Pengaturan ini hanya mengubah tampilan. Autentikasi, role, ownership, database, dan redirect shortlink tetap ditangani YOURLS, Microsoft Entra SSO, dan AuthMgrPlus.</p>';
        if ( $saved ) {
            echo '<div class="notice"><p>Pengaturan theme berhasil disimpan. Muat ulang halaman atau lakukan hard refresh untuk melihat perubahan.</p></div>';
        }
        echo '<form method="post">';
        yourls_nonce_field( 'telu_yourls_theme_settings' );
        echo '<p><label for="telu-service-name"><strong>Nama layanan</strong></label><br><input id="telu-service-name" name="service_name" type="text" maxlength="80" value="' . telu_yourls_theme_escape( $settings['service_name'] ) . '" required></p>';
        echo '<p><label for="telu-organization-name"><strong>Nama organisasi</strong></label><br><input id="telu-organization-name" name="organization_name" type="text" maxlength="80" value="' . telu_yourls_theme_escape( $settings['organization_name'] ) . '" required></p>';
        echo '<p><label for="telu-primary-color"><strong>Warna utama</strong></label><br><input id="telu-primary-color" name="primary_color" type="color" value="' . telu_yourls_theme_escape( $settings['primary_color'] ) . '"></p>';
        echo '<p><label><input name="show_greeting" type="checkbox" value="1"' . ( $settings['show_greeting'] === '1' ? ' checked' : '' ) . '> Tampilkan sapaan nama pengguna pada homepage</label></p>';
        echo '<p><label><input name="show_dashboard" type="checkbox" value="1"' . ( $settings['show_dashboard'] === '1' ? ' checked' : '' ) . '> Tampilkan tombol Dashboard Saya pada homepage</label></p>';
        echo '<p><label><input name="replace_favicon" type="checkbox" value="1"' . ( $settings['replace_favicon'] === '1' ? ' checked' : '' ) . '> Gunakan favicon Telkom University menggantikan favicon YOURLS</label></p>';
        echo '<p><input class="button primary" type="submit" name="telu_theme_save" value="Simpan Pengaturan"></p>';
        echo '</form></div>';
    }
}
