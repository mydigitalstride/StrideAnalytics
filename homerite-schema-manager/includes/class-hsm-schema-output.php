<?php
/**
 * Schema Output Engine (Phase 5).
 *
 * Fires on wp_head and outputs JSON-LD blocks.
 * Also outputs SEO meta tags (title override, meta description, OG, Twitter, robots).
 *
 * @package HomeRite_Schema_Manager
 */

defined( 'ABSPATH' ) || exit;

class HSM_Schema_Output {

	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'output_seo_meta' ],    1 );
		add_action( 'wp_head', [ __CLASS__, 'output_schema' ],      5 );
		add_filter( 'pre_get_document_title', [ __CLASS__, 'filter_title' ] );
	}

	// ------------------------------------------------------------------
	// Helper: get global option with fallback
	// ------------------------------------------------------------------

	private static function g( string $key, $default = '' ) {
		return get_option( $key, $default );
	}

	// ------------------------------------------------------------------
	// Title override
	// ------------------------------------------------------------------

	public static function filter_title( string $title ): string {
		if ( ! is_singular() ) return $title;
		$override = get_post_meta( get_the_ID(), 'hsm_seo_title', true );
		return ( '' !== $override ) ? $override : $title;
	}

	// ------------------------------------------------------------------
	// SEO meta tags
	// ------------------------------------------------------------------

	public static function output_seo_meta(): void {
		if ( ! is_singular() ) return;

		$post_id = get_the_ID();

		// Meta description.
		$desc = get_post_meta( $post_id, 'hsm_seo_description', true );
		if ( '' !== $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}

		// Canonical.
		$canonical = get_post_meta( $post_id, 'hsm_canonical_url', true );
		if ( '' !== $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}

		// Robots.
		$robots = [];
		if ( '1' === get_post_meta( $post_id, 'hsm_noindex', true ) )  $robots[] = 'noindex';
		if ( '1' === get_post_meta( $post_id, 'hsm_nofollow', true ) ) $robots[] = 'nofollow';
		if ( ! empty( $robots ) ) {
			echo '<meta name="robots" content="' . esc_attr( implode( ', ', $robots ) ) . '">' . "\n";
		}

		// Open Graph.
		$og_fields = [
			'og:title'       => get_post_meta( $post_id, 'hsm_og_title', true )       ?: get_the_title( $post_id ),
			'og:description' => get_post_meta( $post_id, 'hsm_og_description', true ) ?: $desc,
			'og:url'         => get_permalink( $post_id ),
			'og:type'        => 'website',
		];
		$og_image = get_post_meta( $post_id, 'hsm_og_image', true );
		if ( '' !== $og_image ) {
			$og_fields['og:image'] = $og_image;
		}
		foreach ( $og_fields as $prop => $content ) {
			if ( '' !== $content ) {
				echo '<meta property="' . esc_attr( $prop ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}

		// Twitter card.
		$tw_title = get_post_meta( $post_id, 'hsm_twitter_title', true )       ?: get_the_title( $post_id );
		$tw_desc  = get_post_meta( $post_id, 'hsm_twitter_description', true ) ?: $desc;
		$tw_image = get_post_meta( $post_id, 'hsm_twitter_image', true );

		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
		if ( '' !== $tw_title ) echo '<meta name="twitter:title" content="' . esc_attr( $tw_title ) . '">' . "\n";
		if ( '' !== $tw_desc )  echo '<meta name="twitter:description" content="' . esc_attr( $tw_desc ) . '">' . "\n";
		if ( '' !== $tw_image ) echo '<meta name="twitter:image" content="' . esc_url( $tw_image ) . '">' . "\n";
	}

	// ------------------------------------------------------------------
	// Schema JSON-LD output
	// ------------------------------------------------------------------

	public static function output_schema(): void {
		$schemas        = [];
		$emitted_types  = [];

		$is_front       = is_front_page();
		$is_singular    = is_singular();
		$post_id        = $is_singular ? get_the_ID() : 0;
		$enabled_types  = $post_id ? ( get_post_meta( $post_id, 'hsm_schema_enabled_types', true ) ?: [] ) : [];

		// --- Homepage always gets LocalBusiness + WebSite + Organization ---
		if ( $is_front ) {
			$schemas[]       = self::build_organization();
			$emitted_types[] = 'Organization';

			$schemas[]       = self::build_local_business( $post_id );
			$emitted_types[] = 'LocalBusiness';

			$schemas[]       = self::build_website();
			$emitted_types[] = 'WebSite';
		}

		if ( $is_singular && $post_id ) {

			// LocalBusiness (non-homepage).
			if ( ! $is_front && in_array( 'LocalBusiness', $enabled_types, true ) && ! in_array( 'LocalBusiness', $emitted_types, true ) ) {
				$schemas[]       = self::build_local_business( $post_id );
				$emitted_types[] = 'LocalBusiness';
			}

			// Service.
			if ( in_array( 'Service', $enabled_types, true ) && ! in_array( 'Service', $emitted_types, true ) ) {
				$s = self::build_service( $post_id );
				if ( $s ) {
					$schemas[]       = $s;
					$emitted_types[] = 'Service';
				}
			}

			// Product.
			if ( in_array( 'Product', $enabled_types, true ) && ! in_array( 'Product', $emitted_types, true ) ) {
				$s = self::build_product( $post_id );
				if ( $s ) {
					$schemas[]       = $s;
					$emitted_types[] = 'Product';
				}
			}

			// FAQPage.
			if ( in_array( 'FAQPage', $enabled_types, true ) && ! in_array( 'FAQPage', $emitted_types, true ) ) {
				$s = self::build_faq( $post_id );
				if ( $s ) {
					$schemas[]       = $s;
					$emitted_types[] = 'FAQPage';
				}
			}

			// BreadcrumbList.
			if ( in_array( 'BreadcrumbList', $enabled_types, true ) && ! in_array( 'BreadcrumbList', $emitted_types, true ) ) {
				$s = self::build_breadcrumb( $post_id );
				if ( $s ) {
					$schemas[]       = $s;
					$emitted_types[] = 'BreadcrumbList';
				}
			}

			// WebPage.
			if ( in_array( 'WebPage', $enabled_types, true ) && ! in_array( 'WebPage', $emitted_types, true ) ) {
				$schemas[]       = self::build_webpage( $post_id );
				$emitted_types[] = 'WebPage';
			}
		}

		// Product taxonomy / category archives (WooCommerce ItemList).
		if ( is_tax( 'product_cat' ) || is_post_type_archive( 'product' ) ) {
			$s = self::build_product_item_list();
			if ( $s ) $schemas[] = $s;
		}

		foreach ( $schemas as $schema ) {
			if ( ! empty( $schema ) ) {
				echo '<script type="application/ld+json">' . "\n";
				echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
				echo "\n</script>\n";
			}
		}
	}

	// ------------------------------------------------------------------
	// Schema builders
	// ------------------------------------------------------------------

	private static function get_logo_url(): string {
		$logo = self::g( 'hsm_logo_url' );
		if ( '' !== $logo ) return $logo;

		// Auto-pull from WP site logo.
		$logo_id = get_theme_mod( 'custom_logo' );
		if ( $logo_id ) {
			$img = wp_get_attachment_image_src( $logo_id, 'full' );
			if ( $img ) return $img[0];
		}
		return '';
	}

	private static function get_address( string $suffix = '' ): array {
		$street = self::g( 'hsm_street' . $suffix );
		if ( '' === $street ) return [];

		return [
			'@type'           => 'PostalAddress',
			'streetAddress'   => $street,
			'addressLocality' => self::g( 'hsm_city' . $suffix ),
			'addressRegion'   => self::g( 'hsm_state' . $suffix ),
			'postalCode'      => self::g( 'hsm_zip' . $suffix ),
			'addressCountry'  => 'US',
		];
	}

	private static function get_opening_hours(): array {
		$hours  = self::g( 'hsm_hours', [] );
		$specs  = [];
		$day_map = [
			'Monday'    => 'Monday',
			'Tuesday'   => 'Tuesday',
			'Wednesday' => 'Wednesday',
			'Thursday'  => 'Thursday',
			'Friday'    => 'Friday',
			'Saturday'  => 'Saturday',
			'Sunday'    => 'Sunday',
		];
		foreach ( $day_map as $key => $schema_day ) {
			if ( ! empty( $hours[ $key ]['open'] ) && ! empty( $hours[ $key ]['close'] ) ) {
				$specs[] = [
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . $schema_day,
					'opens'     => $hours[ $key ]['open'],
					'closes'    => $hours[ $key ]['close'],
				];
			}
		}
		return $specs;
	}

	private static function build_local_business( int $post_id = 0 ): array {
		$type        = self::g( 'hsm_schema_type', 'HomeAndConstructionBusiness' );
		$description = $post_id ? ( get_post_meta( $post_id, 'hsm_lb_description', true ) ?: self::g( 'hsm_slogan' ) ) : self::g( 'hsm_slogan' );
		$phone       = $post_id ? ( get_post_meta( $post_id, 'hsm_lb_phone', true ) ?: self::g( 'hsm_phone' ) ) : self::g( 'hsm_phone' );

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => $type,
			'name'     => self::g( 'hsm_business_name' ),
			'url'      => self::g( 'hsm_primary_url', home_url() ),
		];

		if ( '' !== $description )        $schema['description']  = $description;
		if ( '' !== $phone )              $schema['telephone']    = $phone;
		if ( '' !== self::g( 'hsm_email' ) ) $schema['email']    = self::g( 'hsm_email' );
		if ( '' !== self::g( 'hsm_price_range' ) ) $schema['priceRange'] = self::g( 'hsm_price_range' );
		if ( '' !== self::g( 'hsm_founded_year' ) ) $schema['foundingDate'] = self::g( 'hsm_founded_year' );

		// Logo.
		$logo_url = self::get_logo_url();
		if ( '' !== $logo_url ) {
			$schema['logo'] = [ '@type' => 'ImageObject', 'url' => $logo_url ];
		}

		// Primary address.
		$address = self::get_address();
		if ( ! empty( $address ) ) $schema['address'] = $address;

		// Geo.
		$lat = self::g( 'hsm_latitude' );
		$lng = self::g( 'hsm_longitude' );
		if ( '' !== $lat && '' !== $lng ) {
			$schema['geo'] = [
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			];
		}

		// Service areas.
		$areas = self::g( 'hsm_service_areas', [] );
		if ( ! empty( $areas ) ) {
			$schema['areaServed'] = array_map( fn( $a ) => [ '@type' => 'City', 'name' => $a ], $areas );
		}

		// Hours.
		$hours = self::get_opening_hours();
		if ( ! empty( $hours ) ) $schema['openingHoursSpecification'] = $hours;

		// sameAs.
		$same_as = self::g( 'hsm_same_as', [] );
		if ( ! empty( $same_as ) ) $schema['sameAs'] = $same_as;

		// Aggregate rating.
		$rating_val   = self::g( 'hsm_rating_value' );
		$rating_count = self::g( 'hsm_rating_count' );
		if ( '' !== $rating_val && '' !== $rating_count ) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => (float) $rating_val,
				'reviewCount' => (int) $rating_count,
			];
		}

		// Legal name.
		$legal = self::g( 'hsm_legal_name' );
		if ( '' !== $legal ) $schema['legalName'] = $legal;

		return $schema;
	}

	private static function build_organization(): array {
		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'Organization',
			'name'     => self::g( 'hsm_business_name' ),
			'url'      => self::g( 'hsm_primary_url', home_url() ),
		];

		$logo_url = self::get_logo_url();
		if ( '' !== $logo_url ) {
			$schema['logo'] = [ '@type' => 'ImageObject', 'url' => $logo_url ];
		}

		$same_as = self::g( 'hsm_same_as', [] );
		if ( ! empty( $same_as ) ) $schema['sameAs'] = $same_as;

		$slogan = self::g( 'hsm_slogan' );
		if ( '' !== $slogan ) $schema['slogan'] = $slogan;

		return $schema;
	}

	private static function build_website(): array {
		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'WebSite',
			'name'            => self::g( 'hsm_business_name' ),
			'url'             => home_url(),
			'potentialAction' => [
				'@type'       => 'SearchAction',
				'target'      => [
					'@type'       => 'EntryPoint',
					'urlTemplate' => home_url( '/?s={search_term_string}' ),
				],
				'query-input' => 'required name=search_term_string',
			],
		];
	}

	private static function build_service( int $post_id ): ?array {
		$name = get_post_meta( $post_id, 'hsm_service_name', true ) ?: get_the_title( $post_id );
		if ( '' === $name ) return null;

		$schema = [
			'@context'    => 'https://schema.org',
			'@type'       => 'Service',
			'name'        => $name,
			'provider'    => [
				'@type' => self::g( 'hsm_schema_type', 'LocalBusiness' ),
				'name'  => self::g( 'hsm_business_name' ),
			],
		];

		$desc = get_post_meta( $post_id, 'hsm_service_description', true );
		if ( '' !== $desc ) $schema['description'] = $desc;

		$svc_type = get_post_meta( $post_id, 'hsm_service_type', true );
		if ( '' !== $svc_type ) $schema['serviceType'] = $svc_type;

		$area_override = get_post_meta( $post_id, 'hsm_service_area_override', true );
		if ( '' !== $area_override ) {
			$schema['areaServed'] = $area_override;
		} else {
			$areas = self::g( 'hsm_service_areas', [] );
			if ( ! empty( $areas ) ) {
				$schema['areaServed'] = array_map( fn( $a ) => [ '@type' => 'City', 'name' => $a ], $areas );
			}
		}

		return $schema;
	}

	private static function build_product( int $post_id ): ?array {
		$name = get_post_meta( $post_id, 'hsm_product_name', true ) ?: get_the_title( $post_id );

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => 'Product',
			'name'     => $name,
		];

		$desc = get_post_meta( $post_id, 'hsm_product_description', true );
		if ( '' !== $desc ) $schema['description'] = $desc;

		$brand = get_post_meta( $post_id, 'hsm_product_brand', true );
		if ( '' !== $brand ) $schema['brand'] = [ '@type' => 'Brand', 'name' => $brand ];

		$price        = get_post_meta( $post_id, 'hsm_product_price', true );
		$currency     = get_post_meta( $post_id, 'hsm_product_currency', true ) ?: 'USD';
		$availability = get_post_meta( $post_id, 'hsm_product_availability', true ) ?: 'https://schema.org/InStock';

		if ( '' !== $price ) {
			$schema['offers'] = [
				'@type'         => 'Offer',
				'price'         => $price,
				'priceCurrency' => $currency,
				'availability'  => $availability,
			];
		}

		$warranty = get_post_meta( $post_id, 'hsm_product_warranty', true );
		if ( '' !== $warranty ) {
			$schema['warranty'] = [ '@type' => 'WarrantyPromise', 'description' => $warranty ];
		}

		return $schema;
	}

	private static function build_faq( int $post_id ): ?array {
		$entries = get_post_meta( $post_id, 'hsm_faq_entries', true ) ?: [];
		if ( empty( $entries ) ) return null;

		$items = [];
		foreach ( $entries as $faq ) {
			if ( '' !== $faq['question'] ) {
				$items[] = [
					'@type'          => 'Question',
					'name'           => $faq['question'],
					'acceptedAnswer' => [
						'@type' => 'Answer',
						'text'  => $faq['answer'],
					],
				];
			}
		}

		if ( empty( $items ) ) return null;

		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $items,
		];
	}

	private static function build_breadcrumb( int $post_id ): ?array {
		$post = get_post( $post_id );
		if ( ! $post ) return null;

		$items   = [];
		$pos     = 1;
		$items[] = [
			'@type'    => 'ListItem',
			'position' => $pos++,
			'name'     => get_bloginfo( 'name' ),
			'item'     => home_url(),
		];

		// Walk up the page hierarchy.
		$ancestors = array_reverse( get_post_ancestors( $post_id ) );
		foreach ( $ancestors as $ancestor_id ) {
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $pos++,
				'name'     => get_the_title( $ancestor_id ),
				'item'     => get_permalink( $ancestor_id ),
			];
		}

		$items[] = [
			'@type'    => 'ListItem',
			'position' => $pos,
			'name'     => get_the_title( $post_id ),
			'item'     => get_permalink( $post_id ),
		];

		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'BreadcrumbList',
			'itemListElement' => $items,
		];
	}

	private static function build_webpage( int $post_id ): array {
		$type = 'WebPage';
		$slug = get_post_field( 'post_name', $post_id );

		if ( str_contains( $slug, 'about' ) )   $type = 'AboutPage';
		if ( str_contains( $slug, 'contact' ) ) $type = 'ContactPage';

		return [
			'@context'    => 'https://schema.org',
			'@type'       => $type,
			'name'        => get_the_title( $post_id ),
			'url'         => get_permalink( $post_id ),
			'description' => get_post_meta( $post_id, 'hsm_seo_description', true ),
		];
	}

	private static function build_product_item_list(): ?array {
		if ( ! class_exists( 'WooCommerce' ) ) return null;

		$products = wc_get_products( [ 'limit' => 20, 'status' => 'publish' ] );
		if ( empty( $products ) ) return null;

		$items = [];
		foreach ( $products as $i => $product ) {
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'url'      => get_permalink( $product->get_id() ),
			];
		}

		return [
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'itemListElement' => $items,
		];
	}
}
