<?php
/**
 * Shared Google OAuth 2.0 authentication for Google Business Profile
 * and Google Search Console features.
 *
 * OAuth flows through a centralized relay server so site owners never
 * touch the Google Cloud Console.
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Google_Auth {

	const OPTION_TOKENS   = 'echs_google_tokens';
	const OPTION_LOCATION = 'echs_gbp_location_name';
	const SCOPES          = [
		'https://www.googleapis.com/auth/business.manage',
		'https://www.googleapis.com/auth/webmasters.readonly',
	];

	private static function get_relay_base_url(): string {
		if ( defined( 'ECHS_GOOGLE_RELAY_URL' ) ) {
			return untrailingslashit( ECHS_GOOGLE_RELAY_URL );
		}
		return untrailingslashit(
			apply_filters( 'echs_google_relay_url', 'https://relay.mydigitalstride.com/google' )
		);
	}

	public static function init(): void {
		add_action( 'admin_post_echs_google_connect',    [ __CLASS__, 'start_oauth' ] );
		add_action( 'admin_post_echs_google_callback',   [ __CLASS__, 'handle_callback' ] );
		add_action( 'admin_post_echs_google_disconnect', [ __CLASS__, 'disconnect' ] );
	}

	public static function get_redirect_uri(): string {
		return admin_url( 'admin-post.php?action=echs_google_callback' );
	}

	public static function is_connected(): bool {
		$tokens = get_option( self::OPTION_TOKENS );
		return ! empty( $tokens['access_token'] );
	}

	public static function start_oauth(): void {
		check_admin_referer( 'echs_google_connect' );

		$state = wp_create_nonce( 'echs_oauth_state' );

		$params = [
			'callback_url' => self::get_redirect_uri(),
			'state'        => $state,
			'site_url'     => home_url(),
		];

		wp_redirect( self::get_relay_base_url() . '/authorize?' . http_build_query( $params ) );
		exit;
	}

	public static function handle_callback(): void {
		$settings_url = admin_url( 'admin.php?page=echs-settings&tab=google' );

		if ( ! empty( $_GET['error'] ) || ! wp_verify_nonce( $_GET['state'] ?? '', 'echs_oauth_state' ) ) {
			wp_redirect( $settings_url . '&echs_msg=auth_error' );
			exit;
		}

		$access_token  = sanitize_text_field( $_GET['access_token'] ?? '' );
		$refresh_token = sanitize_text_field( $_GET['refresh_token'] ?? '' );
		$expires_in    = (int) ( $_GET['expires_in'] ?? 3600 );

		if ( empty( $access_token ) ) {
			wp_redirect( $settings_url . '&echs_msg=auth_error' );
			exit;
		}

		$tokens = [
			'access_token'  => $access_token,
			'refresh_token' => $refresh_token,
			'expires_at'    => time() + $expires_in,
		];

		update_option( self::OPTION_TOKENS, $tokens );

		wp_redirect( $settings_url . '&echs_msg=connected' );
		exit;
	}

	public static function disconnect(): void {
		check_admin_referer( 'echs_google_disconnect' );

		delete_option( self::OPTION_TOKENS );
		delete_option( self::OPTION_LOCATION );

		wp_redirect( admin_url( 'admin.php?page=echs-settings&tab=google&echs_msg=disconnected' ) );
		exit;
	}

	public static function get_access_token(): ?string {
		$tokens = get_option( self::OPTION_TOKENS );

		if ( empty( $tokens['access_token'] ) ) {
			return null;
		}

		if ( isset( $tokens['expires_at'] ) && $tokens['expires_at'] < time() + 300 ) {
			return self::refresh_token();
		}

		return $tokens['access_token'];
	}

	private static function refresh_token(): ?string {
		$tokens = get_option( self::OPTION_TOKENS );

		if ( empty( $tokens['refresh_token'] ) ) {
			return null;
		}

		$response = wp_remote_post( self::get_relay_base_url() . '/refresh', [
			'body' => [
				'refresh_token' => $tokens['refresh_token'],
				'site_url'      => home_url(),
			],
		] );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['access_token'] ) ) {
			return null;
		}

		$updated = [
			'access_token'  => $data['access_token'],
			'refresh_token' => $data['refresh_token'] ?? $tokens['refresh_token'],
			'expires_at'    => time() + ( (int) ( $data['expires_in'] ?? 3600 ) ),
		];

		update_option( self::OPTION_TOKENS, $updated );

		return $updated['access_token'];
	}

	public static function request( string $url, string $method = 'GET', ?array $body = null ): array|WP_Error {
		$token = self::get_access_token();

		if ( $token === null ) {
			return new WP_Error( 'no_token', 'Not connected to Google.' );
		}

		$args = [
			'method'  => $method,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
		];

		if ( $body !== null ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 401 ) {
			$new_token = self::refresh_token();

			if ( $new_token === null ) {
				return new WP_Error( 'token_refresh_failed', 'Google token refresh failed.' );
			}

			$args['headers']['Authorization'] = 'Bearer ' . $new_token;
			$response                         = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				return $response;
			}
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $decoded ) ) {
			return new WP_Error( 'invalid_response', 'Invalid JSON response from Google.' );
		}

		return $decoded;
	}

	public static function get_selected_location(): string {
		return get_option( self::OPTION_LOCATION, '' );
	}

	public static function render_settings_section(): void {
		$connected  = self::is_connected();
		$action_url = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="echs-card">
			<h2><?php esc_html_e( 'Google Connection', 'echs' ); ?></h2>

			<?php if ( $connected ) : ?>

				<div class="echs-connected-badge">&#10003; <?php esc_html_e( 'Connected to Google', 'echs' ); ?></div>
				<form method="post" action="<?php echo $action_url; ?>" style="margin-top:8px;">
					<input type="hidden" name="action" value="echs_google_disconnect">
					<?php wp_nonce_field( 'echs_google_disconnect' ); ?>
					<button type="submit" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Disconnect Google account?', 'echs' ); ?>')"><?php esc_html_e( 'Disconnect', 'echs' ); ?></button>
				</form>

			<?php else : ?>

				<p><?php esc_html_e( 'Connect your Google account to enable Google Business Profile management and Google Search Console data.', 'echs' ); ?></p>

				<form method="post" action="<?php echo $action_url; ?>">
					<input type="hidden" name="action" value="echs_google_connect">
					<?php wp_nonce_field( 'echs_google_connect' ); ?>
					<?php submit_button( __( 'Connect Google Account', 'echs' ), 'primary', 'submit', false ); ?>
				</form>

			<?php endif; ?>
		</div>
		<?php
	}
}
