<?php
/**
 * 404 error monitoring: DB logging, email notification, and admin UI.
 *
 * @package HomeRite_Schema_Manager
 */

defined( 'ABSPATH' ) || exit;

class HSM_404_Monitor {

	public static function init(): void {
		add_action( 'template_redirect', [ __CLASS__, 'log_404' ], 2 );
		add_action( 'admin_menu',        [ __CLASS__, 'register_menu' ] );
	}

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'hsm_404_log';
	}

	public static function create_table(): void {
		global $wpdb;
		$table   = self::get_table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			url varchar(2048) NOT NULL,
			referrer varchar(2048) NOT NULL DEFAULT '',
			hit_count bigint unsigned NOT NULL DEFAULT 1,
			notified tinyint(1) NOT NULL DEFAULT 0,
			dismissed tinyint(1) NOT NULL DEFAULT 0,
			first_seen datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_seen datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY url (url(191))
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function log_404(): void {
		if ( ! is_404() ) {
			return;
		}

		global $wpdb;

		$raw_url = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$url     = substr( esc_url_raw( sanitize_text_field( $raw_url ) ), 0, 2048 );

		$raw_referrer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
		$referrer     = $raw_referrer !== '' ? substr( esc_url_raw( sanitize_text_field( $raw_referrer ) ), 0, 2048 ) : '';

		$table    = self::get_table_name();
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE url = %s LIMIT 1",
				$url
			)
		);

		if ( $existing ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET hit_count = hit_count + 1, last_seen = NOW() WHERE id = %d",
					(int) $existing->id
				)
			);
		} else {
			$wpdb->insert(
				$table,
				[
					'url'       => $url,
					'referrer'  => $referrer,
					'hit_count' => 1,
					'notified'  => 0,
					'dismissed' => 0,
				],
				[ '%s', '%s', '%d', '%d', '%d' ]
			);
			self::send_notification( $url, $referrer );
		}
	}

	public static function send_notification( string $url, string $referrer ): void {
		$site_name    = get_bloginfo( 'name' );
		$site_url     = get_bloginfo( 'url' );
		$admin_email  = get_bloginfo( 'admin_email' );
		$subject      = '[' . $site_name . '] 404 Error Detected: ' . $url;
		$full_url     = trailingslashit( $site_url ) . ltrim( $url, '/' );
		$referred_by  = $referrer !== '' ? esc_html( $referrer ) : 'Direct / unknown';
		$detected_at  = wp_date( 'M j, Y g:i a', time() );
		$suggestion   = self::get_suggestion( $url );

		$redirect_manager_url = admin_url( 'admin.php?page=hsm-redirects&hsm_prefill=' . urlencode( $url ) );

		$suggestion_block = '';
		if ( $suggestion !== '' ) {
			$suggestion_block = '<h3>Suggested content</h3><p>' . $suggestion . '</p>';
		}

		$body  = '<h2>404 Error Detected</h2>';
		$body .= '<p>A visitor hit a missing page on <strong>' . esc_html( $site_url ) . '</strong>.</p>';
		$body .= '<table>';
		$body .= '<tr><th>URL not found</th><td>' . esc_html( $full_url ) . '</td></tr>';
		$body .= '<tr><th>Referred from</th><td>' . $referred_by . '</td></tr>';
		$body .= '<tr><th>Detected</th><td>' . esc_html( $detected_at ) . '</td></tr>';
		$body .= '</table>';
		$body .= '<h3>How to fix this</h3>';
		$body .= '<p><strong>Option 1 — Add a 301 Redirect (Recommended)</strong><br>';
		$body .= 'If this page was moved or renamed, set up a redirect so visitors and search engines are sent to the right place.<br>';
		$body .= '<a href="' . esc_url( $redirect_manager_url ) . '">&#8594; Add a redirect in Stride Analytics</a></p>';
		$body .= '<p><strong>Option 2 — Create content at this URL</strong><br>';
		$body .= 'If this page should exist, create it in WordPress.</p>';
		$body .= '<p><strong>Option 3 — Find and fix broken links</strong><br>';
		$body .= 'Search your site and any external sources linking to this URL and update them.</p>';
		$body .= $suggestion_block;
		$body .= '<hr>';
		$body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=hsm-404-monitor' ) ) . '">&#8594; View all 404 errors in Stride Analytics</a></p>';

		add_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ] );

		wp_mail( $admin_email, $subject, $body );

		remove_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ] );
	}

	public static function get_suggestion( string $url ): string {
		$path       = (string) parse_url( $url, PHP_URL_PATH );
		$slug       = basename( $path );
		$slug_words = str_replace( [ '-', '_' ], ' ', $slug );

		if ( '' === trim( $slug_words ) ) {
			return '';
		}

		$query = new WP_Query( [
			's'              => $slug_words,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		] );

		if ( $query->have_posts() ) {
			$post      = $query->posts[0];
			$title     = get_the_title( $post );
			$permalink = get_permalink( $post );
			return 'Similar content found: &ldquo;<a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a>&rdquo; at ' . esc_url( $permalink );
		}

		return '';
	}

	public static function set_html_content_type(): string {
		return 'text/html; charset=UTF-8';
	}

	public static function register_menu(): void {
		add_submenu_page(
			'homerite-schema-settings',
			'404 Monitor',
			'404 Monitor',
			'manage_options',
			'hsm-404-monitor',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table = self::get_table_name();

		$action = isset( $_GET['hsm_action'] ) ? sanitize_key( $_GET['hsm_action'] ) : '';

		if ( 'dismiss' === $action ) {
			$entry_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'hsm_dismiss_404_' . $entry_id );
			$wpdb->update( $table, [ 'dismissed' => 1 ], [ 'id' => $entry_id ], [ '%d' ], [ '%d' ] );
			wp_redirect( add_query_arg( 'hsm_msg', 'dismissed', admin_url( 'admin.php?page=hsm-404-monitor' ) ) );
			exit;
		}

		if ( 'clear_all' === $action ) {
			check_admin_referer( 'hsm_clear_404s' );
			$wpdb->delete( $table, [ 'dismissed' => 1 ], [ '%d' ] );
			wp_redirect( add_query_arg( 'hsm_msg', 'cleared', admin_url( 'admin.php?page=hsm-404-monitor' ) ) );
			exit;
		}

		$msg = isset( $_GET['hsm_msg'] ) ? sanitize_key( $_GET['hsm_msg'] ) : '';

		if ( 'dismissed' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>Entry dismissed.</p></div>';
		} elseif ( 'cleared' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>Dismissed entries cleared.</p></div>';
		}

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE dismissed = 0 ORDER BY last_seen DESC"
		);

		$clear_all_url = wp_nonce_url(
			admin_url( 'admin.php?page=hsm-404-monitor&hsm_action=clear_all' ),
			'hsm_clear_404s'
		);

		echo '<div class="wrap">';
		echo '<h1>404 Monitor</h1>';
		echo '<p class="description">Pages returning 404 errors, tracked automatically. Fix them with a redirect or by updating broken links.</p>';
		echo '<p style="text-align:right;"><a href="' . esc_url( $clear_all_url ) . '" class="button">Clear Dismissed</a></p>';

		if ( empty( $rows ) ) {
			echo '<p>No 404 errors recorded yet.</p>';
		} else {
			echo '<table class="wp-list-table widefat striped">';
			echo '<thead><tr>';
			echo '<th>URL</th><th>Hits</th><th>Referrer</th><th>First Seen</th><th>Last Seen</th><th>Suggested Fix</th><th>Actions</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $rows as $row ) {
				$suggestion = self::get_suggestion( $row->url );
				if ( '' === $suggestion ) {
					$suggestion = '(none found)';
				}

				$referrer_display = '';
				if ( '' === $row->referrer ) {
					$referrer_display = '&mdash;';
				} elseif ( strlen( $row->referrer ) > 60 ) {
					$referrer_display = '<span title="' . esc_attr( $row->referrer ) . '">' . esc_html( substr( $row->referrer, 0, 60 ) ) . '&hellip;</span>';
				} else {
					$referrer_display = esc_html( $row->referrer );
				}

				$first_seen = wp_date( 'M j, Y g:i a', strtotime( $row->first_seen ) );
				$last_seen  = wp_date( 'M j, Y g:i a', strtotime( $row->last_seen ) );

				$redirect_url = admin_url( 'admin.php?page=hsm-redirects&hsm_prefill=' . urlencode( $row->url ) );
				$dismiss_url  = wp_nonce_url(
					admin_url( 'admin.php?page=hsm-404-monitor&hsm_action=dismiss&id=' . absint( $row->id ) ),
					'hsm_dismiss_404_' . absint( $row->id )
				);

				echo '<tr>';
				echo '<td><code>' . esc_html( $row->url ) . '</code></td>';
				echo '<td>' . absint( $row->hit_count ) . '</td>';
				echo '<td>' . $referrer_display . '</td>';
				echo '<td>' . esc_html( $first_seen ) . '</td>';
				echo '<td>' . esc_html( $last_seen ) . '</td>';
				echo '<td>' . $suggestion . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( $redirect_url ) . '" class="button button-small">Add Redirect</a> ';
				echo '<a href="' . esc_url( $dismiss_url ) . '" class="button button-small">Dismiss</a>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		echo '</div>';
	}
}
