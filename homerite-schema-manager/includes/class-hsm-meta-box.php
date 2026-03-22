<?php
/**
 * Per-page meta box — SEO Meta tab + Schema Customizer tab (Phases 3 & 4).
 *
 * @package HomeRite_Schema_Manager
 */

defined( 'ABSPATH' ) || exit;

class HSM_Meta_Box {

	/** Post types that receive the meta box. */
	private static array $post_types = [ 'page', 'post', 'product' ];

	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register' ] );
		add_action( 'save_post',      [ __CLASS__, 'save' ], 10, 2 );
	}

	public static function register(): void {
		foreach ( self::$post_types as $pt ) {
			add_meta_box(
				'hsm_meta_box',
				__( 'SEO &amp; Schema', 'homerite-schema' ),
				[ __CLASS__, 'render' ],
				$pt,
				'normal',
				'high'
			);
		}
	}

	// ------------------------------------------------------------------
	// Save
	// ------------------------------------------------------------------

	public static function save( int $post_id, WP_Post $post ): void {
		// Bail on autosave / revision.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id ) ) return;

		if ( ! isset( $_POST['hsm_meta_box_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['hsm_meta_box_nonce'] ), 'hsm_meta_box_nonce' ) ) {
			return;
		}

		// --- SEO Meta fields ---
		$seo_text_fields = [
			'hsm_seo_title',
			'hsm_seo_description',
			'hsm_canonical_url',
			'hsm_og_title',
			'hsm_og_description',
			'hsm_og_image',
			'hsm_twitter_title',
			'hsm_twitter_description',
			'hsm_twitter_image',
			'hsm_focus_keyword',
		];

		foreach ( $seo_text_fields as $field ) {
			$value = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			update_post_meta( $post_id, $field, $value );
		}

		// Robots toggles.
		$robots_fields = [ 'hsm_noindex', 'hsm_nofollow' ];
		foreach ( $robots_fields as $field ) {
			update_post_meta( $post_id, $field, isset( $_POST[ $field ] ) ? '1' : '0' );
		}

		// --- Schema fields ---
		$schema_types = [ 'LocalBusiness', 'Service', 'Product', 'FAQPage', 'BreadcrumbList', 'WebPage' ];
		$enabled      = [];
		foreach ( $schema_types as $type ) {
			if ( ! empty( $_POST[ 'hsm_schema_enable_' . $type ] ) ) {
				$enabled[] = $type;
			}
		}
		update_post_meta( $post_id, 'hsm_schema_enabled_types', $enabled );

		// LocalBusiness overrides.
		foreach ( [ 'hsm_lb_description', 'hsm_lb_phone', 'hsm_lb_location' ] as $f ) {
			update_post_meta( $post_id, $f, isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : '' );
		}

		// Service fields.
		foreach ( [ 'hsm_service_name', 'hsm_service_description', 'hsm_service_type', 'hsm_service_area_override' ] as $f ) {
			update_post_meta( $post_id, $f, isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : '' );
		}

		// Product fields.
		foreach ( [ 'hsm_product_name', 'hsm_product_description', 'hsm_product_price', 'hsm_product_currency', 'hsm_product_availability', 'hsm_product_warranty', 'hsm_product_brand' ] as $f ) {
			update_post_meta( $post_id, $f, isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : '' );
		}

		// FAQ entries.
		$faqs = [];
		if ( ! empty( $_POST['hsm_faq_question'] ) && is_array( $_POST['hsm_faq_question'] ) ) {
			$questions = array_map( 'sanitize_text_field', wp_unslash( $_POST['hsm_faq_question'] ) );
			$answers   = array_map( 'wp_kses_post', wp_unslash( $_POST['hsm_faq_answer'] ?? [] ) );
			foreach ( $questions as $i => $q ) {
				if ( '' !== $q ) {
					$faqs[] = [ 'question' => $q, 'answer' => $answers[ $i ] ?? '' ];
				}
			}
		}
		update_post_meta( $post_id, 'hsm_faq_entries', $faqs );
	}

	// ------------------------------------------------------------------
	// Render
	// ------------------------------------------------------------------

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'hsm_meta_box_nonce', 'hsm_meta_box_nonce' );

		$enabled_types     = get_post_meta( $post->ID, 'hsm_schema_enabled_types', true ) ?: [];
		$faq_entries       = get_post_meta( $post->ID, 'hsm_faq_entries', true ) ?: [];
		$is_front          = (int) get_option( 'page_on_front' ) === $post->ID;

		$availability_opts = [
			'https://schema.org/InStock'    => 'In Stock',
			'https://schema.org/OutOfStock' => 'Out of Stock',
			'https://schema.org/PreOrder'   => 'Pre-Order',
		];
		?>
		<div id="hsm-meta-box-wrap">
			<!-- Tab Nav -->
			<ul class="hsm-tabs">
				<li class="hsm-tab-link active" data-tab="hsm-tab-seo"><?php esc_html_e( 'SEO Meta', 'homerite-schema' ); ?></li>
				<li class="hsm-tab-link" data-tab="hsm-tab-schema"><?php esc_html_e( 'Schema', 'homerite-schema' ); ?></li>
			</ul>

			<!-- ========== TAB 1: SEO Meta ========== -->
			<div id="hsm-tab-seo" class="hsm-tab-panel active">

				<table class="form-table">
					<tr>
						<th><label for="hsm_seo_title"><?php esc_html_e( 'Title Tag Override', 'homerite-schema' ); ?></label></th>
						<td>
							<input type="text" id="hsm_seo_title" name="hsm_seo_title" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_seo_title', true ) ); ?>" class="large-text">
							<p class="description"><?php esc_html_e( 'Leave blank to use the default WordPress title.', 'homerite-schema' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="hsm_seo_description"><?php esc_html_e( 'Meta Description', 'homerite-schema' ); ?></label></th>
						<td>
							<textarea id="hsm_seo_description" name="hsm_seo_description" rows="3" class="large-text" maxlength="160"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_seo_description', true ) ); ?></textarea>
							<p class="description hsm-char-count" data-max="160"><?php esc_html_e( '0 / 160 characters', 'homerite-schema' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="hsm_canonical_url"><?php esc_html_e( 'Canonical URL', 'homerite-schema' ); ?></label></th>
						<td><input type="url" id="hsm_canonical_url" name="hsm_canonical_url" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_canonical_url', true ) ); ?>" class="large-text"></td>
					</tr>
					<tr>
						<th><label for="hsm_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'homerite-schema' ); ?></label></th>
						<td><input type="text" id="hsm_focus_keyword" name="hsm_focus_keyword" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_focus_keyword', true ) ); ?>" class="regular-text"></td>
					</tr>
				</table>

				<h4><?php esc_html_e( 'Open Graph', 'homerite-schema' ); ?></h4>
				<table class="form-table">
					<tr>
						<th><label for="hsm_og_title"><?php esc_html_e( 'OG Title', 'homerite-schema' ); ?></label></th>
						<td><input type="text" id="hsm_og_title" name="hsm_og_title" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_og_title', true ) ); ?>" class="large-text"></td>
					</tr>
					<tr>
						<th><label for="hsm_og_description"><?php esc_html_e( 'OG Description', 'homerite-schema' ); ?></label></th>
						<td><textarea id="hsm_og_description" name="hsm_og_description" rows="2" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_og_description', true ) ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="hsm_og_image"><?php esc_html_e( 'OG Image URL', 'homerite-schema' ); ?></label></th>
						<td>
							<input type="url" id="hsm_og_image" name="hsm_og_image" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_og_image', true ) ); ?>" class="large-text">
							<button type="button" class="button hsm-upload-image" data-target="hsm_og_image"><?php esc_html_e( 'Choose Image', 'homerite-schema' ); ?></button>
						</td>
					</tr>
				</table>

				<h4><?php esc_html_e( 'Twitter Card', 'homerite-schema' ); ?></h4>
				<table class="form-table">
					<tr>
						<th><label for="hsm_twitter_title"><?php esc_html_e( 'Twitter Title', 'homerite-schema' ); ?></label></th>
						<td><input type="text" id="hsm_twitter_title" name="hsm_twitter_title" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_twitter_title', true ) ); ?>" class="large-text"></td>
					</tr>
					<tr>
						<th><label for="hsm_twitter_description"><?php esc_html_e( 'Twitter Description', 'homerite-schema' ); ?></label></th>
						<td><textarea id="hsm_twitter_description" name="hsm_twitter_description" rows="2" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_twitter_description', true ) ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="hsm_twitter_image"><?php esc_html_e( 'Twitter Image URL', 'homerite-schema' ); ?></label></th>
						<td>
							<input type="url" id="hsm_twitter_image" name="hsm_twitter_image" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_twitter_image', true ) ); ?>" class="large-text">
							<button type="button" class="button hsm-upload-image" data-target="hsm_twitter_image"><?php esc_html_e( 'Choose Image', 'homerite-schema' ); ?></button>
						</td>
					</tr>
				</table>

				<h4><?php esc_html_e( 'Robots', 'homerite-schema' ); ?></h4>
				<p>
					<label>
						<input type="checkbox" name="hsm_noindex" value="1" <?php checked( get_post_meta( $post->ID, 'hsm_noindex', true ), '1' ); ?>>
						<?php esc_html_e( 'noindex — exclude from search engines', 'homerite-schema' ); ?>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" name="hsm_nofollow" value="1" <?php checked( get_post_meta( $post->ID, 'hsm_nofollow', true ), '1' ); ?>>
						<?php esc_html_e( 'nofollow — do not follow links on this page', 'homerite-schema' ); ?>
					</label>
				</p>
			</div><!-- #hsm-tab-seo -->

			<!-- ========== TAB 2: Schema ========== -->
			<div id="hsm-tab-schema" class="hsm-tab-panel">

				<h4><?php esc_html_e( 'Enable Schema Types', 'homerite-schema' ); ?></h4>
				<div class="hsm-schema-types">
					<?php
					$schema_type_list = [
						'LocalBusiness'  => 'LocalBusiness ' . ( $is_front ? '(auto-on for homepage)' : '' ),
						'Service'        => 'Service',
						'Product'        => 'Product',
						'FAQPage'        => 'FAQPage',
						'BreadcrumbList' => 'BreadcrumbList (auto-generated)',
						'WebPage'        => 'WebPage / AboutPage / ContactPage',
					];
					foreach ( $schema_type_list as $type => $label ) :
						$checked = in_array( $type, $enabled_types, true ) || ( $is_front && 'LocalBusiness' === $type );
					?>
						<label class="hsm-schema-type-toggle">
							<input type="checkbox" name="<?php echo esc_attr( 'hsm_schema_enable_' . $type ); ?>" value="1"
								<?php checked( $checked ); ?>
								data-reveals="hsm-schema-section-<?php echo esc_attr( strtolower( $type ) ); ?>">
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>

				<!-- LocalBusiness overrides -->
				<div id="hsm-schema-section-localbusiness" class="hsm-schema-section <?php echo in_array( 'LocalBusiness', $enabled_types, true ) || $is_front ? 'active' : ''; ?>">
					<h4><?php esc_html_e( 'LocalBusiness Override Fields', 'homerite-schema' ); ?></h4>
					<table class="form-table">
						<tr>
							<th><label for="hsm_lb_description"><?php esc_html_e( 'Description Override', 'homerite-schema' ); ?></label></th>
							<td><textarea id="hsm_lb_description" name="hsm_lb_description" rows="3" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_lb_description', true ) ); ?></textarea></td>
						</tr>
						<tr>
							<th><label for="hsm_lb_phone"><?php esc_html_e( 'Phone Override', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_lb_phone" name="hsm_lb_phone" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_lb_phone', true ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_lb_location"><?php esc_html_e( 'Location Note', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_lb_location" name="hsm_lb_location" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_lb_location', true ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Boalsburg office', 'homerite-schema' ); ?>"></td>
						</tr>
					</table>
				</div>

				<!-- Service fields -->
				<div id="hsm-schema-section-service" class="hsm-schema-section <?php echo in_array( 'Service', $enabled_types, true ) ? 'active' : ''; ?>">
					<h4><?php esc_html_e( 'Service Schema', 'homerite-schema' ); ?></h4>
					<table class="form-table">
						<tr>
							<th><label for="hsm_service_name"><?php esc_html_e( 'Service Name', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_service_name" name="hsm_service_name" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_service_name', true ) ); ?>" class="large-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_service_description"><?php esc_html_e( 'Service Description', 'homerite-schema' ); ?></label></th>
							<td><textarea id="hsm_service_description" name="hsm_service_description" rows="3" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_service_description', true ) ); ?></textarea></td>
						</tr>
						<tr>
							<th><label for="hsm_service_type"><?php esc_html_e( 'Service Type / Category', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_service_type" name="hsm_service_type" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_service_type', true ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_service_area_override"><?php esc_html_e( 'Area Served Override', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_service_area_override" name="hsm_service_area_override" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_service_area_override', true ) ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Leave blank to use global service areas', 'homerite-schema' ); ?>"></td>
						</tr>
					</table>
				</div>

				<!-- Product fields -->
				<div id="hsm-schema-section-product" class="hsm-schema-section <?php echo in_array( 'Product', $enabled_types, true ) ? 'active' : ''; ?>">
					<h4><?php esc_html_e( 'Product Schema', 'homerite-schema' ); ?></h4>
					<table class="form-table">
						<tr>
							<th><label for="hsm_product_name"><?php esc_html_e( 'Product Name Override', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_product_name" name="hsm_product_name" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_product_name', true ) ); ?>" class="large-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_product_description"><?php esc_html_e( 'Product Description', 'homerite-schema' ); ?></label></th>
							<td><textarea id="hsm_product_description" name="hsm_product_description" rows="3" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_product_description', true ) ); ?></textarea></td>
						</tr>
						<tr>
							<th><label for="hsm_product_price"><?php esc_html_e( 'Price Override', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_product_price" name="hsm_product_price" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_product_price', true ) ); ?>" class="small-text" placeholder="<?php esc_attr_e( 'Auto-filled from WooCommerce', 'homerite-schema' ); ?>"></td>
						</tr>
						<tr>
							<th><label for="hsm_product_currency"><?php esc_html_e( 'Currency', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_product_currency" name="hsm_product_currency" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_product_currency', true ) ?: 'USD' ); ?>" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_product_availability"><?php esc_html_e( 'Availability', 'homerite-schema' ); ?></label></th>
							<td>
								<select id="hsm_product_availability" name="hsm_product_availability">
									<?php foreach ( $availability_opts as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( get_post_meta( $post->ID, 'hsm_product_availability', true ), $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><label for="hsm_product_brand"><?php esc_html_e( 'Brand', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_product_brand" name="hsm_product_brand" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_product_brand', true ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_product_warranty"><?php esc_html_e( 'Warranty Description', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_product_warranty" name="hsm_product_warranty" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_product_warranty', true ) ); ?>" class="large-text"></td>
						</tr>
					</table>
				</div>

				<!-- FAQ editor -->
				<div id="hsm-schema-section-faqpage" class="hsm-schema-section <?php echo in_array( 'FAQPage', $enabled_types, true ) ? 'active' : ''; ?>">
					<h4><?php esc_html_e( 'FAQ Editor', 'homerite-schema' ); ?></h4>
					<div id="hsm-faq-list">
						<?php if ( empty( $faq_entries ) ) : ?>
							<div class="hsm-faq-row">
								<div class="hsm-faq-handle">&#9776;</div>
								<div class="hsm-faq-fields">
									<input type="text" name="hsm_faq_question[]" placeholder="<?php esc_attr_e( 'Question', 'homerite-schema' ); ?>" class="large-text">
									<textarea name="hsm_faq_answer[]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Answer', 'homerite-schema' ); ?>"></textarea>
								</div>
								<button type="button" class="button hsm-remove-faq-row"><?php esc_html_e( 'Remove', 'homerite-schema' ); ?></button>
							</div>
						<?php else :
							foreach ( $faq_entries as $faq ) : ?>
								<div class="hsm-faq-row">
									<div class="hsm-faq-handle">&#9776;</div>
									<div class="hsm-faq-fields">
										<input type="text" name="hsm_faq_question[]" value="<?php echo esc_attr( $faq['question'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Question', 'homerite-schema' ); ?>">
										<textarea name="hsm_faq_answer[]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Answer', 'homerite-schema' ); ?>"><?php echo esc_textarea( $faq['answer'] ); ?></textarea>
									</div>
									<button type="button" class="button hsm-remove-faq-row"><?php esc_html_e( 'Remove', 'homerite-schema' ); ?></button>
								</div>
							<?php endforeach;
						endif; ?>
					</div>
					<button type="button" class="button" id="hsm-add-faq-row"><?php esc_html_e( '+ Add FAQ', 'homerite-schema' ); ?></button>
				</div>

			</div><!-- #hsm-tab-schema -->
		</div><!-- #hsm-meta-box-wrap -->
		<?php
	}
}
