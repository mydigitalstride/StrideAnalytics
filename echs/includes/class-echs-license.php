<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ECHS_License {

	const API_BASE    = 'https://mydigitalstride.com/echos-updates/api.php';
	const OPTION_KEY  = 'echs_license_key';
	const OPTION_DATA = 'echs_license_data';

	public static function init(): void {
		add_action( 'admin_post_echs_activate_license',   [ __CLASS__, 'handle_activate' ] );
		add_action( 'admin_post_echs_deactivate_license', [ __CLASS__, 'handle_deactivate' ] );
		add_action( 'admin_notices',                       [ __CLASS__, 'maybe_show_notice' ] );
	}

	public static function get_key(): string {
		return (string) get_option( self::OPTION_KEY, '' );
	}

	public static function get_data(): array {
		return (array) get_option( self::OPTION_DATA, [] );
	}

	public static function is_active(): bool {
		$data = self::get_data();
		return ( $data['status'] ?? '' ) === 'active';
	}

	public static function activate( string $key ): array {
		$url = add_query_arg(
			[
				'action'     => 'activate',
				'license'    => rawurlencode( $key ),
				'site'       => rawurlencode( home_url() ),
				'version'    => rawurlencode( ECHS_VERSION ),
				'wp_version' => rawurlencode( get_bloginfo( 'version' ) ),
			],
			self::API_BASE
		);

		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'message' => 'Could not reach the license server. Check your internet connection.',
			];
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $code ) {
			return [
				'success' => false,
				'message' => 'License server returned an error (HTTP ' . $code . ').',
			];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['activated'] ) ) {
			return [
				'success' => false,
				'message' => $body['error'] ?? 'Activation failed.',
			];
		}

		update_option( self::OPTION_KEY, sanitize_text_field( $key ) );

		update_option( self::OPTION_DATA, [
			'status'      => 'active',
			'client_name' => sanitize_text_field( $body['client_name'] ?? '' ),
			'expires_at'  => isset( $body['expires_at'] ) ? sanitize_text_field( $body['expires_at'] ) : null,
			'max_sites'   => (int) ( $body['max_sites'] ?? 1 ),
			'sites_used'  => (int) ( $body['sites_used'] ?? 1 ),
			'checked_at'  => time(),
		] );

		return [
			'success' => true,
			'message' => 'License activated successfully.',
		];
	}

	public static function deactivate(): void {
		$key = self::get_key();

		if ( $key ) {
			$url = add_query_arg(
				[
					'action'  => 'deactivate',
					'license' => rawurlencode( $key ),
					'site'    => rawurlencode( home_url() ),
				],
				self::API_BASE
			);

			wp_remote_get( $url, [ 'timeout' => 15 ] );
		}

		update_option( self::OPTION_DATA, [ 'status' => 'inactive' ] );
	}

	public static function refresh_status(): array {
		$key = self::get_key();

		if ( ! $key ) {
			return [ 'status' => 'inactive' ];
		}

		$url = add_query_arg(
			[
				'action'  => 'check',
				'license' => rawurlencode( $key ),
				'site'    => rawurlencode( home_url() ),
			],
			self::API_BASE
		);

		$response = wp_remote_get( $url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return self::get_data();
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) ) {
			return self::get_data();
		}

		$data = [
			'status'      => sanitize_text_field( $body['status'] ?? 'inactive' ),
			'client_name' => sanitize_text_field( $body['client_name'] ?? '' ),
			'expires_at'  => isset( $body['expires_at'] ) ? sanitize_text_field( $body['expires_at'] ) : null,
			'max_sites'   => (int) ( $body['max_sites'] ?? 1 ),
			'sites_used'  => (int) ( $body['sites_used'] ?? 1 ),
			'checked_at'  => time(),
		];

		update_option( self::OPTION_DATA, $data );

		return $data;
	}

	public static function handle_activate(): void {
		check_admin_referer( 'echs_license_activate' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'echs' ) );
		}

		$key    = sanitize_text_field( wp_unslash( $_POST['echs_license_key'] ?? '' ) );
		$result = self::activate( $key );

		if ( $result['success'] ) {
			wp_safe_redirect( admin_url( 'admin.php?page=echs-settings&echs_license_msg=activated' ) );
		} else {
			wp_safe_redirect( admin_url(
				'admin.php?page=echs-settings&echs_license_msg=error&echs_license_error=' . rawurlencode( $result['message'] )
			) );
		}

		exit;
	}

	public static function handle_deactivate(): void {
		check_admin_referer( 'echs_license_deactivate' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'echs' ) );
		}

		self::deactivate();

		wp_safe_redirect( admin_url( 'admin.php?page=echs-settings&echs_license_msg=deactivated' ) );
		exit;
	}

	public static function maybe_show_notice(): void {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $screen && str_contains( $screen->id, 'echs-settings' ) ) {
			return;
		}

		if ( self::is_active() ) {
			return;
		}

		?>
		<div class="notice notice-warning">
			<p>
				<strong>ECHoS SEO Analytics:</strong> No active license key.
				Updates and premium support require an active license.
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=echs-settings' ) ); ?>">Enter your license key &rarr;</a>
			</p>
		</div>
		<?php
	}

	public static function render_settings_section(): void {
		$msg   = isset( $_GET['echs_license_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['echs_license_msg'] ) ) : '';
		$error = isset( $_GET['echs_license_error'] ) ? sanitize_text_field( wp_unslash( $_GET['echs_license_error'] ) ) : '';

		if ( 'activated' === $msg ) {
			echo '<div class="notice notice-success inline"><p>' . esc_html__( 'License activated successfully.', 'echs' ) . '</p></div>';
		} elseif ( 'deactivated' === $msg ) {
			echo '<div class="notice notice-info inline"><p>' . esc_html__( 'License deactivated for this site.', 'echs' ) . '</p></div>';
		} elseif ( 'error' === $msg && $error ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $error ) . '</p></div>';
		}

		if ( ! self::is_active() ) {
			$saved_key = self::get_key();
			?>
			<div class="echs-card">
				<h2><?php esc_html_e( 'License Key', 'echs' ); ?></h2>
				<p><?php esc_html_e( 'Enter your ECHoS SEO Analytics license key to enable updates and premium support.', 'echs' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="echs_activate_license">
					<?php wp_nonce_field( 'echs_license_activate' ); ?>
					<table class="form-table">
						<tr>
							<th><label for="echs_license_key"><?php esc_html_e( 'License Key', 'echs' ); ?></label></th>
							<td>
								<input
									type="text"
									id="echs_license_key"
									name="echs_license_key"
									value="<?php echo esc_attr( $saved_key ); ?>"
									class="regular-text"
									placeholder="ECHS-XXXXXXXX-XXXXXXXX-XXXXXXXX"
								>
								<p class="description">
									<?php esc_html_e( 'Purchase a license at', 'echs' ); ?>
									<a href="https://mydigitalstride.com/echos-seo-analytics" target="_blank">mydigitalstride.com</a>
								</p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Activate License', 'echs' ), 'primary', 'submit', false ); ?>
				</form>
			</div>
			<?php
		} else {
			$data        = self::get_data();
			$key         = self::get_key();
			$client_name = esc_html( $data['client_name'] ?? '' );
			$sites_used  = (int) ( $data['sites_used'] ?? 1 );
			$max_sites   = (int) ( $data['max_sites'] ?? 1 );
			$expires_at  = ! empty( $data['expires_at'] ) ? esc_html( $data['expires_at'] ) : esc_html__( 'Never', 'echs' );
			$masked_key  = esc_html( substr( $key, 0, 5 ) . str_repeat( '•', max( 0, strlen( $key ) - 5 ) ) );
			?>
			<div class="echs-card">
				<h2><?php esc_html_e( 'License Key', 'echs' ); ?></h2>
				<div class="echs-connected-badge">&#10003; <?php echo esc_html__( 'License Active', 'echs' ) . ' &mdash; ' . $client_name; ?></div>
				<table class="form-table" style="margin-top:12px;">
					<tr>
						<th><?php esc_html_e( 'Key', 'echs' ); ?></th>
						<td><code><?php echo $masked_key; ?></code></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Sites', 'echs' ); ?></th>
						<td>
							<?php
							printf(
								esc_html__( '%1$d of %2$d site(s) activated', 'echs' ),
								$sites_used,
								$max_sites
							);
							?>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Expires', 'echs' ); ?></th>
						<td><?php echo $expires_at; ?></td>
					</tr>
				</table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:12px;">
					<input type="hidden" name="action" value="echs_deactivate_license">
					<?php wp_nonce_field( 'echs_license_deactivate' ); ?>
					<?php submit_button( __( 'Deactivate This Site', 'echs' ), 'secondary delete', 'submit', false ); ?>
				</form>
			</div>
			<?php
		}
	}
}
