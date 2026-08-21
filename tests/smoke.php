<?php

// Minimal YOURLS stubs so pure plugin helpers can be tested without an installation.
define( 'YOURLS_ABSPATH', __DIR__ );
define( 'YOURLS_SITE', 'https://go.example.edu' );
define( 'YOURLS_COOKIEKEY', 'test-cookie-key-that-is-longer-than-thirty-two-characters' );
define( 'YOURLS_PRIVATE', true );
define( 'YOURLS_USER', 'member@student.example.edu' );
define( 'YOURLS_ENTRA_TENANT_ID', '11111111-2222-3333-4444-555555555555' );
define( 'YOURLS_ENTRA_CLIENT_ID', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' );
define( 'YOURLS_ENTRA_CLIENT_SECRET', 'local-test-secret-value-only' );
define( 'TELU_ENTRA_ALLOWED_ROOT_DOMAIN', 'example.edu' );
define( 'YOURLS_ENTRA_ADMIN_EMAILS', array( 'sso-admin@example.edu' ) );
define( 'YOURLS_ENTRA_EDITOR_EMAILS', array( 'sso-editor@unit.example.edu' ) );

function yourls_add_filter() {}
function yourls_add_action() {}
function yourls_register_plugin_page() {}
function yourls_get_option( $name ) {
    return isset( $GLOBALS['test_options'][ $name ] ) ? $GLOBALS['test_options'][ $name ] : false;
}
function yourls_update_option( $name, $value ) {
    $GLOBALS['test_options'][ $name ] = $value;
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

$_SERVER['REQUEST_URI'] = '/result.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_REQUEST['url'] = 'https://example.com/long-link';
check( telu_entra_is_public_creation_request(), 'root result.php POST with URL should be recognized as public creation' );
$_SERVER['REQUEST_URI'] = '/public-keyword';
check( ! telu_entra_is_public_creation_request(), 'shortlink paths must never be treated as public creation' );
unset( $_REQUEST['url'], $_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD'] );

$amp_role_assignment = array( 'administrator' => array( 'admin' ) );
telu_entra_assign_authmgr_role( 'member@student.example.edu' );
telu_entra_assign_authmgr_role( 'sso-editor@unit.example.edu' );
telu_entra_assign_authmgr_role( 'sso-admin@example.edu' );
check( in_array( 'member@student.example.edu', $amp_role_assignment['contributor'], true ), 'default OIDC role should be contributor' );
check( in_array( 'sso-editor@unit.example.edu', $amp_role_assignment['editor'], true ), 'editor allowlist should work' );
check( in_array( 'sso-admin@example.edu', $amp_role_assignment['administrator'], true ), 'admin allowlist should work' );
check( ! telu_entra_current_user_is_administrator(), 'contributor must not be treated as administrator' );
$strict_where = telu_entra_strict_owner_list_where( array(
    'sql'   => ' AND (`user` = :user OR `user` IS NULL) ',
    'binds' => array( 'user' => YOURLS_USER ),
) );
check( strpos( $strict_where['sql'], '`user` IS NULL' ) === false, 'anonymous legacy URLs must be hidden' );
check( $strict_where['binds']['telu_entra_owner'] === YOURLS_USER, 'URL list must bind the signed-in owner' );
check( ! isset( $strict_where['binds']['user'] ), 'obsolete AuthMgrPlus owner bind must be removed' );

$random = random_bytes( 64 );
check( hash_equals( $random, telu_entra_base64url_decode( telu_entra_base64url_encode( $random ) ) ), 'base64url roundtrip' );
check( telu_entra_base64url_decode( 'bad!' ) === false, 'invalid base64url should fail' );
check( count( telu_entra_normalize_csv( 'one, two  three' ) ) === 3, 'CSV normalization should work' );
check( telu_entra_secret_fingerprint() !== '', 'secret fingerprint should be generated' );
check( telu_entra_claims_are_allowed( array() ), 'claims should pass when group and role restrictions are empty' );
$GLOBALS['test_options'][ TELU_ENTRA_GROUPS_OPTION ] = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
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

echo "All smoke tests passed.\n";
