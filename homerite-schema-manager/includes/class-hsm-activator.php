<?php
/**
 * Handles plugin activation, deactivation, and default options.
 *
 * @package HomeRite_Schema_Manager
 */

defined( 'ABSPATH' ) || exit;

class HSM_Activator {

	/**
	 * Default global settings stored in wp_options.
	 */
	public static function activate(): void {
		$defaults = [
			// Business identity.
			'hsm_business_name'   => get_bloginfo( 'name' ),
			'hsm_legal_name'      => '',
			'hsm_schema_type'     => 'HomeAndConstructionBusiness',
			'hsm_phone'           => '',
			'hsm_email'           => get_bloginfo( 'admin_email' ),
			'hsm_primary_url'     => home_url(),

			// Address – primary.
			'hsm_street'          => '',
			'hsm_city'            => '',
			'hsm_state'           => '',
			'hsm_zip'             => '',
			'hsm_latitude'        => '',
			'hsm_longitude'       => '',

			// Secondary locations (repeatable).
			'hsm_secondary_locations' => [],  // array of {label, street, city, state, zip}.

			// Service area.
			'hsm_service_areas'   => [],   // array of city/town strings.

			// Hours of operation (keyed by day abbreviation).
			'hsm_hours'           => [],

			// Social / sameAs.
			'hsm_same_as'         => [],   // array of URLs.

			// Ratings.
			'hsm_rating_value'    => '',
			'hsm_rating_count'    => '',

			// Organization extras.
			'hsm_logo_url'        => '',
			'hsm_price_range'     => '$',
			'hsm_founded_year'    => '',
			'hsm_slogan'          => '',
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Nothing to clean on deactivation (preserve data).
	 */
	public static function deactivate(): void {}
}
