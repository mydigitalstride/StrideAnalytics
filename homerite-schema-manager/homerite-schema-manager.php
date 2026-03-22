<?php
/**
 * Plugin Name:       HomeRite Schema Manager
 * Plugin URI:        https://mydigitalstride.com/homerite-schema-manager
 * Description:       Comprehensive structured data (JSON-LD) and SEO meta management for HomeRite. Supports LocalBusiness, Service, Product, FAQPage, and BreadcrumbList schema types with per-page customization and WooCommerce integration.
 * Version:           1.0.0
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
define( 'HSM_VERSION',     '1.0.0' );
define( 'HSM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'HSM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'HSM_PLUGIN_FILE', __FILE__ );

// Autoload includes.
$hsm_includes = [
	'includes/class-hsm-activator.php',
	'includes/class-hsm-admin.php',
	'includes/class-hsm-global-settings.php',
	'includes/class-hsm-meta-box.php',
	'includes/class-hsm-schema-output.php',
	'includes/class-hsm-woocommerce.php',
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
	HSM_WooCommerce::init();
}
add_action( 'plugins_loaded', 'hsm_init' );
