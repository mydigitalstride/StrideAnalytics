<?php
/**
 * Per-page meta box — SEO Meta tab + Schema Customizer tab.
 *
 * @package HomeRite_Schema_Manager
 */

defined( 'ABSPATH' ) || exit;

class HSM_Meta_Box {

	/** Post types that receive the meta box. */
	private static array $post_types = [ 'page', 'post', 'product' ];

	public static function init(): void {
		add_action( 'add_meta_boxes',             [ __CLASS__, 'register' ] );
		add_action( 'save_post',                  [ __CLASS__, 'save' ], 10, 2 );
		add_action( 'wp_ajax_hsm_scan_content',   [ __CLASS__, 'ajax_scan_content' ] );
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
	// Tooltip helper
	// ------------------------------------------------------------------

	private static function tip( string $text ): string {
		return '<span class="hsm-tooltip" tabindex="0" aria-label="' . esc_attr( $text ) . '">?'
			. '<span class="hsm-tooltip-text" role="tooltip">' . esc_html( $text ) . '</span>'
			. '</span>';
	}

	// ------------------------------------------------------------------
	// Save
	// ------------------------------------------------------------------

	public static function save( int $post_id, WP_Post $post ): void {
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

		// OG / Twitter "same as SEO Meta" flags.
		// Checkbox present = '1' (same as meta / collapsed). Absent = '0' (custom).
		update_post_meta( $post_id, 'hsm_og_same_as_meta',      isset( $_POST['hsm_og_same_as_meta'] )      ? '1' : '0' );
		update_post_meta( $post_id, 'hsm_twitter_same_as_meta', isset( $_POST['hsm_twitter_same_as_meta'] ) ? '1' : '0' );

		// Robots toggles.
		$robots_fields = [ 'hsm_noindex', 'hsm_nofollow' ];
		foreach ( $robots_fields as $field ) {
			update_post_meta( $post_id, $field, isset( $_POST[ $field ] ) ? '1' : '0' );
		}

		// --- Schema fields ---
		$schema_types = [ 'LocalBusiness', 'Service', 'Product', 'FAQPage', 'HowTo', 'Review', 'BreadcrumbList', 'WebPage' ];
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

		// HowTo fields.
		foreach ( [ 'hsm_howto_name', 'hsm_howto_description', 'hsm_howto_total_time' ] as $f ) {
			update_post_meta( $post_id, $f, isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : '' );
		}
		$howto_steps = [];
		if ( ! empty( $_POST['hsm_howto_step_name'] ) && is_array( $_POST['hsm_howto_step_name'] ) ) {
			$step_names = array_map( 'sanitize_text_field', wp_unslash( $_POST['hsm_howto_step_name'] ) );
			$step_texts = array_map( 'wp_kses_post', wp_unslash( $_POST['hsm_howto_step_text'] ?? [] ) );
			foreach ( $step_names as $i => $name ) {
				if ( '' !== $name ) {
					$howto_steps[] = [ 'name' => $name, 'text' => $step_texts[ $i ] ?? '' ];
				}
			}
		}
		update_post_meta( $post_id, 'hsm_howto_steps', $howto_steps );

		// Review fields.
		foreach ( [ 'hsm_review_name', 'hsm_review_body', 'hsm_review_rating', 'hsm_review_author', 'hsm_review_item_name' ] as $f ) {
			update_post_meta( $post_id, $f, isset( $_POST[ $f ] ) ? sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) : '' );
		}
	}

	// ------------------------------------------------------------------
	// AJAX: Scan Content
	// ------------------------------------------------------------------

	public static function ajax_scan_content(): void {
		check_ajax_referer( 'hsm_meta_box_nonce', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( 'Invalid post ID.' );
		}

		$result = [
			'seo_title'           => '',
			'seo_description'     => '',
			'service_name'        => '',
			'service_description' => '',
			'product_name'        => '',
			'product_description' => '',
			'faqs'                => [],
			'howto_steps'         => [],
			'acf_fields'          => [],
			'page_text'           => '',  // Plain text for Content Analysis keyword density.
			'source'              => '',
		];

		// ── 1. ACF fields (fast, in-process) ──────────────────────────────
		if ( function_exists( 'get_fields' ) ) {
			$acf = get_fields( $post_id );
			if ( $acf ) {
				$result['acf_fields'] = self::flatten_acf( (array) $acf );
				self::hydrate_from_acf( $result['acf_fields'], $result );
			}
		}

		// ── 2. WordPress post content (the_content with filters applied) ──
		$post = get_post( $post_id );
		if ( $post ) {
			$content = apply_filters( 'the_content', $post->post_content );
			if ( '' !== $content ) {
				self::hydrate_from_html( $content, $result );
				$result['source'] = 'post_content';
			}
		}

		// ── 3. Rendered front-end HTML (catches page-builder output) ──────
		$url      = get_permalink( $post_id );
		$response = wp_remote_get( $url, [
			'timeout'   => 20,
			'sslverify' => false,
			'headers'   => [ 'X-HSM-Scan' => '1' ],
		] );

		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$html = wp_remote_retrieve_body( $response );

			// Extract plain text for keyword density analysis.
			$result['page_text'] = self::html_to_plain_text( $html );

			// Use rendered HTML result only for fields still empty after post_content pass.
			$rendered = [
				'seo_title'           => '',
				'seo_description'     => '',
				'service_name'        => '',
				'service_description' => '',
				'product_name'        => '',
				'product_description' => '',
				'faqs'                => [],
				'howto_steps'         => [],
			];
			self::hydrate_from_html( $html, $rendered );

			foreach ( [ 'seo_title', 'seo_description', 'service_name', 'service_description', 'product_name', 'product_description' ] as $k ) {
				if ( '' === $result[ $k ] && '' !== $rendered[ $k ] ) {
					$result[ $k ] = $rendered[ $k ];
				}
			}
			if ( empty( $result['faqs'] ) && ! empty( $rendered['faqs'] ) ) {
				$result['faqs'] = $rendered['faqs'];
			}
			if ( empty( $result['howto_steps'] ) && ! empty( $rendered['howto_steps'] ) ) {
				$result['howto_steps'] = $rendered['howto_steps'];
			}
			$result['source'] = 'rendered_html';
		} elseif ( '' === $result['page_text'] ) {
			// Fallback: use ACF flat values as text source.
			$result['page_text'] = implode( ' ', array_filter( $result['acf_fields'], 'is_string' ) );
		}

		wp_send_json_success( $result );
	}

	// ── HTML parser ────────────────────────────────────────────────────────

	private static function hydrate_from_html( string $html, array &$result ): void {
		if ( '' === trim( $html ) ) return;

		libxml_use_internal_errors( true );
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		$xpath = new DOMXPath( $dom );

		// H1 → SEO title / service name / product name.
		foreach ( $xpath->query( '//main//h1 | //article//h1 | //div[contains(@class,"entry")]//h1 | //h1' ) as $node ) {
			$text = self::clean_text( $node->textContent );
			if ( '' !== $text ) {
				if ( '' === $result['seo_title'] )       $result['seo_title']       = $text;
				if ( '' === $result['service_name'] )    $result['service_name']    = $text;
				if ( '' === $result['product_name'] )    $result['product_name']    = $text;
				break;
			}
		}

		// First substantive <p> → meta description / service/product description.
		foreach ( $xpath->query( '//main//p | //article//p | //div[contains(@class,"entry")]//p | //p' ) as $node ) {
			$text = self::clean_text( $node->textContent );
			if ( strlen( $text ) >= 40 ) {
				if ( '' === $result['seo_description'] )     $result['seo_description']     = substr( $text, 0, 160 );
				if ( '' === $result['service_description'] ) $result['service_description'] = $text;
				if ( '' === $result['product_description'] ) $result['product_description'] = $text;
				break;
			}
		}

		// FAQ patterns (runs only if no FAQs found yet).
		if ( empty( $result['faqs'] ) ) {
			$result['faqs'] = self::extract_faqs( $xpath );
		}

		// HowTo steps from <ol><li>.
		if ( empty( $result['howto_steps'] ) ) {
			$result['howto_steps'] = self::extract_steps( $xpath );
		}
	}

	private static function extract_faqs( DOMXPath $xpath ): array {
		$faqs = [];

		// Pattern A: headings containing '?' followed by sibling element.
		foreach ( $xpath->query( '//h2 | //h3 | //h4' ) as $h ) {
			$q = self::clean_text( $h->textContent );
			if ( strpos( $q, '?' ) !== false ) {
				$sibling = $h->nextSibling;
				while ( $sibling && XML_TEXT_NODE === $sibling->nodeType ) {
					$sibling = $sibling->nextSibling;
				}
				if ( $sibling ) {
					$a = self::clean_text( $sibling->textContent );
					if ( '' !== $a ) {
						$faqs[] = [ 'question' => $q, 'answer' => $a ];
					}
				}
			}
			if ( count( $faqs ) >= 10 ) break;
		}

		// Pattern B: <dl><dt>/<dd>.
		if ( empty( $faqs ) ) {
			foreach ( $xpath->query( '//dl/dt' ) as $dt ) {
				$q  = self::clean_text( $dt->textContent );
				$dd = $xpath->query( 'following-sibling::dd[1]', $dt )->item(0);
				if ( $dd && '' !== $q ) {
					$faqs[] = [ 'question' => $q, 'answer' => self::clean_text( $dd->textContent ) ];
				}
				if ( count( $faqs ) >= 10 ) break;
			}
		}

		// Pattern C: <details><summary>.
		if ( empty( $faqs ) ) {
			foreach ( $xpath->query( '//details' ) as $detail ) {
				$summary = $xpath->query( 'summary', $detail )->item(0);
				if ( $summary ) {
					$q = self::clean_text( $summary->textContent );
					$a = self::clean_text( str_replace( $summary->textContent, '', $detail->textContent ) );
					if ( '' !== $q && '' !== $a ) {
						$faqs[] = [ 'question' => $q, 'answer' => $a ];
					}
				}
				if ( count( $faqs ) >= 10 ) break;
			}
		}

		return array_slice( $faqs, 0, 10 );
	}

	private static function extract_steps( DOMXPath $xpath ): array {
		$steps = [];

		foreach ( $xpath->query( '//main//ol | //article//ol | //div[contains(@class,"entry")]//ol | //ol' ) as $ol ) {
			$lis = $xpath->query( 'li', $ol );
			if ( $lis->length < 2 ) continue;
			foreach ( $lis as $li ) {
				$text = self::clean_text( $li->textContent );
				if ( '' === $text ) continue;
				// Use first sentence (≤80 chars) as the step name.
				preg_match( '/^[^.!?]{1,80}/', $text, $m );
				$name    = isset( $m[0] ) ? trim( $m[0] ) : substr( $text, 0, 60 );
				$steps[] = [ 'name' => $name, 'text' => $text ];
			}
			break; // First qualifying <ol> only.
		}

		return array_slice( $steps, 0, 10 );
	}

	// ── ACF helpers ────────────────────────────────────────────────────────

	/**
	 * Recursively flatten ACF fields to key → scalar value for display.
	 *
	 * Handles three ACF array types:
	 *   - Flexible Content: array of layouts, each with an 'acf_fc_layout' key.
	 *     Each layout is recursed individually keyed as field.layout_name[i].
	 *     The whole layout is also stored as JSON under field.layout_name so
	 *     hydrate_from_acf() can match it by name (e.g. 'faqs', 'how_to_steps').
	 *   - Repeater: numeric array where every element is an associative array
	 *     (no acf_fc_layout key). Stored as JSON under the field key.
	 *   - Group / sub-field object: associative array (non-numeric keys).
	 *     Recursed normally.
	 */
	private static function flatten_acf( array $acf, string $prefix = '' ): array {
		$out = [];
		foreach ( $acf as $key => $value ) {
			$full_key = $prefix ? $prefix . '.' . $key : (string) $key;

			if ( ! is_array( $value ) ) {
				if ( is_string( $value ) || is_numeric( $value ) ) {
					$out[ $full_key ] = (string) $value;
				}
				continue;
			}

			// Flexible Content: first element has 'acf_fc_layout'.
			if ( isset( $value[0] ) && is_array( $value[0] ) && array_key_exists( 'acf_fc_layout', $value[0] ) ) {
				foreach ( $value as $i => $layout ) {
					if ( ! is_array( $layout ) ) continue;
					$layout_name = isset( $layout['acf_fc_layout'] ) ? (string) $layout['acf_fc_layout'] : "layout_{$i}";
					$sub_fields  = array_diff_key( $layout, [ 'acf_fc_layout' => '' ] );
					// Store whole layout as JSON (for name-based matching in hydrate).
					$layout_key          = $full_key . '.' . $layout_name;
					$out[ $layout_key ] = wp_json_encode( array_values( (array) $sub_fields ) === $sub_fields
						? $sub_fields
						: [ $sub_fields ]
					);
					// Recurse into individual sub-fields for scalar extraction.
					$nested = self::flatten_acf( $sub_fields, $layout_key . '[' . $i . ']' );
					$out    = array_merge( $out, $nested );
				}
				continue;
			}

			// Repeater: numeric array of associative rows.
			if ( isset( $value[0] ) && is_array( $value[0] ) ) {
				$out[ $full_key ] = wp_json_encode( $value );
				// Also recurse so scalar sub-fields are individually visible.
				foreach ( $value as $i => $row ) {
					if ( is_array( $row ) ) {
						$nested = self::flatten_acf( $row, $full_key . '[' . $i . ']' );
						$out    = array_merge( $out, $nested );
					}
				}
				continue;
			}

			// Group / nested object (associative, non-numeric keys).
			$nested = self::flatten_acf( $value, $full_key );
			$out    = array_merge( $out, $nested );
		}
		return $out;
	}

	/**
	 * Try to map well-known ACF field names to schema fields.
	 */
	private static function hydrate_from_acf( array $flat, array &$result ): void {
		$title_keys = [ 'title', 'heading', 'name', 'page_title', 'service_title', 'product_title' ];
		$desc_keys  = [ 'description', 'content', 'summary', 'excerpt', 'intro', 'body', 'text', 'overview' ];
		$faq_keys   = [ 'faqs', 'faq', 'faq_items', 'questions', 'qa', 'q_and_a' ];
		$step_keys  = [ 'steps', 'how_to_steps', 'howto_steps', 'instructions', 'process' ];

		foreach ( $flat as $key => $value ) {
			$base = strtolower( basename( str_replace( '.', '/', $key ) ) );

			if ( '' === $result['seo_title'] && in_array( $base, $title_keys, true ) && is_string( $value ) ) {
				$result['seo_title']    = $value;
				$result['service_name'] = $value;
				$result['product_name'] = $value;
			}

			if ( '' === $result['seo_description'] && in_array( $base, $desc_keys, true ) && is_string( $value ) && strlen( $value ) >= 20 ) {
				$result['seo_description']     = substr( $value, 0, 160 );
				$result['service_description'] = $value;
				$result['product_description'] = $value;
			}

			// FAQ repeater: JSON-encoded array of rows.
			if ( empty( $result['faqs'] ) && in_array( $base, $faq_keys, true ) ) {
				$rows = json_decode( $value, true );
				if ( is_array( $rows ) ) {
					foreach ( $rows as $row ) {
						$q = $row['question'] ?? $row['q'] ?? $row['faq_question'] ?? '';
						$a = $row['answer']   ?? $row['a'] ?? $row['faq_answer']   ?? '';
						if ( '' !== $q ) {
							$result['faqs'][] = [ 'question' => $q, 'answer' => $a ];
						}
					}
				}
			}

			// Steps repeater.
			if ( empty( $result['howto_steps'] ) && in_array( $base, $step_keys, true ) ) {
				$rows = json_decode( $value, true );
				if ( is_array( $rows ) ) {
					foreach ( $rows as $row ) {
						$name = $row['title'] ?? $row['name'] ?? $row['step_title'] ?? '';
						$text = $row['description'] ?? $row['content'] ?? $row['step_description'] ?? $row['text'] ?? '';
						if ( '' !== $name || '' !== $text ) {
							$result['howto_steps'][] = [ 'name' => $name ?: $text, 'text' => $text ?: $name ];
						}
					}
				}
			}
		}
	}

	private static function clean_text( string $text ): string {
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/**
	 * Strip HTML tags and scripts from a full page HTML string, returning
	 * visible body text suitable for keyword density analysis.
	 */
	private static function html_to_plain_text( string $html ): string {
		// Remove <script>, <style>, <noscript>, <nav>, <footer>, <header> blocks entirely.
		$html = preg_replace( '#<(script|style|noscript|nav|header|footer)[^>]*>.*?</\1>#is', ' ', $html );
		// Strip all remaining tags.
		$text = wp_strip_all_tags( $html );
		// Collapse whitespace.
		return self::clean_text( $text );
	}

	// ------------------------------------------------------------------
	// Render
	// ------------------------------------------------------------------

	public static function render( WP_Post $post ): void {
		wp_nonce_field( 'hsm_meta_box_nonce', 'hsm_meta_box_nonce' );

		$enabled_types = get_post_meta( $post->ID, 'hsm_schema_enabled_types', true ) ?: [];
		$faq_entries   = get_post_meta( $post->ID, 'hsm_faq_entries', true ) ?: [];
		$howto_steps   = get_post_meta( $post->ID, 'hsm_howto_steps', true ) ?: [];
		$is_front      = (int) get_option( 'page_on_front' ) === $post->ID;

		// OG / Twitter sync flags. Default '1' (same as meta) for new/unsaved posts.
		$og_same_meta  = get_post_meta( $post->ID, 'hsm_og_same_as_meta', true );
		$tw_same_meta  = get_post_meta( $post->ID, 'hsm_twitter_same_as_meta', true );
		$og_synced     = ( '0' !== $og_same_meta );   // '' or '1' → synced (default on)
		$tw_synced     = ( '0' !== $tw_same_meta );

		$availability_opts = [
			'https://schema.org/InStock'    => 'In Stock',
			'https://schema.org/OutOfStock' => 'Out of Stock',
			'https://schema.org/PreOrder'   => 'Pre-Order',
		];
		?>
		<div id="hsm-meta-box-wrap" data-post-id="<?php echo esc_attr( $post->ID ); ?>">
			<!-- Tab Nav -->
			<ul class="hsm-tabs">
				<li class="hsm-tab-link active" data-tab="hsm-tab-seo"><?php esc_html_e( 'SEO Meta', 'homerite-schema' ); ?></li>
				<li class="hsm-tab-link" data-tab="hsm-tab-schema"><?php esc_html_e( 'Schema', 'homerite-schema' ); ?></li>
				<li class="hsm-tab-link" data-tab="hsm-tab-analysis"><?php esc_html_e( 'Content Analysis', 'homerite-schema' ); ?></li>
			</ul>

			<!-- ========== TAB 1: SEO Meta ========== -->
			<div id="hsm-tab-seo" class="hsm-tab-panel active">

				<!-- ── Core SEO ── -->
				<table class="form-table">
					<tr>
						<th>
							<label for="hsm_seo_title"><?php esc_html_e( 'Title Tag Override', 'homerite-schema' ); ?></label>
							<?php echo self::tip( 'Overrides the browser tab title and the blue headline shown in Google search results. Leave blank to use the default WordPress title.' ); // phpcs:ignore ?>
						</th>
						<td>
							<input type="text" id="hsm_seo_title" name="hsm_seo_title"
								value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_seo_title', true ) ); ?>"
								class="large-text">
							<p class="description hsm-best-practice">
								&#128270; <?php esc_html_e( 'Best practice: 50–60 characters. Lead with your primary keyword. End with your brand name. Example: "Roof Replacement in Harrisburg – Acme Roofing"', 'homerite-schema' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="hsm_seo_description"><?php esc_html_e( 'Meta Description', 'homerite-schema' ); ?></label>
							<?php echo self::tip( 'The short paragraph shown beneath your page title in search results. Not a direct ranking factor, but a well-written description boosts click-through rate.' ); // phpcs:ignore ?>
						</th>
						<td>
							<textarea id="hsm_seo_description" name="hsm_seo_description" rows="3"
								class="large-text" maxlength="160"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_seo_description', true ) ); ?></textarea>
							<p class="description hsm-char-count" data-max="160"><?php esc_html_e( '0 / 160 characters', 'homerite-schema' ); ?></p>
							<p class="description hsm-best-practice">
								&#128270; <?php esc_html_e( 'Best practice: 120–158 characters. Include your focus keyword, a clear benefit, and a call to action. Appears verbatim in search results.', 'homerite-schema' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="hsm_canonical_url"><?php esc_html_e( 'Canonical URL', 'homerite-schema' ); ?></label>
							<?php echo self::tip( 'Tells Google which URL is the "official" version of this page. Only set this if the same content exists at more than one URL.' ); // phpcs:ignore ?>
						</th>
						<td>
							<input type="url" id="hsm_canonical_url" name="hsm_canonical_url"
								value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_canonical_url', true ) ); ?>"
								class="large-text">
							<p class="description hsm-best-practice">
								&#128270; <?php esc_html_e( 'Best practice: leave blank unless this content appears at multiple URLs. Prevents duplicate-content penalties.', 'homerite-schema' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th>
							<label for="hsm_focus_keyword"><?php esc_html_e( 'Focus Keyword', 'homerite-schema' ); ?></label>
							<?php echo self::tip( 'The single primary keyword this page is trying to rank for. Used by the Content Analysis tab to measure how well your content is optimised.' ); // phpcs:ignore ?>
						</th>
						<td>
							<input type="text" id="hsm_focus_keyword" name="hsm_focus_keyword"
								value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_focus_keyword', true ) ); ?>"
								class="regular-text">
							<p class="description hsm-best-practice">
								&#128270; <?php esc_html_e( 'Best practice: one keyword per page. Use it in your title, meta description, first paragraph, and at least one heading.', 'homerite-schema' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<!-- ── Open Graph ── -->
				<div class="hsm-sync-section">
					<div class="hsm-sync-header">
						<h4>
							<?php esc_html_e( 'Open Graph', 'homerite-schema' ); ?>
							<?php echo self::tip( 'Open Graph tags control how this page appears when shared on Facebook, LinkedIn, and other social networks — the image, title, and description in the preview card.' ); // phpcs:ignore ?>
						</h4>
						<label class="hsm-sync-label">
							<input type="checkbox" id="hsm_og_same_as_meta" name="hsm_og_same_as_meta" value="1"
								<?php checked( $og_synced ); ?>>
							<?php esc_html_e( 'Use SEO Title &amp; Description (recommended)', 'homerite-schema' ); ?>
						</label>
					</div>

					<div id="hsm-og-custom-fields" class="hsm-sync-fields"<?php echo $og_synced ? ' style="display:none"' : ''; ?>>
						<table class="form-table">
							<tr>
								<th>
									<label for="hsm_og_title"><?php esc_html_e( 'OG Title', 'homerite-schema' ); ?></label>
									<?php echo self::tip( 'The headline shown in the social share card. Can differ slightly from your page title — write for curiosity and clicks.' ); // phpcs:ignore ?>
								</th>
								<td>
									<input type="text" id="hsm_og_title" name="hsm_og_title"
										value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_og_title', true ) ); ?>"
										class="large-text">
									<p class="description hsm-best-practice">
										&#128270; <?php esc_html_e( 'Best practice: 60–90 characters. Can be slightly more descriptive or conversational than your title tag.', 'homerite-schema' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="hsm_og_description"><?php esc_html_e( 'OG Description', 'homerite-schema' ); ?></label>
									<?php echo self::tip( 'The supporting text beneath the OG title in a social card. Write for a social audience — explain the benefit and why someone should click.' ); // phpcs:ignore ?>
								</th>
								<td>
									<textarea id="hsm_og_description" name="hsm_og_description" rows="2"
										class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_og_description', true ) ); ?></textarea>
									<p class="description hsm-best-practice">
										&#128270; <?php esc_html_e( 'Best practice: 150–200 characters. Focus on the benefit or outcome, not just a description.', 'homerite-schema' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="hsm_og_image"><?php esc_html_e( 'OG Image URL', 'homerite-schema' ); ?></label>
									<?php echo self::tip( 'The image shown in the social share card. A compelling image dramatically increases click-through on Facebook and LinkedIn.' ); // phpcs:ignore ?>
								</th>
								<td>
									<input type="url" id="hsm_og_image" name="hsm_og_image"
										value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_og_image', true ) ); ?>"
										class="large-text">
									<button type="button" class="button hsm-upload-image" data-target="hsm_og_image">
										<?php esc_html_e( 'Choose Image', 'homerite-schema' ); ?>
									</button>
									<p class="description hsm-best-practice">
										&#128270; <?php esc_html_e( 'Best practice: 1200×630 px, under 8 MB. Use a bold, text-free image that works as a thumbnail.', 'homerite-schema' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- ── Twitter (X) Card ── -->
				<div class="hsm-sync-section">
					<div class="hsm-sync-header">
						<h4>
							<?php esc_html_e( 'Twitter (X) Card', 'homerite-schema' ); ?>
							<?php echo self::tip( 'Twitter (X) Card tags control the preview shown when this page is shared on Twitter/X — the image, headline, and description in the tweet card.' ); // phpcs:ignore ?>
						</h4>
						<label class="hsm-sync-label">
							<input type="checkbox" id="hsm_twitter_same_as_meta" name="hsm_twitter_same_as_meta" value="1"
								<?php checked( $tw_synced ); ?>>
							<?php esc_html_e( 'Use SEO Title &amp; Description (recommended)', 'homerite-schema' ); ?>
						</label>
					</div>

					<div id="hsm-twitter-custom-fields" class="hsm-sync-fields"<?php echo $tw_synced ? ' style="display:none"' : ''; ?>>
						<table class="form-table">
							<tr>
								<th>
									<label for="hsm_twitter_title"><?php esc_html_e( 'Twitter (X) Title', 'homerite-schema' ); ?></label>
									<?php echo self::tip( 'The headline shown in the Twitter/X card. Twitter truncates titles longer than ~70 characters on mobile.' ); // phpcs:ignore ?>
								</th>
								<td>
									<input type="text" id="hsm_twitter_title" name="hsm_twitter_title"
										value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_twitter_title', true ) ); ?>"
										class="large-text">
									<p class="description hsm-best-practice">
										&#128270; <?php esc_html_e( 'Best practice: under 70 characters. Direct and action-oriented works well on Twitter/X.', 'homerite-schema' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="hsm_twitter_description"><?php esc_html_e( 'Twitter (X) Description', 'homerite-schema' ); ?></label>
									<?php echo self::tip( 'Supporting text shown in the Twitter/X card. Keep it punchy — Twitter audiences scroll fast.' ); // phpcs:ignore ?>
								</th>
								<td>
									<textarea id="hsm_twitter_description" name="hsm_twitter_description" rows="2"
										class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_twitter_description', true ) ); ?></textarea>
									<p class="description hsm-best-practice">
										&#128270; <?php esc_html_e( 'Best practice: under 200 characters. Write for a fast-scrolling Twitter/X audience.', 'homerite-schema' ); ?>
									</p>
								</td>
							</tr>
							<tr>
								<th>
									<label for="hsm_twitter_image"><?php esc_html_e( 'Twitter (X) Image URL', 'homerite-schema' ); ?></label>
									<?php echo self::tip( 'The image shown in the Twitter/X card. Twitter crops images differently than Facebook — test your card at cards-dev.twitter.com.' ); // phpcs:ignore ?>
								</th>
								<td>
									<input type="url" id="hsm_twitter_image" name="hsm_twitter_image"
										value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_twitter_image', true ) ); ?>"
										class="large-text">
									<button type="button" class="button hsm-upload-image" data-target="hsm_twitter_image">
										<?php esc_html_e( 'Choose Image', 'homerite-schema' ); ?>
									</button>
									<p class="description hsm-best-practice">
										&#128270; <?php esc_html_e( 'Best practice: 1200×628 px for summary_large_image card. Twitter crops to 2:1 ratio.', 'homerite-schema' ); ?>
									</p>
								</td>
							</tr>
						</table>
					</div>
				</div>

				<!-- ── Robots ── -->
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

				<h4>
					<?php esc_html_e( 'Enable Schema Types', 'homerite-schema' ); ?>
					<?php echo self::tip( 'Schema markup is invisible code that tells Google what your content is about — enabling rich results like star ratings, FAQ dropdowns, and how-to steps directly in search.' ); // phpcs:ignore ?>
				</h4>
				<div class="hsm-schema-types">
					<?php
					$schema_type_list = [
						'LocalBusiness'  => 'LocalBusiness ' . ( $is_front ? '(auto-on for homepage)' : '' ),
						'Service'        => 'Service',
						'Product'        => 'Product',
						'FAQPage'        => 'FAQPage',
						'HowTo'          => 'HowTo',
						'Review'         => 'Review',
						'BreadcrumbList' => 'BreadcrumbList (auto-generated)',
						'WebPage'        => 'WebPage / AboutPage / ContactPage',
					];
					foreach ( $schema_type_list as $type => $label ) :
						$checked = in_array( $type, $enabled_types, true ) || ( $is_front && 'LocalBusiness' === $type );
					?>
						<label class="hsm-schema-type-toggle">
							<input type="checkbox"
								name="<?php echo esc_attr( 'hsm_schema_enable_' . $type ); ?>"
								value="1"
								<?php checked( $checked ); ?>
								data-reveals="hsm-schema-section-<?php echo esc_attr( strtolower( $type ) ); ?>">
							<?php echo esc_html( $label ); ?>
						</label>
					<?php endforeach; ?>
				</div>

				<!-- LocalBusiness overrides -->
				<div id="hsm-schema-section-localbusiness" class="hsm-schema-section <?php echo in_array( 'LocalBusiness', $enabled_types, true ) || $is_front ? 'active' : ''; ?>">
					<h4>
						<?php esc_html_e( 'LocalBusiness Override Fields', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Override global settings for this specific page. Useful when a page represents a specific location or branch of your business.' ); // phpcs:ignore ?>
					</h4>
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
					<h4>
						<?php esc_html_e( 'Service Schema', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Service schema tells Google this page describes a specific service you offer. Helps your service pages appear for relevant searches.' ); // phpcs:ignore ?>
					</h4>
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
							<th>
								<label for="hsm_service_area_override"><?php esc_html_e( 'Area Served Override', 'homerite-schema' ); ?></label>
								<?php echo self::tip( 'If this page targets a specific area different from your global service regions, enter it here (e.g. "Centre County, PA"). Leave blank to use global service areas.' ); // phpcs:ignore ?>
							</th>
							<td><input type="text" id="hsm_service_area_override" name="hsm_service_area_override" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_service_area_override', true ) ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Leave blank to use global service areas', 'homerite-schema' ); ?>"></td>
						</tr>
					</table>
				</div>

				<!-- Product fields -->
				<div id="hsm-schema-section-product" class="hsm-schema-section <?php echo in_array( 'Product', $enabled_types, true ) ? 'active' : ''; ?>">
					<h4>
						<?php esc_html_e( 'Product Schema', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Product schema enables rich results like star ratings, prices, and availability directly in Google search results for this product page.' ); // phpcs:ignore ?>
					</h4>
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
					<h4>
						<?php esc_html_e( 'FAQ Editor', 'homerite-schema' ); ?>
						<?php echo self::tip( 'FAQPage schema can display your Q&As as expandable dropdowns directly in Google search results, increasing visibility without an extra click.' ); // phpcs:ignore ?>
					</h4>
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

				<!-- HowTo editor -->
				<div id="hsm-schema-section-howto" class="hsm-schema-section <?php echo in_array( 'HowTo', $enabled_types, true ) ? 'active' : ''; ?>">
					<h4>
						<?php esc_html_e( 'HowTo Schema', 'homerite-schema' ); ?>
						<?php echo self::tip( 'HowTo schema tells Google this page explains how to do something step-by-step. Google may show your steps as a rich result, which can significantly increase clicks.' ); // phpcs:ignore ?>
					</h4>
					<table class="form-table">
						<tr>
							<th><label for="hsm_howto_name"><?php esc_html_e( 'How-To Title', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_howto_name" name="hsm_howto_name" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_howto_name', true ) ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. How to Replace a Roof Shingle', 'homerite-schema' ); ?>"></td>
						</tr>
						<tr>
							<th><label for="hsm_howto_description"><?php esc_html_e( 'Brief Description', 'homerite-schema' ); ?></label></th>
							<td><textarea id="hsm_howto_description" name="hsm_howto_description" rows="2" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_howto_description', true ) ); ?></textarea></td>
						</tr>
						<tr>
							<th>
								<label for="hsm_howto_total_time"><?php esc_html_e( 'Total Time', 'homerite-schema' ); ?></label>
								<?php echo self::tip( 'How long the whole process takes in ISO 8601 duration format: PT30M = 30 minutes, PT2H = 2 hours, PT1H30M = 1 hour 30 minutes.' ); // phpcs:ignore ?>
							</th>
							<td><input type="text" id="hsm_howto_total_time" name="hsm_howto_total_time" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_howto_total_time', true ) ); ?>" class="small-text" placeholder="PT30M"></td>
						</tr>
					</table>

					<p style="font-weight:600;margin-top:16px;"><?php esc_html_e( 'Steps', 'homerite-schema' ); ?></p>
					<div id="hsm-howto-list">
						<?php if ( empty( $howto_steps ) ) : ?>
							<div class="hsm-howto-row">
								<div class="hsm-faq-handle">&#9776;</div>
								<div class="hsm-faq-fields">
									<input type="text" name="hsm_howto_step_name[]" placeholder="<?php esc_attr_e( 'Step title (e.g. Remove damaged shingle)', 'homerite-schema' ); ?>" class="large-text">
									<textarea name="hsm_howto_step_text[]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Step instructions…', 'homerite-schema' ); ?>"></textarea>
								</div>
								<button type="button" class="button hsm-remove-howto-row"><?php esc_html_e( 'Remove', 'homerite-schema' ); ?></button>
							</div>
						<?php else :
							foreach ( $howto_steps as $step ) : ?>
								<div class="hsm-howto-row">
									<div class="hsm-faq-handle">&#9776;</div>
									<div class="hsm-faq-fields">
										<input type="text" name="hsm_howto_step_name[]" value="<?php echo esc_attr( $step['name'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Step title', 'homerite-schema' ); ?>">
										<textarea name="hsm_howto_step_text[]" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Step instructions…', 'homerite-schema' ); ?>"><?php echo esc_textarea( $step['text'] ); ?></textarea>
									</div>
									<button type="button" class="button hsm-remove-howto-row"><?php esc_html_e( 'Remove', 'homerite-schema' ); ?></button>
								</div>
							<?php endforeach;
						endif; ?>
					</div>
					<button type="button" class="button" id="hsm-add-howto-row"><?php esc_html_e( '+ Add Step', 'homerite-schema' ); ?></button>
				</div>

				<!-- Review schema -->
				<div id="hsm-schema-section-review" class="hsm-schema-section <?php echo in_array( 'Review', $enabled_types, true ) ? 'active' : ''; ?>">
					<h4>
						<?php esc_html_e( 'Review Schema', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Review schema marks this page as containing a customer review. Google may show star ratings next to this page in search results.' ); // phpcs:ignore ?>
					</h4>
					<table class="form-table">
						<tr>
							<th><label for="hsm_review_item_name"><?php esc_html_e( 'Item Being Reviewed', 'homerite-schema' ); ?></label></th>
							<td>
								<input type="text" id="hsm_review_item_name" name="hsm_review_item_name" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_review_item_name', true ) ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Roof Replacement Service', 'homerite-schema' ); ?>">
								<p class="description"><?php esc_html_e( 'The product, service, or business being reviewed.', 'homerite-schema' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="hsm_review_name"><?php esc_html_e( 'Review Headline', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_review_name" name="hsm_review_name" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_review_name', true ) ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'e.g. Excellent roof replacement — fast and clean', 'homerite-schema' ); ?>"></td>
						</tr>
						<tr>
							<th><label for="hsm_review_body"><?php esc_html_e( 'Review Text', 'homerite-schema' ); ?></label></th>
							<td><textarea id="hsm_review_body" name="hsm_review_body" rows="4" class="large-text"><?php echo esc_textarea( get_post_meta( $post->ID, 'hsm_review_body', true ) ); ?></textarea></td>
						</tr>
						<tr>
							<th>
								<label for="hsm_review_rating"><?php esc_html_e( 'Star Rating', 'homerite-schema' ); ?></label>
								<?php echo self::tip( 'The star rating given in this review, from 1 (lowest) to 5 (highest).' ); // phpcs:ignore ?>
							</th>
							<td><input type="number" id="hsm_review_rating" name="hsm_review_rating" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_review_rating', true ) ?: '5' ); ?>" min="1" max="5" step="0.5" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_review_author"><?php esc_html_e( 'Reviewer Name', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_review_author" name="hsm_review_author" value="<?php echo esc_attr( get_post_meta( $post->ID, 'hsm_review_author', true ) ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Jane Smith', 'homerite-schema' ); ?>"></td>
						</tr>
					</table>
				</div>

			</div><!-- #hsm-tab-schema -->

		<!-- ========== TAB 3: Content Analysis ========== -->
		<div id="hsm-tab-analysis" class="hsm-tab-panel">

			<!-- ── Focus Keyword Density ── -->
			<div class="hsm-analysis-card">
				<h4 class="hsm-analysis-title">
					<?php esc_html_e( 'Focus Keyword Density', 'homerite-schema' ); ?>
					<?php echo self::tip( 'Keyword density is how often your focus keyword appears relative to total word count. The ideal range is 1–3 %. Too low and the page may not rank; too high looks spammy to Google.' ); // phpcs:ignore ?>
				</h4>

				<div class="hsm-kd-meta">
					<span class="hsm-kd-keyword-display">
						<?php esc_html_e( 'Keyword:', 'homerite-schema' ); ?>
						<strong id="hsm-kd-keyword-label"><?php esc_html_e( '(none set)', 'homerite-schema' ); ?></strong>
					</span>
					<button type="button" class="button button-small" id="hsm-ca-scan">
						&#8635; <?php esc_html_e( 'Scan Content', 'homerite-schema' ); ?>
					</button>
				</div>

				<div id="hsm-kd-results" class="hsm-kd-results" style="display:none">
					<!-- Density meter bar -->
					<div class="hsm-meter-wrap">
						<div class="hsm-meter-track">
							<div class="hsm-meter-fill" id="hsm-kd-bar" style="width:0%"></div>
							<!-- Optimal zone marker -->
							<div class="hsm-meter-zone" style="left:25%;width:37.5%"></div>
						</div>
						<div class="hsm-meter-labels">
							<span>0%</span><span>1%</span><span>2%</span><span>3%</span><span>4%+</span>
						</div>
					</div>

					<div class="hsm-kd-stats">
						<span><?php esc_html_e( 'Density:', 'homerite-schema' ); ?> <strong id="hsm-kd-density">—</strong></span>
						<span><?php esc_html_e( 'Uses:', 'homerite-schema' ); ?> <strong id="hsm-kd-count">—</strong></span>
						<span><?php esc_html_e( 'Words:', 'homerite-schema' ); ?> <strong id="hsm-kd-words">—</strong></span>
					</div>

					<div id="hsm-kd-badge" class="hsm-kd-badge"></div>

					<div class="hsm-kd-checklist" id="hsm-kd-checklist">
						<!-- Filled by JS -->
					</div>
				</div>

				<p class="description" id="hsm-kd-no-keyword" style="display:none">
					<?php esc_html_e( 'Set a Focus Keyword on the SEO Meta tab first, then click Scan Content.', 'homerite-schema' ); ?>
				</p>
			</div>

			<!-- ── Schema Type Suggestions ── -->
			<div class="hsm-analysis-card">
				<h4 class="hsm-analysis-title">
					<?php esc_html_e( 'Schema Type Suggestions', 'homerite-schema' ); ?>
					<?php echo self::tip( 'Stride Analytics scans your page content for common patterns and recommends relevant schema types. Click "Apply" on any suggestion to enable that schema in the Schema tab.' ); // phpcs:ignore ?>
				</h4>

				<div id="hsm-suggestions-list" class="hsm-suggestions-list">
					<p class="description"><?php esc_html_e( 'Click "Scan Content" above to analyse your page and generate recommendations.', 'homerite-schema' ); ?></p>
				</div>
			</div>

		</div><!-- #hsm-tab-analysis -->
		</div><!-- #hsm-meta-box-wrap -->
		<?php
	}
}
