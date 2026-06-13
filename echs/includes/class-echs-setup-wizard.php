<?php
defined( 'ABSPATH' ) || exit;

class ECHS_Setup_Wizard {

	const OPTION_COMPLETE = 'echs_setup_complete';
	const STEPS = [ 'business', 'location', 'service_area', 'hours', 'social', 'registration' ];

	public static function init(): void {
		if ( get_option( self::OPTION_COMPLETE ) ) {
			return;
		}
		add_action( 'admin_menu', [ __CLASS__, 'register_page' ] );
		add_action( 'admin_init', [ __CLASS__, 'maybe_redirect' ] );
		add_action( 'wp_ajax_echs_setup_save', [ __CLASS__, 'ajax_save' ] );
	}

	public static function register_page(): void {
		$hook = add_submenu_page(
			null,
			'ECHoS Setup',
			'Setup',
			'manage_options',
			'echs-setup',
			[ __CLASS__, 'render' ]
		);
		add_action( 'admin_print_styles-' . $hook, [ __CLASS__, 'enqueue_assets' ] );
	}

	public static function maybe_redirect(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( get_option( self::OPTION_COMPLETE ) ) {
			return;
		}

		global $pagenow;
		$page = $_GET['page'] ?? '';

		if ( 'admin.php' === $pagenow && 'echs-setup' === $page ) {
			return;
		}

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		$is_echs_page = 'admin.php' === $pagenow && str_starts_with( $page, 'echs-' );
		if ( $is_echs_page ) {
			wp_safe_redirect( admin_url( 'admin.php?page=echs-setup' ) );
			exit;
		}
	}

	public static function enqueue_assets(): void {
		wp_enqueue_style( 'echs-admin', ECHS_PLUGIN_URL . 'assets/css/admin.css', [], ECHS_VERSION );
	}

	public static function ajax_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized.' );
		}
		check_ajax_referer( 'echs_setup_nonce', '_nonce' );

		$step = sanitize_key( $_POST['step'] ?? '' );

		switch ( $step ) {
			case 'business':
				$fields = [ 'echs_business_name', 'echs_phone', 'echs_email' ];
				foreach ( $fields as $f ) {
					if ( isset( $_POST[ $f ] ) ) {
						update_option( $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
					}
				}
				if ( isset( $_POST['echs_business_type'] ) ) {
					$allowed = ECHS_Global_Settings::get_all_allowed_types();
					$type    = sanitize_text_field( wp_unslash( $_POST['echs_business_type'] ) );
					if ( in_array( $type, $allowed, true ) ) {
						update_option( 'echs_business_type', $type );
					}
				}
				if ( isset( $_POST['echs_description'] ) ) {
					update_option( 'echs_description', sanitize_textarea_field( wp_unslash( $_POST['echs_description'] ) ) );
				}
				break;

			case 'location':
				$fields = [ 'echs_street', 'echs_city', 'echs_state', 'echs_zip' ];
				foreach ( $fields as $f ) {
					if ( isset( $_POST[ $f ] ) ) {
						update_option( $f, sanitize_text_field( wp_unslash( $_POST[ $f ] ) ) );
					}
				}
				break;

			case 'service_area':
				$areas = [];
				if ( ! empty( $_POST['echs_service_areas'] ) && is_array( $_POST['echs_service_areas'] ) ) {
					foreach ( array_map( 'sanitize_text_field', wp_unslash( $_POST['echs_service_areas'] ) ) as $area ) {
						if ( '' !== $area ) {
							$areas[] = $area;
						}
					}
				}
				update_option( 'echs_service_areas', $areas );
				break;

			case 'hours':
				$days  = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
				$hours = [];
				foreach ( $days as $day ) {
					$open  = isset( $_POST[ 'echs_hours_open_' . $day ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'echs_hours_open_' . $day ] ) ) : '';
					$close = isset( $_POST[ 'echs_hours_close_' . $day ] ) ? sanitize_text_field( wp_unslash( $_POST[ 'echs_hours_close_' . $day ] ) ) : '';
					$hours[ $day ] = [ 'open' => $open, 'close' => $close ];
				}
				update_option( 'echs_hours', $hours );
				break;

			case 'social':
				$urls = [];
				if ( ! empty( $_POST['echs_same_as'] ) && is_array( $_POST['echs_same_as'] ) ) {
					foreach ( wp_unslash( $_POST['echs_same_as'] ) as $url ) {
						$url = esc_url_raw( trim( $url ) );
						if ( '' !== $url ) {
							$urls[] = $url;
						}
					}
				}
				update_option( 'echs_same_as', $urls );
				break;

			case 'registration':
				$reg = [
					'first_name'        => sanitize_text_field( wp_unslash( $_POST['echs_reg_first_name'] ?? '' ) ),
					'last_name'         => sanitize_text_field( wp_unslash( $_POST['echs_reg_last_name'] ?? '' ) ),
					'email'             => sanitize_email( wp_unslash( $_POST['echs_reg_email'] ?? '' ) ),
					'company'           => sanitize_text_field( wp_unslash( $_POST['echs_reg_company'] ?? '' ) ),
					'consent_marketing' => ! empty( $_POST['echs_reg_consent_marketing'] ),
					'consent_data'      => ! empty( $_POST['echs_reg_consent_data'] ),
				];
				update_option( 'echs_registration', $reg );

				self::send_setup_email( $reg );

				update_option( self::OPTION_COMPLETE, '1' );
				wp_send_json_success( [ 'redirect' => admin_url( 'admin.php?page=echs-settings&setup=complete' ) ] );
				return;
		}

		wp_send_json_success();
	}

	private static function send_setup_email( array $reg ): void {
		$site_url = home_url();
		$site_name = get_bloginfo( 'name' );

		$business_name = get_option( 'echs_business_name', '' );
		$business_type = get_option( 'echs_business_type', '' );
		$phone         = get_option( 'echs_phone', '' );
		$biz_email     = get_option( 'echs_email', '' );
		$street        = get_option( 'echs_street', '' );
		$city          = get_option( 'echs_city', '' );
		$state         = get_option( 'echs_state', '' );
		$zip           = get_option( 'echs_zip', '' );
		$service_areas = get_option( 'echs_service_areas', [] );
		$hours         = get_option( 'echs_hours', [] );
		$same_as       = get_option( 'echs_same_as', [] );

		$address = implode( ', ', array_filter( [ $street, $city, $state, $zip ] ) );

		$body  = "New ECHoS SEO Analytics Setup\n";
		$body .= "==============================\n\n";

		$body .= "REGISTRATION\n";
		$body .= "Name: {$reg['first_name']} {$reg['last_name']}\n";
		$body .= "Email: {$reg['email']}\n";
		$body .= "Company: {$reg['company']}\n";
		$body .= "Marketing consent: " . ( $reg['consent_marketing'] ? 'Yes' : 'No' ) . "\n";
		$body .= "Data usage consent: " . ( $reg['consent_data'] ? 'Yes' : 'No' ) . "\n\n";

		$body .= "SITE\n";
		$body .= "URL: {$site_url}\n";
		$body .= "Site name: {$site_name}\n\n";

		$body .= "BUSINESS IDENTITY\n";
		$body .= "Business name: {$business_name}\n";
		$body .= "Type: {$business_type}\n";
		$body .= "Phone: {$phone}\n";
		$body .= "Email: {$biz_email}\n\n";

		$body .= "PRIMARY LOCATION\n";
		$body .= "Address: {$address}\n\n";

		if ( ! empty( $service_areas ) ) {
			$body .= "SERVICE AREA\n";
			$body .= implode( ', ', $service_areas ) . "\n\n";
		}

		if ( ! empty( $hours ) ) {
			$body .= "HOURS OF OPERATION\n";
			foreach ( $hours as $day => $times ) {
				if ( $times['open'] && $times['close'] ) {
					$body .= ucfirst( $day ) . ": {$times['open']} – {$times['close']}\n";
				} else {
					$body .= ucfirst( $day ) . ": Closed\n";
				}
			}
			$body .= "\n";
		}

		if ( ! empty( $same_as ) ) {
			$body .= "SOCIAL PROFILES\n";
			foreach ( $same_as as $url ) {
				$body .= "  {$url}\n";
			}
		}

		wp_mail(
			'Results@MyDigitalStride.com',
			'ECHoS Setup: ' . $business_name . ' (' . $site_url . ')',
			$body,
			[ 'Content-Type: text/plain; charset=UTF-8' ]
		);
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$categories = ECHS_Global_Settings::get_business_categories();
		$nonce      = wp_create_nonce( 'echs_setup_nonce' );
		?>
		<style>
			.echs-wizard-wrap { max-width:700px; margin:40px auto; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
			.echs-wizard-header { text-align:center; margin-bottom:32px; }
			.echs-wizard-header h1 { font-size:28px; margin-bottom:8px; }
			.echs-wizard-header p { color:#646970; font-size:15px; }

			.echs-wizard-progress { display:flex; gap:4px; margin-bottom:32px; }
			.echs-wizard-progress .echs-prog-step { flex:1; height:4px; background:#ddd; border-radius:2px; transition:background .3s; }
			.echs-wizard-progress .echs-prog-step.done { background:#2271b1; }
			.echs-wizard-progress .echs-prog-step.active { background:#2271b1; }

			.echs-wizard-step { display:none; background:#fff; border:1px solid #c3c4c7; border-radius:8px; padding:28px 32px; }
			.echs-wizard-step.active { display:block; }
			.echs-wizard-step h2 { margin:0 0 4px; font-size:20px; }
			.echs-wizard-step .echs-step-desc { color:#646970; margin:0 0 20px; font-size:13px; }

			.echs-wizard-step label { display:block; font-weight:600; margin-bottom:4px; font-size:13px; }
			.echs-wizard-step label .echs-req { color:#d63638; }
			.echs-wizard-step input[type=text],
			.echs-wizard-step input[type=email],
			.echs-wizard-step input[type=url],
			.echs-wizard-step input[type=tel],
			.echs-wizard-step input[type=time],
			.echs-wizard-step select,
			.echs-wizard-step textarea { width:100%; padding:8px 10px; margin-bottom:14px; border:1px solid #8c8f94; border-radius:4px; font-size:14px; box-sizing:border-box; }
			.echs-wizard-step textarea { min-height:70px; resize:vertical; }

			.echs-wizard-row { display:grid; grid-template-columns:1fr 1fr; gap:0 16px; }
			.echs-wizard-row-3 { display:grid; grid-template-columns:1fr 1fr 100px; gap:0 16px; }

			.echs-wizard-nav { display:flex; justify-content:space-between; margin-top:20px; padding-top:16px; border-top:1px solid #f0f0f1; }
			.echs-wizard-nav .button { min-width:120px; text-align:center; }

			.echs-wizard-hours-table { width:100%; border-collapse:collapse; margin-bottom:14px; }
			.echs-wizard-hours-table th { text-align:left; font-size:12px; color:#646970; text-transform:uppercase; padding:4px 8px 4px 0; }
			.echs-wizard-hours-table td { padding:3px 8px 3px 0; }
			.echs-wizard-hours-table input[type=time] { width:100%; margin-bottom:0; padding:5px 8px; }
			.echs-wizard-hours-table .echs-day-label { font-weight:600; font-size:13px; min-width:90px; }

			.echs-wizard-repeatable { margin-bottom:14px; }
			.echs-wizard-repeatable .echs-wiz-rep-row { display:flex; gap:8px; margin-bottom:6px; }
			.echs-wizard-repeatable .echs-wiz-rep-row input { flex:1; margin-bottom:0; }

			.echs-wizard-social-row { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
			.echs-wizard-social-row .echs-social-icon { width:32px; text-align:center; font-size:18px; flex-shrink:0; color:#646970; }
			.echs-wizard-social-row input { flex:1; margin-bottom:0; }

			.echs-wizard-consent { margin-bottom:12px; }
			.echs-wizard-consent label { display:flex; align-items:flex-start; gap:8px; font-weight:normal; cursor:pointer; }
			.echs-wizard-consent input[type=checkbox] { margin-top:2px; flex-shrink:0; }

			.echs-wizard-error { color:#d63638; font-size:13px; margin-top:-8px; margin-bottom:12px; display:none; }
			.echs-wizard-skip { color:#646970; font-size:13px; cursor:pointer; background:none; border:none; text-decoration:underline; padding:0; }

			@media (max-width:600px) {
				.echs-wizard-wrap { margin:20px 10px; }
				.echs-wizard-step { padding:20px 16px; }
				.echs-wizard-row, .echs-wizard-row-3 { grid-template-columns:1fr; }
			}
		</style>

		<div class="echs-wizard-wrap">
			<div class="echs-wizard-header">
				<h1>Welcome to ECHoS SEO Analytics</h1>
				<p>Let's get your site set up for SEO success. This takes about 3 minutes.</p>
			</div>

			<div class="echs-wizard-progress">
				<?php foreach ( self::STEPS as $i => $s ) : ?>
					<div class="echs-prog-step" data-step="<?php echo $i; ?>"></div>
				<?php endforeach; ?>
			</div>

			<!-- Step 1: Business Identity -->
			<div class="echs-wizard-step active" data-step="business">
				<h2>Business Identity</h2>
				<p class="echs-step-desc">Tell us about your business. This information powers your structured data and local SEO.</p>

				<label for="wiz_business_name">Business Name <span class="echs-req">*</span></label>
				<input type="text" id="wiz_business_name" name="echs_business_name" value="<?php echo esc_attr( get_option( 'echs_business_name' ) ); ?>" required>
				<div class="echs-wizard-error" id="err_business_name">Business name is required.</div>

				<label for="wiz_business_type">Business Type</label>
				<select id="wiz_business_type" name="echs_business_type">
					<option value="">— Select —</option>
					<?php foreach ( $categories as $cat => $types ) : ?>
						<optgroup label="<?php echo esc_attr( $cat ); ?>">
							<?php foreach ( $types as $schema => $label ) : ?>
								<option value="<?php echo esc_attr( $schema ); ?>" <?php selected( get_option( 'echs_business_type' ), $schema ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>

				<div class="echs-wizard-row">
					<div>
						<label for="wiz_phone">Phone</label>
						<input type="tel" id="wiz_phone" name="echs_phone" value="<?php echo esc_attr( get_option( 'echs_phone' ) ); ?>" placeholder="+1-555-555-5555">
					</div>
					<div>
						<label for="wiz_biz_email">Business Email</label>
						<input type="email" id="wiz_biz_email" name="echs_email" value="<?php echo esc_attr( get_option( 'echs_email' ) ); ?>">
					</div>
				</div>

				<label for="wiz_description">Short Description</label>
				<textarea id="wiz_description" name="echs_description" placeholder="A brief description of your business and services."><?php echo esc_textarea( get_option( 'echs_description' ) ); ?></textarea>

				<div class="echs-wizard-nav">
					<div></div>
					<button type="button" class="button button-primary echs-wiz-next">Continue</button>
				</div>
			</div>

			<!-- Step 2: Primary Location -->
			<div class="echs-wizard-step" data-step="location">
				<h2>Primary Location</h2>
				<p class="echs-step-desc">Your main office or business address. Used for Google Maps and local search.</p>

				<label for="wiz_street">Street Address</label>
				<input type="text" id="wiz_street" name="echs_street" value="<?php echo esc_attr( get_option( 'echs_street' ) ); ?>">

				<div class="echs-wizard-row-3">
					<div>
						<label for="wiz_city">City</label>
						<input type="text" id="wiz_city" name="echs_city" value="<?php echo esc_attr( get_option( 'echs_city' ) ); ?>">
					</div>
					<div>
						<label for="wiz_state">State</label>
						<input type="text" id="wiz_state" name="echs_state" value="<?php echo esc_attr( get_option( 'echs_state' ) ); ?>" placeholder="PA">
					</div>
					<div>
						<label for="wiz_zip">ZIP</label>
						<input type="text" id="wiz_zip" name="echs_zip" value="<?php echo esc_attr( get_option( 'echs_zip' ) ); ?>">
					</div>
				</div>

				<div class="echs-wizard-nav">
					<button type="button" class="button echs-wiz-prev">Back</button>
					<div>
						<button type="button" class="echs-wizard-skip echs-wiz-skip">Skip</button>
						<button type="button" class="button button-primary echs-wiz-next" style="margin-left:12px;">Continue</button>
					</div>
				</div>
			</div>

			<!-- Step 3: Service Area -->
			<div class="echs-wizard-step" data-step="service_area">
				<h2>Service Area</h2>
				<p class="echs-step-desc">Which cities or towns do you serve? This helps Google show you in local searches for those areas.</p>

				<div class="echs-wizard-repeatable" id="wiz-service-areas">
					<?php
					$areas = get_option( 'echs_service_areas', [] );
					if ( empty( $areas ) ) {
						$areas = [ '' ];
					}
					foreach ( $areas as $area ) :
					?>
					<div class="echs-wiz-rep-row">
						<input type="text" name="echs_service_areas[]" value="<?php echo esc_attr( $area ); ?>" placeholder="e.g. Harrisburg">
						<button type="button" class="button echs-wiz-remove-row">&times;</button>
					</div>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button" id="wiz-add-area">+ Add City/Town</button>

				<div class="echs-wizard-nav">
					<button type="button" class="button echs-wiz-prev">Back</button>
					<div>
						<button type="button" class="echs-wizard-skip echs-wiz-skip">Skip</button>
						<button type="button" class="button button-primary echs-wiz-next" style="margin-left:12px;">Continue</button>
					</div>
				</div>
			</div>

			<!-- Step 4: Hours of Operation -->
			<div class="echs-wizard-step" data-step="hours">
				<h2>Hours of Operation</h2>
				<p class="echs-step-desc">Set your business hours. Leave a day blank to mark it as closed.</p>

				<table class="echs-wizard-hours-table">
					<thead><tr><th>Day</th><th>Open</th><th>Close</th></tr></thead>
					<tbody>
					<?php
					$days  = [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
					$hours = get_option( 'echs_hours', [] );
					foreach ( $days as $day ) :
						$open  = $hours[ $day ]['open'] ?? '';
						$close = $hours[ $day ]['close'] ?? '';
					?>
					<tr>
						<td class="echs-day-label"><?php echo esc_html( ucfirst( $day ) ); ?></td>
						<td><input type="time" name="echs_hours_open_<?php echo esc_attr( $day ); ?>" value="<?php echo esc_attr( $open ); ?>"></td>
						<td><input type="time" name="echs_hours_close_<?php echo esc_attr( $day ); ?>" value="<?php echo esc_attr( $close ); ?>"></td>
					</tr>
					<?php endforeach; ?>
					</tbody>
				</table>

				<div class="echs-wizard-nav">
					<button type="button" class="button echs-wiz-prev">Back</button>
					<div>
						<button type="button" class="echs-wizard-skip echs-wiz-skip">Skip</button>
						<button type="button" class="button button-primary echs-wiz-next" style="margin-left:12px;">Continue</button>
					</div>
				</div>
			</div>

			<!-- Step 5: Social Profiles -->
			<div class="echs-wizard-step" data-step="social">
				<h2>Social Profiles</h2>
				<p class="echs-step-desc">Link your social media profiles. These appear in your schema markup and help Google connect your brand.</p>

				<?php
				$same_as = get_option( 'echs_same_as', [] );
				$socials = [
					[ 'icon' => '&#xf09a;', 'label' => 'Facebook',  'placeholder' => 'https://facebook.com/yourbusiness' ],
					[ 'icon' => '&#xf16d;', 'label' => 'Instagram', 'placeholder' => 'https://instagram.com/yourbusiness' ],
					[ 'icon' => '&#xf0e1;', 'label' => 'LinkedIn',  'placeholder' => 'https://linkedin.com/company/yourbusiness' ],
					[ 'icon' => '&#xf167;', 'label' => 'YouTube',   'placeholder' => 'https://youtube.com/@yourbusiness' ],
					[ 'icon' => '&#xf099;', 'label' => 'X/Twitter', 'placeholder' => 'https://x.com/yourbusiness' ],
					[ 'icon' => '&#xf2c6;', 'label' => 'Nextdoor',  'placeholder' => 'https://nextdoor.com/pages/yourbusiness' ],
				];
				foreach ( $socials as $idx => $s ) :
					$val = $same_as[ $idx ] ?? '';
				?>
				<div class="echs-wizard-social-row">
					<span class="echs-social-icon" title="<?php echo esc_attr( $s['label'] ); ?>"><?php echo esc_html( $s['label'][0] ); ?></span>
					<input type="url" name="echs_same_as[]" value="<?php echo esc_attr( $val ); ?>" placeholder="<?php echo esc_attr( $s['placeholder'] ); ?>">
				</div>
				<?php endforeach; ?>

				<div class="echs-wizard-nav">
					<button type="button" class="button echs-wiz-prev">Back</button>
					<div>
						<button type="button" class="echs-wizard-skip echs-wiz-skip">Skip</button>
						<button type="button" class="button button-primary echs-wiz-next" style="margin-left:12px;">Continue</button>
					</div>
				</div>
			</div>

			<!-- Step 6: Registration -->
			<div class="echs-wizard-step" data-step="registration">
				<h2>Almost Done!</h2>
				<p class="echs-step-desc">Tell us about yourself so we can support you and keep you updated.</p>

				<div class="echs-wizard-row">
					<div>
						<label for="wiz_first_name">First Name <span class="echs-req">*</span></label>
						<input type="text" id="wiz_first_name" name="echs_reg_first_name" required>
						<div class="echs-wizard-error" id="err_first_name">First name is required.</div>
					</div>
					<div>
						<label for="wiz_last_name">Last Name <span class="echs-req">*</span></label>
						<input type="text" id="wiz_last_name" name="echs_reg_last_name" required>
						<div class="echs-wizard-error" id="err_last_name">Last name is required.</div>
					</div>
				</div>

				<label for="wiz_reg_email">Email <span class="echs-req">*</span></label>
				<input type="email" id="wiz_reg_email" name="echs_reg_email" required>
				<div class="echs-wizard-error" id="err_reg_email">A valid email is required.</div>

				<label for="wiz_reg_company">Company Name <span class="echs-req">*</span></label>
				<input type="text" id="wiz_reg_company" name="echs_reg_company" required>
				<div class="echs-wizard-error" id="err_reg_company">Company name is required.</div>

				<div class="echs-wizard-consent">
					<label>
						<input type="checkbox" name="echs_reg_consent_marketing" value="1">
						I consent to receiving marketing communications from Digital Stride, including product updates, tips, and promotions. You can unsubscribe at any time.
					</label>
				</div>

				<div class="echs-wizard-consent">
					<label>
						<input type="checkbox" name="echs_reg_consent_data" value="1">
						I consent to Digital Stride using anonymized usage data to improve the ECHoS plugin and help inform better SEO recommendations.
					</label>
				</div>

				<div class="echs-wizard-nav">
					<button type="button" class="button echs-wiz-prev">Back</button>
					<button type="button" class="button button-primary echs-wiz-finish" style="min-width:160px;">Complete Setup</button>
				</div>
			</div>
		</div>

		<script>
		(function(){
			var steps    = <?php echo wp_json_encode( self::STEPS ); ?>,
				current  = 0,
				nonce    = '<?php echo esc_js( $nonce ); ?>',
				saving   = false,
				adminAjax = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

			function showStep(idx) {
				document.querySelectorAll('.echs-wizard-step').forEach(function(el){ el.classList.remove('active'); });
				document.querySelectorAll('.echs-prog-step').forEach(function(el, i){
					el.classList.remove('active','done');
					if (i < idx) el.classList.add('done');
					if (i === idx) el.classList.add('active');
				});
				var target = document.querySelector('.echs-wizard-step[data-step="'+steps[idx]+'"]');
				if (target) target.classList.add('active');
				current = idx;
				window.scrollTo({top:0, behavior:'smooth'});
			}

			function getStepData(stepName) {
				var el = document.querySelector('.echs-wizard-step[data-step="'+stepName+'"]');
				if (!el) return new FormData();
				var data = new FormData();
				el.querySelectorAll('input,select,textarea').forEach(function(inp){
					if (inp.name === '') return;
					if (inp.type === 'checkbox') {
						if (inp.checked) data.append(inp.name, inp.value);
					} else {
						data.append(inp.name, inp.value);
					}
				});
				data.append('step', stepName);
				data.append('action', 'echs_setup_save');
				data.append('_nonce', nonce);
				return data;
			}

			function clearErrors() {
				document.querySelectorAll('.echs-wizard-error').forEach(function(e){ e.style.display='none'; });
			}

			function showError(id) {
				var el = document.getElementById(id);
				if (el) el.style.display = 'block';
			}

			function validateStep(stepName) {
				clearErrors();
				if (stepName === 'business') {
					var name = document.getElementById('wiz_business_name').value.trim();
					if (!name) { showError('err_business_name'); return false; }
				}
				if (stepName === 'registration') {
					var valid = true;
					if (!document.getElementById('wiz_first_name').value.trim()) { showError('err_first_name'); valid = false; }
					if (!document.getElementById('wiz_last_name').value.trim()) { showError('err_last_name'); valid = false; }
					var email = document.getElementById('wiz_reg_email').value.trim();
					if (!email || email.indexOf('@') < 1) { showError('err_reg_email'); valid = false; }
					if (!document.getElementById('wiz_reg_company').value.trim()) { showError('err_reg_company'); valid = false; }
					return valid;
				}
				return true;
			}

			function saveAndAdvance(stepName, nextIdx) {
				if (saving) return;
				saving = true;

				var btn = document.querySelector('.echs-wizard-step[data-step="'+stepName+'"] .echs-wiz-next, .echs-wizard-step[data-step="'+stepName+'"] .echs-wiz-finish');
				if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

				fetch(adminAjax, { method:'POST', body: getStepData(stepName) })
					.then(function(r){ return r.json(); })
					.then(function(resp){
						saving = false;
						if (btn) { btn.disabled = false; btn.textContent = stepName === 'registration' ? 'Complete Setup' : 'Continue'; }
						if (resp.success) {
							if (resp.data && resp.data.redirect) {
								window.location.href = resp.data.redirect;
								return;
							}
							if (nextIdx !== undefined) showStep(nextIdx);
						}
					})
					.catch(function(err){
						saving = false;
						if (btn) { btn.disabled = false; btn.textContent = stepName === 'registration' ? 'Complete Setup' : 'Continue'; }
						console.error('ECHoS setup save error:', err);
					});
			}

			document.addEventListener('click', function(e){
				var t = e.target.closest('button');
				if (!t) return;

				if (t.classList.contains('echs-wiz-next')) {
					if (!validateStep(steps[current])) return;
					saveAndAdvance(steps[current], current + 1);
				}

				if (t.classList.contains('echs-wiz-prev')) {
					clearErrors();
					showStep(current - 1);
				}

				if (t.classList.contains('echs-wiz-skip')) {
					showStep(current + 1);
				}

				if (t.classList.contains('echs-wiz-finish')) {
					if (!validateStep('registration')) return;
					saveAndAdvance('registration');
				}

				if (t.classList.contains('echs-wiz-remove-row')) {
					var container = t.closest('.echs-wizard-repeatable');
					var rows = container.querySelectorAll('.echs-wiz-rep-row');
					if (rows.length > 1) {
						t.closest('.echs-wiz-rep-row').remove();
					} else {
						t.closest('.echs-wiz-rep-row').querySelector('input').value = '';
					}
				}
			});

			document.getElementById('wiz-add-area').addEventListener('click', function(){
				var container = document.getElementById('wiz-service-areas');
				var row = document.createElement('div');
				row.className = 'echs-wiz-rep-row';
				row.innerHTML = '<input type="text" name="echs_service_areas[]" placeholder="e.g. Harrisburg">' +
					'<button type="button" class="button echs-wiz-remove-row">&times;</button>';
				container.appendChild(row);
				row.querySelector('input').focus();
			});

			showStep(0);
		})();
		</script>
		<?php
	}
}
