<?php
/**
 * Global settings page.
 *
 * @package Stride_Analytics
 */

defined( 'ABSPATH' ) || exit;

class HSM_Global_Settings {

	public static function init(): void {
		add_action( 'admin_post_hsm_save_global', [ __CLASS__, 'save' ] );
	}

	// ------------------------------------------------------------------
	// Business category / type data
	// ------------------------------------------------------------------

	/**
	 * Returns the full category → schema type map.
	 * Used in the settings UI and passed to JS for the cascading dropdowns.
	 */
	public static function get_business_categories(): array {
		return [
			'Home & Construction' => [
				'HomeAndConstructionBusiness' => 'Home & Construction (General)',
				'GeneralContractor'           => 'General Contractor',
				'RoofingContractor'           => 'Roofing Contractor',
				'HVACBusiness'                => 'HVAC / Heating & Cooling',
				'Plumber'                     => 'Plumber / Plumbing',
				'Electrician'                 => 'Electrician',
				'HousePainter'                => 'House Painter',
				'Locksmith'                   => 'Locksmith',
				'MovingCompany'               => 'Moving Company',
			],
			'Food & Dining' => [
				'FoodEstablishment'           => 'Food Establishment (General)',
				'Restaurant'                  => 'Restaurant',
				'CafeOrCoffeeShop'            => 'Cafe / Coffee Shop',
				'BarOrPub'                    => 'Bar or Pub',
				'Bakery'                      => 'Bakery',
				'FastFoodRestaurant'          => 'Fast Food Restaurant',
				'IceCreamShop'                => 'Ice Cream Shop',
				'Winery'                      => 'Winery / Brewery',
			],
			'Healthcare & Medical' => [
				'MedicalBusiness'             => 'Medical Business (General)',
				'Physician'                   => 'Doctor / Physician',
				'Dentist'                     => 'Dentist',
				'Pharmacy'                    => 'Pharmacy',
				'Optician'                    => 'Optician / Optometrist',
				'MedicalClinic'               => 'Medical Clinic / Urgent Care',
				'Physiotherapist'             => 'Physical Therapist',
				'Chiropractor'                => 'Chiropractor',
			],
			'Beauty & Personal Care' => [
				'BeautySalon'                 => 'Beauty Salon (General)',
				'HairSalon'                   => 'Hair Salon / Barber',
				'NailSalon'                   => 'Nail Salon',
				'DaySpa'                      => 'Day Spa',
				'TattooParlor'                => 'Tattoo Parlor',
			],
			'Fitness & Sports' => [
				'SportsActivityLocation'      => 'Sports & Fitness (General)',
				'HealthClub'                  => 'Gym / Health Club',
				'ExerciseGym'                 => 'Exercise Gym',
				'BowlingAlley'                => 'Bowling Alley',
				'GolfCourse'                  => 'Golf Course',
				'TennisComplex'               => 'Tennis Complex',
			],
			'Automotive' => [
				'AutomotiveBusiness'          => 'Automotive (General)',
				'AutoRepair'                  => 'Auto Repair / Mechanic',
				'AutoDealer'                  => 'Car Dealership',
				'AutoPartsStore'              => 'Auto Parts Store',
				'GasStation'                  => 'Gas Station',
				'AutoBodyShop'                => 'Auto Body Shop',
			],
			'Retail & Shopping' => [
				'Store'                       => 'Retail Store (General)',
				'ClothingStore'               => 'Clothing / Apparel Store',
				'ElectronicsStore'            => 'Electronics Store',
				'BookStore'                   => 'Bookstore',
				'GroceryStore'                => 'Grocery / Supermarket',
				'HardwareStore'               => 'Hardware Store',
				'PetStore'                    => 'Pet Store',
				'SportingGoodsStore'          => 'Sporting Goods Store',
				'HomeGoodsStore'              => 'Home Goods Store',
				'FurnitureStore'              => 'Furniture Store',
				'JewelryStore'                => 'Jewelry Store',
				'ToyStore'                    => 'Toy Store',
			],
			'Legal & Financial' => [
				'LegalService'                => 'Legal Service (General)',
				'Attorney'                    => 'Attorney / Law Firm',
				'Notary'                      => 'Notary',
				'FinancialService'            => 'Financial Service (General)',
				'AccountingService'           => 'Accountant / CPA',
				'InsuranceAgency'             => 'Insurance Agency',
				'BankOrCreditUnion'           => 'Bank / Credit Union',
			],
			'Real Estate & Lodging' => [
				'RealEstateAgent'             => 'Real Estate Agent / Agency',
				'LodgingBusiness'             => 'Lodging (General)',
				'Hotel'                       => 'Hotel',
				'Motel'                       => 'Motel',
				'BedAndBreakfast'             => 'Bed & Breakfast',
				'Hostel'                      => 'Hostel',
				'Resort'                      => 'Resort',
			],
			'Entertainment & Events' => [
				'EntertainmentBusiness'       => 'Entertainment (General)',
				'MovieTheater'                => 'Movie Theater / Cinema',
				'NightClub'                   => 'Night Club',
				'AmusementPark'               => 'Amusement Park',
				'Casino'                      => 'Casino',
				'PerformingArtsTheater'       => 'Theater / Performing Arts',
				'ArtGallery'                  => 'Art Gallery / Museum',
				'ComedyClub'                  => 'Comedy Club',
			],
			'Travel & Hospitality' => [
				'TravelAgency'                => 'Travel Agency',
				'TouristInformationCenter'    => 'Tourist Information Center',
			],
			'Professional Services' => [
				'ProfessionalService'         => 'Professional Services (General)',
				'EmploymentAgency'            => 'Staffing / Employment Agency',
				'ComputerStore'               => 'IT / Computer Services',
			],
			'Education' => [
				'EducationalOrganization'     => 'Educational Organization (General)',
				'School'                      => 'School (K–12)',
				'CollegeOrUniversity'         => 'College / University',
				'Library'                     => 'Library',
			],
			'Other' => [
				'LocalBusiness'               => 'Local Business (Generic)',
				'Organization'                => 'Organization / Nonprofit',
			],
		];
	}

	/**
	 * Returns a flat list of all allowed schema type values.
	 */
	public static function get_all_allowed_types(): array {
		$all = [];
		foreach ( self::get_business_categories() as $types ) {
			foreach ( array_keys( $types ) as $type ) {
				$all[] = $type;
			}
		}
		return $all;
	}

	// ------------------------------------------------------------------
	// Tooltip helper
	// ------------------------------------------------------------------

	private static function tip( string $text ): string {
		return '<span class="hsm-tooltip" tabindex="0" aria-label="' . esc_attr( $text ) . '">?'
			. '<span class="hsm-tooltip-text" role="tooltip">' . esc_html( $text ) . '</span>'
			. '</span>';
	}

	// ------------------------------------------------------------------
	// Save handler
	// ------------------------------------------------------------------

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'homerite-schema' ) );
		}

		check_admin_referer( 'hsm_global_settings_save', 'hsm_global_nonce' );

		// --- Schema / business text fields ---
		$text_fields = [
			'hsm_business_name',
			'hsm_legal_name',
			'hsm_phone',
			'hsm_email',
			'hsm_primary_url',
			'hsm_street',
			'hsm_city',
			'hsm_state',
			'hsm_zip',
			'hsm_latitude',
			'hsm_longitude',
			'hsm_rating_value',
			'hsm_rating_count',
			'hsm_logo_url',
			'hsm_price_range',
			'hsm_founded_year',
			'hsm_slogan',
		];

		// Secondary locations – repeatable address blocks.
		$secondary_locations = [];
		if ( ! empty( $_POST['hsm_loc_street'] ) && is_array( $_POST['hsm_loc_street'] ) ) {
			$streets = array_map( 'sanitize_text_field', wp_unslash( $_POST['hsm_loc_street'] ) );
			$cities  = array_map( 'sanitize_text_field', wp_unslash( $_POST['hsm_loc_city']   ?? [] ) );
			$states  = array_map( 'sanitize_text_field', wp_unslash( $_POST['hsm_loc_state']  ?? [] ) );
			$zips    = array_map( 'sanitize_text_field', wp_unslash( $_POST['hsm_loc_zip']    ?? [] ) );
			$labels  = array_map( 'sanitize_text_field', wp_unslash( $_POST['hsm_loc_label']  ?? [] ) );
			foreach ( $streets as $i => $street ) {
				if ( '' !== $street ) {
					$secondary_locations[] = [
						'label'  => $labels[ $i ] ?? '',
						'street' => $street,
						'city'   => $cities[ $i ]  ?? '',
						'state'  => $states[ $i ]  ?? '',
						'zip'    => $zips[ $i ]    ?? '',
					];
				}
			}
		}
		update_option( 'hsm_secondary_locations', $secondary_locations );

		foreach ( $text_fields as $field ) {
			$raw = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			update_option( $field, $raw );
		}

		// Schema type – whitelist against all registered types.
		$allowed_types = self::get_all_allowed_types();
		$schema_type   = isset( $_POST['hsm_schema_type'] ) ? sanitize_text_field( wp_unslash( $_POST['hsm_schema_type'] ) ) : 'HomeAndConstructionBusiness';
		update_option( 'hsm_schema_type', in_array( $schema_type, $allowed_types, true ) ? $schema_type : 'HomeAndConstructionBusiness' );

		// Service areas – repeatable text array.
		$areas = [];
		if ( ! empty( $_POST['hsm_service_areas'] ) && is_array( $_POST['hsm_service_areas'] ) ) {
			foreach ( array_map( 'sanitize_text_field', wp_unslash( $_POST['hsm_service_areas'] ) ) as $area ) {
				if ( '' !== $area ) {
					$areas[] = $area;
				}
			}
		}
		update_option( 'hsm_service_areas', $areas );

		// Hours of operation.
		$days  = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
		$hours = [];
		foreach ( $days as $day ) {
			$open  = isset( $_POST[ 'hsm_hours_open_' . $day ] )  ? sanitize_text_field( wp_unslash( $_POST[ 'hsm_hours_open_' . $day ] ) )  : '';
			$close = isset( $_POST[ 'hsm_hours_close_' . $day ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'hsm_hours_close_' . $day ] ) ) : '';
			if ( '' !== $open || '' !== $close ) {
				$hours[ $day ] = [ 'open' => $open, 'close' => $close ];
			}
		}
		update_option( 'hsm_hours', $hours );

		// sameAs URLs – repeatable URL array.
		$same_as = [];
		if ( ! empty( $_POST['hsm_same_as'] ) && is_array( $_POST['hsm_same_as'] ) ) {
			foreach ( wp_unslash( $_POST['hsm_same_as'] ) as $url ) {
				$clean = esc_url_raw( $url );
				if ( '' !== $clean ) {
					$same_as[] = $clean;
				}
			}
		}
		update_option( 'hsm_same_as', $same_as );

		// --- Tracking fields (keep original option names for back-compat) ---
		$tracking_text = [ 'tracking_gtm_id', 'tracking_facebook_pixel_id', 'tracking_linkedin_partner_id', 'utm_form_field' ];
		foreach ( $tracking_text as $field ) {
			$raw = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			update_option( $field, $raw );
		}

		// GTM approval — clicking "Connect GTM" sets this.
		if ( ! empty( $_POST['approve_gtm'] ) ) {
			update_option( 'gtm_approved', '1' );
		}
		// If GTM ID is cleared, reset approval.
		if ( '' === get_option( 'tracking_gtm_id' ) ) {
			update_option( 'gtm_approved', '' );
		}

		// Toggle checkboxes (0 or 1).
		update_option( 'disable_comments',  ! empty( $_POST['disable_comments'] )  ? '1' : '' );
		update_option( 'disable_pingbacks', ! empty( $_POST['disable_pingbacks'] ) ? '1' : '' );

		wp_redirect( add_query_arg( [ 'page' => 'homerite-schema-settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	// ------------------------------------------------------------------
	// Render page
	// ------------------------------------------------------------------

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$days                = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
		$hours               = get_option( 'hsm_hours', [] );
		$service_areas       = get_option( 'hsm_service_areas', [] );
		$same_as             = get_option( 'hsm_same_as', [] );
		$secondary_locations = get_option( 'hsm_secondary_locations', [] );
		$saved_type          = get_option( 'hsm_schema_type', 'HomeAndConstructionBusiness' );
		$business_categories = self::get_business_categories();

		// Find the saved type's category for pre-selecting the category dropdown.
		$saved_category = '';
		foreach ( $business_categories as $cat => $types ) {
			if ( array_key_exists( $saved_type, $types ) ) {
				$saved_category = $cat;
				break;
			}
		}
		?>
		<div class="wrap hsm-settings-wrap">
			<h1><?php esc_html_e( 'Stride Analytics — Global Settings', 'homerite-schema' ); ?></h1>

			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'homerite-schema' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hsm_save_global">
				<?php wp_nonce_field( 'hsm_global_settings_save', 'hsm_global_nonce' ); ?>

				<!-- ===== Business Identity ===== -->
				<div class="hsm-card">
					<h2>
						<?php esc_html_e( 'Business Identity', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Your company\'s public profile — name, type, and contact details that search engines show when people look you up online.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table">
						<tr>
							<th><label for="hsm_business_name"><?php esc_html_e( 'Business Name', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_business_name" name="hsm_business_name" value="<?php echo esc_attr( get_option( 'hsm_business_name' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_legal_name"><?php esc_html_e( 'Legal Name', 'homerite-schema' ); ?></label></th>
							<td>
								<input type="text" id="hsm_legal_name" name="hsm_legal_name" value="<?php echo esc_attr( get_option( 'hsm_legal_name' ) ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Official registered name if different from the display name.', 'homerite-schema' ); ?></p>
							</td>
						</tr>

						<!-- Business Type — two-step cascading dropdowns -->
						<tr>
							<th>
								<?php esc_html_e( 'Business Type', 'homerite-schema' ); ?>
								<?php echo self::tip( 'Choose the industry category first, then pick the specific schema.org type. This tells Google exactly what kind of business you are.' ); // phpcs:ignore ?>
							</th>
							<td>
								<div class="hsm-business-type-row">
									<!-- Step 1: Industry category (cosmetic, not submitted) -->
									<div class="hsm-type-step">
										<label class="hsm-type-step-label" for="hsm_business_category">
											<?php esc_html_e( 'Step 1 — Industry', 'homerite-schema' ); ?>
										</label>
										<select id="hsm_business_category" name="">
											<option value=""><?php esc_html_e( '— Select Industry —', 'homerite-schema' ); ?></option>
											<?php foreach ( array_keys( $business_categories ) as $cat ) : ?>
												<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $saved_category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>

									<!-- Step 2: Specific type (submitted) -->
									<div class="hsm-type-step">
										<label class="hsm-type-step-label" for="hsm_schema_type">
											<?php esc_html_e( 'Step 2 — Business Type', 'homerite-schema' ); ?>
										</label>
										<select id="hsm_schema_type" name="hsm_schema_type">
											<?php foreach ( $business_categories as $cat => $types ) : ?>
												<optgroup label="<?php echo esc_attr( $cat ); ?>">
													<?php foreach ( $types as $val => $label ) : ?>
														<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $saved_type, $val ); ?>><?php echo esc_html( $label ); ?></option>
													<?php endforeach; ?>
												</optgroup>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<p class="description"><?php esc_html_e( 'Select your industry first to narrow down the list, then choose your specific business type. This value powers your schema.org @type.', 'homerite-schema' ); ?></p>
							</td>
						</tr>

						<tr>
							<th><label for="hsm_phone"><?php esc_html_e( 'Phone', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_phone" name="hsm_phone" value="<?php echo esc_attr( get_option( 'hsm_phone' ) ); ?>" class="regular-text" placeholder="+1-555-555-5555"></td>
						</tr>
						<tr>
							<th><label for="hsm_email"><?php esc_html_e( 'Email', 'homerite-schema' ); ?></label></th>
							<td><input type="email" id="hsm_email" name="hsm_email" value="<?php echo esc_attr( get_option( 'hsm_email' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_primary_url"><?php esc_html_e( 'Primary URL', 'homerite-schema' ); ?></label></th>
							<td><input type="url" id="hsm_primary_url" name="hsm_primary_url" value="<?php echo esc_attr( get_option( 'hsm_primary_url' ) ); ?>" class="regular-text"></td>
						</tr>
					</table>
				</div>

				<!-- ===== Primary Address ===== -->
				<div class="hsm-card">
					<h2>
						<?php esc_html_e( 'Primary Address &amp; Location', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Your main office or business location. Google uses this to show your business on maps and in local search results near you.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table">
						<tr>
							<th><label for="hsm_street"><?php esc_html_e( 'Street Address', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_street" name="hsm_street" value="<?php echo esc_attr( get_option( 'hsm_street' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_city"><?php esc_html_e( 'City', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_city" name="hsm_city" value="<?php echo esc_attr( get_option( 'hsm_city' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_state"><?php esc_html_e( 'State', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_state" name="hsm_state" value="<?php echo esc_attr( get_option( 'hsm_state' ) ); ?>" class="regular-text" placeholder="PA"></td>
						</tr>
						<tr>
							<th><label for="hsm_zip"><?php esc_html_e( 'ZIP Code', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_zip" name="hsm_zip" value="<?php echo esc_attr( get_option( 'hsm_zip' ) ); ?>" class="small-text"></td>
						</tr>
						<tr>
							<th>
								<?php esc_html_e( 'Coordinates', 'homerite-schema' ); ?>
								<?php echo self::tip( 'Exact lat/long pinpoints your location on the map. Click "Find Coordinates" to auto-fill from the address above.' ); // phpcs:ignore ?>
							</th>
							<td>
								<div class="hsm-geo-row">
									<label for="hsm_latitude" class="hsm-geo-label"><?php esc_html_e( 'Lat', 'homerite-schema' ); ?></label>
									<input type="text" id="hsm_latitude" name="hsm_latitude" value="<?php echo esc_attr( get_option( 'hsm_latitude' ) ); ?>" class="small-text" placeholder="40.7128">
									<label for="hsm_longitude" class="hsm-geo-label"><?php esc_html_e( 'Long', 'homerite-schema' ); ?></label>
									<input type="text" id="hsm_longitude" name="hsm_longitude" value="<?php echo esc_attr( get_option( 'hsm_longitude' ) ); ?>" class="small-text" placeholder="-74.0060">
									<button type="button" id="hsm-find-coords" class="button">
										<?php esc_html_e( 'Find Coordinates', 'homerite-schema' ); ?>
									</button>
									<span id="hsm-geo-status" class="description" style="margin-left:8px;"></span>
								</div>
								<p class="description"><?php esc_html_e( 'Results powered by OpenStreetMap.', 'homerite-schema' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- ===== Secondary Locations ===== -->
				<div class="hsm-card">
					<h2>
						<?php esc_html_e( 'Secondary Locations', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Additional offices or locations. Google will know you have a physical presence in each place.' ); // phpcs:ignore ?>
					</h2>
					<p class="description"><?php esc_html_e( 'Add each additional office or job site address.', 'homerite-schema' ); ?></p>

					<div id="hsm-locations-list">
						<?php
						$locations_to_show = ! empty( $secondary_locations ) ? $secondary_locations : [ [] ];
						foreach ( $locations_to_show as $loc ) :
							$loc_label  = $loc['label']  ?? '';
							$loc_street = $loc['street'] ?? '';
							$loc_city   = $loc['city']   ?? '';
							$loc_state  = $loc['state']  ?? '';
							$loc_zip    = $loc['zip']    ?? '';
						?>
						<div class="hsm-location-block">
							<div class="hsm-location-block-header">
								<input type="text" name="hsm_loc_label[]" value="<?php echo esc_attr( $loc_label ); ?>" class="regular-text hsm-location-label-input" placeholder="<?php esc_attr_e( 'Location name (e.g. Boalsburg Office)', 'homerite-schema' ); ?>">
								<button type="button" class="button hsm-remove-location"><?php esc_html_e( 'Remove', 'homerite-schema' ); ?></button>
							</div>
							<div class="hsm-location-block-fields">
								<input type="text" name="hsm_loc_street[]" value="<?php echo esc_attr( $loc_street ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Street Address', 'homerite-schema' ); ?>">
								<input type="text" name="hsm_loc_city[]"   value="<?php echo esc_attr( $loc_city ); ?>"   class="regular-text" placeholder="<?php esc_attr_e( 'City', 'homerite-schema' ); ?>">
								<input type="text" name="hsm_loc_state[]"  value="<?php echo esc_attr( $loc_state ); ?>"  class="small-text"   placeholder="<?php esc_attr_e( 'State', 'homerite-schema' ); ?>">
								<input type="text" name="hsm_loc_zip[]"    value="<?php echo esc_attr( $loc_zip ); ?>"    class="small-text"   placeholder="<?php esc_attr_e( 'ZIP', 'homerite-schema' ); ?>">
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<button type="button" class="button" id="hsm-add-location"><?php esc_html_e( '+ Add Location', 'homerite-schema' ); ?></button>
				</div>

				<!-- ===== Service Area ===== -->
				<div class="hsm-card">
					<h2>
						<?php esc_html_e( 'Service Area', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Towns and cities where you do work. Google uses this to match your business with searchers in those areas.' ); // phpcs:ignore ?>
					</h2>
					<p class="description"><?php esc_html_e( 'Add each city or town you serve.', 'homerite-schema' ); ?></p>
					<div id="hsm-service-areas-list">
						<?php
						$areas_to_show = ! empty( $service_areas ) ? $service_areas : [ '' ];
						foreach ( $areas_to_show as $area ) :
						?>
							<div class="hsm-repeatable-row">
								<input type="text" name="hsm_service_areas[]" value="<?php echo esc_attr( $area ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Harrisburg', 'homerite-schema' ); ?>">
								<button type="button" class="button hsm-remove-row"><?php esc_html_e( 'Remove', 'homerite-schema' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button hsm-add-area" data-target="hsm-service-areas-list" data-name="hsm_service_areas[]"><?php esc_html_e( '+ Add City/Town', 'homerite-schema' ); ?></button>
				</div>

				<!-- ===== Hours of Operation ===== -->
				<div class="hsm-card">
					<h2>
						<?php esc_html_e( 'Hours of Operation', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Your regular business hours. Google can show these in search results and on Google Maps.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table hsm-hours-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Day', 'homerite-schema' ); ?></th>
								<th><?php esc_html_e( 'Opens', 'homerite-schema' ); ?></th>
								<th><?php esc_html_e( 'Closes', 'homerite-schema' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $days as $day ) :
							$open  = $hours[ $day ]['open']  ?? '';
							$close = $hours[ $day ]['close'] ?? '';
						?>
							<tr>
								<td><?php echo esc_html( $day ); ?></td>
								<td><input type="time" name="<?php echo esc_attr( 'hsm_hours_open_' . $day ); ?>" value="<?php echo esc_attr( $open ); ?>"></td>
								<td><input type="time" name="<?php echo esc_attr( 'hsm_hours_close_' . $day ); ?>" value="<?php echo esc_attr( $close ); ?>"></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- ===== Social / sameAs ===== -->
				<div class="hsm-card">
					<h2>
						<?php esc_html_e( 'Social Profiles', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Links to your business profiles on Google, Facebook, Nextdoor, BBB, and similar sites. Helps search engines confirm who you are.' ); // phpcs:ignore ?>
					</h2>
					<p class="description"><?php esc_html_e( 'Google Business, Facebook, Nextdoor, BBB, etc.', 'homerite-schema' ); ?></p>
					<div id="hsm-same-as-list">
						<?php
						$same_as_to_show = ! empty( $same_as ) ? $same_as : [ '' ];
						foreach ( $same_as_to_show as $url ) :
						?>
							<div class="hsm-repeatable-row">
								<input type="url" name="hsm_same_as[]" value="<?php echo esc_attr( $url ); ?>" class="regular-text" placeholder="https://">
								<button type="button" class="button hsm-remove-row"><?php esc_html_e( 'Remove', 'homerite-schema' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button hsm-add-area" data-target="hsm-same-as-list" data-name="hsm_same_as[]" data-type="url"><?php esc_html_e( '+ Add Profile URL', 'homerite-schema' ); ?></button>
				</div>

				<!-- ===== Ratings ===== -->
				<div class="hsm-card">
					<h2>
						<?php esc_html_e( 'Aggregate Rating', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Your overall customer rating. Enter your average star rating and total review count so Google may show gold stars next to your business in search results.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table">
						<tr>
							<th><label for="hsm_rating_value"><?php esc_html_e( 'Average Star Rating', 'homerite-schema' ); ?></label></th>
							<td><input type="number" id="hsm_rating_value" name="hsm_rating_value" value="<?php echo esc_attr( get_option( 'hsm_rating_value' ) ); ?>" min="1" max="5" step="0.1" class="small-text" placeholder="4.9"></td>
						</tr>
						<tr>
							<th><label for="hsm_rating_count"><?php esc_html_e( 'Total Review Count', 'homerite-schema' ); ?></label></th>
							<td><input type="number" id="hsm_rating_count" name="hsm_rating_count" value="<?php echo esc_attr( get_option( 'hsm_rating_count' ) ); ?>" min="0" class="small-text" placeholder="128"></td>
						</tr>
					</table>
				</div>

				<!-- ===== Organization Extras ===== -->
				<div class="hsm-card">
					<h2>
						<?php esc_html_e( 'Organization Extras', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Extra details — logo, pricing level, years in business, and a short description. Help Google paint a fuller picture of who you are.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table">
						<tr>
							<th><label for="hsm_logo_url"><?php esc_html_e( 'Logo URL', 'homerite-schema' ); ?></label></th>
							<td>
								<input type="url" id="hsm_logo_url" name="hsm_logo_url" value="<?php echo esc_attr( get_option( 'hsm_logo_url' ) ); ?>" class="regular-text">
								<button type="button" class="button hsm-upload-image" data-target="hsm_logo_url"><?php esc_html_e( 'Choose Image', 'homerite-schema' ); ?></button>
								<p class="description"><?php esc_html_e( 'Leave blank to auto-use the WordPress site logo.', 'homerite-schema' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="hsm_price_range"><?php esc_html_e( 'Price Range', 'homerite-schema' ); ?></label></th>
							<td>
								<select id="hsm_price_range" name="hsm_price_range">
									<?php foreach ( [ '$', '$$', '$$$', '$$$$' ] as $pr ) : ?>
										<option value="<?php echo esc_attr( $pr ); ?>" <?php selected( get_option( 'hsm_price_range', '$' ), $pr ); ?>><?php echo esc_html( $pr ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( '$ = budget-friendly, $$$$ = premium. Shown on Google Maps.', 'homerite-schema' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="hsm_founded_year"><?php esc_html_e( 'Founded Year', 'homerite-schema' ); ?></label></th>
							<td><input type="number" id="hsm_founded_year" name="hsm_founded_year" value="<?php echo esc_attr( get_option( 'hsm_founded_year' ) ); ?>" min="1800" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_slogan"><?php esc_html_e( 'Slogan / Description', 'homerite-schema' ); ?></label></th>
							<td><textarea id="hsm_slogan" name="hsm_slogan" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'hsm_slogan' ) ); ?></textarea></td>
						</tr>
					</table>
				</div>

				<!-- ===== Tracking ===== -->
				<div class="hsm-card">
					<h2>
						<?php esc_html_e( 'Analytics &amp; Tracking', 'homerite-schema' ); ?>
						<?php echo self::tip( 'Add your marketing tracking codes here so you can measure site visitors, traffic sources, and which ads are working.' ); // phpcs:ignore ?>
					</h2>

					<table class="form-table">
						<tr>
							<th>
								<label for="tracking_gtm_id"><?php esc_html_e( 'Google Tag Manager ID', 'homerite-schema' ); ?></label>
								<?php echo self::tip( 'Manage all your tracking codes from one dashboard without touching code. Your ID starts with "GTM-".' ); // phpcs:ignore ?>
							</th>
							<td>
								<input type="text" id="tracking_gtm_id" name="tracking_gtm_id" value="<?php echo esc_attr( get_option( 'tracking_gtm_id' ) ); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
								<?php if ( get_option( 'tracking_gtm_id' ) && ! get_option( 'gtm_approved' ) ) : ?>
									<p>
										<input type="hidden" name="approve_gtm" value="1">
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Connect GTM', 'homerite-schema' ); ?></button>
										<span class="description"><?php esc_html_e( 'Save and activate your GTM container.', 'homerite-schema' ); ?></span>
									</p>
								<?php elseif ( get_option( 'gtm_approved' ) ) : ?>
									<p class="hsm-connected-badge">&#10003; <?php esc_html_e( 'GTM Connected', 'homerite-schema' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th>
								<label for="tracking_facebook_pixel_id"><?php esc_html_e( 'Facebook Pixel ID', 'homerite-schema' ); ?></label>
								<?php echo self::tip( 'Tracks visitors from Facebook/Instagram ads so you can measure results and retarget them later.' ); // phpcs:ignore ?>
							</th>
							<td><input type="text" id="tracking_facebook_pixel_id" name="tracking_facebook_pixel_id" value="<?php echo esc_attr( get_option( 'tracking_facebook_pixel_id' ) ); ?>" class="regular-text" placeholder="1234567890"></td>
						</tr>
						<tr>
							<th>
								<label for="tracking_linkedin_partner_id"><?php esc_html_e( 'LinkedIn Partner ID', 'homerite-schema' ); ?></label>
								<?php echo self::tip( 'Tracks professionals who visit from LinkedIn. Useful for B2B advertising to specific job roles.' ); // phpcs:ignore ?>
							</th>
							<td><input type="text" id="tracking_linkedin_partner_id" name="tracking_linkedin_partner_id" value="<?php echo esc_attr( get_option( 'tracking_linkedin_partner_id' ) ); ?>" class="regular-text" placeholder="123456"></td>
						</tr>
						<tr>
							<th>
								<label for="utm_form_field"><?php esc_html_e( 'UTM Form Field Name', 'homerite-schema' ); ?></label>
								<?php echo self::tip( 'UTM parameters track which ad, email, or link brought a visitor to your site. Enter the name of the hidden form field where you want UTM data stored.' ); // phpcs:ignore ?>
							</th>
							<td><input type="text" id="utm_form_field" name="utm_form_field" value="<?php echo esc_attr( get_option( 'utm_form_field' ) ); ?>" class="regular-text" placeholder="utm_source"></td>
						</tr>
					</table>

					<h3 style="margin-top:20px;">
						<?php esc_html_e( 'Site Behaviour', 'homerite-schema' ); ?>
					</h3>
					<table class="form-table">
						<tr>
							<th>
								<?php esc_html_e( 'Disable All Comments', 'homerite-schema' ); ?>
								<?php echo self::tip( 'Turns off comments on all posts and pages. Good for business sites that want a clean, professional look.' ); // phpcs:ignore ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="disable_comments" value="1" <?php checked( '1', get_option( 'disable_comments' ) ); ?>>
									<?php esc_html_e( 'Yes, disable comments sitewide', 'homerite-schema' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th>
								<?php esc_html_e( 'Disable Pingbacks', 'homerite-schema' ); ?>
								<?php echo self::tip( 'Pingbacks are automatic notifications when another site links to yours. Disabling reduces spam and unnecessary server traffic.' ); // phpcs:ignore ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="disable_pingbacks" value="1" <?php checked( '1', get_option( 'disable_pingbacks' ) ); ?>>
									<?php esc_html_e( 'Yes, disable pingbacks sitewide', 'homerite-schema' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Save Settings', 'homerite-schema' ) ); ?>
			</form>
		</div>
		<?php
	}
}
