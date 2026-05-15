<?php
/**
 * Admin menu registration and asset enqueuing.
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Admin {

	public static function init(): void {
		add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
		add_action( 'wp_dashboard_setup',    [ __CLASS__, 'register_dashboard_widget' ] );
	}

	/**
	 * Add top-level "ECHoS SEO Analytics" menu entry in the WP left sidebar.
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'ECHoS SEO Analytics', 'echs' ),
			__( 'ECHoS SEO Analytics', 'echs' ),
			'manage_options',
			'echs-settings',
			[ 'ECHS_Global_Settings', 'render_page' ],
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
			'toplevel_page_echs-settings',
			'echos-seo-analytics_page_echs-redirects',
			'echos-seo-analytics_page_echs-gbp',
			'echos-seo-analytics_page_echs-gbp-jobs',
			'post.php',
			'post-new.php',
		];

		if ( ! in_array( $hook, $relevant, true ) ) {
			return;
		}

		wp_enqueue_style(
			'echs-admin',
			ECHS_PLUGIN_URL . 'assets/css/admin.css',
			[],
			ECHS_VERSION
		);

		wp_enqueue_media(); // for image upload picker.

		wp_enqueue_script(
			'echs-admin',
			ECHS_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			ECHS_VERSION,
			true
		);

		wp_localize_script( 'echs-admin', 'echsData', [
			'nonce'         => wp_create_nonce( 'echs_meta_box_nonce' ),
			'ajaxurl'       => admin_url( 'admin-ajax.php' ),
			'businessTypes' => ECHS_Global_Settings::get_business_categories(),
		] );
	}

	public static function register_dashboard_widget(): void {
		wp_add_dashboard_widget(
			'echs_dashboard_widget',
			'ECHoS SEO Analytics',
			[ __CLASS__, 'render_dashboard_widget' ]
		);
	}

	public static function render_dashboard_widget(): void {
		echo '<div class="echs-dashboard-widget">';

		// Broadcast message (if any).
		ECHS_Broadcast::render_widget_section();

		// Google Search status.
		ECHS_SEO_Status::render_widget_section();

		// Google Business Profile health.
		ECHS_GBP::render_widget_section();

		echo '<p class="echs-widget-footer echs-widget-settings-link">'
			. '<a href="' . esc_url( admin_url( 'admin.php?page=echs-settings' ) ) . '">'
			. 'ECHoS SEO Analytics Settings &rarr;</a></p>';

		echo '</div>';
	}
}
