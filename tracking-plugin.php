<?php
/**
 * Plugin Name: Stride Analytics - Basic (Deprecated)
 * Description: This plugin has been fully merged into Stride Analytics. Please deactivate and delete this plugin — all features are available in Stride Analytics.
 * Version: 1.2
 * Author: Digital Stride
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_notices', static function (): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning">'
		. '<p><strong>Stride Analytics - Basic</strong> is no longer needed. '
		. 'All of its features (GTM, Facebook Pixel, LinkedIn Insight Tag, comment controls) '
		. 'are now built into <strong>Stride Analytics</strong>. '
		. 'Please <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">deactivate and delete</a> this plugin.</p>'
		. '</div>';
} );
