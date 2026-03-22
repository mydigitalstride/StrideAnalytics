<?php
/**
 * Global settings page (Phase 2).
 *
 * @package HomeRite_Schema_Manager
 */

defined( 'ABSPATH' ) || exit;

class HSM_Global_Settings {

	public static function init(): void {
		add_action( 'admin_post_hsm_save_global', [ __CLASS__, 'save' ] );
	}

	// ------------------------------------------------------------------
	// Save handler
	// ------------------------------------------------------------------

	public static function save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'homerite-schema' ) );
		}

		check_admin_referer( 'hsm_global_settings_save', 'hsm_global_nonce' );

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
			'hsm_street_2',
			'hsm_city_2',
			'hsm_state_2',
			'hsm_zip_2',
			'hsm_rating_value',
			'hsm_rating_count',
			'hsm_logo_url',
			'hsm_price_range',
			'hsm_founded_year',
			'hsm_slogan',
		];

		foreach ( $text_fields as $field ) {
			$raw = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
			update_option( $field, $raw );
		}

		// Schema type – whitelist.
		$allowed_types = [ 'LocalBusiness', 'HomeAndConstructionBusiness', 'GeneralContractor', 'RoofingContractor', 'HVACBusiness', 'Plumber', 'Electrician' ];
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

		wp_redirect( add_query_arg( [ 'page' => 'homerite-schema-settings', 'saved' => '1' ], admin_url( 'options-general.php' ) ) );
		exit;
	}

	// ------------------------------------------------------------------
	// Render page
	// ------------------------------------------------------------------

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$days         = [ 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ];
		$hours        = get_option( 'hsm_hours', [] );
		$service_areas = get_option( 'hsm_service_areas', [] );
		$same_as      = get_option( 'hsm_same_as', [] );

		$schema_types = [
			'LocalBusiness'               => 'LocalBusiness',
			'HomeAndConstructionBusiness' => 'HomeAndConstructionBusiness',
			'GeneralContractor'           => 'GeneralContractor',
			'RoofingContractor'           => 'RoofingContractor',
			'HVACBusiness'                => 'HVACBusiness',
			'Plumber'                     => 'Plumber',
			'Electrician'                 => 'Electrician',
		];
		?>
		<div class="wrap hsm-settings-wrap">
			<h1><?php esc_html_e( 'HomeRite Schema Manager — Global Settings', 'homerite-schema' ); ?></h1>

			<?php if ( isset( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'homerite-schema' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hsm_save_global">
				<?php wp_nonce_field( 'hsm_global_settings_save', 'hsm_global_nonce' ); ?>

				<!-- ===== Business Identity ===== -->
				<div class="hsm-card">
					<h2><?php esc_html_e( 'Business Identity', 'homerite-schema' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="hsm_business_name"><?php esc_html_e( 'Business Name', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_business_name" name="hsm_business_name" value="<?php echo esc_attr( get_option( 'hsm_business_name' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_legal_name"><?php esc_html_e( 'Legal Name', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_legal_name" name="hsm_legal_name" value="<?php echo esc_attr( get_option( 'hsm_legal_name' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_schema_type"><?php esc_html_e( 'Schema @type', 'homerite-schema' ); ?></label></th>
							<td>
								<select id="hsm_schema_type" name="hsm_schema_type">
									<?php foreach ( $schema_types as $val => $label ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( get_option( 'hsm_schema_type' ), $val ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
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
					<h2><?php esc_html_e( 'Primary Address &amp; Location', 'homerite-schema' ); ?></h2>
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
							<th><label for="hsm_latitude"><?php esc_html_e( 'Latitude', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_latitude" name="hsm_latitude" value="<?php echo esc_attr( get_option( 'hsm_latitude' ) ); ?>" class="small-text" placeholder="40.7128"></td>
						</tr>
						<tr>
							<th><label for="hsm_longitude"><?php esc_html_e( 'Longitude', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_longitude" name="hsm_longitude" value="<?php echo esc_attr( get_option( 'hsm_longitude' ) ); ?>" class="small-text" placeholder="-74.0060"></td>
						</tr>
					</table>
				</div>

				<!-- ===== Secondary Address (Boalsburg) ===== -->
				<div class="hsm-card">
					<h2><?php esc_html_e( 'Secondary Address (Boalsburg)', 'homerite-schema' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="hsm_street_2"><?php esc_html_e( 'Street Address', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_street_2" name="hsm_street_2" value="<?php echo esc_attr( get_option( 'hsm_street_2' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_city_2"><?php esc_html_e( 'City', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_city_2" name="hsm_city_2" value="<?php echo esc_attr( get_option( 'hsm_city_2' ) ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_state_2"><?php esc_html_e( 'State', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_state_2" name="hsm_state_2" value="<?php echo esc_attr( get_option( 'hsm_state_2' ) ); ?>" class="regular-text" placeholder="PA"></td>
						</tr>
						<tr>
							<th><label for="hsm_zip_2"><?php esc_html_e( 'ZIP Code', 'homerite-schema' ); ?></label></th>
							<td><input type="text" id="hsm_zip_2" name="hsm_zip_2" value="<?php echo esc_attr( get_option( 'hsm_zip_2' ) ); ?>" class="small-text"></td>
						</tr>
					</table>
				</div>

				<!-- ===== Service Area ===== -->
				<div class="hsm-card">
					<h2><?php esc_html_e( 'Service Area', 'homerite-schema' ); ?></h2>
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
					<h2><?php esc_html_e( 'Hours of Operation', 'homerite-schema' ); ?></h2>
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
					<h2><?php esc_html_e( 'Social Profiles (sameAs)', 'homerite-schema' ); ?></h2>
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
					<h2><?php esc_html_e( 'Aggregate Rating (Manual Override)', 'homerite-schema' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="hsm_rating_value"><?php esc_html_e( 'Rating Value', 'homerite-schema' ); ?></label></th>
							<td><input type="number" id="hsm_rating_value" name="hsm_rating_value" value="<?php echo esc_attr( get_option( 'hsm_rating_value' ) ); ?>" min="1" max="5" step="0.1" class="small-text"></td>
						</tr>
						<tr>
							<th><label for="hsm_rating_count"><?php esc_html_e( 'Review Count', 'homerite-schema' ); ?></label></th>
							<td><input type="number" id="hsm_rating_count" name="hsm_rating_count" value="<?php echo esc_attr( get_option( 'hsm_rating_count' ) ); ?>" min="0" class="small-text"></td>
						</tr>
					</table>
				</div>

				<!-- ===== Organization Extras ===== -->
				<div class="hsm-card">
					<h2><?php esc_html_e( 'Organization Extras', 'homerite-schema' ); ?></h2>
					<table class="form-table">
						<tr>
							<th><label for="hsm_logo_url"><?php esc_html_e( 'Logo URL', 'homerite-schema' ); ?></label></th>
							<td>
								<input type="url" id="hsm_logo_url" name="hsm_logo_url" value="<?php echo esc_attr( get_option( 'hsm_logo_url' ) ); ?>" class="regular-text">
								<button type="button" class="button hsm-upload-image" data-target="hsm_logo_url"><?php esc_html_e( 'Choose Image', 'homerite-schema' ); ?></button>
								<p class="description"><?php esc_html_e( 'Leave blank to auto-use WordPress site logo.', 'homerite-schema' ); ?></p>
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

				<?php submit_button( __( 'Save Global Settings', 'homerite-schema' ) ); ?>
			</form>
		</div>
		<?php
	}
}
