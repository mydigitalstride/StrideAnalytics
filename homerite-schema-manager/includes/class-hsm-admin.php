<?php
/**
 * Admin menu registration and asset enqueuing.
 *
 * @package HomeRite_Schema_Manager
 */

defined( 'ABSPATH' ) || exit;

class HSM_Admin {

	public static function init(): void {
		add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
	}

	/**
	 * Add top-level "Stride Analytics" menu entry in the WP left sidebar.
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'Stride Analytics', 'homerite-schema' ),
			__( 'Stride Analytics', 'homerite-schema' ),
			'manage_options',
			'homerite-schema-settings',
			[ 'HSM_Global_Settings', 'render_page' ],
			'dashicons-chart-line',
			25
		);
	}

	/**
	 * Enqueue CSS + JS only on the plugin's own admin pages and post edit screens.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public static function enqueue_assets( string $hook ): void {
		$relevant = [
			'toplevel_page_homerite-schema-settings',
			'post.php',
			'post-new.php',
		];

		if ( ! in_array( $hook, $relevant, true ) ) {
			return;
		}

		wp_enqueue_style(
			'hsm-admin',
			HSM_PLUGIN_URL . 'assets/css/admin.css',
			[],
			HSM_VERSION
		);

		wp_enqueue_media(); // for image upload picker.

		wp_enqueue_script(
			'hsm-admin',
			HSM_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			HSM_VERSION,
			true
		);

		wp_localize_script( 'hsm-admin', 'hsmData', [
			'nonce'         => wp_create_nonce( 'hsm_meta_box_nonce' ),
			'ajaxurl'       => admin_url( 'admin-ajax.php' ),
			'businessTypes' => HSM_Global_Settings::get_business_categories(),
		] );
	}
}
