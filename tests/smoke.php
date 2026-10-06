<?php

// Minimal YOURLS stubs so pure plugin helpers can be tested without an installation.
define( 'YOURLS_ABSPATH', __DIR__ );
define( 'YOURLS_SITE', 'https://go.example.edu' );
define( 'YOURLS_COOKIEKEY', 'test-cookie-key-that-is-longer-than-thirty-two-characters' );
define( 'YOURLS_PRIVATE', true );
define( 'YOURLS_USER', 'member@student.example.edu' );
define( 'YOURLS_DB_TABLE_URL', 'yourls_url' );
define( 'YOURLS_ENTRA_TENANT_ID', '11111111-2222-3333-4444-555555555555' );
define( 'YOURLS_ENTRA_CLIENT_ID', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' );
define( 'YOURLS_ENTRA_CLIENT_SECRET', 'local-test-secret-value-only' );
define( 'TELU_ENTRA_ALLOWED_ROOT_DOMAIN', 'example.edu' );
define( 'YOURLS_ENTRA_ADMIN_EMAILS', array( 'sso-admin@example.edu' ) );
define( 'YOURLS_ENTRA_EDITOR_EMAILS', array( 'sso-editor@unit.example.edu' ) );

function yourls_add_filter() {}
function yourls_add_action() {}
function yourls_register_plugin_page() {}
function yourls_shunt_default() { return '__yourls_shunt_default__'; }
function yourls_get_option( $name ) {
    return isset( $GLOBALS['test_options'][ $name ] ) ? $GLOBALS['test_options'][ $name ] : false;
}
function yourls_update_option( $name, $value ) {
    $GLOBALS['test_options'][ $name ] = $value;
}
function amp_keyword_owner( $keyword ) {
    return isset( $GLOBALS['test_owner'][ $keyword ] ) ? $GLOBALS['test_owner'][ $keyword ] : null;
}

class TeluEntraFakeDb {
    public function fetchAffected( $sql, $binds ) {
        if ( isset( $binds['telu_entra_keyword'], $binds['telu_entra_user'] ) ) {
            $GLOBALS['test_owner'][ $binds['telu_entra_keyword'] ] = $binds['telu_entra_user'];
        }
        return 1;
    }

    public function fetchObject( $sql, $binds = array() ) {
        if ( isset( $binds['telu_keyword'] ) ) {
            $kw = $binds['telu_keyword'];
            $owner = isset( $GLOBALS['test_owner'][ $kw ] ) ? $GLOBALS['test_owner'][ $kw ] : null;
            return (object) array( 'user' => $owner );
        }
        return (object) array( 'count' => 0, 'sum' => 0 );
    }
}

require dirname( __DIR__ ) . '/plugin.php';

function check( $condition, $message ) {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

telu_entra_migrate_legacy_domain();
check( $GLOBALS['test_options'][ TELU_ENTRA_DOMAIN_OPTION ] === 'example.edu', 'legacy domain should migrate to the database' );

check( telu_entra_email_is_allowed( 'user@example.edu' ), 'root domain should pass' );
check( telu_entra_email_is_allowed( 'user@student.example.edu' ), 'subdomain should pass' );
check( telu_entra_email_is_allowed( 'user@deep.unit.example.edu' ), 'nested subdomain should pass' );
check( telu_entra_display_name_from_claims( array( 'name' => 'Budi Santoso' ), 'user@example.com' ) === 'Budi Santoso', 'display name claim should be used' );
check( telu_entra_display_name_from_claims( array(), 'user@example.com' ) === 'user@example.com', 'missing display name should fall back to email' );
check( ! telu_entra_email_is_allowed( 'user@evilexample.edu' ), 'look-alike domain should fail' );
check( ! telu_entra_email_is_allowed( 'user@example.edu.example.com' ), 'suffix attack should fail' );
check( ! telu_entra_email_is_allowed( 'not-an-email' ), 'invalid email should fail' );
check( telu_entra_preserve_safe_custom_keyword( 'ampaign', 'Campaign-2026_A', true ) === 'Campaign-2026_A', 'custom keyword must preserve uppercase, hyphen and underscore' );
check( telu_entra_preserve_safe_custom_keyword( 'unchanged', 'Ignored-Value', false ) === 'unchanged', 'ordinary keyword sanitization must remain controlled by YOURLS' );
check( telu_entra_preserve_safe_custom_keyword( '', 'safe/path?query#fragment', true ) === 'safepathqueryfragment', 'reserved URL delimiters must be removed' );
$shunt = yourls_shunt_default();
check( telu_entra_validate_custom_keyword( $shunt, 'https://example.com', 'Campaign-2026_A', '' ) === $shunt, 'safe custom keyword must continue into YOURLS' );
$invalid_keyword = telu_entra_validate_custom_keyword( $shunt, 'https://example.com', 'Campaign 2026', '' );
check( is_array( $invalid_keyword ) && $invalid_keyword['code'] === 'error:keyword-format', 'unsupported custom keyword must fail instead of being truncated' );
$invalid_scheme = telu_entra_validate_custom_keyword( $shunt, 'javascript:alert(1)', 'test-js', '' );
check( is_array( $invalid_scheme ) && $invalid_scheme['code'] === 'error:invalid-scheme', 'insecure URL scheme must be rejected' );
$self_redirect = telu_entra_validate_custom_keyword( $shunt, 'https://go.example.edu/abc', 'test-loop', '' );
check( is_array( $self_redirect ) && $self_redirect['code'] === 'error:self-redirect', 'self-redirect loop must be rejected' );
$reserved_kw = telu_entra_validate_custom_keyword( $shunt, 'https://example.com', 'admin', '' );
check( is_array( $reserved_kw ) && $reserved_kw['code'] === 'error:keyword-reserved', 'system reserved keywords must be rejected' );
check( telu_entra_owner_identity_matches( ' Member@Student.Example.edu ', 'member@student.example.edu' ), 'owner comparison must tolerate legacy case and outer whitespace' );
check( ! telu_entra_owner_identity_matches( 'other@student.example.edu', 'member@student.example.edu' ), 'owner comparison must never match another identity' );
check( ! telu_entra_owner_identity_matches( '', 'member@student.example.edu' ), 'blank owners must remain unclaimed' );

$_SERVER['REQUEST_URI'] = '/result.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_REQUEST['url'] = 'https://example.com/long-link';
check( telu_entra_is_public_creation_request(), 'root result.php POST with URL should be recognized as public creation' );
$_SERVER['REQUEST_URI'] = '/public-keyword';
check( ! telu_entra_is_public_creation_request(), 'shortlink paths must never be treated as public creation' );
unset( $_REQUEST['url'], $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'] );

$amp_role_assignment = array( 'administrator' => array( 'admin' ) );
$GLOBALS['test_options'][ TELU_ENTRA_ENABLED_OPTION ] = '1';
telu_entra_assign_authmgr_role( 'member@student.example.edu' );
telu_entra_assign_authmgr_role( 'sso-editor@unit.example.edu' );
telu_entra_assign_authmgr_role( 'sso-admin@example.edu' );
check( in_array( 'member@student.example.edu', $amp_role_assignment['contributor'], true ), 'default OIDC role should be contributor' );
check( in_array( 'sso-editor@unit.example.edu', $amp_role_assignment['editor'], true ), 'editor allowlist should work' );
check( ! telu_entra_current_user_is_administrator(), 'contributor must not be treated as administrator' );

$dummy_checks = (object) array( 'last_result' => (object) array( 'latest' => '1.10.6' ) );
check( telu_entra_filter_core_version_checks( $dummy_checks ) === false, 'regular user must not receive core version update checks' );
check( telu_entra_shunt_maybe_check_core_version( '__default__' ) === false, 'regular user must not trigger core version check' );

$amp_role_assignment['administrator'][] = 'member@student.example.edu';
check( telu_entra_filter_core_version_checks( $dummy_checks ) === $dummy_checks, 'super admin must receive core version update checks' );
check( telu_entra_shunt_maybe_check_core_version( '__default__' ) === '__default__', 'super admin must allow core version check' );
array_pop( $amp_role_assignment['administrator'] );
$strict_where = telu_entra_strict_owner_list_where( array(
    'sql'   => ' AND (`user` = :user OR `user` IS NULL) ',
    'binds' => array( 'user' => YOURLS_USER ),
) );
check( strpos( $strict_where['sql'], '`user` IS NULL' ) === false, 'anonymous legacy URLs must be hidden' );
check( $strict_where['binds']['telu_entra_owner'] === YOURLS_USER, 'URL list must bind the signed-in owner' );
check( ! isset( $strict_where['binds']['user'] ), 'obsolete AuthMgrPlus owner bind must be removed' );

$GLOBALS['test_options'][ TELU_ENTRA_ENABLED_OPTION ] = '0';
$disabled_where = array( 'sql' => 'original', 'binds' => array( 'user' => YOURLS_USER ) );
check( telu_entra_strict_owner_list_where( $disabled_where ) === $disabled_where, 'disabled SSO must not alter AuthMgrPlus owner filtering' );
$GLOBALS['test_options'][ TELU_ENTRA_ENABLED_OPTION ] = '1';

$GLOBALS['telu_entra_public_creation_email'] = 'member@student.example.edu';
$GLOBALS['test_owner']['homepage-test'] = null;
$ydb = new TeluEntraFakeDb();
telu_entra_verify_public_creation_owner( array( true, 'https://example.com', 'homepage-test' ) );
check( $GLOBALS['test_owner']['homepage-test'] === 'member@student.example.edu', 'homepage insert must be repaired to the verified Entra owner' );
$latest_audit = json_decode( $GLOBALS['test_options'][ TELU_ENTRA_AUDIT_OPTION ], true );
check( $latest_audit[0]['event'] === 'homepage_owner_repaired', 'homepage owner repair must be audited' );

// Regular user stats access check
check( telu_entra_current_user_owns_keyword( 'homepage-test' ) === true, 'regular user should own their created link' );
check( telu_entra_current_user_owns_keyword( 'homepage-test+' ) === true, 'plus sign in stats url should be stripped and allowed' );
$GLOBALS['test_owner']['someone-else-link'] = 'other@student.example.edu';
check( telu_entra_current_user_owns_keyword( 'someone-else-link' ) === false, 'regular user must not own other users links' );
$amp_role_assignment['administrator'][] = 'member@student.example.edu';
check( telu_entra_current_user_owns_keyword( 'someone-else-link' ) === true, 'admin user should be allowed to view stats for any link' );
array_pop( $amp_role_assignment['administrator'] );
check( telu_entra_current_user_owns_keyword( 'someone-else-link' ) === false, 'revoked admin role should revert to normal ownership checks' );

$random = random_bytes( 64 );
check( hash_equals( $random, telu_entra_base64url_decode( telu_entra_base64url_encode( $random ) ) ), 'base64url roundtrip' );
check( telu_entra_base64url_decode( 'bad!' ) === false, 'invalid base64url should fail' );
check( count( telu_entra_normalize_csv( 'one, two  three' ) ) === 3, 'CSV normalization should work' );
check( telu_entra_secret_fingerprint() !== '', 'secret fingerprint should be generated' );
$policy_before = telu_entra_policy_fingerprint();
check( telu_entra_claims_are_allowed( array() ), 'claims should pass when group and role restrictions are empty' );
$GLOBALS['test_options'][ TELU_ENTRA_GROUPS_OPTION ] = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
check( ! hash_equals( $policy_before, telu_entra_policy_fingerprint() ), 'authorization policy changes must invalidate existing sessions' );
check( telu_entra_claims_are_allowed( array( 'groups' => array( 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' ) ) ), 'allowed group should pass' );
check( ! telu_entra_claims_are_allowed( array( 'groups' => array( 'ffffffff-1111-2222-3333-444444444444' ) ) ), 'unlisted group should fail' );
$GLOBALS['test_options'][ TELU_ENTRA_GROUPS_OPTION ] = '';
$GLOBALS['test_options'][ TELU_ENTRA_ROLES_OPTION ] = 'Shortlink.Creator';
check( telu_entra_claims_are_allowed( array( 'roles' => array( 'Shortlink.Creator' ) ) ), 'allowed app role should pass' );
check( ! telu_entra_claims_are_allowed( array( 'roles' => array( 'Other.Role' ) ) ), 'unlisted app role should fail' );
$GLOBALS['test_options'][ TELU_ENTRA_ROLES_OPTION ] = '';
check( telu_entra_safe_return_path( '/admin/index.php?page=2' ) === '/admin/index.php?page=2', 'admin return path should pass' );
check( telu_entra_safe_return_path( '/' ) === '/', 'homepage return path should pass' );
check( telu_entra_safe_return_path( '/abc123' ) === '/admin/', 'shortlink must not be accepted as a post-login return path' );
check( telu_entra_safe_return_path( '//evil.example/admin/' ) === '/admin/', 'protocol-relative return path should fail' );
check( telu_entra_safe_return_path( 'https://evil.example/admin/' ) === '/admin/', 'absolute return URL should fail' );

$_SERVER['REQUEST_URI'] = '/';
check( telu_entra_is_root_homepage_request(), 'slash should be detected as root homepage' );
$_SERVER['REQUEST_URI'] = '/index.php';
check( telu_entra_is_root_homepage_request(), 'index.php should be detected as root homepage' );
$_SERVER['REQUEST_URI'] = '/admin/';
check( ! telu_entra_is_root_homepage_request(), 'admin should not be detected as root homepage' );
$_SERVER['REQUEST_URI'] = '/abc123';
check( ! telu_entra_is_root_homepage_request(), 'shortlink keyword should not be detected as root homepage' );
$_SERVER['REQUEST_URI'] = '/result.php';
check( ! telu_entra_is_root_homepage_request(), 'result.php should not be detected as root homepage' );

// Prove the JWK-to-PEM converter by signing and verifying with a generated RSA key.
$private = openssl_pkey_new( array( 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ) );
check( $private !== false, 'RSA test key generation' );
$details = openssl_pkey_get_details( $private );
$jwk = array(
    'kty' => 'RSA',
    'n'   => telu_entra_base64url_encode( $details['rsa']['n'] ),
    'e'   => telu_entra_base64url_encode( $details['rsa']['e'] ),
);
$pem = telu_entra_jwk_to_pem( $jwk );
$message = 'telu-entra-sso-test';
openssl_sign( $message, $signature, $private, OPENSSL_ALGO_SHA256 );
check( openssl_verify( $message, $signature, $pem, OPENSSL_ALGO_SHA256 ) === 1, 'JWK PEM signature verification' );

// Signed cookie payload format must reject tampering.
$payload = array( 'email' => 'user@example.edu', 'expires' => time() + 60 );
$json = json_encode( $payload, JSON_UNESCAPED_SLASHES );
$encoded = telu_entra_base64url_encode( $json );
$signature = telu_entra_base64url_encode( hash_hmac( 'sha256', $encoded, telu_entra_hmac_key(), true ) );
$_COOKIE[ TELU_ENTRA_AUTH_COOKIE ] = $encoded . '.' . $signature;
check( telu_entra_read_signed_cookie( TELU_ENTRA_AUTH_COOKIE )['email'] === $payload['email'], 'signed payload should pass' );
$_COOKIE[ TELU_ENTRA_AUTH_COOKIE ] = $encoded . '.' . substr( $signature, 0, -1 ) . ( substr( $signature, -1 ) === 'A' ? 'B' : 'A' );
check( telu_entra_read_signed_cookie( TELU_ENTRA_AUTH_COOKIE ) === null, 'tampered payload should fail' );

// Theme DOM script & CSS assertions
$theme_script = telu_yourls_theme_dom_script( false );
check( strpos( $theme_script, 'bookmarklet' ) !== false, 'theme script must include bookmarklet removal logic' );
check( strpos( $theme_script, 'Direktorat Pusat Teknologi Informasi' ) !== false, 'theme script must include official directorate name' );
check( strpos( $theme_script, 'attachCopyAndQr' ) !== false, 'theme script must include copy and QR generator' );
check( strpos( $theme_script, 'telu_logout' ) !== false, 'theme script must include public logout link' );
check( strpos( $theme_script, 'isInfos' ) !== false, 'theme script must detect stats page to prevent double header' );

$theme_css = file_get_contents( dirname( __DIR__ ) . '/assets/theme.css' );
check( strpos( $theme_css, '.bookmarklet' ) !== false, 'theme css must hide bookmarklet elements' );
check( strpos( $theme_css, 'min-width: 680px' ) !== false, 'theme css must ensure admin table responsiveness' );
check( strpos( $theme_css, 'telu-role-user .notice:has(a[href*="yourls.org"])' ) !== false, 'theme css must hide core update notice for regular users' );
check( strpos( $theme_css, '.telu-action-group' ) !== false, 'theme css must include action group styles' );
check( strpos( $theme_css, '.telu-logout-link' ) !== false, 'theme css must include navbar logout styles' );
check( strpos( $theme_css, 'body#infos .telu-public-brand' ) !== false, 'theme css must hide public brand header on stats page' );

echo "All smoke tests passed.\n";


