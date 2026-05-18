<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ECHS_Tasks {

	const CACHE_KEY = 'echs_tasks_cache';
	const CACHE_TTL = 3600;

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'save_post', [ __CLASS__, 'bust_cache' ] );
		add_action( 'delete_post', [ __CLASS__, 'bust_cache' ] );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'echs-settings',
			'SEO Tasks',
			'SEO Tasks',
			'manage_options',
			'echs-tasks',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function bust_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	public static function get_tasks(): array {
		if ( ! is_admin() ) {
			return [];
		}

		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;

		$tasks = [];

		$business_name = get_option( 'echs_business_name' );
		$phone         = get_option( 'echs_phone' );
		$street        = get_option( 'echs_street' );
		$gs_complete   = ! empty( $business_name ) && ! empty( $phone ) && ! empty( $street );

		$tasks[] = [
			'id'       => 'global_settings',
			'title'    => 'Complete your business profile',
			'priority' => 'high',
			'done'     => 0,
			'total'    => 0,
			'minutes'  => $gs_complete ? 0 : 5,
			'url'      => admin_url( 'admin.php?page=echs-settings' ),
			'complete' => $gs_complete,
		];

		$seo_titles_posts_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
		);
		$seo_titles_posts_done = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'post'
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value != ''",
				'echs_seo_title'
			)
		);
		$tasks[] = [
			'id'       => 'seo_titles_posts',
			'title'    => 'Add SEO titles to your Posts',
			'priority' => 'high',
			'done'     => $seo_titles_posts_done,
			'total'    => $seo_titles_posts_total,
			'minutes'  => max( 0, ( $seo_titles_posts_total - $seo_titles_posts_done ) ) * 2,
			'url'      => admin_url( 'edit.php?post_type=post' ),
			'complete' => $seo_titles_posts_done >= $seo_titles_posts_total && $seo_titles_posts_total > 0,
		];

		$seo_titles_pages_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'"
		);
		$seo_titles_pages_done = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'page'
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value != ''",
				'echs_seo_title'
			)
		);
		$tasks[] = [
			'id'       => 'seo_titles_pages',
			'title'    => 'Add SEO titles to your Pages',
			'priority' => 'high',
			'done'     => $seo_titles_pages_done,
			'total'    => $seo_titles_pages_total,
			'minutes'  => max( 0, ( $seo_titles_pages_total - $seo_titles_pages_done ) ) * 2,
			'url'      => admin_url( 'edit.php?post_type=page' ),
			'complete' => $seo_titles_pages_done >= $seo_titles_pages_total && $seo_titles_pages_total > 0,
		];

		$meta_desc_posts_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
		);
		$meta_desc_posts_done = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'post'
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value != ''",
				'echs_seo_description'
			)
		);
		$tasks[] = [
			'id'       => 'meta_desc_posts',
			'title'    => 'Add meta descriptions to your Posts',
			'priority' => 'medium',
			'done'     => $meta_desc_posts_done,
			'total'    => $meta_desc_posts_total,
			'minutes'  => max( 0, ( $meta_desc_posts_total - $meta_desc_posts_done ) ) * 3,
			'url'      => admin_url( 'edit.php?post_type=post' ),
			'complete' => $meta_desc_posts_done >= $meta_desc_posts_total && $meta_desc_posts_total > 0,
		];

		$meta_desc_pages_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish'"
		);
		$meta_desc_pages_done = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'page'
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value != ''",
				'echs_seo_description'
			)
		);
		$tasks[] = [
			'id'       => 'meta_desc_pages',
			'title'    => 'Add meta descriptions to your Pages',
			'priority' => 'medium',
			'done'     => $meta_desc_pages_done,
			'total'    => $meta_desc_pages_total,
			'minutes'  => max( 0, ( $meta_desc_pages_total - $meta_desc_pages_done ) ) * 3,
			'url'      => admin_url( 'edit.php?post_type=page' ),
			'complete' => $meta_desc_pages_done >= $meta_desc_pages_total && $meta_desc_pages_total > 0,
		];

		$focus_kw_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
		);
		$focus_kw_done = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'post'
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value != ''",
				'echs_focus_keywords'
			)
		);
		$tasks[] = [
			'id'       => 'focus_keywords',
			'title'    => 'Add focus keywords to your Posts',
			'priority' => 'medium',
			'done'     => $focus_kw_done,
			'total'    => $focus_kw_total,
			'minutes'  => max( 0, ( $focus_kw_total - $focus_kw_done ) ) * 2,
			'url'      => admin_url( 'edit.php?post_type=post' ),
			'complete' => $focus_kw_done >= $focus_kw_total && $focus_kw_total > 0,
		];

		$schema_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
		);
		$schema_done = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
				WHERE p.post_type = 'post'
				AND p.post_status = 'publish'
				AND pm.meta_key = %s
				AND pm.meta_value != ''",
				'echs_schema_enabled_types'
			)
		);
		$tasks[] = [
			'id'       => 'schema_markup',
			'title'    => 'Enable schema markup on your Posts',
			'priority' => 'medium',
			'done'     => $schema_done,
			'total'    => $schema_total,
			'minutes'  => max( 0, ( $schema_total - $schema_done ) ) * 3,
			'url'      => admin_url( 'edit.php?post_type=post' ),
			'complete' => $schema_done >= $schema_total && $schema_total > 0,
		];

		$fix_404_count = 0;
		try {
			$table_404 = $wpdb->prefix . 'echs_404_log';
			$fix_404_count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$table_404} WHERE dismissed = 0"
			);
		} catch ( \Throwable $e ) {
			$fix_404_count = 0;
		}
		$tasks[] = [
			'id'       => 'fix_404s',
			'title'    => 'Resolve 404 errors',
			'priority' => $fix_404_count > 0 ? 'high' : 'low',
			'done'     => 0,
			'total'    => $fix_404_count,
			'minutes'  => $fix_404_count * 5,
			'url'      => admin_url( 'admin.php?page=echs-404-monitor' ),
			'complete' => $fix_404_count === 0,
		];

		$gbp_connected = class_exists( 'ECHS_Google_Auth' ) && ECHS_Google_Auth::is_connected();
		$tasks[] = [
			'id'       => 'gbp_connect',
			'title'    => 'Connect Google Business Profile',
			'priority' => 'medium',
			'done'     => 0,
			'total'    => 0,
			'minutes'  => $gbp_connected ? 0 : 10,
			'url'      => admin_url( 'admin.php?page=echs-settings#echs-google-api' ),
			'complete' => $gbp_connected,
		];

		$rating_value    = get_option( 'echs_rating_value' );
		$rating_complete = $rating_value !== '';
		$tasks[] = [
			'id'       => 'aggregate_rating',
			'title'    => 'Configure your aggregate star rating',
			'priority' => 'low',
			'done'     => 0,
			'total'    => 0,
			'minutes'  => $rating_complete ? 0 : 2,
			'url'      => admin_url( 'admin.php?page=echs-settings' ),
			'complete' => $rating_complete,
		];

		$yoast_exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT 1",
				'_yoast_wpseo_title'
			)
		);
		if ( $yoast_exists > 0 ) {
			$tasks[] = [
				'id'       => 'yoast_import',
				'title'    => 'Import your existing Yoast SEO data',
				'priority' => 'high',
				'done'     => 0,
				'total'    => 0,
				'minutes'  => 2,
				'url'      => admin_url( 'admin.php?page=echs-yoast-migrator' ),
				'complete' => false,
			];
		}

		$og_total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish'"
		);
		$og_missing = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} p
			WHERE p.post_type = 'post'
			AND p.post_status = 'publish'
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm
				WHERE pm.post_id = p.ID
				AND pm.meta_key = 'echs_og_image'
				AND pm.meta_value != ''
			)
			AND NOT EXISTS (
				SELECT 1 FROM {$wpdb->postmeta} pm2
				WHERE pm2.post_id = p.ID
				AND pm2.meta_key = '_thumbnail_id'
				AND pm2.meta_value != ''
			)"
		);
		$og_done = $og_total - $og_missing;
		$tasks[] = [
			'id'       => 'og_images_posts',
			'title'    => 'Add social share images to your Posts',
			'priority' => 'low',
			'done'     => $og_done,
			'total'    => $og_total,
			'minutes'  => max( 0, $og_missing ) * 3,
			'url'      => admin_url( 'edit.php?post_type=post' ),
			'complete' => $og_done >= $og_total && $og_total > 0,
		];

		$priority_order = [ 'high' => 0, 'medium' => 1, 'low' => 2 ];

		$incomplete = array_filter( $tasks, fn( $t ) => ! $t['complete'] );
		$complete   = array_filter( $tasks, fn( $t ) => $t['complete'] );

		usort( $incomplete, fn( $a, $b ) => $priority_order[ $a['priority'] ] <=> $priority_order[ $b['priority'] ] );
		usort( $complete, fn( $a, $b ) => $priority_order[ $a['priority'] ] <=> $priority_order[ $b['priority'] ] );

		$sorted = array_values( array_merge( array_values( $incomplete ), array_values( $complete ) ) );

		set_transient( self::CACHE_KEY, $sorted, self::CACHE_TTL );

		return $sorted;
	}

	public static function format_duration( int $minutes ): string {
		if ( $minutes <= 0 ) {
			return '0m';
		}
		if ( $minutes < 60 ) {
			return $minutes . 'm';
		}
		$hours = intdiv( $minutes, 60 );
		$mins  = $minutes % 60;
		return $hours . 'h ' . $mins . 'm';
	}

	private static function progress_circle( int $done, int $total ): string {
		if ( $total <= 0 ) {
			return '';
		}
		$fraction   = $done / $total;
		$filled     = round( 50.3 * $fraction, 1 );
		$remainder  = round( 50.3 - $filled, 1 );
		$color      = $fraction >= 1.0 ? '#00a32a' : '#2271b1';

		return '<svg width="20" height="20" viewBox="0 0 20 20" style="vertical-align:middle;margin-right:4px">'
			. '<circle cx="10" cy="10" r="8" fill="none" stroke="#e0e0e0" stroke-width="2.5"/>'
			. '<circle cx="10" cy="10" r="8" fill="none" stroke="' . esc_attr( $color ) . '" stroke-width="2.5"'
			. ' stroke-dasharray="' . esc_attr( $filled ) . ' ' . esc_attr( $remainder ) . '"'
			. ' stroke-dashoffset="12.6"'
			. ' transform="rotate(-90 10 10)"/>'
			. '</svg>';
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.' ) );
		}

		$tasks      = self::get_tasks();
		$incomplete = array_filter( $tasks, fn( $t ) => ! $t['complete'] );
		$complete   = array_filter( $tasks, fn( $t ) => $t['complete'] );

		$priority_icons = [
			'high'   => '&#8593;&#8593;',
			'medium' => '&#8801;',
			'low'    => '&#8595;',
		];
		$priority_labels = [
			'high'   => 'High',
			'medium' => 'Medium',
			'low'    => 'Low',
		];
		?>
		<style>
		.echs-tasks-list { max-width: 860px; }
		.echs-task-row {
			display: flex; align-items: center; gap: 12px;
			padding: 14px 16px; background: #fff;
			border: 1px solid #e5e7eb; border-radius: 6px;
			margin-bottom: 6px;
		}
		.echs-task-row:hover { border-color: #2271b1; }
		.echs-task-icon { flex: 0 0 24px; }
		.echs-task-body { flex: 1; }
		.echs-task-title { font-size: 14px; font-weight: 500; text-decoration: none; color: #1d2327; }
		.echs-task-title:hover { color: #2271b1; }
		.echs-task-meta { display: flex; align-items: center; gap: 16px; }
		.echs-task-priority-badge { display:flex; align-items:center; gap:4px; font-size:12px; font-weight:600; white-space:nowrap; }
		.echs-priority-high   { color: #d63638; }
		.echs-priority-medium { color: #dba617; }
		.echs-priority-low    { color: #787c82; }
		.echs-task-duration { display:flex; align-items:center; gap:4px; font-size:12px; color:#787c82; white-space:nowrap; }
		.echs-task-progress { display:flex; align-items:center; font-size:12px; color:#3c434a; white-space:nowrap; }
		.echs-task-arrow { font-size:20px; color:#787c82; text-decoration:none; line-height:1; }
		.echs-task-arrow:hover { color:#2271b1; }
		.echs-task-row.echs-task-complete { opacity: 0.55; }
		.echs-task-row.echs-task-complete .echs-task-icon { color: #00a32a; }
		.echs-tasks-complete-header { font-size:12px; font-weight:600; color:#787c82; text-transform:uppercase; letter-spacing:.05em; margin: 20px 0 8px; }
		.echs-widget-tasks h4 { font-size:13px; }
		.echs-widget-task-row { display:flex; align-items:center; gap:8px; padding:6px 0; text-decoration:none; color:#1d2327; font-size:13px; border-bottom:1px solid #f0f0f0; }
		.echs-widget-task-row:hover { color:#2271b1; }
		.echs-task-dot { width:8px; height:8px; border-radius:50%; background:currentColor; flex:0 0 8px; }
		.echs-widget-task-title { flex:1; }
		.echs-widget-task-count { font-size:11px; color:#787c82; }
		.echs-widget-task-chevron { font-size:18px; color:#787c82; }
		</style>
		<div class="wrap">
			<h1>SEO Tasks</h1>
			<p class="description">Action items to improve your site's search performance. Completed tasks move to the bottom.</p>
			<div class="echs-tasks-list">
				<?php foreach ( $incomplete as $task ) : ?>
					<div class="echs-task-row echs-task-priority-<?php echo esc_attr( $task['priority'] ); ?>" data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
						<div class="echs-task-icon">
							<svg width="20" height="20" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="10" cy="10" r="4" fill="currentColor"/></svg>
						</div>
						<div class="echs-task-body">
							<a href="<?php echo esc_url( $task['url'] ); ?>" class="echs-task-title"><?php echo esc_html( $task['title'] ); ?></a>
						</div>
						<div class="echs-task-meta">
							<span class="echs-task-priority-badge echs-priority-<?php echo esc_attr( $task['priority'] ); ?>">
								<?php echo $priority_icons[ $task['priority'] ]; ?>
								<?php echo esc_html( $priority_labels[ $task['priority'] ] ); ?>
							</span>
							<span class="echs-task-duration">
								<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
								<?php echo esc_html( self::format_duration( $task['minutes'] ) ); ?>
							</span>
							<?php if ( $task['total'] > 0 ) : ?>
								<span class="echs-task-progress">
									<?php echo self::progress_circle( $task['done'], $task['total'] ); ?>
									<?php echo esc_html( $task['done'] ) . '/' . esc_html( $task['total'] ); ?>
								</span>
							<?php endif; ?>
							<a href="<?php echo esc_url( $task['url'] ); ?>" class="echs-task-arrow" aria-label="Go to task">&#8250;</a>
						</div>
					</div>
				<?php endforeach; ?>

				<?php if ( ! empty( $complete ) ) : ?>
					<div class="echs-tasks-complete-header">Completed</div>
					<?php foreach ( $complete as $task ) : ?>
						<div class="echs-task-row echs-task-complete echs-task-priority-<?php echo esc_attr( $task['priority'] ); ?>" data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
							<div class="echs-task-icon">
								<svg width="20" height="20" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" fill="none" stroke="currentColor" stroke-width="2"/><polyline points="6 10 9 13 14 8" fill="none" stroke="currentColor" stroke-width="2"/></svg>
							</div>
							<div class="echs-task-body">
								<a href="<?php echo esc_url( $task['url'] ); ?>" class="echs-task-title"><?php echo esc_html( $task['title'] ); ?></a>
							</div>
							<div class="echs-task-meta">
								<span class="echs-task-priority-badge echs-priority-<?php echo esc_attr( $task['priority'] ); ?>">
									<?php echo $priority_icons[ $task['priority'] ]; ?>
									<?php echo esc_html( $priority_labels[ $task['priority'] ] ); ?>
								</span>
								<span class="echs-task-duration">
									<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
									<?php echo esc_html( self::format_duration( $task['minutes'] ) ); ?>
								</span>
								<?php if ( $task['total'] > 0 ) : ?>
									<span class="echs-task-progress">
										<?php echo self::progress_circle( $task['done'], $task['total'] ); ?>
										<?php echo esc_html( $task['done'] ) . '/' . esc_html( $task['total'] ); ?>
									</span>
								<?php endif; ?>
								<a href="<?php echo esc_url( $task['url'] ); ?>" class="echs-task-arrow" aria-label="Go to task">&#8250;</a>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	public static function render_widget_section(): void {
		$tasks      = self::get_tasks();
		$incomplete = array_filter( $tasks, fn( $t ) => ! $t['complete'] );
		$top_three  = array_slice( array_values( $incomplete ), 0, 3 );
		?>
		<div class="echs-widget-tasks">
			<h4 style="margin:12px 0 8px;">SEO Tasks</h4>
			<?php foreach ( $top_three as $task ) : ?>
				<a href="<?php echo esc_url( $task['url'] ); ?>" class="echs-widget-task-row echs-priority-<?php echo esc_attr( $task['priority'] ); ?>">
					<span class="echs-task-dot"></span>
					<span class="echs-widget-task-title"><?php echo esc_html( $task['title'] ); ?></span>
					<?php if ( $task['total'] > 0 ) : ?>
						<span class="echs-widget-task-count"><?php echo esc_html( $task['done'] ) . '/' . esc_html( $task['total'] ); ?></span>
					<?php endif; ?>
					<span class="echs-widget-task-chevron">&#8250;</span>
				</a>
			<?php endforeach; ?>
			<p class="echs-widget-footer"><a href="<?php echo esc_url( admin_url( 'admin.php?page=echs-tasks' ) ); ?>">View all SEO tasks &rarr;</a></p>
		</div>
		<?php
	}
}
