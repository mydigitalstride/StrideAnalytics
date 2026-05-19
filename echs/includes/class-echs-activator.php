<?php
/**
 * Handles plugin activation, deactivation, and default options.
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Activator {

	/**
	 * Default global settings stored in wp_options.
	 */
	public static function activate(): void {
		ECHS_Sitemap::activate();
		ECHS_Redirects::create_table();
		ECHS_404_Monitor::create_table();
		ECHS_404_Monitor::schedule_cron();

		$defaults = [
			// Business identity.
			'echs_business_name'   => get_bloginfo( 'name' ),
			'echs_legal_name'      => '',
			'echs_schema_type'     => 'HomeAndConstructionBusiness',
			'echs_phone'           => '',
			'echs_email'           => get_bloginfo( 'admin_email' ),
			'echs_primary_url'     => home_url(),

			// Address – primary.
			'echs_street'          => '',
			'echs_city'            => '',
			'echs_state'           => '',
			'echs_zip'             => '',
			'echs_latitude'        => '',
			'echs_longitude'       => '',

			// Secondary locations (repeatable).
			'echs_secondary_locations' => [],  // array of {label, street, city, state, zip}.

			// Service area.
			'echs_service_areas'   => [],   // array of city/town strings.

			// Hours of operation (keyed by day abbreviation).
			'echs_hours'           => [],

			// Social / sameAs.
			'echs_same_as'         => [],   // array of URLs.

			// Ratings.
			'echs_rating_value'    => '',
			'echs_rating_count'    => '',

			// Organization extras.
			'echs_logo_url'        => '',
			'echs_price_range'     => '$',
			'echs_founded_year'    => '',
			'echs_slogan'          => '',
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Preserve data on deactivation but clear the scheduled cron event.
	 */
	public static function deactivate(): void {
		ECHS_404_Monitor::clear_cron();
	}
}
