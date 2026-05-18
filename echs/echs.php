<?php
/**
 * Plugin Name:       ECHoS SEO Analytics
 * Plugin URI:        https://mydigitalstride.com/echos-seo-analytics
 * Description:       Engineering, Construction, Home Services SEO Analytics. All-in-one SEO, structured data (JSON-LD), and marketing analytics plugin. Manages LocalBusiness schema, XML sitemaps, redirect manager, 404 monitoring, multi-keyword clustering, readability analysis, WooCommerce, GTM, Facebook Pixel, LinkedIn Insight Tag, Google Search status, broadcast messages, and Google Business Profile management with geo-tagged job photo push.
 * Version:           2.4.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Digital Stride
 * Author URI:        https://mydigitalstride.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       echs
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'ECHS_VERSION',     '2.4.0' );
define( 'ECHS_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'ECHS_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'ECHS_PLUGIN_FILE', __FILE__ );

// Register custom cron interval (6 hours) needed by ECHS_SEO_Status.
add_filter( 'cron_schedules', static function ( array $schedules ): array {
	if ( ! isset( $schedules['echs_sixhours'] ) ) {
		$schedules['echs_sixhours'] = [
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => 'Every 6 Hours',
		];
	}
	return $schedules;
} );

// Autoload includes.
$echs_includes = [
	'includes/class-echs-activator.php',
	'includes/class-echs-admin.php',
	'includes/class-echs-global-settings.php',
	'includes/class-echs-meta-box.php',
	'includes/class-echs-schema-output.php',
	'includes/class-echs-tracking.php',
	'includes/class-echs-woocommerce.php',
	'includes/class-echs-seo-status.php',
	'includes/class-echs-broadcast.php',
	'includes/class-echs-sitemap.php',
	'includes/class-echs-redirects.php',
	'includes/class-echs-404-monitor.php',
	'includes/class-echs-google-auth.php',
	'includes/class-echs-gbp.php',
	'includes/class-echs-gbp-jobs.php',
	'includes/class-echs-yoast-migrator.php',
	'includes/class-echs-tasks.php',
];

foreach ( $echs_includes as $file ) {
	require_once ECHS_PLUGIN_DIR . $file;
}

// Activation / deactivation hooks.
register_activation_hook( __FILE__,   [ 'ECHS_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'ECHS_Activator', 'deactivate' ] );

/**
 * Bootstrap the plugin.
 */
function echs_init(): void {
	ECHS_Admin::init();
	ECHS_Global_Settings::init();
	ECHS_Meta_Box::init();
	ECHS_Schema_Output::init();
	ECHS_Tracking::init();
	ECHS_WooCommerce::init();
	ECHS_SEO_Status::init();
	ECHS_Broadcast::init();
	ECHS_Sitemap::init();
	ECHS_Redirects::init();
	ECHS_404_Monitor::init();
	ECHS_Google_Auth::init();
	ECHS_GBP::init();
	ECHS_GBP_Jobs::init();
	ECHS_Yoast_Migrator::init();
	ECHS_Tasks::init();
}
add_action( 'plugins_loaded', 'echs_init' );
