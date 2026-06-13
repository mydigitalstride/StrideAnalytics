<?php
/**
 * Bulk SEO editor — list all pages/posts with editable Title, Meta
 * Description, noindex toggle, readability score, and keyword frequency.
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Bulk_SEO {

	private static array $post_types = [ 'page', 'post', 'product' ];

	public static function init(): void {
		add_action( 'admin_menu',                     [ __CLASS__, 'register_menu' ] );
		add_action( 'wp_ajax_echs_bulk_seo_save',     [ __CLASS__, 'ajax_save' ] );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'echs-settings',
			__( 'Bulk SEO Editor', 'echs' ),
			__( 'Bulk SEO', 'echs' ),
			'manage_options',
			'echs-bulk-seo',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function ajax_save(): void {
		check_ajax_referer( 'echs_bulk_seo_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission denied.' );
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$field   = sanitize_text_field( $_POST['field'] ?? '' );
		$value   = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

		$allowed = [ 'echs_seo_title', 'echs_seo_description', 'echs_noindex' ];
		if ( ! $post_id || ! in_array( $field, $allowed, true ) ) {
			wp_send_json_error( 'Invalid request.' );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, self::$post_types, true ) ) {
			wp_send_json_error( 'Invalid post.' );
		}

		if ( $field === 'echs_noindex' ) {
			$value = ( $value === '1' ) ? '1' : '0';
		}

		update_post_meta( $post_id, $field, $value );

		wp_send_json_success( [ 'post_id' => $post_id, 'field' => $field ] );
	}

	private static function flesch_score( string $text ): ?float {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', trim( $text ) );
		if ( empty( $text ) ) {
			return null;
		}

		$sentences = preg_split( '/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentence_count = count( $sentences );

		preg_match_all( '/[a-z]+/i', $text, $m );
		$words = $m[0];
		$word_count = count( $words );

		if ( $sentence_count < 3 || $word_count < 10 ) {
			return null;
		}

		$syllable_count = 0;
		foreach ( $words as $w ) {
			$syllable_count += self::count_syllables( $w );
		}

		$score = 206.835
			- 1.015 * ( $word_count / $sentence_count )
			- 84.6 * ( $syllable_count / $word_count );

		return max( 0.0, min( 100.0, round( $score, 1 ) ) );
	}

	private static function count_syllables( string $word ): int {
		$word = strtolower( $word );
		if ( strlen( $word ) <= 3 ) {
			return 1;
		}
		$word = preg_replace( '/(?:[^laeiouy]es|ed|[^laeiouy]e)$/', '', $word );
		$word = preg_replace( '/^y/', '', $word );
		preg_match_all( '/[aeiouy]{1,2}/', $word, $m );
		$count = count( $m[0] );
		return max( 1, $count );
	}

	private static function count_keyword( string $content, string $keyword ): int {
		if ( empty( $keyword ) || empty( $content ) ) {
			return 0;
		}
		$text    = strtolower( wp_strip_all_tags( $content ) );
		$needle  = strtolower( $keyword );
		$escaped = preg_quote( $needle, '/' );
		preg_match_all( '/\b' . $escaped . '\b/', $text, $m );
		return count( $m[0] );
	}

	private static function flesch_label( float $score ): array {
		if ( $score >= 60 ) {
			return [ 'color' => '#00a32a', 'label' => 'Easy' ];
		}
		if ( $score >= 30 ) {
			return [ 'color' => '#dba617', 'label' => 'Moderate' ];
		}
		return [ 'color' => '#d63638', 'label' => 'Difficult' ];
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_type = sanitize_text_field( $_GET['post_type'] ?? 'page' );
		if ( ! in_array( $current_type, self::$post_types, true ) ) {
			$current_type = 'page';
		}

		$paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$per_page = 30;

		$query = new WP_Query( [
			'post_type'      => $current_type,
			'post_status'    => [ 'publish', 'draft', 'pending', 'future' ],
			'posts_per_page' => $per_page,
			'paged'          => $paged,
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		$total_pages = $query->max_num_pages;
		$nonce       = wp_create_nonce( 'echs_bulk_seo_nonce' );
		?>
		<div class="wrap echs-settings-wrap">
			<h1><?php esc_html_e( 'Bulk SEO Editor', 'echs' ); ?></h1>

			<div class="echs-bulk-seo-filters" style="margin:12px 0 16px;display:flex;gap:8px;align-items:center;">
				<?php foreach ( self::$post_types as $pt ) :
					$pt_obj = get_post_type_object( $pt );
					if ( ! $pt_obj ) continue;
					$active = ( $pt === $current_type );
				?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=echs-bulk-seo&post_type=' . $pt ) ); ?>"
					   class="button <?php echo $active ? 'button-primary' : ''; ?>">
						<?php echo esc_html( $pt_obj->labels->name ); ?>
					</a>
				<?php endforeach; ?>
				<span style="margin-left:auto;color:#646970;font-size:13px;">
					<?php printf( esc_html__( '%d items found', 'echs' ), $query->found_posts ); ?>
				</span>
			</div>

			<?php if ( ! $query->have_posts() ) : ?>
				<p><?php esc_html_e( 'No posts found.', 'echs' ); ?></p>
			<?php else : ?>

			<table class="wp-list-table widefat fixed striped echs-bulk-seo-table">
				<thead>
					<tr>
						<th class="echs-col-page"><?php esc_html_e( 'Page', 'echs' ); ?></th>
						<th class="echs-col-title"><?php esc_html_e( 'SEO Title', 'echs' ); ?></th>
						<th class="echs-col-desc"><?php esc_html_e( 'Meta Description', 'echs' ); ?></th>
						<th class="echs-col-noindex"><?php esc_html_e( 'Noindex', 'echs' ); ?></th>
						<th class="echs-col-read"><?php esc_html_e( 'Readability', 'echs' ); ?></th>
						<th class="echs-col-kw"><?php esc_html_e( 'Keywords', 'echs' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php while ( $query->have_posts() ) : $query->the_post();
						$post_id   = get_the_ID();
						$post_obj  = get_post( $post_id );
						$title     = get_the_title();
						$seo_title = get_post_meta( $post_id, 'echs_seo_title', true );
						$seo_desc  = get_post_meta( $post_id, 'echs_seo_description', true );
						$noindex   = get_post_meta( $post_id, 'echs_noindex', true ) === '1';
						$status    = get_post_status();
						$content   = $post_obj->post_content ?? '';

						$title_len = mb_strlen( $seo_title );
						$desc_len  = mb_strlen( $seo_desc );

						// Readability.
						$flesch      = self::flesch_score( $content );
						$flesch_info = $flesch !== null ? self::flesch_label( $flesch ) : null;

						// Focus keywords.
						$keywords = get_post_meta( $post_id, 'echs_focus_keywords', true );
						if ( ! is_array( $keywords ) ) {
							$legacy = get_post_meta( $post_id, 'echs_focus_keyword', true );
							$keywords = $legacy ? [ $legacy ] : [];
						}
						$keywords = array_filter( $keywords );

						$kw_counts = [];
						foreach ( $keywords as $kw ) {
							$kw_counts[] = [
								'keyword' => $kw,
								'count'   => self::count_keyword( $content, $kw ),
							];
						}
					?>
					<tr data-post-id="<?php echo esc_attr( $post_id ); ?>">
						<td class="echs-col-page">
							<strong>
								<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
									<?php echo esc_html( $title ?: __( '(no title)', 'echs' ) ); ?>
								</a>
							</strong>
							<?php if ( $status !== 'publish' ) : ?>
								<span class="echs-bulk-status-label"><?php echo esc_html( ucfirst( $status ) ); ?></span>
							<?php endif; ?>
						</td>
						<td class="echs-col-title">
							<input type="text"
								class="echs-bulk-field"
								data-field="echs_seo_title"
								data-post-id="<?php echo esc_attr( $post_id ); ?>"
								value="<?php echo esc_attr( $seo_title ); ?>"
								placeholder="<?php echo esc_attr( $title ); ?>"
							>
							<span class="echs-bulk-charcount">
								<?php echo esc_html( $title_len ); ?> <?php esc_html_e( 'chars', 'echs' ); ?>
							</span>
						</td>
						<td class="echs-col-desc">
							<textarea
								class="echs-bulk-field"
								data-field="echs_seo_description"
								data-post-id="<?php echo esc_attr( $post_id ); ?>"
								rows="2"
								placeholder="<?php esc_attr_e( 'Enter meta description…', 'echs' ); ?>"
							><?php echo esc_textarea( $seo_desc ); ?></textarea>
							<span class="echs-bulk-charcount <?php echo $desc_len > 160 ? 'echs-over' : ''; ?>">
								<?php echo esc_html( $desc_len ); ?>/160
							</span>
						</td>
						<td class="echs-col-noindex" style="text-align:center;">
							<input type="checkbox"
								class="echs-bulk-noindex"
								data-post-id="<?php echo esc_attr( $post_id ); ?>"
								<?php checked( $noindex ); ?>
							>
						</td>
						<td class="echs-col-read" style="text-align:center;">
							<?php if ( $flesch_info ) : ?>
								<span style="color:<?php echo esc_attr( $flesch_info['color'] ); ?>;font-weight:600;font-size:13px;"
									  title="<?php echo esc_attr( $flesch_info['label'] ); ?>">
									<?php echo esc_html( $flesch ); ?>
								</span>
								<span style="display:block;font-size:10px;color:#646970;">
									<?php echo esc_html( $flesch_info['label'] ); ?>
								</span>
							<?php else : ?>
								<span style="color:#646970;font-size:11px;">—</span>
							<?php endif; ?>
						</td>
						<td class="echs-col-kw">
							<?php if ( empty( $kw_counts ) ) : ?>
								<span style="color:#646970;font-size:11px;"><?php esc_html_e( 'No keywords set', 'echs' ); ?></span>
							<?php else : ?>
								<?php foreach ( $kw_counts as $i => $kc ) :
									$count_color = $kc['count'] === 0 ? '#d63638' : ( $kc['count'] >= 3 ? '#00a32a' : '#dba617' );
								?>
									<div class="echs-bulk-kw-row">
										<span class="echs-bulk-kw-name" title="<?php echo esc_attr( $kc['keyword'] ); ?>">
											<?php echo esc_html( $kc['keyword'] ); ?>
										</span>
										<span class="echs-bulk-kw-count" style="color:<?php echo esc_attr( $count_color ); ?>;">
											<?php echo esc_html( $kc['count'] ); ?>×
										</span>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</td>
					</tr>
					<?php endwhile; wp_reset_postdata(); ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav" style="margin-top:12px;">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( [
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $paged,
							'total'   => $total_pages,
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
						] );
						?>
					</div>
				</div>
			<?php endif; ?>

			<?php endif; ?>

			<input type="hidden" id="echs-bulk-seo-nonce" value="<?php echo esc_attr( $nonce ); ?>">
		</div>

		<style>
			.echs-bulk-seo-table th,
			.echs-bulk-seo-table td { vertical-align: top; padding: 8px; }
			.echs-col-page  { width: 20%; }
			.echs-col-title { width: 22%; }
			.echs-col-desc  { width: 26%; }
			.echs-col-noindex { width: 6%; text-align: center; }
			.echs-col-read  { width: 8%; text-align: center; }
			.echs-col-kw    { width: 18%; }
			.echs-bulk-seo-table input.echs-bulk-field,
			.echs-bulk-seo-table textarea.echs-bulk-field { width: 100%; }
			.echs-bulk-seo-table textarea.echs-bulk-field { resize: vertical; min-height: 48px; }
			.echs-bulk-field.echs-bulk-saving { opacity: 0.5; }
			.echs-bulk-field.echs-bulk-saved { border-color: #00a32a; }
			.echs-bulk-field.echs-bulk-error { border-color: #d63638; }
			.echs-bulk-charcount { font-size: 11px; color: #646970; }
			.echs-bulk-charcount.echs-over { color: #d63638; }
			.echs-bulk-status-label { color: #646970; font-size: 11px; }
			.echs-bulk-kw-row {
				display: flex; justify-content: space-between; align-items: center;
				padding: 2px 0; font-size: 12px;
			}
			.echs-bulk-kw-name {
				overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
				max-width: 120px; color: #1d2327;
			}
			.echs-bulk-kw-count { font-weight: 600; white-space: nowrap; margin-left: 4px; }
		</style>

		<script>
		jQuery(function($) {
			var saveTimers = {};

			function updateCharCount($field) {
				var len = $field.val().length;
				var $count = $field.siblings('.echs-bulk-charcount');
				if ($field.data('field') === 'echs_seo_description') {
					$count.text(len + '/160');
					$count.toggleClass('echs-over', len > 160);
				} else {
					$count.text(len + ' chars');
					$count.toggleClass('echs-over', len > 60);
				}
			}

			function saveField(postId, field, value) {
				var key = postId + '-' + field;
				if (saveTimers[key]) clearTimeout(saveTimers[key]);

				saveTimers[key] = setTimeout(function() {
					var $el = $('[data-post-id="' + postId + '"][data-field="' + field + '"]');
					if ($el.length) $el.removeClass('echs-bulk-saved echs-bulk-error').addClass('echs-bulk-saving');

					$.post(ajaxurl, {
						action:  'echs_bulk_seo_save',
						nonce:   $('#echs-bulk-seo-nonce').val(),
						post_id: postId,
						field:   field,
						value:   value
					}).done(function(r) {
						if ($el.length) {
							$el.removeClass('echs-bulk-saving');
							if (r.success) {
								$el.addClass('echs-bulk-saved');
								setTimeout(function() { $el.removeClass('echs-bulk-saved'); }, 1500);
							} else {
								$el.addClass('echs-bulk-error');
							}
						}
					}).fail(function() {
						if ($el.length) $el.removeClass('echs-bulk-saving').addClass('echs-bulk-error');
					});
				}, 800);
			}

			$('.echs-bulk-field').on('input', function() {
				var $el = $(this);
				updateCharCount($el);
				saveField($el.data('post-id'), $el.data('field'), $el.val());
			});

			$('.echs-bulk-noindex').on('change', function() {
				var $cb = $(this);
				var val = $cb.is(':checked') ? '1' : '0';
				saveField($cb.data('post-id'), 'echs_noindex', val);
			});
		});
		</script>
		<?php
	}
}
