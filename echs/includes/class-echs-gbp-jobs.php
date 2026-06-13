<?php
/**
 * Handles pushing geo-tagged job photos from WordPress to Google Business Profile.
 *
 * @package ECHoS_SEO_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_GBP_Jobs {

	const MEDIA_API = 'https://mybusiness.googleapis.com/v4/';
	const POST_API  = 'https://mybusiness.googleapis.com/v4/';
	const NOMINATIM = 'https://nominatim.openstreetmap.org/search';

	public static function init(): void {
		add_action( 'admin_menu',                          [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_post_echs_push_job_photos',      [ __CLASS__, 'handle_push' ] );
		add_action( 'wp_ajax_echs_geocode_job_address',     [ __CLASS__, 'ajax_geocode' ] );
		add_action( 'admin_enqueue_scripts',               [ __CLASS__, 'maybe_enqueue_scripts' ] );
	}

	public static function register_menu(): void {
		if ( ! ECHS_License::is_active() ) {
			return;
		}
		add_submenu_page(
			'echs-settings',
			'Push Job Photos',
			'Job Photos',
			'manage_options',
			'echs-gbp-jobs',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function maybe_enqueue_scripts( string $hook ): void {
		if ( $hook !== 'stride-analytics_page_echs-gbp-jobs' ) {
			return;
		}

		wp_enqueue_media();
		add_action( 'admin_footer', [ __CLASS__, 'output_footer_js' ] );
	}

	public static function output_footer_js(): void {
		$screen = get_current_screen();

		if ( ! $screen || $screen->id !== 'stride-analytics_page_echs-gbp-jobs' ) {
			return;
		}
		?>
		<script>
		(function($){
		    var frame;
		    var selectedIds = [];

		    $('#echs-select-photos').on('click', function(){
		        if(frame){ frame.open(); return; }
		        frame = wp.media({
		            title: 'Select Job Photos',
		            button: { text: 'Add to Job' },
		            multiple: true
		        });
		        frame.on('select', function(){
		            var attachments = frame.state().get('selection').toJSON();
		            attachments.forEach(function(a){
		                if(selectedIds.indexOf(a.id) === -1){
		                    selectedIds.push(a.id);
		                    var thumb = a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url;
		                    $('#echs-job-photos-list').append(
		                        '<div class="echs-job-photo" data-id="'+a.id+'" style="display:inline-block;margin:4px;position:relative;">' +
		                        '<img src="'+thumb+'" style="width:80px;height:80px;object-fit:cover;border-radius:3px;">' +
		                        '<button type="button" class="echs-remove-job-photo" data-id="'+a.id+'" style="position:absolute;top:2px;right:2px;padding:0 4px;line-height:18px;font-size:14px;">&times;</button>' +
		                        '</div>'
		                    );
		                }
		            });
		            updateHiddenIds();
		            $('#echs-no-photos-msg').hide();
		        });
		        frame.open();
		    });

		    $(document).on('click', '.echs-remove-job-photo', function(){
		        var id = parseInt($(this).data('id'));
		        selectedIds = selectedIds.filter(function(i){ return i !== id; });
		        $('[data-id="'+id+'"]').remove();
		        updateHiddenIds();
		        if(!selectedIds.length) $('#echs-no-photos-msg').show();
		    });

		    function updateHiddenIds(){
		        $('#echs-job-photo-ids').val(selectedIds.join(','));
		    }

		    $('#echs-geocode-address').on('click', function(){
		        var address = $('#echs-job-address').val().trim();
		        if(!address) return;
		        var $btn = $(this);
		        var $status = $('#echs-geocode-status');
		        $btn.prop('disabled', true).text('Searching…');
		        $.get('https://nominatim.openstreetmap.org/search', {
		            format: 'json', limit: 1, q: address
		        }, function(data){
		            if(data && data.length){
		                $('#echs-job-lat').val(parseFloat(data[0].lat).toFixed(6));
		                $('#echs-job-lng').val(parseFloat(data[0].lon).toFixed(6));
		                $status.css('color','#00a32a').text('✓ Coordinates found');
		            } else {
		                $status.css('color','#d63638').text('Address not found');
		            }
		        }).always(function(){ $btn.prop('disabled',false).text('Find Coordinates'); });
		    });
		})(jQuery);
		</script>
		<?php
	}

	public static function render_page(): void {
		if ( ! ECHS_Google_Auth::is_connected() ) {
			echo '<div class="wrap"><h1>Push Job Photos to Google Business Profile</h1>';
			echo '<div class="notice notice-warning"><p>Not connected to Google. Please connect via the <a href="' . esc_url( admin_url( 'admin.php?page=echs-settings&tab=google' ) ) . '">Google settings tab</a>.</p></div></div>';
			return;
		}

		$location = ECHS_Google_Auth::get_selected_location();

		if ( empty( $location ) ) {
			echo '<div class="wrap"><h1>Push Job Photos to Google Business Profile</h1>';
			echo '<div class="notice notice-warning"><p>No Google Business Profile location selected. Please select one in the <a href="' . esc_url( admin_url( 'admin.php?page=echs-settings&tab=google' ) ) . '">Google settings tab</a>.</p></div></div>';
			return;
		}

		$msg   = sanitize_key( $_GET['echs_msg'] ?? '' );
		$count = absint( $_GET['count'] ?? 0 );
		?>
		<div class="wrap">
			<h1>Push Job Photos to Google Business Profile</h1>

			<?php if ( $msg === 'pushed' ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sprintf( '%d photo(s) successfully pushed to Google Business Profile.', $count ) ); ?></p></div>
			<?php elseif ( $msg === 'no_photos' ) : ?>
				<div class="notice notice-error is-dismissible"><p>No photos were selected. Please select at least one photo.</p></div>
			<?php elseif ( $msg === 'no_location' ) : ?>
				<div class="notice notice-error is-dismissible"><p>No Google Business Profile location configured.</p></div>
			<?php elseif ( $msg === 'error' ) : ?>
				<div class="notice notice-error is-dismissible"><p>An error occurred while pushing photos. Please try again.</p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="echs_push_job_photos">
				<?php wp_nonce_field( 'echs_push_job_photos' ); ?>

				<div class="echs-card">
					<h2>Job Details</h2>
					<table class="form-table">
						<tr>
							<th scope="row">Job Title</th>
							<td>
								<input type="text" name="echs_job_title" class="large-text" placeholder="e.g. Roof Replacement &#8211; Oak Street" required>
							</td>
						</tr>
						<tr>
							<th scope="row">Job Description</th>
							<td>
								<textarea name="echs_job_description" rows="4" class="large-text" placeholder="Brief description of the work performed, materials used, location&#8230;"></textarea>
							</td>
						</tr>
						<tr>
							<th scope="row">Job Address</th>
							<td>
								<input type="text" name="echs_job_address" id="echs-job-address" class="large-text" placeholder="123 Oak Street, Harrisburg, PA 17101">
								<p>
									<button type="button" class="button" id="echs-geocode-address">Find Coordinates</button>
									<span id="echs-geocode-status"></span>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row">GPS Coordinates</th>
							<td>
								<label>Lat <input type="text" name="echs_job_lat" id="echs-job-lat" class="small-text" placeholder="40.2732"></label>
								&nbsp;
								<label>Lng <input type="text" name="echs_job_lng" id="echs-job-lng" class="small-text" placeholder="-76.8867"></label>
								<p class="description">Used to geo-tag the uploaded photos with the job site location.</p>
							</td>
						</tr>
					</table>
				</div>

				<div class="echs-card">
					<h2>Job Photos</h2>
					<p class="description">Select photos from your media library that show this job. Only selected photos will be pushed to Google Business Profile.</p>

					<div id="echs-job-photos-list">
						<p id="echs-no-photos-msg">No photos selected yet.</p>
					</div>

					<button type="button" class="button" id="echs-select-photos" style="margin-top:10px;">+ Select Photos from Media Library</button>
					<input type="hidden" name="echs_job_photo_ids" id="echs-job-photo-ids" value="">
				</div>

				<div class="echs-card">
					<h2>GBP Post (Optional)</h2>
					<p class="description">Create a Google Business Profile update post alongside the photos.</p>
					<label>
						<input type="checkbox" name="echs_create_post" value="1" checked>
						Create a GBP post with job title, description, and first photo
					</label>
				</div>

				<?php submit_button( 'Push to Google Business Profile', 'primary' ); ?>
			</form>
		</div>
		<?php
	}

	public static function handle_push(): void {
		check_admin_referer( 'echs_push_job_photos' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Insufficient permissions.' );
		}

		$location = ECHS_Google_Auth::get_selected_location();

		if ( empty( $location ) ) {
			wp_redirect( add_query_arg( [ 'echs_msg' => 'no_location' ], admin_url( 'admin.php?page=echs-gbp-jobs' ) ) );
			exit;
		}

		$title       = sanitize_text_field( $_POST['echs_job_title'] ?? '' );
		$description = sanitize_textarea_field( $_POST['echs_job_description'] ?? '' );
		$address     = sanitize_text_field( $_POST['echs_job_address'] ?? '' );
		$lat         = (float) ( $_POST['echs_job_lat'] ?? 0 );
		$lng         = (float) ( $_POST['echs_job_lng'] ?? 0 );
		$photo_ids   = array_map( 'absint', explode( ',', $_POST['echs_job_photo_ids'] ?? '' ) );
		$create_post = ! empty( $_POST['echs_create_post'] );

		$photo_ids = array_filter( $photo_ids );

		if ( empty( $photo_ids ) ) {
			wp_redirect( add_query_arg( [ 'echs_msg' => 'no_photos' ], admin_url( 'admin.php?page=echs-gbp-jobs' ) ) );
			exit;
		}

		$pushed_count      = 0;
		$first_pushed_url  = '';

		foreach ( $photo_ids as $attachment_id ) {
			$photo_url = wp_get_attachment_url( $attachment_id );

			if ( ! $photo_url ) {
				continue;
			}

			if ( $lat !== 0.0 && $lng !== 0.0 ) {
				$photo_url = self::embed_gps_exif( $attachment_id, $lat, $lng );
			}

			$success = self::push_photo( $location, $photo_url );

			if ( $success ) {
				if ( $first_pushed_url === '' ) {
					$first_pushed_url = $photo_url;
				}
				$pushed_count++;
			}
		}

		if ( $create_post && $pushed_count > 0 && $first_pushed_url !== '' ) {
			self::create_local_post( $location, $title, $description, $first_pushed_url );
		}

		wp_redirect( add_query_arg( [ 'echs_msg' => 'pushed', 'count' => $pushed_count ], admin_url( 'admin.php?page=echs-gbp-jobs' ) ) );
		exit;
	}

	public static function embed_gps_exif( string $attachment_id, float $lat, float $lng ): string {
		$orig_path    = get_attached_file( (int) $attachment_id );
		$fallback_url = wp_get_attachment_url( (int) $attachment_id );

		if ( ! $orig_path || ! file_exists( $orig_path ) ) {
			return $fallback_url;
		}

		$ext = strtolower( pathinfo( $orig_path, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, [ 'jpg', 'jpeg' ], true ) ) {
			return $fallback_url;
		}

		$upload_dir  = wp_upload_dir();
		$dest_dir    = $upload_dir['basedir'] . '/echs-jobs';

		wp_mkdir_p( $dest_dir );

		$timestamp = time();
		$dest_path = $dest_dir . '/' . $timestamp . '-' . basename( $orig_path );
		$dest_url  = $upload_dir['baseurl'] . '/echs-jobs/' . $timestamp . '-' . basename( $orig_path );

		if ( ! copy( $orig_path, $dest_path ) ) {
			return $fallback_url;
		}

		if ( extension_loaded( 'imagick' ) ) {
			try {
				$imagick = new Imagick( $dest_path );

				$lat_abs = abs( $lat );
				$lat_deg = floor( $lat_abs );
				$lat_min = floor( ( $lat_abs - $lat_deg ) * 60 );
				$lat_sec = ( ( $lat_abs - $lat_deg ) * 60 - $lat_min ) * 60;

				$lng_abs = abs( $lng );
				$lng_deg = floor( $lng_abs );
				$lng_min = floor( ( $lng_abs - $lng_deg ) * 60 );
				$lng_sec = ( ( $lng_abs - $lng_deg ) * 60 - $lng_min ) * 60;

				$lat_ref = $lat >= 0 ? 'N' : 'S';
				$lng_ref = $lng >= 0 ? 'E' : 'W';

				$imagick->setImageProperty( 'exif:GPSLatitudeRef', $lat_ref );
				$imagick->setImageProperty( 'exif:GPSLatitude', sprintf( '%d/1,%d/1,%d/1000', $lat_deg, $lat_min, (int) round( $lat_sec * 1000 ) ) );
				$imagick->setImageProperty( 'exif:GPSLongitudeRef', $lng_ref );
				$imagick->setImageProperty( 'exif:GPSLongitude', sprintf( '%d/1,%d/1,%d/1000', $lng_deg, $lng_min, (int) round( $lng_sec * 1000 ) ) );

				$imagick->writeImage( $dest_path );
				$imagick->clear();
				$imagick->destroy();
			} catch ( Exception $e ) {
				// GPS embedding failed; the file copy is still usable.
			}
		}

		return $dest_url;
	}

	public static function push_photo( string $location_name, string $photo_url ): bool {
		$response = ECHS_Google_Auth::request(
			self::MEDIA_API . $location_name . '/media',
			'POST',
			[
				'mediaFormat'         => 'PHOTO',
				'locationAssociation' => [ 'category' => 'AT_WORK' ],
				'sourceUrl'           => $photo_url,
			]
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return isset( $response['name'] );
	}

	public static function create_local_post( string $location_name, string $title, string $description, string $photo_url ): bool {
		$summary = trim( $title . "\n\n" . $description );

		$response = ECHS_Google_Auth::request(
			self::POST_API . $location_name . '/localPosts',
			'POST',
			[
				'languageCode' => 'en-US',
				'summary'      => $summary,
				'callToAction' => [
					'actionType' => 'LEARN_MORE',
					'url'        => get_home_url(),
				],
				'media'        => [
					[
						'mediaFormat' => 'PHOTO',
						'sourceUrl'   => $photo_url,
					],
				],
				'topicType'    => 'STANDARD',
			]
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		return isset( $response['name'] );
	}

	public static function ajax_geocode(): void {
		check_ajax_referer( 'echs_meta_box_nonce', 'nonce' );

		$address = sanitize_text_field( $_POST['address'] ?? '' );

		if ( empty( $address ) ) {
			wp_send_json_error( 'Address not found' );
		}

		$response = wp_remote_get(
			self::NOMINATIM . '?' . http_build_query( [ 'format' => 'json', 'limit' => 1, 'q' => $address ] ),
			[
				'headers' => [ 'Accept-Language' => 'en-US' ],
				'timeout' => 10,
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( 'Address not found' );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || empty( $data ) ) {
			wp_send_json_error( 'Address not found' );
		}

		wp_send_json_success( [ 'lat' => $data[0]['lat'], 'lng' => $data[0]['lon'] ] );
	}
}
