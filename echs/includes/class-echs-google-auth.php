<?php
/**
 * Shared Google OAuth 2.0 authentication for Google Business Profile
 * and Google Search Console features.
 *
 * Supports two modes:
 *   1. Managed (default) — OAuth flows through a centralized relay server
 *      so site owners never touch the Google Cloud Console.
 *   2. Custom — site owner provides their own Client ID / Secret.
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Google_Auth {

	const OPTION_CREDS    = 'echs_google_credentials';
	const OPTION_TOKENS   = 'echs_google_tokens';
	const OPTION_LOCATION = 'echs_gbp_location_name';
	const OPTION_MODE     = 'echs_google_auth_mode';
	const AUTH_URL        = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL       = 'https://oauth2.googleapis.com/token';
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

	public static function is_managed_mode(): bool {
		return get_option( self::OPTION_MODE, 'managed' ) === 'managed';
	}

	public static function init(): void {
		add_action( 'admin_post_echs_google_connect',    [ __CLASS__, 'start_oauth' ] );
		add_action( 'admin_post_echs_google_callback',   [ __CLASS__, 'handle_callback' ] );
		add_action( 'admin_post_echs_google_disconnect', [ __CLASS__, 'disconnect' ] );
		add_action( 'admin_post_echs_save_google_creds', [ __CLASS__, 'save_credentials' ] );
		add_action( 'admin_post_echs_save_google_mode',  [ __CLASS__, 'save_auth_mode' ] );
	}

	public static function get_credentials(): array {
		return get_option( self::OPTION_CREDS, [ 'client_id' => '', 'client_secret' => '' ] );
	}

	public static function get_redirect_uri(): string {
		return admin_url( 'admin-post.php?action=echs_google_callback' );
	}

	public static function is_connected(): bool {
		$tokens = get_option( self::OPTION_TOKENS );
		return ! empty( $tokens['access_token'] );
	}

	public static function save_auth_mode(): void {
		check_admin_referer( 'echs_google_mode_nonce' );

		$mode = ( $_POST['echs_auth_mode'] ?? '' ) === 'custom' ? 'custom' : 'managed';
		update_option( self::OPTION_MODE, $mode );

		wp_redirect( admin_url( 'admin.php?page=echs-settings&tab=google&echs_msg=mode_saved' ) );
		exit;
	}

	public static function save_credentials(): void {
		check_admin_referer( 'echs_google_creds_nonce' );

		$creds = [
			'client_id'     => sanitize_text_field( $_POST['echs_client_id'] ?? '' ),
			'client_secret' => sanitize_text_field( $_POST['echs_client_secret'] ?? '' ),
		];

		update_option( self::OPTION_CREDS, $creds );

		wp_redirect( admin_url( 'admin.php?page=echs-settings&tab=google&echs_msg=creds_saved' ) );
		exit;
	}

	public static function start_oauth(): void {
		check_admin_referer( 'echs_google_connect' );

		if ( self::is_managed_mode() ) {
			self::start_managed_oauth();
		} else {
			self::start_custom_oauth();
		}
	}

	private static function start_managed_oauth(): void {
		$state = wp_create_nonce( 'echs_oauth_state' );

		$params = [
			'callback_url' => self::get_redirect_uri(),
			'state'        => $state,
			'site_url'     => home_url(),
		];

		wp_redirect( self::get_relay_base_url() . '/authorize?' . http_build_query( $params ) );
		exit;
	}

	private static function start_custom_oauth(): void {
		$creds  = self::get_credentials();
		$params = [
			'client_id'     => $creds['client_id'],
			'redirect_uri'  => self::get_redirect_uri(),
			'response_type' => 'code',
			'scope'         => implode( ' ', self::SCOPES ),
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => wp_create_nonce( 'echs_oauth_state' ),
		];

		wp_redirect( self::AUTH_URL . '?' . http_build_query( $params ) );
		exit;
	}

	public static function handle_callback(): void {
		$settings_url = admin_url( 'admin.php?page=echs-settings&tab=google' );

		if ( ! empty( $_GET['error'] ) || ! wp_verify_nonce( $_GET['state'] ?? '', 'echs_oauth_state' ) ) {
			wp_redirect( $settings_url . '&echs_msg=auth_error' );
			exit;
		}

		if ( self::is_managed_mode() ) {
			self::handle_managed_callback( $settings_url );
		} else {
			self::handle_custom_callback( $settings_url );
		}
	}

	private static function handle_managed_callback( string $settings_url ): void {
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

	private static function handle_custom_callback( string $settings_url ): void {
		$creds    = self::get_credentials();
		$response = wp_remote_post( self::TOKEN_URL, [
			'body' => [
				'code'          => sanitize_text_field( $_GET['code'] ?? '' ),
				'client_id'     => $creds['client_id'],
				'client_secret' => $creds['client_secret'],
				'redirect_uri'  => self::get_redirect_uri(),
				'grant_type'    => 'authorization_code',
			],
		] );

		if ( is_wp_error( $response ) ) {
			wp_redirect( $settings_url . '&echs_msg=auth_error' );
			exit;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['access_token'] ) ) {
			wp_redirect( $settings_url . '&echs_msg=auth_error' );
			exit;
		}

		$tokens = [
			'access_token'  => $data['access_token'],
			'refresh_token' => $data['refresh_token'] ?? '',
			'expires_at'    => time() + ( (int) ( $data['expires_in'] ?? 3600 ) ),
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

		if ( self::is_managed_mode() ) {
			return self::refresh_managed_token( $tokens );
		}

		return self::refresh_custom_token( $tokens );
	}

	private static function refresh_managed_token( array $tokens ): ?string {
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

	private static function refresh_custom_token( array $tokens ): ?string {
		$creds = self::get_credentials();

		$response = wp_remote_post( self::TOKEN_URL, [
			'body' => [
				'grant_type'    => 'refresh_token',
				'refresh_token' => $tokens['refresh_token'],
				'client_id'     => $creds['client_id'],
				'client_secret' => $creds['client_secret'],
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
		$connected    = self::is_connected();
		$managed      = self::is_managed_mode();
		$creds        = self::get_credentials();
		$redirect_uri = self::get_redirect_uri();
		$action_url   = esc_url( admin_url( 'admin-post.php' ) );
		?>
		<div class="echs-card">
			<h2><?php esc_html_e( 'Google Connection', 'echs' ); ?></h2>

			<?php if ( $connected ) : ?>

				<div class="echs-connected-badge">&#10003; <?php esc_html_e( 'Connected to Google', 'echs' ); ?></div>
				<?php if ( $managed ) : ?>
					<p class="description"><?php esc_html_e( 'Using managed connection via Stride Analytics.', 'echs' ); ?></p>
				<?php else : ?>
					<p class="description"><?php esc_html_e( 'Using custom OAuth credentials.', 'echs' ); ?></p>
				<?php endif; ?>
				<form method="post" action="<?php echo $action_url; ?>" style="margin-top:8px;">
					<input type="hidden" name="action" value="echs_google_disconnect">
					<?php wp_nonce_field( 'echs_google_disconnect' ); ?>
					<button type="submit" class="button button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Disconnect Google account?', 'echs' ); ?>')"><?php esc_html_e( 'Disconnect', 'echs' ); ?></button>
				</form>

			<?php elseif ( $managed ) : ?>

				<p><?php esc_html_e( 'Connect your Google account to enable Google Business Profile management and Google Search Console data. No setup required — just click the button below.', 'echs' ); ?></p>

				<form method="post" action="<?php echo $action_url; ?>">
					<input type="hidden" name="action" value="echs_google_connect">
					<?php wp_nonce_field( 'echs_google_connect' ); ?>
					<?php submit_button( __( 'Connect Google Account', 'echs' ), 'primary', 'submit', false ); ?>
				</form>

				<p style="margin-top:16px;">
					<a href="#echs-custom-creds" onclick="document.getElementById('echs-custom-creds-section').style.display='block';this.style.display='none';return false;">
						<?php esc_html_e( 'Advanced: Use your own Google Cloud credentials', 'echs' ); ?>
					</a>
				</p>

				<div id="echs-custom-creds-section" style="display:none;margin-top:12px;padding-top:12px;border-top:1px solid #ddd;">
					<p class="description"><?php esc_html_e( 'Switch to custom mode if you want full control over the OAuth application. You will need to create credentials in the Google Cloud Console.', 'echs' ); ?></p>
					<form method="post" action="<?php echo $action_url; ?>" style="margin-top:8px;">
						<input type="hidden" name="action" value="echs_save_google_mode">
						<input type="hidden" name="echs_auth_mode" value="custom">
						<?php wp_nonce_field( 'echs_google_mode_nonce' ); ?>
						<?php submit_button( __( 'Switch to Custom Credentials', 'echs' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>

			<?php else : ?>

				<p><?php esc_html_e( 'Connect your Google account to enable Google Business Profile management and Google Search Console data.', 'echs' ); ?></p>

				<form method="post" action="<?php echo $action_url; ?>">
					<input type="hidden" name="action" value="echs_save_google_creds">
					<?php wp_nonce_field( 'echs_google_creds_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'Client ID', 'echs' ); ?></th>
							<td>
								<input type="text" name="echs_client_id" value="<?php echo esc_attr( $creds['client_id'] ); ?>" class="large-text">
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Client Secret', 'echs' ); ?></th>
							<td>
								<input type="password" name="echs_client_secret" value="<?php echo esc_attr( $creds['client_secret'] ); ?>" class="regular-text">
							</td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Authorized Redirect URI', 'echs' ); ?></th>
							<td>
								<code><?php echo esc_html( $redirect_uri ); ?></code>
								<p class="description"><?php esc_html_e( 'Copy this URL into your Google Cloud Console → OAuth 2.0 Client → Authorized redirect URIs.', 'echs' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Save Credentials', 'echs' ), 'secondary', 'submit', false ); ?>
				</form>

				<?php if ( ! empty( $creds['client_id'] ) ) : ?>
					<form method="post" action="<?php echo $action_url; ?>" style="margin-top:12px;">
						<input type="hidden" name="action" value="echs_google_connect">
						<?php wp_nonce_field( 'echs_google_connect' ); ?>
						<?php submit_button( __( 'Connect Google Account', 'echs' ), 'primary', 'submit', false ); ?>
					</form>
				<?php endif; ?>

				<p style="margin-top:16px;">
					<a href="#" onclick="if(confirm('<?php esc_attr_e( 'Switch back to managed mode? Your custom credentials will be kept but not used.', 'echs' ); ?>')){document.getElementById('echs-switch-managed-form').submit();}return false;">
						<?php esc_html_e( 'Switch back to managed connection (no Cloud Console needed)', 'echs' ); ?>
					</a>
				</p>
				<form id="echs-switch-managed-form" method="post" action="<?php echo $action_url; ?>" style="display:none;">
					<input type="hidden" name="action" value="echs_save_google_mode">
					<input type="hidden" name="echs_auth_mode" value="managed">
					<?php wp_nonce_field( 'echs_google_mode_nonce' ); ?>
				</form>

			<?php endif; ?>
		</div>

		<?php if ( ! $connected && ! $managed ) : ?>
		<div class="echs-card">
			<h2><?php esc_html_e( 'Custom Credentials Setup', 'echs' ); ?></h2>
			<ol>
				<li><?php printf(
					wp_kses(
						__( 'Go to <a href="%s" target="_blank">Google Cloud Console</a> and create or select a project.', 'echs' ),
						[ 'a' => [ 'href' => [], 'target' => [] ] ]
					),
					'https://console.cloud.google.com/'
				); ?></li>
				<li><?php echo wp_kses(
					__( 'Enable the <strong>Google Business Profile API</strong> and <strong>Google Search Console API</strong>.', 'echs' ),
					[ 'strong' => [] ]
				); ?></li>
				<li><?php echo wp_kses(
					__( 'Go to <strong>APIs &amp; Services → Credentials</strong> → Create OAuth 2.0 Client ID (type: Web application).', 'echs' ),
					[ 'strong' => [] ]
				); ?></li>
				<li><?php esc_html_e( 'Add the Authorized Redirect URI shown above.', 'echs' ); ?></li>
				<li><?php esc_html_e( 'Copy the Client ID and Client Secret here and save.', 'echs' ); ?></li>
			</ol>
		</div>
		<?php endif; ?>
		<?php
	}
}
