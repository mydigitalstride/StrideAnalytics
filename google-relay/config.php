<?php
/**
 * Google OAuth Relay — configuration.
 *
 * Copy this file to the server and fill in real values.
 * NEVER commit actual secrets to the repository.
 */

// Google OAuth 2.0 credentials (from Google Cloud Console).
define( 'GOOGLE_CLIENT_ID',     getenv( 'GOOGLE_CLIENT_ID' )     ?: 'YOUR_CLIENT_ID' );
define( 'GOOGLE_CLIENT_SECRET', getenv( 'GOOGLE_CLIENT_SECRET' ) ?: 'YOUR_CLIENT_SECRET' );

// The public URL where this relay is hosted (no trailing slash).
define( 'RELAY_BASE_URL', getenv( 'RELAY_BASE_URL' ) ?: 'https://relay.mydigitalstride.com/google' );

// Google endpoints.
define( 'GOOGLE_AUTH_URL',  'https://accounts.google.com/o/oauth2/v2/auth' );
define( 'GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token' );

// Scopes the plugin requires.
define( 'GOOGLE_SCOPES', implode( ' ', [
    'https://www.googleapis.com/auth/business.manage',
    'https://www.googleapis.com/auth/webmasters.readonly',
] ) );

// Rate-limit: max requests per IP per minute.
define( 'RATE_LIMIT', 30 );
