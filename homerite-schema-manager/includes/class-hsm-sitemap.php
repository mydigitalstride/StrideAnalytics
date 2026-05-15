<?php
/**
 * XML sitemap generation, routing, and Google ping.
 *
 * @package HomeRite_Schema_Manager
 */

defined( 'ABSPATH' ) || exit;

class HSM_Sitemap {

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'add_rewrite_rules' ] );
		add_filter( 'query_vars', [ __CLASS__, 'add_query_vars' ] );
		add_action( 'template_redirect', [ __CLASS__, 'handle_request' ] );
		add_action( 'save_post', [ __CLASS__, 'ping_google' ], 10, 2 );
		add_action( 'admin_init', [ __CLASS__, 'maybe_flush' ] );
	}

	public static function activate(): void {
		update_option( 'hsm_sitemap_flush', 1 );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule( '^sitemap\.xml$', 'index.php?hsm_sitemap=index', 'top' );
		add_rewrite_rule( '^sitemap-([a-z0-9_-]+)\.xml$', 'index.php?hsm_sitemap=$matches[1]', 'top' );
	}

	public static function add_query_vars( array $vars ): array {
		$vars[] = 'hsm_sitemap';
		return $vars;
	}

	public static function maybe_flush(): void {
		if ( get_option( 'hsm_sitemap_flush' ) ) {
			flush_rewrite_rules();
			delete_option( 'hsm_sitemap_flush' );
		}
	}

	public static function handle_request(): void {
		$sitemap = get_query_var( 'hsm_sitemap' );
		if ( ! $sitemap ) {
			return;
		}
		if ( $sitemap === 'index' ) {
			self::output_index();
		} else {
			self::output_type( $sitemap );
		}
	}

	private static function get_public_post_types(): array {
		$types = get_post_types( [ 'public' => true ], 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	private static function output_index(): void {
		$types = self::get_public_post_types();

		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

		foreach ( $types as $type ) {
			$query = new WP_Query( [
				'post_type'              => $type,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			] );

			if ( ! $query->have_posts() ) {
				continue;
			}

			$post    = $query->posts[0];
			$lastmod = gmdate( 'c', strtotime( $post->post_modified_gmt ) );
			$loc     = esc_url( home_url( 'sitemap-' . $type . '.xml' ) );

			echo "\t<sitemap>\n";
			echo "\t\t<loc>" . $loc . "</loc>\n";
			echo "\t\t<lastmod>" . esc_html( $lastmod ) . "</lastmod>\n";
			echo "\t</sitemap>\n";
		}

		echo '</sitemapindex>';
		exit;
	}

	private static function output_type( string $type ): void {
		$allowed = self::get_public_post_types();

		if ( ! in_array( $type, $allowed, true ) ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			return;
		}

		$posts = get_posts( [
			'post_type'              => $type,
			'post_status'            => 'publish',
			'posts_per_page'         => -1,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		] );

		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
		echo '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

		if ( $type === 'page' ) {
			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url( home_url( '/' ) ) . "</loc>\n";
			echo "\t\t<changefreq>weekly</changefreq>\n";
			echo "\t\t<priority>1.0</priority>\n";
			echo "\t</url>\n";
		}

		foreach ( $posts as $post ) {
			$loc        = esc_url( get_permalink( $post ) );
			$lastmod    = esc_html( gmdate( 'c', strtotime( $post->post_modified_gmt ) ) );
			$changefreq = ( $type === 'post' ) ? 'weekly' : 'monthly';
			$priority   = self::get_priority( $post, $type );

			echo "\t<url>\n";
			echo "\t\t<loc>" . $loc . "</loc>\n";
			echo "\t\t<lastmod>" . $lastmod . "</lastmod>\n";
			echo "\t\t<changefreq>" . $changefreq . "</changefreq>\n";
			echo "\t\t<priority>" . $priority . "</priority>\n";

			$thumb_id = get_post_thumbnail_id( $post->ID );
			if ( $thumb_id ) {
				$img_url = esc_url( wp_get_attachment_url( $thumb_id ) );
				if ( $img_url ) {
					echo "\t\t<image:image>\n";
					echo "\t\t\t<image:loc>" . $img_url . "</image:loc>\n";
					echo "\t\t</image:image>\n";
				}
			}

			echo "\t</url>\n";
		}

		echo '</urlset>';
		exit;
	}

	private static function get_priority( WP_Post $post, string $type ): string {
		if ( (int) $post->ID === (int) get_option( 'page_on_front' ) ) {
			return '0.9';
		}
		return match ( $type ) {
			'page' => '0.8',
			'post' => '0.6',
			default => '0.5',
		};
	}

	public static function ping_google( int $post_id, WP_Post $post ): void {
		if ( $post->post_status !== 'publish' ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		$transient = 'hsm_sitemap_pinged_' . $post_id;
		if ( get_transient( $transient ) ) {
			return;
		}
		set_transient( $transient, 1, HOUR_IN_SECONDS );
		$ping_url = 'https://www.google.com/ping?sitemap=' . urlencode( home_url( 'sitemap.xml' ) );
		wp_remote_get( $ping_url, [
			'blocking'  => false,
			'sslverify' => true,
			'timeout'   => 5,
		] );
	}
}
