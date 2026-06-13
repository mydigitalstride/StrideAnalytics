<?php
/**
 * Redirect management: DB table, interception, and admin UI.
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Redirects {

	public static function init(): void {
		add_action( 'template_redirect',              [ __CLASS__, 'maybe_redirect' ], 1 );
		add_action( 'admin_menu',                     [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_post_echs_add_redirect',    [ __CLASS__, 'handle_add' ] );
		add_action( 'admin_post_echs_delete_redirect', [ __CLASS__, 'handle_delete' ] );
		add_action( 'echs_increment_redirect_hit',     [ __CLASS__, 'increment_hit' ] );
	}

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'echs_redirects';
	}

	public static function create_table(): void {
		global $wpdb;
		$table      = self::get_table_name();
		$charset    = $wpdb->get_charset_collate();
		$sql        = "CREATE TABLE {$table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			source_url varchar(2048) NOT NULL DEFAULT '',
			target_url varchar(2048) NOT NULL DEFAULT '',
			redirect_type smallint NOT NULL DEFAULT 301,
			hit_count bigint unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY source_url (source_url(191))
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	private static function normalize( string $url ): string {
		return trailingslashit( strtolower( trim( $url ) ) );
	}

	private static function load_redirects(): array {
		$cached = get_transient( 'echs_redirects_cache' );
		if ( false !== $cached ) {
			return $cached;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			'SELECT id, source_url, target_url, redirect_type FROM ' . self::get_table_name(),
			ARRAY_A
		);
		$rows = $rows ?: [];
		set_transient( 'echs_redirects_cache', $rows, HOUR_IN_SECONDS );
		return $rows;
	}

	public static function maybe_redirect(): void {
		$request_uri  = $_SERVER['REQUEST_URI'] ?? '';
		$current_path = (string) parse_url( $request_uri, PHP_URL_PATH );
		if ( '' === $current_path ) {
			return;
		}

		$normalized   = self::normalize( $current_path );
		$no_slash     = rtrim( $normalized, '/' );
		$redirects    = self::load_redirects();

		foreach ( $redirects as $row ) {
			$source_norm = self::normalize( $row['source_url'] );
			if ( $normalized === $source_norm || $no_slash === $source_norm || $normalized === rtrim( $source_norm, '/' ) ) {
				wp_schedule_single_event( time(), 'echs_increment_redirect_hit', [ (int) $row['id'] ] );
				wp_redirect( $row['target_url'], (int) $row['redirect_type'] );
				exit;
			}
		}
	}

	public static function increment_hit( int $redirect_id ): void {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::get_table_name() . ' SET hit_count = hit_count + 1 WHERE id = %d',
				$redirect_id
			)
		);
	}

	public static function register_menu(): void {
		add_submenu_page(
			'echs-settings',
			'Redirect Manager',
			'Redirects',
			'manage_options',
			'echs-redirects',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$redirects = $wpdb->get_results(
			'SELECT * FROM ' . self::get_table_name() . ' ORDER BY id DESC',
			ARRAY_A
		) ?: [];

		$msg = sanitize_key( $_GET['echs_msg'] ?? '' );
		?>
		<div class="wrap">
			<h1>Redirect Manager</h1>

			<?php if ( 'added' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p>Redirect added.</p></div>
			<?php elseif ( 'deleted' === $msg ) : ?>
				<div class="notice notice-success is-dismissible"><p>Redirect deleted.</p></div>
			<?php elseif ( 'error' === $msg ) : ?>
				<div class="notice notice-error is-dismissible"><p>An error occurred. Please try again.</p></div>
			<?php endif; ?>

			<div class="echs-card" style="margin-bottom:20px;">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="echs_add_redirect">
					<?php wp_nonce_field( 'echs_add_redirect_action', 'echs_redirect_nonce' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="echs_source_url">Source URL</label></th>
							<td><input type="text" id="echs_source_url" name="echs_source_url" class="regular-text" placeholder="/old-page/" required value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['echs_prefill'] ?? '' ) ) ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="echs_target_url">Destination URL</label></th>
							<td><input type="text" id="echs_target_url" name="echs_target_url" class="regular-text" placeholder="https://… or /new-page/" required value="<?php echo esc_attr( sanitize_text_field( wp_unslash( $_GET['echs_prefill_target'] ?? '' ) ) ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><label for="echs_redirect_type">Type</label></th>
							<td>
								<select id="echs_redirect_type" name="echs_redirect_type">
									<option value="301">301 Permanent</option>
									<option value="302">302 Temporary</option>
									<option value="307">307 Temporary</option>
								</select>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Add Redirect', 'primary', 'submit', false ); ?>
				</form>
			</div>

			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th>Source</th>
						<th>Destination</th>
						<th>Type</th>
						<th>Hits</th>
						<th>Date Added</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $redirects ) ) : ?>
					<tr>
						<td colspan="6">
							<p>No redirects found.</p>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $redirects as $row ) :
						$delete_nonce = wp_create_nonce( 'echs_delete_redirect_' . $row['id'] );
						$delete_url   = admin_url(
							'admin-post.php?action=echs_delete_redirect&id=' . (int) $row['id'] . '&_wpnonce=' . $delete_nonce
						);
					?>
					<tr>
						<td><?php echo esc_html( $row['source_url'] ); ?></td>
						<td><?php echo esc_html( $row['target_url'] ); ?></td>
						<td><?php echo esc_html( $row['redirect_type'] ); ?></td>
						<td><?php echo esc_html( number_format( (int) $row['hit_count'] ) ); ?></td>
						<td><?php echo esc_html( $row['created_at'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $delete_url ); ?>"
							   class="button button-small"
							   onclick="return confirm('Delete this redirect?');">Delete</a>
						</td>
					</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function handle_add(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}
		check_admin_referer( 'echs_add_redirect_action', 'echs_redirect_nonce' );

		$source = sanitize_text_field( $_POST['echs_source_url'] ?? '' );
		$source = esc_url_raw( $source );
		$target = sanitize_text_field( $_POST['echs_target_url'] ?? '' );
		$target = esc_url_raw( $target );

		if ( '' === $source || '' === $target ) {
			wp_redirect( admin_url( 'admin.php?page=echs-redirects&echs_msg=error' ) );
			exit;
		}

		if ( ! str_starts_with( $source, '/' ) && ! preg_match( '#^https?://#i', $source ) ) {
			$source = '/' . $source;
		}

		$type_raw = (int) ( $_POST['echs_redirect_type'] ?? 301 );
		$type     = in_array( $type_raw, [ 301, 302, 307 ], true ) ? $type_raw : 301;

		global $wpdb;
		$inserted = $wpdb->insert(
			self::get_table_name(),
			[
				'source_url'    => $source,
				'target_url'    => $target,
				'redirect_type' => $type,
			],
			[ '%s', '%s', '%d' ]
		);

		delete_transient( 'echs_redirects_cache' );

		if ( false !== $inserted && class_exists( 'ECHS_404_Monitor' ) ) {
			ECHS_404_Monitor::dismiss_by_url( $source );
		}

		$msg = false !== $inserted ? 'added' : 'error';
		wp_redirect( admin_url( 'admin.php?page=echs-redirects&echs_msg=' . $msg ) );
		exit;
	}

	public static function handle_delete(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Unauthorized.' );
		}

		$id = (int) ( $_GET['id'] ?? 0 );
		if ( $id <= 0 ) {
			wp_redirect( admin_url( 'admin.php?page=echs-redirects&echs_msg=error' ) );
			exit;
		}

		check_admin_referer( 'echs_delete_redirect_' . $id );

		global $wpdb;
		$wpdb->delete( self::get_table_name(), [ 'id' => $id ], [ '%d' ] );

		delete_transient( 'echs_redirects_cache' );

		wp_redirect( admin_url( 'admin.php?page=echs-redirects&echs_msg=deleted' ) );
		exit;
	}
}
