<?php
/**
 * Bulk SEO editor — list all pages/posts with editable Title and Meta
 * Description fields so users can mass-edit without opening each post.
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

		$post_id     = (int) ( $_POST['post_id'] ?? 0 );
		$field       = sanitize_text_field( $_POST['field'] ?? '' );
		$value       = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );

		if ( ! $post_id || ! in_array( $field, [ 'echs_seo_title', 'echs_seo_description' ], true ) ) {
			wp_send_json_error( 'Invalid request.' );
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, self::$post_types, true ) ) {
			wp_send_json_error( 'Invalid post.' );
		}

		update_post_meta( $post_id, $field, $value );

		wp_send_json_success( [ 'post_id' => $post_id, 'field' => $field ] );
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_type = sanitize_text_field( $_GET['post_type'] ?? 'page' );
		if ( ! in_array( $current_type, self::$post_types, true ) ) {
			$current_type = 'page';
		}

		$paged = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
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

		$type_obj = get_post_type_object( $current_type );
		$type_label = $type_obj ? $type_obj->labels->name : ucfirst( $current_type );

		$nonce = wp_create_nonce( 'echs_bulk_seo_nonce' );
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
						<th style="width:30%;"><?php esc_html_e( 'Page', 'echs' ); ?></th>
						<th style="width:30%;"><?php esc_html_e( 'SEO Title', 'echs' ); ?></th>
						<th style="width:30%;"><?php esc_html_e( 'Meta Description', 'echs' ); ?></th>
						<th style="width:10%;text-align:center;"><?php esc_html_e( 'Status', 'echs' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php while ( $query->have_posts() ) : $query->the_post();
						$post_id   = get_the_ID();
						$title     = get_the_title();
						$seo_title = get_post_meta( $post_id, 'echs_seo_title', true );
						$seo_desc  = get_post_meta( $post_id, 'echs_seo_description', true );
						$status    = get_post_status();

						$has_title = ! empty( $seo_title );
						$has_desc  = ! empty( $seo_desc );
						$title_len = mb_strlen( $seo_title );
						$desc_len  = mb_strlen( $seo_desc );
					?>
					<tr data-post-id="<?php echo esc_attr( $post_id ); ?>">
						<td>
							<strong>
								<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">
									<?php echo esc_html( $title ?: __( '(no title)', 'echs' ) ); ?>
								</a>
							</strong>
							<?php if ( $status !== 'publish' ) : ?>
								<span style="color:#646970;font-size:11px;"> — <?php echo esc_html( ucfirst( $status ) ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<input type="text"
								class="echs-bulk-field large-text"
								data-field="echs_seo_title"
								data-post-id="<?php echo esc_attr( $post_id ); ?>"
								value="<?php echo esc_attr( $seo_title ); ?>"
								placeholder="<?php echo esc_attr( $title ); ?>"
							>
							<span class="echs-bulk-charcount" style="font-size:11px;color:#646970;">
								<?php echo esc_html( $title_len ); ?> <?php esc_html_e( 'chars', 'echs' ); ?>
							</span>
						</td>
						<td>
							<textarea
								class="echs-bulk-field large-text"
								data-field="echs_seo_description"
								data-post-id="<?php echo esc_attr( $post_id ); ?>"
								rows="2"
								placeholder="<?php esc_attr_e( 'Enter meta description…', 'echs' ); ?>"
							><?php echo esc_textarea( $seo_desc ); ?></textarea>
							<span class="echs-bulk-charcount" style="font-size:11px;color:#646970;">
								<?php echo esc_html( $desc_len ); ?>/160 <?php esc_html_e( 'chars', 'echs' ); ?>
							</span>
						</td>
						<td style="text-align:center;">
							<?php if ( $has_title && $has_desc ) : ?>
								<span style="color:#00a32a;font-size:16px;" title="<?php esc_attr_e( 'Title and description set', 'echs' ); ?>">&#10003;</span>
							<?php elseif ( $has_title || $has_desc ) : ?>
								<span style="color:#dba617;font-size:16px;" title="<?php esc_attr_e( 'Partially complete', 'echs' ); ?>">&#9679;</span>
							<?php else : ?>
								<span style="color:#d63638;font-size:16px;" title="<?php esc_attr_e( 'Missing title and description', 'echs' ); ?>">&#10007;</span>
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
			.echs-bulk-seo-table td { vertical-align: top; padding: 8px; }
			.echs-bulk-seo-table input.echs-bulk-field,
			.echs-bulk-seo-table textarea.echs-bulk-field { width: 100%; }
			.echs-bulk-seo-table textarea.echs-bulk-field { resize: vertical; min-height: 48px; }
			.echs-bulk-field.echs-bulk-saving { opacity: 0.5; }
			.echs-bulk-field.echs-bulk-saved { border-color: #00a32a; }
			.echs-bulk-field.echs-bulk-error { border-color: #d63638; }
		</style>

		<script>
		jQuery(function($) {
			var saveTimers = {};

			function updateCharCount($field) {
				var len = $field.val().length;
				var $count = $field.siblings('.echs-bulk-charcount');
				if ($field.data('field') === 'echs_seo_description') {
					$count.text(len + '/160 chars');
					$count.css('color', len > 160 ? '#d63638' : '#646970');
				} else {
					$count.text(len + ' chars');
					$count.css('color', (len > 60 ? '#d63638' : '#646970'));
				}
			}

			function updateStatusIcon($row) {
				var hasTitle = !!$row.find('[data-field="echs_seo_title"]').val().trim();
				var hasDesc  = !!$row.find('[data-field="echs_seo_description"]').val().trim();
				var $td = $row.find('td:last');
				if (hasTitle && hasDesc) {
					$td.html('<span style="color:#00a32a;font-size:16px;" title="Title and description set">&#10003;</span>');
				} else if (hasTitle || hasDesc) {
					$td.html('<span style="color:#dba617;font-size:16px;" title="Partially complete">&#9679;</span>');
				} else {
					$td.html('<span style="color:#d63638;font-size:16px;" title="Missing title and description">&#10007;</span>');
				}
			}

			$('.echs-bulk-field').on('input', function() {
				var $el = $(this);
				var key = $el.data('post-id') + '-' + $el.data('field');

				updateCharCount($el);

				if (saveTimers[key]) clearTimeout(saveTimers[key]);

				saveTimers[key] = setTimeout(function() {
					$el.removeClass('echs-bulk-saved echs-bulk-error').addClass('echs-bulk-saving');

					$.post(ajaxurl, {
						action:  'echs_bulk_seo_save',
						nonce:   $('#echs-bulk-seo-nonce').val(),
						post_id: $el.data('post-id'),
						field:   $el.data('field'),
						value:   $el.val()
					}).done(function(r) {
						$el.removeClass('echs-bulk-saving');
						if (r.success) {
							$el.addClass('echs-bulk-saved');
							updateStatusIcon($el.closest('tr'));
							setTimeout(function() { $el.removeClass('echs-bulk-saved'); }, 1500);
						} else {
							$el.addClass('echs-bulk-error');
						}
					}).fail(function() {
						$el.removeClass('echs-bulk-saving').addClass('echs-bulk-error');
					});
				}, 800);
			});
		});
		</script>
		<?php
	}
}
