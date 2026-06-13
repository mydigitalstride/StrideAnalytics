<?php
/**
 * Global settings page.
 *
 * @package Stride_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Global_Settings {

	public static function init(): void {
		add_action( 'admin_post_echs_save_global', [ __CLASS__, 'save' ] );
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
		return '<span class="echs-tooltip" tabindex="0" aria-label="' . esc_attr( $text ) . '">?'
			. '<span class="echs-tooltip-text" role="tooltip">' . esc_html( $text ) . '</span>'
			. '</span>';
	}

	// ------------------------------------------------------------------
	// Save handler
	// ------------------------------------------------------------------

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'echs' ) );
		}

		check_admin_referer( 'echs_global_settings_save', 'echs_global_nonce' );

		// --- Schema / business text fields ---
		$text_fields = [
			'echs_business_name',
			'echs_legal_name',
			'echs_phone',
			'echs_email',
			'echs_primary_url',
			'echs_street',
			'echs_city',
			'echs_state',
			'echs_zip',
			'echs_latitude',
			'echs_longitude',
			'echs_rating_value',
			'echs_rating_count',
			'echs_logo_url',
			'echs_default_og_image',
			'echs_price_range',
			'echs_founded_year',
			'echs_slogan',
		];

		// Secondary locations – repeatable address blocks.
		$secondary_locations = [];
		if ( ! empty( $_POST['echs_loc_street'] ) && is_array( $_POST['echs_loc_street'] ) ) {
			$streets = array_map( 'sanitize_text_field', wp_unslash( $_POST['echs_loc_street'] ) );
			$cities  = array_map( 'sanitize_text_field', wp_unslash( $_POST['echs_loc_city']   ?? [] ) );
			$states  = array_map( 'sanitize_text_field', wp_unslash( $_POST['echs_loc_state']  ?? [] ) );
			$zips    = array_map( 'sanitize_text_field', wp_unslash( $_POST['echs_loc_zip']    ?? [] ) );
			$labels  = array_map( 'sanitize_text_field', wp_unslash( $_POST['echs_loc_label']  ?? [] ) );
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
		update_option( 'echs_secondary_locations', $secondary_locations );

		foreach ( $text_fields as $field ) {
			$raw = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			update_option( $field, $raw );
		}

		// Schema type – whitelist against all registered types.
		$allowed_types = self::get_all_allowed_types();
		$schema_type   = isset( $_POST['echs_schema_type'] ) ? sanitize_text_field( wp_unslash( $_POST['echs_schema_type'] ) ) : 'HomeAndConstructionBusiness';
		update_option( 'echs_schema_type', in_array( $schema_type, $allowed_types, true ) ? $schema_type : 'HomeAndConstructionBusiness' );

		// Service areas – repeatable text array.
		$areas = [];
		if ( ! empty( $_POST['echs_service_areas'] ) && is_array( $_POST['echs_service_areas'] ) ) {
			foreach ( array_map( 'sanitize_text_field', wp_unslash( $_POST['echs_service_areas'] ) ) as $area ) {
				if ( '' !== $area ) {
					$areas[] = $area;
				}
			}
		}
		update_option( 'echs_service_areas', $areas );

		// Hours of operation.
		$days  = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
		$hours = [];
		foreach ( $days as $day ) {
			$open  = isset( $_POST[ 'echs_hours_open_' . $day ] )  ? sanitize_text_field( wp_unslash( $_POST[ 'echs_hours_open_' . $day ] ) )  : '';
			$close = isset( $_POST[ 'echs_hours_close_' . $day ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'echs_hours_close_' . $day ] ) ) : '';
			if ( '' !== $open || '' !== $close ) {
				$hours[ $day ] = [ 'open' => $open, 'close' => $close ];
			}
		}
		update_option( 'echs_hours', $hours );

		// sameAs URLs – repeatable URL array.
		$same_as = [];
		if ( ! empty( $_POST['echs_same_as'] ) && is_array( $_POST['echs_same_as'] ) ) {
			foreach ( wp_unslash( $_POST['echs_same_as'] ) as $url ) {
				$clean = esc_url_raw( $url );
				if ( '' !== $clean ) {
					$same_as[] = $clean;
				}
			}
		}
		update_option( 'echs_same_as', $same_as );

		// --- Team members ---
		$team_members = [];
		if ( ! empty( $_POST['echs_team_name'] ) && is_array( $_POST['echs_team_name'] ) ) {
			$names      = array_map( 'sanitize_text_field', wp_unslash( $_POST['echs_team_name'] ) );
			$titles     = array_map( 'sanitize_text_field', wp_unslash( $_POST['echs_team_job_title'] ?? [] ) );
			$linkedins  = wp_unslash( $_POST['echs_team_linkedin'] ?? [] );
			$images     = wp_unslash( $_POST['echs_team_image'] ?? [] );
			foreach ( $names as $i => $name ) {
				if ( '' !== $name ) {
					$team_members[] = [
						'name'      => $name,
						'job_title' => $titles[ $i ] ?? '',
						'linkedin'  => esc_url_raw( $linkedins[ $i ] ?? '' ),
						'image'     => esc_url_raw( $images[ $i ] ?? '' ),
					];
				}
			}
		}
		update_option( 'echs_team_members', $team_members );

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

		wp_redirect( add_query_arg( [ 'page' => 'echs-settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
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
		$hours               = get_option( 'echs_hours', [] );
		$service_areas       = get_option( 'echs_service_areas', [] );
		$same_as             = get_option( 'echs_same_as', [] );
		$secondary_locations = get_option( 'echs_secondary_locations', [] );
		$team_members        = get_option( 'echs_team_members', [] );
		$saved_type          = get_option( 'echs_schema_type', 'HomeAndConstructionBusiness' );
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
		<div class="wrap echs-settings-wrap">
			<h1><?php esc_html_e( 'ECHoS SEO Analytics — Global Settings', 'echs' ); ?></h1>

			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'echs' ); ?></p></div>
			<?php endif; ?>

			<?php if ( isset( $_GET['setup'] ) && 'complete' === $_GET['setup'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><strong>Setup complete!</strong> Your business information has been saved. You can fine-tune everything below.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="echs_save_global">
				<?php wp_nonce_field( 'echs_global_settings_save', 'echs_global_nonce' ); ?>

				<!-- ===== Business Identity ===== -->
				<div class="echs-card">
					<h2>
						<?php esc_html_e( 'Business Identity', 'echs' ); ?>
						<?php echo self::tip( 'Your company\'s public profile — name, type, and contact details that search engines show when people look you up online.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table">
						<tr>
							<th><label for="echs_business_name"><?php esc_html_e( 'Business Name', 'echs' ); ?></label></th>
							<td><input type="text" id="echs_business_name" name="echs_business_name" value="<?php echo esc_attr( get_option( 'echs_business_name' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="echs_legal_name"><?php esc_html_e( 'Legal Name', 'echs' ); ?></label></th>
							<td>
								<input type="text" id="echs_legal_name" name="echs_legal_name" value="<?php echo esc_attr( get_option( 'echs_legal_name' ) ); ?>" class="regular-text">
								<p class="description"><?php esc_html_e( 'Official registered name if different from the display name.', 'echs' ); ?></p>
							</td>
						</tr>

						<!-- Business Type — two-step cascading dropdowns -->
						<tr>
							<th>
								<?php esc_html_e( 'Business Type', 'echs' ); ?>
								<?php echo self::tip( 'Choose the industry category first, then pick the specific schema.org type. This tells Google exactly what kind of business you are.' ); // phpcs:ignore ?>
							</th>
							<td>
								<div class="echs-business-type-row">
									<!-- Step 1: Industry category (cosmetic, not submitted) -->
									<div class="echs-type-step">
										<label class="echs-type-step-label" for="echs_business_category">
											<?php esc_html_e( 'Step 1 — Industry', 'echs' ); ?>
										</label>
										<select id="echs_business_category" name="">
											<option value=""><?php esc_html_e( '— Select Industry —', 'echs' ); ?></option>
											<?php foreach ( array_keys( $business_categories ) as $cat ) : ?>
												<option value="<?php echo esc_attr( $cat ); ?>" <?php selected( $saved_category, $cat ); ?>><?php echo esc_html( $cat ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>

									<!-- Step 2: Specific type (submitted) -->
									<div class="echs-type-step">
										<label class="echs-type-step-label" for="echs_schema_type">
											<?php esc_html_e( 'Step 2 — Business Type', 'echs' ); ?>
										</label>
										<select id="echs_schema_type" name="echs_schema_type">
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
								<p class="description"><?php esc_html_e( 'Select your industry first to narrow down the list, then choose your specific business type. This value powers your schema.org @type.', 'echs' ); ?></p>
							</td>
						</tr>

						<tr>
							<th><label for="echs_phone"><?php esc_html_e( 'Phone', 'echs' ); ?></label></th>
							<td><input type="text" id="echs_phone" name="echs_phone" value="<?php echo esc_attr( get_option( 'echs_phone' ) ); ?>" class="regular-text" placeholder="+1-555-555-5555"></td>
						</tr>
						<tr>
							<th><label for="echs_email"><?php esc_html_e( 'Email', 'echs' ); ?></label></th>
							<td><input type="email" id="echs_email" name="echs_email" value="<?php echo esc_attr( get_option( 'echs_email' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="echs_primary_url"><?php esc_html_e( 'Primary URL', 'echs' ); ?></label></th>
							<td><input type="url" id="echs_primary_url" name="echs_primary_url" value="<?php echo esc_attr( get_option( 'echs_primary_url' ) ); ?>" class="regular-text"></td>
						</tr>
					</table>
				</div>

				<!-- ===== Primary Address ===== -->
				<div class="echs-card">
					<h2>
						<?php esc_html_e( 'Primary Address &amp; Location', 'echs' ); ?>
						<?php echo self::tip( 'Your main office or business location. Google uses this to show your business on maps and in local search results near you.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table">
						<tr>
							<th><label for="echs_street"><?php esc_html_e( 'Street Address', 'echs' ); ?></label></th>
							<td><input type="text" id="echs_street" name="echs_street" value="<?php echo esc_attr( get_option( 'echs_street' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="echs_city"><?php esc_html_e( 'City', 'echs' ); ?></label></th>
							<td><input type="text" id="echs_city" name="echs_city" value="<?php echo esc_attr( get_option( 'echs_city' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="echs_state"><?php esc_html_e( 'State', 'echs' ); ?></label></th>
							<td><input type="text" id="echs_state" name="echs_state" value="<?php echo esc_attr( get_option( 'echs_state' ) ); ?>" class="regular-text" placeholder="PA"></td>
						</tr>
						<tr>
							<th><label for="echs_zip"><?php esc_html_e( 'ZIP Code', 'echs' ); ?></label></th>
							<td><input type="text" id="echs_zip" name="echs_zip" value="<?php echo esc_attr( get_option( 'echs_zip' ) ); ?>" class="small-text"></td>
						</tr>
						<tr>
							<th>
								<?php esc_html_e( 'Coordinates', 'echs' ); ?>
								<?php echo self::tip( 'Exact lat/long pinpoints your location on the map. Click "Find Coordinates" to auto-fill from the address above.' ); // phpcs:ignore ?>
							</th>
							<td>
								<div class="echs-geo-row">
									<label for="echs_latitude" class="echs-geo-label"><?php esc_html_e( 'Lat', 'echs' ); ?></label>
									<input type="text" id="echs_latitude" name="echs_latitude" value="<?php echo esc_attr( get_option( 'echs_latitude' ) ); ?>" class="small-text" placeholder="40.7128">
									<label for="echs_longitude" class="echs-geo-label"><?php esc_html_e( 'Long', 'echs' ); ?></label>
									<input type="text" id="echs_longitude" name="echs_longitude" value="<?php echo esc_attr( get_option( 'echs_longitude' ) ); ?>" class="small-text" placeholder="-74.0060">
									<button type="button" id="echs-find-coords" class="button">
										<?php esc_html_e( 'Find Coordinates', 'echs' ); ?>
									</button>
									<span id="echs-geo-status" class="description" style="margin-left:8px;"></span>
								</div>
								<p class="description"><?php esc_html_e( 'Results powered by OpenStreetMap.', 'echs' ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<!-- ===== Secondary Locations ===== -->
				<div class="echs-card">
					<h2>
						<?php esc_html_e( 'Secondary Locations', 'echs' ); ?>
						<?php echo self::tip( 'Additional offices or locations. Google will know you have a physical presence in each place.' ); // phpcs:ignore ?>
					</h2>
					<p class="description"><?php esc_html_e( 'Add each additional office or job site address.', 'echs' ); ?></p>

					<div id="echs-locations-list">
						<?php
						$locations_to_show = ! empty( $secondary_locations ) ? $secondary_locations : [ [] ];
						foreach ( $locations_to_show as $loc ) :
							$loc_label  = $loc['label']  ?? '';
							$loc_street = $loc['street'] ?? '';
							$loc_city   = $loc['city']   ?? '';
							$loc_state  = $loc['state']  ?? '';
							$loc_zip    = $loc['zip']    ?? '';
						?>
						<div class="echs-location-block">
							<div class="echs-location-block-header">
								<input type="text" name="echs_loc_label[]" value="<?php echo esc_attr( $loc_label ); ?>" class="regular-text echs-location-label-input" placeholder="<?php esc_attr_e( 'Location name (e.g. Boalsburg Office)', 'echs' ); ?>">
								<button type="button" class="button echs-remove-location"><?php esc_html_e( 'Remove', 'echs' ); ?></button>
							</div>
							<div class="echs-location-block-fields">
								<input type="text" name="echs_loc_street[]" value="<?php echo esc_attr( $loc_street ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Street Address', 'echs' ); ?>">
								<input type="text" name="echs_loc_city[]"   value="<?php echo esc_attr( $loc_city ); ?>"   class="regular-text" placeholder="<?php esc_attr_e( 'City', 'echs' ); ?>">
								<input type="text" name="echs_loc_state[]"  value="<?php echo esc_attr( $loc_state ); ?>"  class="small-text"   placeholder="<?php esc_attr_e( 'State', 'echs' ); ?>">
								<input type="text" name="echs_loc_zip[]"    value="<?php echo esc_attr( $loc_zip ); ?>"    class="small-text"   placeholder="<?php esc_attr_e( 'ZIP', 'echs' ); ?>">
							</div>
						</div>
						<?php endforeach; ?>
					</div>

					<button type="button" class="button" id="echs-add-location"><?php esc_html_e( '+ Add Location', 'echs' ); ?></button>
				</div>

				<!-- ===== Service Area ===== -->
				<div class="echs-card">
					<h2>
						<?php esc_html_e( 'Service Area', 'echs' ); ?>
						<?php echo self::tip( 'Towns and cities where you do work. Google uses this to match your business with searchers in those areas.' ); // phpcs:ignore ?>
					</h2>
					<p class="description"><?php esc_html_e( 'Add each city or town you serve.', 'echs' ); ?></p>
					<div id="echs-service-areas-list">
						<?php
						$areas_to_show = ! empty( $service_areas ) ? $service_areas : [ '' ];
						foreach ( $areas_to_show as $area ) :
						?>
							<div class="echs-repeatable-row">
								<input type="text" name="echs_service_areas[]" value="<?php echo esc_attr( $area ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Harrisburg', 'echs' ); ?>">
								<button type="button" class="button echs-remove-row"><?php esc_html_e( 'Remove', 'echs' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button echs-add-area" data-target="echs-service-areas-list" data-name="echs_service_areas[]"><?php esc_html_e( '+ Add City/Town', 'echs' ); ?></button>
				</div>

				<!-- ===== Hours of Operation ===== -->
				<div class="echs-card">
					<h2>
						<?php esc_html_e( 'Hours of Operation', 'echs' ); ?>
						<?php echo self::tip( 'Your regular business hours. Google can show these in search results and on Google Maps.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table echs-hours-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Day', 'echs' ); ?></th>
								<th><?php esc_html_e( 'Opens', 'echs' ); ?></th>
								<th><?php esc_html_e( 'Closes', 'echs' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $days as $day ) :
							$open  = $hours[ $day ]['open']  ?? '';
							$close = $hours[ $day ]['close'] ?? '';
						?>
							<tr>
								<td><?php echo esc_html( $day ); ?></td>
								<td><input type="time" name="<?php echo esc_attr( 'echs_hours_open_' . $day ); ?>" value="<?php echo esc_attr( $open ); ?>"></td>
								<td><input type="time" name="<?php echo esc_attr( 'echs_hours_close_' . $day ); ?>" value="<?php echo esc_attr( $close ); ?>"></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<!-- ===== Social / sameAs ===== -->
				<div class="echs-card">
					<h2>
						<?php esc_html_e( 'Social Profiles', 'echs' ); ?>
						<?php echo self::tip( 'Links to your business profiles on Google, Facebook, Nextdoor, BBB, and similar sites. Helps search engines confirm who you are.' ); // phpcs:ignore ?>
					</h2>
					<p class="description"><?php esc_html_e( 'Google Business, Facebook, Nextdoor, BBB, etc.', 'echs' ); ?></p>
					<div id="echs-same-as-list">
						<?php
						$same_as_to_show = ! empty( $same_as ) ? $same_as : [ '' ];
						foreach ( $same_as_to_show as $url ) :
						?>
							<div class="echs-repeatable-row">
								<input type="url" name="echs_same_as[]" value="<?php echo esc_attr( $url ); ?>" class="regular-text" placeholder="https://">
								<button type="button" class="button echs-remove-row"><?php esc_html_e( 'Remove', 'echs' ); ?></button>
							</div>
						<?php endforeach; ?>
					</div>
					<button type="button" class="button echs-add-area" data-target="echs-same-as-list" data-name="echs_same_as[]" data-type="url"><?php esc_html_e( '+ Add Profile URL', 'echs' ); ?></button>
				</div>

				<!-- ===== Ratings ===== -->
				<div class="echs-card">
					<h2>
						<?php esc_html_e( 'Aggregate Rating', 'echs' ); ?>
						<?php echo self::tip( 'Your overall customer rating. Enter your average star rating and total review count so Google may show gold stars next to your business in search results.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table">
						<tr>
							<th><label for="echs_rating_value"><?php esc_html_e( 'Average Star Rating', 'echs' ); ?></label></th>
							<td><input type="number" id="echs_rating_value" name="echs_rating_value" value="<?php echo esc_attr( get_option( 'echs_rating_value' ) ); ?>" min="1" max="5" step="0.1" class="small-text" placeholder="4.9"></td>
						</tr>
						<tr>
							<th><label for="echs_rating_count"><?php esc_html_e( 'Total Review Count', 'echs' ); ?></label></th>
							<td><input type="number" id="echs_rating_count" name="echs_rating_count" value="<?php echo esc_attr( get_option( 'echs_rating_count' ) ); ?>" min="0" class="small-text" placeholder="128"></td>
						</tr>
					</table>
				</div>

				<!-- ===== Organization Extras ===== -->
				<div class="echs-card">
					<h2>
						<?php esc_html_e( 'Organization Extras', 'echs' ); ?>
						<?php echo self::tip( 'Extra details — logo, pricing level, years in business, and a short description. Help Google paint a fuller picture of who you are.' ); // phpcs:ignore ?>
					</h2>
					<table class="form-table">
						<tr>
							<th><label for="echs_logo_url"><?php esc_html_e( 'Logo URL', 'echs' ); ?></label></th>
							<td>
								<input type="url" id="echs_logo_url" name="echs_logo_url" value="<?php echo esc_attr( get_option( 'echs_logo_url' ) ); ?>" class="regular-text">
								<button type="button" class="button echs-upload-image" data-target="echs_logo_url"><?php esc_html_e( 'Choose Image', 'echs' ); ?></button>
								<p class="description"><?php esc_html_e( 'Leave blank to auto-use the WordPress site logo.', 'echs' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="echs_default_og_image"><?php esc_html_e( 'Default Social Image', 'echs' ); ?></label></th>
							<td>
								<input type="url" id="echs_default_og_image" name="echs_default_og_image" value="<?php echo esc_attr( get_option( 'echs_default_og_image' ) ); ?>" class="regular-text">
								<button type="button" class="button echs-upload-image" data-target="echs_default_og_image"><?php esc_html_e( 'Choose Image', 'echs' ); ?></button>
								<p class="description"><?php esc_html_e( 'Fallback og:image used when a page has no featured image and no custom OG image. Prevents blank social previews.', 'echs' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="echs_price_range"><?php esc_html_e( 'Price Range', 'echs' ); ?></label></th>
							<td>
								<select id="echs_price_range" name="echs_price_range">
									<?php foreach ( [ '$', '$$', '$$$', '$$$$' ] as $pr ) : ?>
										<option value="<?php echo esc_attr( $pr ); ?>" <?php selected( get_option( 'echs_price_range', '$' ), $pr ); ?>><?php echo esc_html( $pr ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php esc_html_e( '$ = budget-friendly, $$$$ = premium. Shown on Google Maps.', 'echs' ); ?></p>
							</td>
						</tr>
						<tr>
							<th><label for="echs_founded_year"><?php esc_html_e( 'Founded Year', 'echs' ); ?></label></th>
							<td><input type="number" id="echs_founded_year" name="echs_founded_year" value="<?php echo esc_attr( get_option( 'echs_founded_year' ) ); ?>" min="1800" max="<?php echo esc_attr( gmdate( 'Y' ) ); ?>" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="echs_slogan"><?php esc_html_e( 'Slogan / Description', 'echs' ); ?></label></th>
							<td><textarea id="echs_slogan" name="echs_slogan" rows="3" class="large-text"><?php echo esc_textarea( get_option( 'echs_slogan' ) ); ?></textarea></td>
						</tr>
					</table>
				</div>

				<!-- ===== Team Members ===== -->
			<div class="echs-card">
				<h2>
					<?php esc_html_e( 'Team Members', 'echs' ); ?>
					<?php echo self::tip( 'Add team members so AI and Google can identify real people behind the business. Names, job titles, and LinkedIn URLs are output as Person schema on every page.' ); // phpcs:ignore ?>
				</h2>

				<!-- Column headers -->
				<div style="display:grid;grid-template-columns:160px 1fr 1fr 1fr auto;gap:8px;align-items:center;margin-bottom:4px;padding:0 4px;">
					<strong><?php esc_html_e( 'Full Name', 'echs' ); ?></strong>
					<strong><?php esc_html_e( 'Job Title', 'echs' ); ?></strong>
					<strong><?php esc_html_e( 'LinkedIn URL', 'echs' ); ?></strong>
					<strong><?php esc_html_e( 'Headshot URL', 'echs' ); ?></strong>
					<span></span>
				</div>

				<div id="echs-team-list">
					<?php
					$team_to_show = ! empty( $team_members ) ? $team_members : [ [] ];
					foreach ( $team_to_show as $member ) :
					?>
					<div class="echs-repeatable-row echs-team-row">
						<input type="text" name="echs_team_name[]"      value="<?php echo esc_attr( $member['name']      ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. Jane Smith', 'echs' ); ?>">
						<input type="text" name="echs_team_job_title[]" value="<?php echo esc_attr( $member['job_title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'e.g. CEO / Founder', 'echs' ); ?>">
						<input type="url"  name="echs_team_linkedin[]"  value="<?php echo esc_attr( $member['linkedin']  ?? '' ); ?>" placeholder="https://linkedin.com/in/...">
						<input type="url"  name="echs_team_image[]"     value="<?php echo esc_attr( $member['image']     ?? '' ); ?>" placeholder="<?php esc_attr_e( 'optional', 'echs' ); ?>">
						<button type="button" class="button echs-remove-team-row"><?php esc_html_e( 'Remove', 'echs' ); ?></button>
					</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button echs-add-team-member" data-target="echs-team-list" style="margin-top:8px;"><?php esc_html_e( '+ Add Team Member', 'echs' ); ?></button>
			</div>

			<!-- ===== Tracking ===== -->
				<div class="echs-card">
					<h2>
						<?php esc_html_e( 'Analytics &amp; Tracking', 'echs' ); ?>
						<?php echo self::tip( 'Add your marketing tracking codes here so you can measure site visitors, traffic sources, and which ads are working.' ); // phpcs:ignore ?>
					</h2>

					<table class="form-table">
						<tr>
							<th>
								<label for="tracking_gtm_id"><?php esc_html_e( 'Google Tag Manager ID', 'echs' ); ?></label>
								<?php echo self::tip( 'Manage all your tracking codes from one dashboard without touching code. Your ID starts with "GTM-".' ); // phpcs:ignore ?>
							</th>
							<td>
								<input type="text" id="tracking_gtm_id" name="tracking_gtm_id" value="<?php echo esc_attr( get_option( 'tracking_gtm_id' ) ); ?>" class="regular-text" placeholder="GTM-XXXXXXX">
								<?php if ( get_option( 'tracking_gtm_id' ) && ! get_option( 'gtm_approved' ) ) : ?>
									<p>
										<input type="hidden" name="approve_gtm" value="1">
										<button type="submit" class="button button-primary"><?php esc_html_e( 'Connect GTM', 'echs' ); ?></button>
										<span class="description"><?php esc_html_e( 'Save and activate your GTM container.', 'echs' ); ?></span>
									</p>
								<?php elseif ( get_option( 'gtm_approved' ) ) : ?>
									<p class="echs-connected-badge">&#10003; <?php esc_html_e( 'GTM Connected', 'echs' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th>
								<label for="tracking_facebook_pixel_id"><?php esc_html_e( 'Facebook Pixel ID', 'echs' ); ?></label>
								<?php echo self::tip( 'Tracks visitors from Facebook/Instagram ads so you can measure results and retarget them later.' ); // phpcs:ignore ?>
							</th>
							<td><input type="text" id="tracking_facebook_pixel_id" name="tracking_facebook_pixel_id" value="<?php echo esc_attr( get_option( 'tracking_facebook_pixel_id' ) ); ?>" class="regular-text" placeholder="1234567890"></td>
						</tr>
						<tr>
							<th>
								<label for="tracking_linkedin_partner_id"><?php esc_html_e( 'LinkedIn Partner ID', 'echs' ); ?></label>
								<?php echo self::tip( 'Tracks professionals who visit from LinkedIn. Useful for B2B advertising to specific job roles.' ); // phpcs:ignore ?>
							</th>
							<td><input type="text" id="tracking_linkedin_partner_id" name="tracking_linkedin_partner_id" value="<?php echo esc_attr( get_option( 'tracking_linkedin_partner_id' ) ); ?>" class="regular-text" placeholder="123456"></td>
						</tr>
						<tr>
							<th>
								<label for="utm_form_field"><?php esc_html_e( 'UTM Form Field Name', 'echs' ); ?></label>
								<?php echo self::tip( 'UTM parameters track which ad, email, or link brought a visitor to your site. Enter the name of the hidden form field where you want UTM data stored.' ); // phpcs:ignore ?>
							</th>
							<td><input type="text" id="utm_form_field" name="utm_form_field" value="<?php echo esc_attr( get_option( 'utm_form_field' ) ); ?>" class="regular-text" placeholder="utm_source"></td>
						</tr>
					</table>

					<h3 style="margin-top:20px;">
						<?php esc_html_e( 'Site Behaviour', 'echs' ); ?>
					</h3>
					<table class="form-table">
						<tr>
							<th>
								<?php esc_html_e( 'Disable All Comments', 'echs' ); ?>
								<?php echo self::tip( 'Turns off comments on all posts and pages. Good for business sites that want a clean, professional look.' ); // phpcs:ignore ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="disable_comments" value="1" <?php checked( '1', get_option( 'disable_comments' ) ); ?>>
									<?php esc_html_e( 'Yes, disable comments sitewide', 'echs' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th>
								<?php esc_html_e( 'Disable Pingbacks', 'echs' ); ?>
								<?php echo self::tip( 'Pingbacks are automatic notifications when another site links to yours. Disabling reduces spam and unnecessary server traffic.' ); // phpcs:ignore ?>
							</th>
							<td>
								<label>
									<input type="checkbox" name="disable_pingbacks" value="1" <?php checked( '1', get_option( 'disable_pingbacks' ) ); ?>>
									<?php esc_html_e( 'Yes, disable pingbacks sitewide', 'echs' ); ?>
								</label>
							</td>
						</tr>
					</table>
				</div>

				<?php submit_button( __( 'Save Settings', 'echs' ) ); ?>
			</form>

			<?php ECHS_License::render_settings_section(); ?>

			<?php if ( ECHS_License::is_active() ) : ?>
				<?php ECHS_Google_Auth::render_settings_section(); ?>
			<?php else : ?>
				<div class="echs-card">
					<h2><?php esc_html_e( 'Google Business Profile', 'echs' ); ?></h2>
					<p><?php esc_html_e( 'Google Business Profile management, reviews monitoring, Q&A tracking, and job photo uploads are available with an active license.', 'echs' ); ?></p>
					<p>
						<a href="https://mydigitalstride.com/echos-seo-analytics" target="_blank" class="button button-primary">
							<?php esc_html_e( 'Get a License', 'echs' ); ?>
						</a>
					</p>
				</div>
			<?php endif; ?>

		</div>
		<?php
	}
}
