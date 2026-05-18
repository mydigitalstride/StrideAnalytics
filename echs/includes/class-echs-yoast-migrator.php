<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ECHS_Yoast_Migrator {

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'register_menu' ] );
		add_action( 'wp_ajax_echs_yoast_migrate_batch', [ self::class, 'ajax_migrate_batch' ] );
		add_action( 'wp_ajax_echs_yoast_migrate_global', [ self::class, 'ajax_migrate_global' ] );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'echs-settings',
			'Import from Yoast SEO',
			'Import from Yoast',
			'manage_options',
			'echs-yoast-migrator',
			[ self::class, 'render_page' ]
		);
	}

	public static function get_stats(): array {
		global $wpdb;

		$yoast_active = (
			is_plugin_active( 'wordpress-seo/wp-seo.php' ) ||
			is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' )
		);

		$yoast_has_data = (bool) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\_yoast\_wpseo\_%' LIMIT 1"
		);

		$posts_with_yoast = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
				WHERE meta_key IN (%s, %s)",
				'_yoast_wpseo_title',
				'_yoast_wpseo_metadesc'
			)
		);

		$posts_total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_status = %s AND post_type IN ('post', 'page')",
				'publish'
			)
		);

		$already_migrated = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
				WHERE meta_key = %s",
				'echs_seo_title'
			)
		);

		return [
			'yoast_active'     => $yoast_active,
			'yoast_has_data'   => $yoast_has_data,
			'posts_with_yoast' => $posts_with_yoast,
			'posts_total'      => $posts_total,
			'already_migrated' => $already_migrated,
		];
	}

	public static function ajax_migrate_batch(): void {
		check_ajax_referer( 'echs_yoast_migrate' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}

		global $wpdb;

		$offset    = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$overwrite = ! empty( $_POST['overwrite'] ) && '1' === (string) $_POST['overwrite'];

		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.post_id
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key IN (%s, %s)
				  AND p.post_status = %s
				  AND p.post_type IN ('post', 'page')
				ORDER BY pm.post_id ASC
				LIMIT 50 OFFSET %d",
				'_yoast_wpseo_title',
				'_yoast_wpseo_metadesc',
				'publish',
				$offset
			)
		);

		$totals = [ 'migrated' => 0, 'skipped' => 0, 'empty' => 0 ];

		foreach ( $post_ids as $post_id ) {
			$result = self::migrate_post( (int) $post_id, $overwrite );
			$totals['migrated'] += $result['migrated'];
			$totals['skipped']  += $result['skipped'];
			$totals['empty']    += $result['empty'];
		}

		$processed   = count( $post_ids );
		$done        = $processed < 50;
		$next_offset = $offset + $processed;

		wp_send_json_success( [
			'migrated'    => $totals['migrated'],
			'skipped'     => $totals['skipped'],
			'empty'       => $totals['empty'],
			'processed'   => $processed,
			'done'        => $done,
			'next_offset' => $next_offset,
		] );
	}

	public static function migrate_post( int $post_id, bool $overwrite ): array {
		$map = [
			'echs_seo_title'           => '_yoast_wpseo_title',
			'echs_seo_description'     => '_yoast_wpseo_metadesc',
			'echs_canonical_url'       => '_yoast_wpseo_canonical',
			'echs_og_title'            => '_yoast_wpseo_opengraph-title',
			'echs_og_description'      => '_yoast_wpseo_opengraph-description',
			'echs_og_image'            => '_yoast_wpseo_opengraph-image',
			'echs_twitter_title'       => '_yoast_wpseo_twitter-title',
			'echs_twitter_description' => '_yoast_wpseo_twitter-description',
			'echs_twitter_image'       => '_yoast_wpseo_twitter-image',
		];

		$counts = [ 'migrated' => 0, 'skipped' => 0, 'empty' => 0 ];

		foreach ( $map as $echs_key => $yoast_key ) {
			$yoast_value = get_post_meta( $post_id, $yoast_key, true );

			if ( '' === (string) $yoast_value || null === $yoast_value ) {
				$counts['empty']++;
				continue;
			}

			$echs_value = get_post_meta( $post_id, $echs_key, true );

			if ( '' !== (string) $echs_value && ! $overwrite ) {
				$counts['skipped']++;
				continue;
			}

			update_post_meta( $post_id, $echs_key, $yoast_value );
			$counts['migrated']++;
		}

		$focuskw = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );

		if ( '' !== (string) $focuskw && null !== $focuskw ) {
			$echs_focuskw = get_post_meta( $post_id, 'echs_focus_keyword', true );

			if ( '' === (string) $echs_focuskw || $overwrite ) {
				update_post_meta( $post_id, 'echs_focus_keyword', $focuskw );
				$counts['migrated']++;
			} else {
				$counts['skipped']++;
			}

			$echs_keywords = get_post_meta( $post_id, 'echs_focus_keywords', true );

			if ( empty( $echs_keywords ) || $overwrite ) {
				update_post_meta( $post_id, 'echs_focus_keywords', [ $focuskw ] );
			}
		} else {
			$counts['empty']++;
		}

		$noindex_raw = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );

		if ( '' !== (string) $noindex_raw && null !== $noindex_raw ) {
			$noindex_value    = ( '1' === (string) $noindex_raw ) ? '1' : '0';
			$echs_noindex_val = get_post_meta( $post_id, 'echs_noindex', true );

			if ( '' === (string) $echs_noindex_val || $overwrite ) {
				update_post_meta( $post_id, 'echs_noindex', $noindex_value );
				$counts['migrated']++;
			} else {
				$counts['skipped']++;
			}
		}

		$nofollow_raw = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true );

		if ( '' !== (string) $nofollow_raw && null !== $nofollow_raw ) {
			$nofollow_value    = ( '1' === (string) $nofollow_raw ) ? '1' : '0';
			$echs_nofollow_val = get_post_meta( $post_id, 'echs_nofollow', true );

			if ( '' === (string) $echs_nofollow_val || $overwrite ) {
				update_post_meta( $post_id, 'echs_nofollow', $nofollow_value );
				$counts['migrated']++;
			} else {
				$counts['skipped']++;
			}
		}

		return $counts;
	}

	public static function ajax_migrate_global(): void {
		check_ajax_referer( 'echs_yoast_migrate' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( '', 403 );
		}

		$overwrite = ! empty( $_POST['overwrite'] ) && '1' === (string) $_POST['overwrite'];
		$changes   = [];

		$yoast_social = get_option( 'wpseo_social', [] );

		if ( is_array( $yoast_social ) ) {
			$echs_same_as = get_option( 'echs_same_as', [] );

			if ( ! is_array( $echs_same_as ) ) {
				$echs_same_as = [];
			}

			$social_fields = [
				'facebook_site'  => null,
				'twitter_site'   => 'https://x.com/',
				'instagram_url'  => null,
				'linkedin_url'   => null,
				'youtube_url'    => null,
			];

			$added_count = 0;

			foreach ( $social_fields as $field => $prefix ) {
				if ( empty( $yoast_social[ $field ] ) ) {
					continue;
				}

				$url = (string) $yoast_social[ $field ];

				if ( null !== $prefix ) {
					if ( ! preg_match( '#^https?://#i', $url ) ) {
						$url = $prefix . ltrim( $url, '/' );
					}
				}

				if ( in_array( $url, $echs_same_as, true ) ) {
					continue;
				}

				if ( 'facebook_site' === $field || 'twitter_site' === $field ) {
					array_unshift( $echs_same_as, $url );
				} else {
					$echs_same_as[] = $url;
				}

				$added_count++;
			}

			if ( $added_count > 0 ) {
				update_option( 'echs_same_as', $echs_same_as );
				$changes[] = sprintf( 'Added %d social URL(s) to sameAs list', $added_count );
			}
		}

		$yoast_wpseo = get_option( 'wpseo', [] );

		if ( is_array( $yoast_wpseo ) ) {
			if ( ! empty( $yoast_wpseo['company_name'] ) ) {
				$echs_business_name = get_option( 'echs_business_name', '' );

				if ( '' === (string) $echs_business_name || $overwrite ) {
					update_option( 'echs_business_name', $yoast_wpseo['company_name'] );
					$changes[] = 'Business name set from Yoast';
				}
			}

			if ( ! empty( $yoast_wpseo['company_logo'] ) ) {
				$echs_logo_url = get_option( 'echs_logo_url', '' );

				if ( '' === (string) $echs_logo_url || $overwrite ) {
					$logo_url = wp_get_attachment_url( (int) $yoast_wpseo['company_logo'] );

					if ( $logo_url ) {
						update_option( 'echs_logo_url', $logo_url );
						$changes[] = 'Logo URL set from Yoast';
					}
				}
			}
		}

		$yoast_titles = get_option( 'wpseo_titles', [] );

		if ( is_array( $yoast_titles ) && ! empty( $yoast_titles['open_graph_frontpage_image'] ) ) {
			$echs_default_og = get_option( 'echs_default_og_image', '' );

			if ( '' === (string) $echs_default_og || $overwrite ) {
				update_option( 'echs_default_og_image', $yoast_titles['open_graph_frontpage_image'] );
				$changes[] = 'Default OG image set from Yoast';
			}
		}

		wp_send_json_success( [
			'success' => true,
			'changes' => $changes,
		] );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$stats = self::get_stats();
		$nonce = wp_create_nonce( 'echs_yoast_migrate' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( 'Import from Yoast SEO' ); ?></h1>

			<?php if ( $stats['yoast_active'] ) : ?>
				<span style="display:inline-block;background:#00a32a;color:#fff;padding:4px 10px;border-radius:3px;font-weight:600;margin-bottom:16px;">
					<?php echo esc_html( 'Yoast SEO detected' ); ?>
				</span>
			<?php elseif ( $stats['yoast_has_data'] ) : ?>
				<span style="display:inline-block;background:#dba617;color:#fff;padding:4px 10px;border-radius:3px;font-weight:600;margin-bottom:16px;">
					<?php echo esc_html( 'Yoast data found in database (plugin deactivated)' ); ?>
				</span>
			<?php else : ?>
				<div class="notice notice-warning"><p><?php echo esc_html( 'No Yoast SEO data found in this database.' ); ?></p></div>
				<?php return; ?>
			<?php endif; ?>

			<div class="echs-card" style="background:#fff;border:1px solid #c3c4c7;padding:20px 24px;max-width:780px;margin-top:16px;">
				<h2><?php echo esc_html( 'What will be migrated' ); ?></h2>
				<p>
					<?php
					echo wp_kses(
						sprintf(
							'Found <strong>%1$s</strong> posts/pages with Yoast metadata out of %2$s total. <strong>%3$s</strong> already have ECHoS SEO data set.',
							esc_html( (string) $stats['posts_with_yoast'] ),
							esc_html( (string) $stats['posts_total'] ),
							esc_html( (string) $stats['already_migrated'] )
						),
						[ 'strong' => [] ]
					);
					?>
				</p>
				<ul>
					<li>&#10003; <?php echo esc_html( 'SEO title and meta description (per post/page)' ); ?></li>
					<li>&#10003; <?php echo esc_html( 'Focus keyword → primary keyword cluster slot' ); ?></li>
					<li>&#10003; <?php echo esc_html( 'Canonical URLs' ); ?></li>
					<li>&#10003; <?php echo esc_html( 'Open Graph title, description, and image' ); ?></li>
					<li>&#10003; <?php echo esc_html( 'Twitter/X card title, description, and image' ); ?></li>
					<li>&#10003; <?php echo esc_html( 'Noindex / nofollow settings' ); ?></li>
					<li>&#10003; <?php echo esc_html( 'Business name and logo (global settings)' ); ?></li>
					<li>&#10003; <?php echo esc_html( 'Social profile URLs → sameAs list (global settings)' ); ?></li>
					<li>&#10003; <?php echo esc_html( 'Default OG image (global settings)' ); ?></li>
				</ul>

				<label>
					<input type="checkbox" id="echs-yoast-overwrite">
					<?php echo esc_html( 'Overwrite existing ECHoS SEO data (default: skip fields already filled in ECHoS)' ); ?>
				</label>
			</div>

			<div class="echs-card" style="background:#fff;border:1px solid #c3c4c7;padding:20px 24px;max-width:780px;margin-top:16px;">
				<h2><?php echo esc_html( 'Run Migration' ); ?></h2>
				<button id="echs-yoast-start" class="button button-primary button-large">
					<?php echo esc_html( 'Start Import' ); ?>
				</button>
				<div id="echs-yoast-progress" style="display:none; margin-top:16px;">
					<div style="background:#e0e0e0; border-radius:4px; height:20px; overflow:hidden;">
						<div id="echs-yoast-bar" style="background:#2271b1; height:100%; width:0; transition:width 0.3s;"></div>
					</div>
					<p id="echs-yoast-status" style="margin-top:8px;"></p>
				</div>
				<div id="echs-yoast-result" style="display:none; margin-top:16px;"></div>
			</div>
		</div>

		<script>
		(function($){
			var total = <?php echo wp_json_encode( (int) $stats['posts_with_yoast'] ); ?>;
			var processed = 0;
			var migrated = 0, skipped = 0, empty = 0;
			var overwrite = false;

			$('#echs-yoast-start').on('click', function(){
				overwrite = $('#echs-yoast-overwrite').is(':checked');
				$(this).prop('disabled', true);
				$('#echs-yoast-progress').show();
				$('#echs-yoast-result').hide();

				$.post(ajaxurl, {
					action: 'echs_yoast_migrate_global',
					overwrite: overwrite ? 1 : 0,
					_ajax_nonce: '<?php echo esc_js( $nonce ); ?>'
				}, function(res){
					runBatch(0);
				});
			});

			function runBatch(offset) {
				$.post(ajaxurl, {
					action: 'echs_yoast_migrate_batch',
					offset: offset,
					overwrite: overwrite ? 1 : 0,
					_ajax_nonce: '<?php echo esc_js( $nonce ); ?>'
				}, function(res){
					if(!res.success) return;
					var d = res.data;
					processed += d.processed;
					migrated  += d.migrated;
					skipped   += d.skipped;
					empty     += d.empty;

					var pct = total > 0 ? Math.min(100, Math.round(processed / total * 100)) : 100;
					$('#echs-yoast-bar').css('width', pct + '%');
					$('#echs-yoast-status').text('Processing... ' + processed + ' of ' + total + ' posts');

					if(d.done) {
						$('#echs-yoast-status').text('Complete!');
						$('#echs-yoast-result').html(
							'<div class="notice notice-success"><p>' +
							'<strong>Import complete.</strong> ' + migrated + ' fields imported, ' +
							skipped + ' skipped (ECHoS data already present), ' + empty + ' had no Yoast data.' +
							'</p><p>You can now safely deactivate and delete Yoast SEO from ' +
							'<a href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">Plugins &rarr; Installed Plugins</a>.' +
							'</p></div>'
						).show();
					} else {
						runBatch(d.next_offset);
					}
				}, 'json');
			}
		})(jQuery);
		</script>
		<?php
	}
}
