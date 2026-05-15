<?php
/**
 * Plugin Name:       Stride Analytics
 * Plugin URI:        https://mydigitalstride.com/stride-analytics
 * Description:       All-in-one SEO, structured data (JSON-LD), and marketing analytics plugin. Manages LocalBusiness, Service, Product, FAQPage, HowTo, Review, and BreadcrumbList schema types with per-page customization, WooCommerce integration, GTM, Facebook Pixel, LinkedIn Insight Tag, comment/pingback controls, Google Search status monitoring, and broadcast messages from Digital Stride.
 * Version:           2.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Digital Stride
 * Author URI:        https://mydigitalstride.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       homerite-schema
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'HSM_VERSION',     '2.1.0' );
define( 'HSM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'HSM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'HSM_PLUGIN_FILE', __FILE__ );

// Register custom cron interval (6 hours) needed by HSM_SEO_Status.
add_filter( 'cron_schedules', static function ( array $schedules ): array {
	if ( ! isset( $schedules['hsm_sixhours'] ) ) {
		$schedules['hsm_sixhours'] = [
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => 'Every 6 Hours',
		];
	}
	return $schedules;
} );

// Autoload includes.
$hsm_includes = [
	'includes/class-hsm-activator.php',
	'includes/class-hsm-admin.php',
	'includes/class-hsm-global-settings.php',
	'includes/class-hsm-meta-box.php',
	'includes/class-hsm-schema-output.php',
	'includes/class-hsm-tracking.php',
	'includes/class-hsm-woocommerce.php',
	'includes/class-hsm-seo-status.php',
	'includes/class-hsm-broadcast.php',
];

foreach ( $hsm_includes as $file ) {
	require_once HSM_PLUGIN_DIR . $file;
}

// Activation / deactivation hooks.
register_activation_hook( __FILE__,   [ 'HSM_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'HSM_Activator', 'deactivate' ] );

/**
 * Bootstrap the plugin.
 */
function hsm_init(): void {
	HSM_Admin::init();
	HSM_Global_Settings::init();
	HSM_Meta_Box::init();
	HSM_Schema_Output::init();
	HSM_Tracking::init();
	HSM_WooCommerce::init();
	HSM_SEO_Status::init();
	HSM_Broadcast::init();
}
add_action( 'plugins_loaded', 'hsm_init' );
