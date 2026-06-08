<?php
/**
 * Google OAuth Relay Server
 *
 * Handles OAuth 2.0 authorization and token refresh on behalf of all
 * ECHoS plugin installations so individual site owners never need access
 * to the Google Cloud Console.
 *
 * Endpoints:
 *   GET  /authorize  — start the OAuth flow (called by the plugin)
 *   GET  /callback   — Google redirects here after consent
 *   POST /refresh    — exchange a refresh token for a new access token
 */

require_once __DIR__ . '/config.php';

header( 'X-Robots-Tag: noindex' );

$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$path = '/' . trim( str_replace( parse_url( RELAY_BASE_URL, PHP_URL_PATH ) ?: '', '', $path ), '/' );

switch ( $path ) {
    case '/authorize':
        handle_authorize();
        break;
    case '/callback':
        handle_callback();
        break;
    case '/refresh':
        handle_refresh();
        break;
    default:
        json_response( [ 'error' => 'not_found' ], 404 );
}

// ─── Authorize ───────────────────────────────────────────────────────

function handle_authorize(): void {
    $callback_url = filter_input( INPUT_GET, 'callback_url', FILTER_VALIDATE_URL );
    $state        = trim( $_GET['state'] ?? '' );
    $site_url     = filter_input( INPUT_GET, 'site_url', FILTER_VALIDATE_URL );

    if ( ! $callback_url || ! $state || ! $site_url ) {
        json_response( [ 'error' => 'missing_params', 'message' => 'callback_url, state, and site_url are required.' ], 400 );
    }

    session_start();
    $_SESSION['oauth_callback_url'] = $callback_url;
    $_SESSION['oauth_state']        = $state;
    $_SESSION['oauth_site_url']     = $site_url;

    $params = http_build_query( [
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => RELAY_BASE_URL . '/callback',
        'response_type' => 'code',
        'scope'         => GOOGLE_SCOPES,
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => session_id(),
    ] );

    header( 'Location: ' . GOOGLE_AUTH_URL . '?' . $params );
    exit;
}

// ─── Callback (Google → Relay → WordPress) ───────────────────────────

function handle_callback(): void {
    $session_id = $_GET['state'] ?? '';
    if ( ! $session_id ) {
        error_redirect( null, 'Missing session state.' );
    }

    session_id( $session_id );
    session_start();

    $callback_url = $_SESSION['oauth_callback_url'] ?? '';
    $orig_state   = $_SESSION['oauth_state']        ?? '';

    if ( ! $callback_url || ! $orig_state ) {
        error_redirect( null, 'Session expired or invalid.' );
    }

    if ( ! empty( $_GET['error'] ) ) {
        error_redirect( $callback_url, $_GET['error'] );
    }

    $code = $_GET['code'] ?? '';
    if ( ! $code ) {
        error_redirect( $callback_url, 'No authorization code received.' );
    }

    $token_data = exchange_code( $code );

    if ( isset( $token_data['error'] ) ) {
        error_redirect( $callback_url, $token_data['error_description'] ?? $token_data['error'] );
    }

    session_destroy();

    $params = http_build_query( [
        'state'         => $orig_state,
        'access_token'  => $token_data['access_token'],
        'refresh_token' => $token_data['refresh_token'] ?? '',
        'expires_in'    => $token_data['expires_in']    ?? 3600,
    ] );

    header( 'Location: ' . $callback_url . '&' . $params );
    exit;
}

// ─── Refresh ─────────────────────────────────────────────────────────

function handle_refresh(): void {
    if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
        json_response( [ 'error' => 'method_not_allowed' ], 405 );
    }

    $refresh_token = trim( $_POST['refresh_token'] ?? '' );

    if ( ! $refresh_token ) {
        json_response( [ 'error' => 'missing_refresh_token' ], 400 );
    }

    $ch = curl_init( GOOGLE_TOKEN_URL );
    curl_setopt_array( $ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query( [
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refresh_token,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
        ] ),
        CURLOPT_HTTPHEADER => [ 'Content-Type: application/x-www-form-urlencoded' ],
        CURLOPT_TIMEOUT    => 15,
    ] );

    $response = curl_exec( $ch );
    $http     = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    curl_close( $ch );

    if ( ! $response ) {
        json_response( [ 'error' => 'google_unreachable' ], 502 );
    }

    $data = json_decode( $response, true );

    json_response( $data, $http >= 400 ? $http : 200 );
}

// ─── Helpers ─────────────────────────────────────────────────────────

function exchange_code( string $code ): array {
    $ch = curl_init( GOOGLE_TOKEN_URL );
    curl_setopt_array( $ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => http_build_query( [
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => RELAY_BASE_URL . '/callback',
            'grant_type'    => 'authorization_code',
        ] ),
        CURLOPT_HTTPHEADER => [ 'Content-Type: application/x-www-form-urlencoded' ],
        CURLOPT_TIMEOUT    => 15,
    ] );

    $response = curl_exec( $ch );
    curl_close( $ch );

    if ( ! $response ) {
        return [ 'error' => 'google_unreachable' ];
    }

    return json_decode( $response, true ) ?: [ 'error' => 'invalid_json' ];
}

function error_redirect( ?string $callback_url, string $message ): never {
    if ( $callback_url ) {
        header( 'Location: ' . $callback_url . '&error=' . urlencode( $message ) );
    } else {
        header( 'Content-Type: text/plain', true, 400 );
        echo 'OAuth error: ' . $message;
    }
    exit;
}

function json_response( array $data, int $status = 200 ): never {
    http_response_code( $status );
    header( 'Content-Type: application/json' );
    header( 'Cache-Control: no-store' );
    echo json_encode( $data );
    exit;
}
