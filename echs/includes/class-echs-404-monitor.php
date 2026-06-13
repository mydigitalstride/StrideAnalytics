<?php
/**
 * 404 error monitoring: DB logging, email notification, and admin UI.
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_404_Monitor {

	const CRON_HOOK  = 'echs_404_daily_summary';
	const DB_VERSION = '1.1';
	const DB_VER_OPT = 'echs_404_db_version';

	// URL path fragments that indicate automated scanners/vulnerability probes.
	private static array $bot_url_patterns = [
		'.env', 'wp-config', 'xmlrpc.php', '.git/', 'phpmyadmin', 'adminer',
		'shell.php', 'webshell', '.htaccess', 'backup.zip', 'backup.sql',
		'.ds_store', 'config.php', 'setup.php', 'install.php', '/vendor/',
		'composer.json', 'package.json', 'cgi-bin', 'server-status',
		'/actuator/', 'web.config', '.bash_history', '.ssh/', '/passwd',
		'/etc/shadow', '/proc/', 'phpinfo', 'eval(', 'base64_decode',
	];

	// UA substrings (matched lowercase) that identify bots or automated tools.
	private static array $bot_ua_patterns = [
		'bot', 'spider', 'crawl', 'slurp', 'curl/', 'wget/', 'python-',
		'java/', 'go-http', 'libwww', 'scrapy', 'nikto', 'sqlmap',
		'masscan', 'zgrab', 'nuclei', 'dirbuster', 'gobuster', 'nmap',
		'httpclient', 'requests/', 'okhttp', 'perl/', 'ruby/', 'axios/',
		'postman', 'insomnia', 'dataforseo', 'semrushbot', 'ahrefsbot',
		'mj12bot', 'dotbot', 'screaming frog',
	];

	public static function init(): void {
		add_action( 'template_redirect',                  [ __CLASS__, 'log_404' ], 2 );
		add_action( 'admin_menu',                         [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_init',                         [ __CLASS__, 'handle_actions' ] );
		add_action( self::CRON_HOOK,                      [ __CLASS__, 'send_daily_summary' ] );
		add_action( 'admin_post_echs_save_404_settings',  [ __CLASS__, 'save_settings' ] );
		add_action( 'wp_ajax_echs_404_add_redirect',      [ __CLASS__, 'ajax_add_redirect' ] );
		self::maybe_upgrade_table();
		self::schedule_cron();
	}

	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'echs_404_log';
	}

	public static function create_table(): void {
		global $wpdb;
		$table   = self::get_table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			id bigint unsigned NOT NULL AUTO_INCREMENT,
			url varchar(2048) NOT NULL,
			referrer varchar(2048) NOT NULL DEFAULT '',
			user_agent varchar(512) NOT NULL DEFAULT '',
			is_bot tinyint(1) NOT NULL DEFAULT 0,
			hit_count bigint unsigned NOT NULL DEFAULT 1,
			notified tinyint(1) NOT NULL DEFAULT 0,
			dismissed tinyint(1) NOT NULL DEFAULT 0,
			first_seen datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_seen datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY url (url(191)),
			KEY is_bot (is_bot)
		) {$charset};";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Run dbDelta to apply schema changes on existing installs without reactivation.
	 */
	public static function maybe_upgrade_table(): void {
		if ( get_option( self::DB_VER_OPT ) !== self::DB_VERSION ) {
			self::create_table();
			update_option( self::DB_VER_OPT, self::DB_VERSION );
		}
	}

	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$midnight = strtotime( 'tomorrow midnight', current_time( 'timestamp' ) );
			wp_schedule_event( $midnight, 'daily', self::CRON_HOOK );
		}
	}

	public static function clear_cron(): void {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Return true if the request looks like a bot or automated scanner.
	 */
	public static function classify_as_bot( string $url, string $ua ): bool {
		if ( '' === trim( $ua ) ) {
			return true;
		}

		$ua_lower = strtolower( $ua );
		foreach ( self::$bot_ua_patterns as $pattern ) {
			if ( str_contains( $ua_lower, $pattern ) ) {
				return true;
			}
		}

		$url_lower = strtolower( $url );
		foreach ( self::$bot_url_patterns as $pattern ) {
			if ( str_contains( $url_lower, $pattern ) ) {
				return true;
			}
		}

		return false;
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

		$raw_ua     = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$user_agent = substr( sanitize_text_field( $raw_ua ), 0, 512 );

		$is_bot = self::classify_as_bot( $url, $user_agent ) ? 1 : 0;

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
					'url'        => $url,
					'referrer'   => $referrer,
					'user_agent' => $user_agent,
					'is_bot'     => $is_bot,
					'hit_count'  => 1,
					'notified'   => 0,
					'dismissed'  => 0,
				],
				[ '%s', '%s', '%s', '%d', '%d', '%d', '%d' ]
			);
		}
	}

	/**
	 * Save the email-enabled toggle from the admin settings panel.
	 */
	public static function save_settings(): void {
		check_admin_referer( 'echs_save_404_settings' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'echs' ) );
		}
		update_option( 'echs_404_email_enabled', isset( $_POST['echs_404_email_enabled'] ) ? 1 : 0 );
		wp_redirect( add_query_arg( 'echs_msg', 'settings_saved', admin_url( 'admin.php?page=echs-404-monitor' ) ) );
		exit;
	}

	/**
	 * Daily summary email — human traffic only, skipped when notifications are disabled.
	 */
	public static function send_daily_summary(): void {
		if ( ! get_option( 'echs_404_email_enabled', 1 ) ) {
			return;
		}

		global $wpdb;

		$table = self::get_table_name();
		$rows  = $wpdb->get_results(
			"SELECT * FROM {$table} WHERE notified = 0 AND is_bot = 0 ORDER BY last_seen DESC"
		);

		if ( empty( $rows ) ) {
			return;
		}

		$site_name   = get_bloginfo( 'name' );
		$site_url    = get_bloginfo( 'url' );
		$admin_email = get_bloginfo( 'admin_email' );
		$count       = count( $rows );
		$subject     = '[' . $site_name . '] Daily 404 Summary: ' . $count . ' error' . ( $count > 1 ? 's' : '' ) . ' detected';
		$date_label  = wp_date( 'M j, Y' );

		$body  = '<h2>Daily 404 Error Summary — ' . esc_html( $date_label ) . '</h2>';
		$body .= '<p><strong>' . $count . '</strong> new 404 error' . ( $count > 1 ? 's were' : ' was' ) . ' recorded on <strong>' . esc_html( $site_url ) . '</strong> since the last report.</p>';

		$body .= '<table cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;width:100%">';
		$body .= '<thead style="background:#f0f0f0"><tr>';
		$body .= '<th style="text-align:left">URL not found</th>';
		$body .= '<th style="text-align:left">Hits</th>';
		$body .= '<th style="text-align:left">Referred from</th>';
		$body .= '<th style="text-align:left">First seen</th>';
		$body .= '<th style="text-align:left">Last seen</th>';
		$body .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$full_url    = trailingslashit( $site_url ) . ltrim( $row->url, '/' );
			$referred_by = $row->referrer !== '' ? esc_html( $row->referrer ) : 'Direct / unknown';
			$first_seen  = wp_date( 'M j, Y g:i a', strtotime( $row->first_seen ) );
			$last_seen   = wp_date( 'M j, Y g:i a', strtotime( $row->last_seen ) );

			$redirect_url = admin_url( 'admin.php?page=echs-redirects&echs_prefill=' . urlencode( $row->url ) );

			$body .= '<tr>';
			$body .= '<td><a href="' . esc_url( $redirect_url ) . '">' . esc_html( $full_url ) . '</a></td>';
			$body .= '<td>' . absint( $row->hit_count ) . '</td>';
			$body .= '<td>' . $referred_by . '</td>';
			$body .= '<td>' . esc_html( $first_seen ) . '</td>';
			$body .= '<td>' . esc_html( $last_seen ) . '</td>';
			$body .= '</tr>';
		}

		$body .= '</tbody></table>';
		$body .= '<br>';
		$body .= '<p><strong>How to fix these errors</strong><br>';
		$body .= 'Click any URL in the table above to open the redirect manager and add a 301 redirect, or visit your site to create the missing content.</p>';
		$body .= '<hr>';
		$body .= '<p><a href="' . esc_url( admin_url( 'admin.php?page=echs-404-monitor' ) ) . '">&#8594; View all 404 errors in ECHoS SEO Analytics</a></p>';

		add_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ] );
		wp_mail( $admin_email, $subject, $body );
		remove_filter( 'wp_mail_content_type', [ __CLASS__, 'set_html_content_type' ] );

		$ids = implode( ',', array_map( 'absint', wp_list_pluck( $rows, 'id' ) ) );
		$wpdb->query( "UPDATE {$table} SET notified = 1 WHERE id IN ({$ids})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_suggestion( string $url ): array {
		$path       = (string) parse_url( $url, PHP_URL_PATH );
		$slug       = basename( $path );
		$slug_words = str_replace( [ '-', '_' ], ' ', $slug );

		if ( '' === trim( $slug_words ) ) {
			return [];
		}

		$query = new WP_Query( [
			's'              => $slug_words,
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		] );

		if ( $query->have_posts() ) {
			$post = $query->posts[0];
			return [
				'title'     => get_the_title( $post ),
				'permalink' => get_permalink( $post ),
			];
		}

		return [];
	}

	public static function ajax_add_redirect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}
		check_ajax_referer( 'echs_404_redirect', '_nonce' );

		$source   = sanitize_text_field( $_POST['source'] ?? '' );
		$target   = sanitize_text_field( $_POST['target'] ?? '' );
		$type_raw = (int) ( $_POST['type'] ?? 301 );
		$type     = in_array( $type_raw, [ 301, 302, 307 ], true ) ? $type_raw : 301;
		$entry_id = absint( $_POST['entry_id'] ?? 0 );

		if ( '' === $source || '' === $target ) {
			wp_send_json_error( 'Source and destination are required.' );
		}

		$source = esc_url_raw( $source );
		$target = esc_url_raw( $target );

		if ( ! str_starts_with( $source, '/' ) && ! preg_match( '#^https?://#i', $source ) ) {
			$source = '/' . $source;
		}

		global $wpdb;
		$inserted = $wpdb->insert(
			ECHS_Redirects::get_table_name(),
			[
				'source_url'    => $source,
				'target_url'    => $target,
				'redirect_type' => $type,
			],
			[ '%s', '%s', '%d' ]
		);

		if ( false === $inserted ) {
			wp_send_json_error( 'Failed to create redirect.' );
		}

		delete_transient( 'echs_redirects_cache' );
		self::dismiss_by_url( $source );

		if ( $entry_id > 0 ) {
			$table = self::get_table_name();
			$wpdb->update( $table, [ 'dismissed' => 1 ], [ 'id' => $entry_id ], [ '%d' ], [ '%d' ] );
		}

		wp_send_json_success( [ 'message' => 'Redirect created and 404 dismissed.' ] );
	}

	public static function dismiss_by_url( string $url ): void {
		global $wpdb;
		$table = self::get_table_name();
		$normalized = strtolower( trim( $url ) );
		$wpdb->query( $wpdb->prepare(
			"UPDATE {$table} SET dismissed = 1 WHERE LOWER(TRIM(url)) = %s OR LOWER(TRIM(url)) = %s",
			$normalized,
			rtrim( $normalized, '/' )
		) );
	}

	public static function set_html_content_type(): string {
		return 'text/html; charset=UTF-8';
	}

	public static function register_menu(): void {
		add_submenu_page(
			'echs-settings',
			'404 Monitor',
			'404 Monitor',
			'manage_options',
			'echs-404-monitor',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function handle_actions(): void {
		if ( ! isset( $_GET['page'] ) || 'echs-404-monitor' !== $_GET['page'] ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table  = self::get_table_name();
		$action = isset( $_GET['echs_action'] ) ? sanitize_key( $_GET['echs_action'] ) : '';

		if ( 'dismiss' === $action ) {
			$entry_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
			check_admin_referer( 'echs_dismiss_404_' . $entry_id );
			$wpdb->update( $table, [ 'dismissed' => 1 ], [ 'id' => $entry_id ], [ '%d' ], [ '%d' ] );
			wp_safe_redirect( add_query_arg( 'echs_msg', 'dismissed', admin_url( 'admin.php?page=echs-404-monitor' ) ) );
			exit;
		}

		if ( 'clear_all' === $action ) {
			check_admin_referer( 'echs_clear_404s' );
			$wpdb->delete( $table, [ 'dismissed' => 1 ], [ '%d' ] );
			wp_safe_redirect( add_query_arg( 'echs_msg', 'cleared', admin_url( 'admin.php?page=echs-404-monitor' ) ) );
			exit;
		}
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		global $wpdb;
		$table = self::get_table_name();

		$msg = isset( $_GET['echs_msg'] ) ? sanitize_key( $_GET['echs_msg'] ) : '';

		if ( 'dismissed' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>Entry dismissed.</p></div>';
		} elseif ( 'cleared' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>Dismissed entries cleared.</p></div>';
		} elseif ( 'settings_saved' === $msg ) {
			echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
		}

		$filter   = isset( $_GET['echs_filter'] ) ? sanitize_key( $_GET['echs_filter'] ) : 'human';
		$base_url = admin_url( 'admin.php?page=echs-404-monitor' );

		$where = 'WHERE dismissed = 0';
		if ( 'bot' === $filter ) {
			$where .= ' AND is_bot = 1';
		} elseif ( 'human' === $filter ) {
			$where .= ' AND is_bot = 0';
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT * FROM {$table} {$where} ORDER BY last_seen DESC" );

		$clear_all_url = wp_nonce_url(
			admin_url( 'admin.php?page=echs-404-monitor&echs_action=clear_all' ),
			'echs_clear_404s'
		);
		$email_enabled = (bool) get_option( 'echs_404_email_enabled', 1 );

		echo '<div class="wrap">';
		echo '<h1>404 Monitor</h1>';

		// Email notification settings panel.
		echo '<div style="background:#fff;border:1px solid #c3c4c7;padding:12px 16px;margin-bottom:20px;max-width:580px;">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="echs_save_404_settings">';
		wp_nonce_field( 'echs_save_404_settings' );
		echo '<label style="font-weight:600;">';
		echo '<input type="checkbox" name="echs_404_email_enabled" value="1"' . checked( $email_enabled, true, false ) . '> ';
		echo 'Send daily 404 email summary';
		echo '</label>';
		echo '<p style="margin:6px 0 8px;color:#646970;font-size:13px;">Sends a daily digest of human-traffic 404s to the site admin. Bot and scanner hits are always excluded from the email.</p>';
		echo '<button type="submit" class="button">Save</button>';
		echo '</form>';
		echo '</div>';

		echo '<p class="description">Pages returning 404 errors, tracked automatically. Fix them with a redirect or by updating broken links.</p>';

		// Filter tabs.
		$tabs = [
			'human' => 'Human traffic',
			'bot'   => 'Bots &amp; scanners',
			'all'   => 'All',
		];
		echo '<ul class="subsubsub" style="margin-bottom:10px;">';
		$tab_links = [];
		foreach ( $tabs as $tab_key => $tab_label ) {
			$class       = ( $filter === $tab_key ) ? ' class="current"' : '';
			$tab_links[] = '<li><a href="' . esc_url( add_query_arg( 'echs_filter', $tab_key, $base_url ) ) . '"' . $class . '>' . $tab_label . '</a></li>';
		}
		echo implode( ' | ', $tab_links );
		echo '</ul>';

		echo '<p style="text-align:right;"><a href="' . esc_url( $clear_all_url ) . '" class="button">Clear Dismissed</a></p>';

		if ( empty( $rows ) ) {
			echo '<p>No 404 errors recorded yet.</p>';
		} else {
			echo '<style>
				.echs-404-table { table-layout:fixed; }
				.echs-404-table th, .echs-404-table td { word-wrap:break-word; overflow-wrap:break-word; vertical-align:top; }
				.echs-404-table .col-url { width:25%; }
				.echs-404-table .col-type { width:55px; }
				.echs-404-table .col-hits { width:40px; text-align:center; }
				.echs-404-table .col-ref { width:12%; }
				.echs-404-table .col-seen { width:80px; }
				.echs-404-table .col-fix { width:22%; }
				.echs-404-table .col-actions { width:120px; }
				.echs-404-table code { font-size:12px; word-break:break-all; }
				.echs-404-table .echs-ref-text { font-size:12px; color:#646970; word-break:break-all; }
				.echs-404-table .echs-suggestion a { word-break:break-all; }
				#echs-redirect-modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:100000; }
				#echs-redirect-modal { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#fff; border-radius:8px; padding:24px 28px; width:480px; max-width:90vw; z-index:100001; box-shadow:0 8px 32px rgba(0,0,0,.25); }
				#echs-redirect-modal h3 { margin:0 0 16px; }
				#echs-redirect-modal label { display:block; font-weight:600; margin-bottom:4px; font-size:13px; }
				#echs-redirect-modal input[type=text], #echs-redirect-modal select { width:100%; margin-bottom:12px; }
				#echs-redirect-modal .echs-modal-buttons { display:flex; gap:8px; justify-content:flex-end; margin-top:8px; }
				#echs-redirect-modal .echs-modal-status { margin-top:8px; font-size:13px; }
			</style>';
			echo '<table class="wp-list-table widefat striped echs-404-table">';
			echo '<thead><tr>';
			echo '<th class="col-url">URL</th><th class="col-type">Type</th><th class="col-hits">Hits</th><th class="col-ref">Referrer</th><th class="col-seen">Last Seen</th><th class="col-fix">Suggested Fix</th><th class="col-actions">Actions</th>';
			echo '</tr></thead>';
			echo '<tbody>';

			foreach ( $rows as $row ) {
				$suggestion = self::get_suggestion( $row->url );

				if ( '' === $row->referrer ) {
					$referrer_display = '&mdash;';
				} else {
					$ref_host = (string) parse_url( $row->referrer, PHP_URL_HOST );
					$referrer_display = '<span class="echs-ref-text" title="' . esc_attr( $row->referrer ) . '">' . esc_html( $ref_host ?: substr( $row->referrer, 0, 30 ) ) . '</span>';
				}

				$last_seen = wp_date( 'M j, Y', strtotime( $row->last_seen ) );

				$dismiss_url = wp_nonce_url(
					admin_url( 'admin.php?page=echs-404-monitor&echs_action=dismiss&id=' . absint( $row->id ) ),
					'echs_dismiss_404_' . absint( $row->id )
				);

				$is_bot     = ! empty( $row->is_bot );
				$type_badge = $is_bot
					? '<span style="background:#f0b849;color:#1d2327;font-size:11px;padding:1px 6px;border-radius:3px;font-weight:600;">Bot</span>'
					: '<span style="background:#d7f0dc;color:#1d7a34;font-size:11px;padding:1px 6px;border-radius:3px;font-weight:600;">Human</span>';

				$suggest_target = ! empty( $suggestion ) ? esc_attr( $suggestion['permalink'] ) : '';

				echo '<tr data-entry-id="' . absint( $row->id ) . '">';
				echo '<td class="col-url"><code>' . esc_html( $row->url ) . '</code></td>';
				echo '<td class="col-type">' . $type_badge . '</td>';
				echo '<td class="col-hits">' . absint( $row->hit_count ) . '</td>';
				echo '<td class="col-ref">' . $referrer_display . '</td>';
				echo '<td class="col-seen">' . esc_html( $last_seen ) . '</td>';
				echo '<td class="col-fix echs-suggestion">';
				if ( ! empty( $suggestion ) ) {
					echo '&ldquo;<a href="' . esc_url( $suggestion['permalink'] ) . '">' . esc_html( $suggestion['title'] ) . '</a>&rdquo;';
					echo '<br><button type="button" class="button button-small echs-redir-btn" style="margin-top:4px;" data-source="' . esc_attr( $row->url ) . '" data-target="' . $suggest_target . '" data-entry="' . absint( $row->id ) . '">Redirect to this</button>';
				} else {
					echo '<span style="color:#999;">(none found)</span>';
				}
				echo '</td>';
				echo '<td class="col-actions">';
				if ( ! $is_bot ) {
					echo '<button type="button" class="button button-small echs-redir-btn" style="margin-bottom:4px;display:block;width:100%;text-align:center;" data-source="' . esc_attr( $row->url ) . '" data-target="" data-entry="' . absint( $row->id ) . '">Add Redirect</button>';
				}
				echo '<a href="' . esc_url( $dismiss_url ) . '" class="button button-small" style="display:block;text-align:center;">Dismiss</a>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody></table>';
		}

		// Redirect modal.
		echo '<div id="echs-redirect-modal-overlay"></div>';
		echo '<div id="echs-redirect-modal" style="display:none;">';
		echo '<h3>Add Redirect</h3>';
		echo '<input type="hidden" id="echs-redir-entry-id" value="">';
		echo '<label for="echs-redir-source">Source URL (404)</label>';
		echo '<input type="text" id="echs-redir-source" class="regular-text" readonly>';
		echo '<label for="echs-redir-target">Destination URL</label>';
		echo '<input type="text" id="echs-redir-target" class="regular-text" placeholder="https://… or /new-page/">';
		echo '<label for="echs-redir-type">Redirect Type</label>';
		echo '<select id="echs-redir-type"><option value="301">301 Permanent</option><option value="302">302 Temporary</option><option value="307">307 Temporary</option></select>';
		echo '<div class="echs-modal-buttons">';
		echo '<button type="button" class="button" id="echs-redir-cancel">Cancel</button>';
		echo '<button type="button" class="button button-primary" id="echs-redir-save">Create Redirect</button>';
		echo '</div>';
		echo '<div class="echs-modal-status" id="echs-redir-status"></div>';
		echo '</div>';

		?>
		<script>
		(function(){
			var overlay = document.getElementById('echs-redirect-modal-overlay'),
				modal   = document.getElementById('echs-redirect-modal'),
				srcEl   = document.getElementById('echs-redir-source'),
				tgtEl   = document.getElementById('echs-redir-target'),
				typeEl  = document.getElementById('echs-redir-type'),
				entryEl = document.getElementById('echs-redir-entry-id'),
				status  = document.getElementById('echs-redir-status'),
				activeRow = null;

			function openModal(source, target, entryId) {
				srcEl.value   = source;
				tgtEl.value   = target;
				entryEl.value = entryId;
				typeEl.value  = '301';
				status.textContent = '';
				overlay.style.display = 'block';
				modal.style.display   = 'block';
				if (!target) tgtEl.focus();
			}

			function closeModal() {
				overlay.style.display = 'none';
				modal.style.display   = 'none';
				activeRow = null;
			}

			document.querySelectorAll('.echs-redir-btn').forEach(function(btn){
				btn.addEventListener('click', function(){
					activeRow = btn.closest('tr');
					openModal(btn.dataset.source, btn.dataset.target, btn.dataset.entry);
				});
			});

			overlay.addEventListener('click', closeModal);
			document.getElementById('echs-redir-cancel').addEventListener('click', closeModal);

			document.addEventListener('keydown', function(e){
				if (e.key === 'Escape' && modal.style.display === 'block') closeModal();
			});

			document.getElementById('echs-redir-save').addEventListener('click', function(){
				var source = srcEl.value.trim(),
					target = tgtEl.value.trim();

				if (!target) {
					status.innerHTML = '<span style="color:#d63638;">Destination URL is required.</span>';
					tgtEl.focus();
					return;
				}

				var saveBtn = this;
				saveBtn.disabled = true;
				saveBtn.textContent = 'Saving…';
				status.textContent = '';

				var data = new FormData();
				data.append('action', 'echs_404_add_redirect');
				data.append('_nonce', '<?php echo wp_create_nonce( "echs_404_redirect" ); ?>');
				data.append('source', source);
				data.append('target', target);
				data.append('type', typeEl.value);
				data.append('entry_id', entryEl.value);

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r){ return r.json(); })
					.then(function(resp){
						if (resp.success) {
							status.innerHTML = '<span style="color:#00a32a;">Redirect created! Removing row…</span>';
							if (activeRow) {
								activeRow.style.transition = 'opacity .3s';
								activeRow.style.opacity = '0';
								setTimeout(function(){ activeRow.remove(); }, 300);
							}
							setTimeout(closeModal, 600);
						} else {
							status.innerHTML = '<span style="color:#d63638;">' + (resp.data || 'Error') + '</span>';
							saveBtn.disabled = false;
							saveBtn.textContent = 'Create Redirect';
						}
					})
					.catch(function(){
						status.innerHTML = '<span style="color:#d63638;">Request failed. Please try again.</span>';
						saveBtn.disabled = false;
						saveBtn.textContent = 'Create Redirect';
					});
			});
		})();
		</script>
		<?php

		echo '</div>';
	}
}
