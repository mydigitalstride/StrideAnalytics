<?php
/**
 * Google Business Profile location management, health analysis, reviews
 * monitoring, and Q&A monitoring.
 *
 * @package Stride_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_GBP {

	const ACCOUNT_API = 'https://mybusinessaccountmanagement.googleapis.com/v1/';
	const INFO_API    = 'https://mybusinessbusinessinformation.googleapis.com/v1/';
	const LEGACY_API  = 'https://mybusiness.googleapis.com/v4/';
	const QA_API      = 'https://mybusinessqanda.googleapis.com/v1/';

	public static function init(): void {
		add_action( 'admin_menu',                       [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_post_echs_gbp_save_location', [ __CLASS__, 'save_location' ] );
		add_action( 'wp_dashboard_setup',               [ __CLASS__, 'register_dashboard_section' ] );
	}

	public static function register_menu(): void {
		add_submenu_page(
			'echs-settings',
			__( 'Google Business Profile', 'echs' ),
			__( 'GBP', 'echs' ),
			'manage_options',
			'echs-gbp',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_dashboard_section(): void {
		ECHS_Admin::register_dashboard_widget();
	}

	public static function get_accounts(): array {
		$result = ECHS_Google_Auth::request( self::ACCOUNT_API . 'accounts' );
		if ( is_wp_error( $result ) ) {
			return [];
		}
		return $result['accounts'] ?? [];
	}

	public static function get_locations( string $account_name ): array {
		$result = ECHS_Google_Auth::request(
			self::INFO_API . $account_name . '/locations?readMask=name,title'
		);
		if ( is_wp_error( $result ) ) {
			return [];
		}
		return $result['locations'] ?? [];
	}

	public static function get_location_detail( string $location_name ): array {
		$result = ECHS_Google_Auth::request(
			self::INFO_API . $location_name . '?readMask=name,title,phoneNumbers,websiteUri,regularHours,categories,profile'
		);
		if ( is_wp_error( $result ) ) {
			return [];
		}
		return $result;
	}

	public static function save_location(): void {
		check_admin_referer( 'echs_gbp_save_location' );

		$location = sanitize_text_field( wp_unslash( $_POST['echs_gbp_location'] ?? '' ) );
		update_option( 'echs_gbp_location_name', $location );

		wp_redirect( admin_url( 'admin.php?page=echs-gbp&echs_msg=location_saved' ) );
		exit;
	}

	public static function get_reviews( string $location_name ): array {
		$result = ECHS_Google_Auth::request( self::LEGACY_API . $location_name . '/reviews?pageSize=10' );
		if ( is_wp_error( $result ) ) {
			return [];
		}
		return $result['reviews'] ?? [];
	}

	public static function get_questions( string $location_name ): array {
		$result = ECHS_Google_Auth::request(
			self::QA_API . $location_name . '/questions?pageSize=10&answersPerQuestion=1'
		);
		if ( is_wp_error( $result ) ) {
			return [];
		}
		return $result['questions'] ?? [];
	}

	public static function get_health_score( string $location_name ): array {
		$detail  = self::get_location_detail( $location_name );
		$reviews = self::get_reviews( $location_name );

		$has_description = ! empty( $detail['profile']['description'] );
		$has_phone       = ! empty( $detail['phoneNumbers']['primaryPhone'] );
		$has_website     = ! empty( $detail['websiteUri'] );
		$has_hours       = ! empty( $detail['regularHours']['periods'] );

		$thirty_days_ago  = time() - ( 30 * DAY_IN_SECONDS );
		$has_recent_review = false;
		foreach ( $reviews as $review ) {
			if ( ! empty( $review['updateTime'] ) ) {
				$review_time = strtotime( $review['updateTime'] );
				if ( $review_time && $review_time >= $thirty_days_ago ) {
					$has_recent_review = true;
					break;
				}
			}
		}

		$total_reviews = count( $reviews );

		$checks = [
			[ 'label' => 'Business description set',    'pass' => $has_description ],
			[ 'label' => 'Primary phone number set',    'pass' => $has_phone ],
			[ 'label' => 'Website URL set',             'pass' => $has_website ],
			[ 'label' => 'Business hours configured',   'pass' => $has_hours ],
			[
				'label'  => 'Has recent reviews (30 days)',
				'pass'   => $has_recent_review,
				'detail' => $total_reviews . ' reviews total',
			],
		];

		$passing = count( array_filter( $checks, fn( $c ) => $c['pass'] ) );
		$score   = $passing * 20;

		return [
			'score'  => $score,
			'checks' => $checks,
		];
	}

	public static function get_home_service_suggestions( array $location ): array {
		$suggestions = [];

		$description = $location['profile']['description'] ?? '';
		if ( empty( $description ) ) {
			$suggestions[] = 'Add a business description that mentions your service area, years in business, and specialties.';
		}

		$media_count = count( $location['mediaItems'] ?? [] );
		if ( $media_count < 5 ) {
			$suggestions[] = 'Add AT_WORK photos showing completed jobs to build trust with potential customers.';
		}

		$categories      = $location['categories'] ?? [];
		$all_cat_names   = [];
		if ( ! empty( $categories['primaryCategory']['displayName'] ) ) {
			$all_cat_names[] = strtolower( $categories['primaryCategory']['displayName'] );
		}
		foreach ( $categories['additionalCategories'] ?? [] as $cat ) {
			if ( ! empty( $cat['displayName'] ) ) {
				$all_cat_names[] = strtolower( $cat['displayName'] );
			}
		}
		$contractor_terms   = [ 'contractor', 'roofing', 'plumb', 'electri', 'hvac', 'painter', 'handyman', 'remodel' ];
		$has_contractor_cat = false;
		foreach ( $all_cat_names as $name ) {
			foreach ( $contractor_terms as $term ) {
				if ( str_contains( $name, $term ) ) {
					$has_contractor_cat = true;
					break 2;
				}
			}
		}
		if ( ! $has_contractor_cat ) {
			$suggestions[] = 'Consider adding service-specific categories (e.g. Roofing Contractor, General Contractor) as secondary categories.';
		}

		$suggestions[] = 'Respond to all reviews within 24 hours — businesses that respond get 12% more review requests.';
		$suggestions[] = 'Post a job photo at least once per week to keep your profile active in local search.';
		$suggestions[] = 'Enable Google Messaging to let customers contact you directly from Search and Maps.';

		return $suggestions;
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$connected     = ECHS_Google_Auth::is_connected();
		$location_name = get_option( 'echs_gbp_location_name', '' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Google Business Profile', 'echs' ); ?></h1>

			<?php if ( isset( $_GET['echs_msg'] ) && 'location_saved' === $_GET['echs_msg'] ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Location saved successfully.', 'echs' ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! $connected ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'Connect your Google account first.', 'echs' ); ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=echs-settings&tab=google' ) ); ?>">
							<?php esc_html_e( 'Go to Google Settings →', 'echs' ); ?>
						</a>
					</p>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php if ( empty( $location_name ) ) : ?>
				<?php
				$accounts_raw = ECHS_Google_Auth::request( self::ACCOUNT_API . 'accounts' );
				$accounts     = is_wp_error( $accounts_raw ) ? [] : ( $accounts_raw['accounts'] ?? [] );
				$locations    = [];
				$locations_raw = null;
				if ( ! empty( $accounts[0]['name'] ) ) {
					$locations_raw = ECHS_Google_Auth::request(
						self::INFO_API . $accounts[0]['name'] . '/locations?readMask=name,title'
					);
					$locations = is_wp_error( $locations_raw ) ? [] : ( $locations_raw['locations'] ?? [] );
				}
				?>

				<?php if ( current_user_can( 'manage_options' ) && ( empty( $accounts ) || empty( $locations ) ) ) : ?>
					<div class="notice notice-info" style="margin-bottom:16px;">
						<p><strong><?php esc_html_e( 'Diagnostic Info (admin only):', 'echs' ); ?></strong></p>
						<p><?php esc_html_e( 'Accounts API response:', 'echs' ); ?></p>
						<pre style="background:#f6f7f7;padding:8px;overflow:auto;max-height:200px;font-size:12px;"><?php
							echo esc_html( is_wp_error( $accounts_raw )
								? 'WP_Error: ' . $accounts_raw->get_error_message()
								: wp_json_encode( $accounts_raw, JSON_PRETTY_PRINT )
							);
						?></pre>
						<?php if ( $locations_raw !== null ) : ?>
							<p><?php esc_html_e( 'Locations API response:', 'echs' ); ?></p>
							<pre style="background:#f6f7f7;padding:8px;overflow:auto;max-height:200px;font-size:12px;"><?php
								echo esc_html( is_wp_error( $locations_raw )
									? 'WP_Error: ' . $locations_raw->get_error_message()
									: wp_json_encode( $locations_raw, JSON_PRETTY_PRINT )
								);
							?></pre>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="echs-card">
					<h2><?php esc_html_e( 'Select Your Business Location', 'echs' ); ?></h2>
					<?php if ( empty( $locations ) ) : ?>
						<p><?php esc_html_e( 'No locations found on your Google account. Make sure your Google account has access to a Business Profile.', 'echs' ); ?></p>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="echs_gbp_save_location">
							<?php wp_nonce_field( 'echs_gbp_save_location' ); ?>
							<table class="form-table">
								<tr>
									<th>
										<label for="echs_gbp_location">
											<?php esc_html_e( 'Business Location', 'echs' ); ?>
										</label>
									</th>
									<td>
										<select id="echs_gbp_location" name="echs_gbp_location">
											<?php foreach ( $locations as $loc ) : ?>
												<option value="<?php echo esc_attr( $loc['name'] ?? '' ); ?>">
													<?php echo esc_html( $loc['title'] ?? $loc['name'] ?? '' ); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
								</tr>
							</table>
							<?php submit_button( __( 'Save Location', 'echs' ) ); ?>
						</form>
					<?php endif; ?>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<?php
			$health      = self::get_health_score( $location_name );
			$reviews     = self::get_reviews( $location_name );
			$questions   = self::get_questions( $location_name );
			$detail      = self::get_location_detail( $location_name );
			$suggestions = self::get_home_service_suggestions( $detail );

			$score  = $health['score'];
			$checks = $health['checks'];

			$passing_count = count( array_filter( $checks, fn( $c ) => $c['pass'] ) );

			if ( $score >= 80 ) {
				$level_class = 'echs-level-optimal';
			} elseif ( $score >= 60 ) {
				$level_class = 'echs-level-fair';
			} else {
				$level_class = 'echs-level-low';
			}

			$location_title = $detail['title'] ?? $location_name;
			?>

			<div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-start;">

				<div class="echs-card" style="flex:1;min-width:300px;">
					<h2><?php esc_html_e( 'Profile Health', 'echs' ); ?></h2>
					<p style="font-size:13px;color:#646970;margin-top:-8px;">
						<?php echo esc_html( $location_title ); ?>
					</p>

					<div class="echs-meter-track">
						<div class="echs-meter-fill <?php echo esc_attr( $level_class ); ?>" style="width:<?php echo esc_attr( (string) $score ); ?>%"></div>
					</div>
					<p style="font-size:12px;margin:4px 0 12px;">
						<?php
						printf(
							esc_html__( '%1$d/100 — %2$d of 5 checks passing', 'echs' ),
							(int) $score,
							(int) $passing_count
						);
						?>
					</p>

					<ul style="margin:0 0 16px;padding:0;list-style:none;">
						<?php foreach ( $checks as $check ) : ?>
							<li class="echs-check-item" style="display:flex;align-items:center;gap:8px;margin-bottom:6px;font-size:13px;">
								<span style="color:<?php echo $check['pass'] ? '#00a32a' : '#d63638'; ?>;font-size:16px;">
									<?php echo $check['pass'] ? '&#10003;' : '&#10007;'; ?>
								</span>
								<?php echo esc_html( $check['label'] ); ?>
								<?php if ( ! empty( $check['detail'] ) ) : ?>
									<span style="color:#646970;font-size:12px;">(<?php echo esc_html( $check['detail'] ); ?>)</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<?php if ( ! empty( $suggestions ) ) : ?>
						<h3 style="font-size:13px;margin:0 0 8px;"><?php esc_html_e( 'Recommendations', 'echs' ); ?></h3>
						<ul style="margin:0;padding-left:18px;font-size:12px;color:#3c434a;">
							<?php foreach ( $suggestions as $suggestion ) : ?>
								<li style="margin-bottom:6px;"><?php echo esc_html( $suggestion ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="echs-card" style="flex:1;min-width:300px;">
					<h2><?php esc_html_e( 'Recent Reviews', 'echs' ); ?></h2>
					<?php if ( empty( $reviews ) ) : ?>
						<p><?php esc_html_e( 'No reviews found.', 'echs' ); ?></p>
					<?php else : ?>
						<ul style="margin:0;padding:0;list-style:none;">
							<?php foreach ( array_slice( $reviews, 0, 5 ) as $review ) :
								$star_rating  = $review['starRating'] ?? '';
								$author       = $review['reviewer']['displayName'] ?? __( 'Anonymous', 'echs' );
								$comment      = $review['comment'] ?? '';
								$excerpt      = mb_strlen( $comment ) > 80 ? mb_substr( $comment, 0, 80 ) . '…' : $comment;
								$update_time  = $review['updateTime'] ?? '';
								$date_display = $update_time ? gmdate( 'M j, Y', strtotime( $update_time ) ) : '';
								$has_reply    = ! empty( $review['reviewReply']['comment'] );

								$star_map = [
									'ONE' => 1, 'TWO' => 2, 'THREE' => 3, 'FOUR' => 4, 'FIVE' => 5,
								];
								$stars = $star_map[ $star_rating ] ?? 0;
							?>
								<li style="border-bottom:1px solid #f0f0f0;padding:10px 0;">
									<div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
										<span style="color:#f0ad00;font-size:14px;">
											<?php echo esc_html( str_repeat( '★', $stars ) . str_repeat( '☆', 5 - $stars ) ); ?>
										</span>
										<strong style="font-size:13px;"><?php echo esc_html( $author ); ?></strong>
										<?php if ( ! $has_reply ) : ?>
											<span style="background:#d63638;color:#fff;font-size:10px;padding:2px 6px;border-radius:3px;">
												<?php esc_html_e( 'Needs reply', 'echs' ); ?>
											</span>
										<?php endif; ?>
										<?php if ( $date_display ) : ?>
											<span style="color:#646970;font-size:11px;margin-left:auto;"><?php echo esc_html( $date_display ); ?></span>
										<?php endif; ?>
									</div>
									<?php if ( $excerpt ) : ?>
										<p style="margin:0;font-size:12px;color:#3c434a;"><?php echo esc_html( $excerpt ); ?></p>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
						<p style="margin-top:12px;">
							<a href="https://business.google.com/" target="_blank" rel="noopener">
								<?php esc_html_e( 'View all on Google →', 'echs' ); ?>
							</a>
						</p>
					<?php endif; ?>
				</div>

				<div class="echs-card" style="flex:1;min-width:300px;">
					<h2><?php esc_html_e( 'Q&amp;A', 'echs' ); ?></h2>
					<?php if ( empty( $questions ) ) : ?>
						<p><?php esc_html_e( 'No questions found.', 'echs' ); ?></p>
					<?php else : ?>
						<ul style="margin:0;padding:0;list-style:none;">
							<?php foreach ( array_slice( $questions, 0, 5 ) as $question ) :
								$question_text = $question['text'] ?? '';
								$has_answer    = ! empty( $question['topAnswers'] ) || ! empty( $question['totalAnswerCount'] );
							?>
								<li style="border-bottom:1px solid #f0f0f0;padding:10px 0;">
									<div style="display:flex;align-items:flex-start;gap:8px;">
										<span style="font-size:13px;flex:1;"><?php echo esc_html( $question_text ); ?></span>
										<?php if ( ! $has_answer ) : ?>
											<span style="background:#d63638;color:#fff;font-size:10px;padding:2px 6px;border-radius:3px;white-space:nowrap;">
												<?php esc_html_e( 'Unanswered', 'echs' ); ?>
											</span>
										<?php endif; ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>

				<div class="echs-card" style="flex:1;min-width:300px;">
					<h2><?php esc_html_e( 'Quick Actions', 'echs' ); ?></h2>
					<ul style="margin:0;padding:0;list-style:none;">
						<li style="margin-bottom:12px;">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=echs-gbp-jobs' ) ); ?>" class="button button-secondary">
								<?php esc_html_e( 'Push Job Photo', 'echs' ); ?>
							</a>
						</li>
						<li style="margin-bottom:12px;">
							<a href="https://business.google.com/" target="_blank" rel="noopener" class="button button-secondary">
								<?php esc_html_e( 'GBP Dashboard on Google ↗', 'echs' ); ?>
							</a>
						</li>
						<li>
							<a href="<?php echo esc_url(
								add_query_arg(
									[
										'action'   => 'echs_gbp_clear_location',
										'_wpnonce' => wp_create_nonce( 'echs_gbp_clear_location' ),
									],
									admin_url( 'admin-post.php' )
								)
							); ?>" class="button button-link">
								<?php esc_html_e( 'Change Selected Location', 'echs' ); ?>
							</a>
						</li>
					</ul>
				</div>

			</div>
		</div>
		<?php
	}

	public static function render_widget_section(): void {
		if ( ! ECHS_Google_Auth::is_connected() ) {
			return;
		}

		$location_name = get_option( 'echs_gbp_location_name', '' );
		if ( empty( $location_name ) ) {
			return;
		}

		$transient_key = 'echs_gbp_health_' . md5( $location_name );
		$health        = get_transient( $transient_key );

		if ( false === $health ) {
			$health = self::get_health_score( $location_name );
			set_transient( $transient_key, $health, 6 * HOUR_IN_SECONDS );
		}

		$score         = (int) ( $health['score'] ?? 0 );
		$checks        = $health['checks'] ?? [];
		$passing_count = count( array_filter( $checks, fn( $c ) => $c['pass'] ) );

		if ( $score >= 80 ) {
			$level_class = 'echs-level-optimal';
		} elseif ( $score >= 60 ) {
			$level_class = 'echs-level-fair';
		} else {
			$level_class = 'echs-level-low';
		}

		echo '<h3 class="echs-widget-section-title"><span class="dashicons dashicons-building"></span> '
			. esc_html__( 'GBP Health', 'echs' ) . '</h3>';

		echo '<div class="echs-meter-track">'
			. '<div class="echs-meter-fill ' . esc_attr( $level_class ) . '" style="width:' . esc_attr( (string) $score ) . '%"></div>'
			. '</div>';

		printf(
			'<p style="font-size:12px;margin:4px 0 0;">%s</p>',
			esc_html(
				sprintf(
					__( '%1$d/100 — %2$d of 5 checks passing', 'echs' ),
					$score,
					$passing_count
				)
			)
		);

		echo '<p class="echs-widget-footer"><a href="' . esc_url( admin_url( 'admin.php?page=echs-gbp' ) ) . '">'
			. esc_html__( 'View GBP Dashboard →', 'echs' ) . '</a></p>';
	}
}
