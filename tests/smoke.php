<?php

// Minimal YOURLS stubs so pure plugin helpers can be tested without an installation.
define( 'YOURLS_ABSPATH', __DIR__ );
define( 'YOURLS_SITE', 'https://s.telkomuniversity.ac.id' );
define( 'YOURLS_COOKIEKEY', 'test-cookie-key-that-is-longer-than-thirty-two-characters' );
define( 'YOURLS_PRIVATE', true );
define( 'TELU_ENTRA_TENANT_ID', '11111111-2222-3333-4444-555555555555' );
define( 'TELU_ENTRA_CLIENT_ID', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee' );
define( 'TELU_ENTRA_CLIENT_SECRET', 'local-test-secret-value-only' );
define( 'TELU_ENTRA_ALLOWED_ROOT_DOMAIN', 'telkomuniversity.ac.id' );
define( 'TELU_ENTRA_ADMIN_EMAILS', array( 'sso-admin@telkomuniversity.ac.id' ) );
define( 'TELU_ENTRA_EDITOR_EMAILS', array( 'sso-editor@unit.telkomuniversity.ac.id' ) );

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

check( telu_entra_email_is_allowed( 'user@telkomuniversity.ac.id' ), 'root domain should pass' );
check( telu_entra_email_is_allowed( 'user@student.telkomuniversity.ac.id' ), 'subdomain should pass' );
check( telu_entra_email_is_allowed( 'user@deep.unit.telkomuniversity.ac.id' ), 'nested subdomain should pass' );
check( telu_entra_display_name_from_claims( array( 'name' => 'Budi Santoso' ), 'user@example.com' ) === 'Budi Santoso', 'display name claim should be used' );
check( telu_entra_display_name_from_claims( array(), 'user@example.com' ) === 'user@example.com', 'missing display name should fall back to email' );
check( ! telu_entra_email_is_allowed( 'user@eviltelkomuniversity.ac.id' ), 'look-alike domain should fail' );
check( ! telu_entra_email_is_allowed( 'user@telkomuniversity.ac.id.example.com' ), 'suffix attack should fail' );
check( ! telu_entra_email_is_allowed( 'not-an-email' ), 'invalid email should fail' );

$amp_role_assignment = array( 'administrator' => array( 'admin' ) );
telu_entra_assign_authmgr_role( 'member@student.telkomuniversity.ac.id' );
telu_entra_assign_authmgr_role( 'sso-editor@unit.telkomuniversity.ac.id' );
telu_entra_assign_authmgr_role( 'sso-admin@telkomuniversity.ac.id' );
check( in_array( 'member@student.telkomuniversity.ac.id', $amp_role_assignment['contributor'], true ), 'default OIDC role should be contributor' );
check( in_array( 'sso-editor@unit.telkomuniversity.ac.id', $amp_role_assignment['editor'], true ), 'editor allowlist should work' );
check( in_array( 'sso-admin@telkomuniversity.ac.id', $amp_role_assignment['administrator'], true ), 'admin allowlist should work' );

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
$payload = array( 'email' => 'user@telkomuniversity.ac.id', 'expires' => time() + 60 );
$json = json_encode( $payload, JSON_UNESCAPED_SLASHES );
$encoded = telu_entra_base64url_encode( $json );
$signature = telu_entra_base64url_encode( hash_hmac( 'sha256', $encoded, telu_entra_hmac_key(), true ) );
$_COOKIE[ TELU_ENTRA_AUTH_COOKIE ] = $encoded . '.' . $signature;
check( telu_entra_read_signed_cookie( TELU_ENTRA_AUTH_COOKIE )['email'] === $payload['email'], 'signed payload should pass' );
$_COOKIE[ TELU_ENTRA_AUTH_COOKIE ] = $encoded . '.' . substr( $signature, 0, -1 ) . ( substr( $signature, -1 ) === 'A' ? 'B' : 'A' );
check( telu_entra_read_signed_cookie( TELU_ENTRA_AUTH_COOKIE ) === null, 'tampered payload should fail' );

echo "All smoke tests passed.\n";
