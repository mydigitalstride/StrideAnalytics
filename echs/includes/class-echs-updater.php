<?php
/**
 * Remote update checker — polls mydigitalstride.com for new plugin versions
 * and injects them into WordPress's native update pipeline.
 *
 * Server-side: publish a JSON file at UPDATE_URL whenever you release a new
 * version (see the sample JSON in the project README / docs).
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Updater {

	const UPDATE_URL  = 'https://mydigitalstride.com/echos-updates/api.php';
	const CACHE_KEY   = 'echs_update_info';
	const CACHE_TTL   = 3 * HOUR_IN_SECONDS;
	const PLUGIN_FILE = 'echs/echs.php';

	private static function transient_key(): string {
		return self::CACHE_KEY . '_' . ECHS_VERSION;
	}

	public static function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ __CLASS__, 'check_for_update' ] );
		add_filter( 'plugins_api',                           [ __CLASS__, 'plugin_info' ], 10, 3 );
		add_action( 'upgrader_process_complete',             [ __CLASS__, 'bust_cache' ], 10, 2 );
		add_action( 'admin_notices',                         [ __CLASS__, 'maybe_show_changelog_notice' ] );
	}

	private static function fetch_remote_info(): array|false {
		$cached = get_transient( self::transient_key() );
		if ( false !== $cached ) {
			return $cached;
		}

		$url = add_query_arg( [
			'action'     => 'info',
			'license'    => ECHS_License::get_key(),
			'site'       => home_url(),
			'version'    => ECHS_VERSION,
			'wp_version' => get_bloginfo( 'version' ),
		], self::UPDATE_URL );

		$response = wp_remote_get( $url, [
			'timeout'    => 10,
			'user-agent' => 'ECHoS/' . ECHS_VERSION . '; ' . home_url(),
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data['version'] ) || empty( $data['download_url'] ) ) {
			return false;
		}

		set_transient( self::transient_key(), $data, self::CACHE_TTL );
		return $data;
	}

	public static function check_for_update( mixed $transient ): mixed {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$info            = self::fetch_remote_info();
		$current_version = $transient->checked[ self::PLUGIN_FILE ] ?? '';

		if ( ! $info || ! version_compare( $info['version'], $current_version, '>' ) ) {
			return $transient;
		}

		$transient->response[ self::PLUGIN_FILE ] = (object) [
			'id'          => 'echs/echs',
			'slug'        => 'echs',
			'plugin'      => self::PLUGIN_FILE,
			'new_version' => $info['version'],
			'url'         => $info['url'] ?? 'https://mydigitalstride.com/echos-seo-analytics',
			'package'     => $info['download_url'],
			'icons'       => $info['icons'] ?? [],
			'banners'     => $info['banners'] ?? [],
			'tested'      => $info['tested'] ?? '',
			'requires'    => $info['requires'] ?? '6.0',
			'requires_php'=> $info['requires_php'] ?? '8.0',
			'upgrade_notice' => $info['upgrade_notice'] ?? '',
		];

		return $transient;
	}

	public static function plugin_info( mixed $result, string $action, object $args ): mixed {
		if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== 'echs' ) {
			return $result;
		}

		$info = self::fetch_remote_info();
		if ( ! $info ) {
			return $result;
		}

		return (object) [
			'name'          => $info['name'] ?? 'ECHoS SEO Analytics',
			'slug'          => 'echs',
			'version'       => $info['version'],
			'author'        => '<a href="https://mydigitalstride.com">Digital Stride</a>',
			'author_profile'=> 'https://mydigitalstride.com',
			'homepage'      => $info['url'] ?? 'https://mydigitalstride.com/echos-seo-analytics',
			'requires'      => $info['requires'] ?? '6.0',
			'requires_php'  => $info['requires_php'] ?? '8.0',
			'tested'        => $info['tested'] ?? '',
			'last_updated'  => $info['last_updated'] ?? '',
			'download_link' => $info['download_url'],
			'sections'      => array_merge( [
				'description' => '',
				'changelog'   => '',
			], $info['sections'] ?? [] ),
			'banners'       => $info['banners'] ?? [],
		];
	}

	public static function bust_cache( WP_Upgrader $upgrader, array $options ): void {
		if ( 'update' === ( $options['action'] ?? '' ) && 'plugin' === ( $options['type'] ?? '' ) ) {
			delete_transient( self::transient_key() );
			if ( ! empty( $options['plugins'] ) && in_array( self::PLUGIN_FILE, (array) $options['plugins'], true ) ) {
				update_option( 'echs_last_updated_version', ECHS_VERSION );
			}
		}
	}

	public static function maybe_show_changelog_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$last = get_option( 'echs_last_updated_version', '' );
		if ( '' === $last || $last === ECHS_VERSION ) {
			return;
		}

		$info = self::fetch_remote_info();
		$changelog = $info['sections']['changelog'] ?? '';

		if ( '' === $changelog ) {
			update_option( 'echs_last_updated_version', ECHS_VERSION );
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		?>
		<div class="notice notice-success is-dismissible" id="echs-update-notice">
			<p><strong>ECHoS SEO Analytics updated to v<?php echo esc_html( ECHS_VERSION ); ?></strong></p>
			<div style="max-height:120px;overflow:auto;font-size:13px;">
				<?php echo wp_kses_post( $changelog ); ?>
			</div>
		</div>
		<script>
		document.getElementById('echs-update-notice')
			?.querySelector('.notice-dismiss')
			?.addEventListener('click', function(){
				fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
					method: 'POST',
					headers: {'Content-Type': 'application/x-www-form-urlencoded'},
					body: 'action=echs_dismiss_update_notice&_ajax_nonce=<?php echo esc_js( wp_create_nonce( 'echs_dismiss_update_notice' ) ); ?>'
				});
			});
		</script>
		<?php
	}

	public static function ajax_dismiss_notice(): void {
		check_ajax_referer( 'echs_dismiss_update_notice' );
		if ( current_user_can( 'manage_options' ) ) {
			update_option( 'echs_last_updated_version', ECHS_VERSION );
		}
		wp_die();
	}
}
