<?php
/**
 * Broadcast messages: fetches a JSON file from mydigitalstride.com every 12 h,
 * shows a dismissible admin notice and a dashboard widget panel.
 *
 * ─── JSON format (host at https://mydigitalstride.com/stride-broadcast.json) ───
 * {
 *   "id":        "2026-05-001",          ← bump to re-show to dismissed users
 *   "type":      "info",                 ← info | warning | success | error
 *   "title":     "New in v2.1",
 *   "message":   "ACF Pro support is now available…",
 *   "expires":   "2026-07-01",           ← ISO date; omit to show indefinitely
 *   "link":      "https://mydigitalstride.com/changelog",
 *   "link_text": "See what's new"
 * }
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * @package Stride_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_Broadcast {

	const SOURCE_URL  = 'https://mydigitalstride.com/stride-broadcast.json';
	const TRANSIENT   = 'echs_broadcast_message';
	const CACHE_SECS  = 12 * HOUR_IN_SECONDS;
	const CRON_HOOK   = 'echs_fetch_broadcast';
	const DISMISS_KEY = 'echs_dismissed_broadcast';

	public static function init(): void {
		add_action( self::CRON_HOOK,                    [ __CLASS__, 'fetch_and_cache' ] );
		add_action( 'admin_init',                       [ __CLASS__, 'schedule_cron' ] );
		add_action( 'admin_notices',                    [ __CLASS__, 'maybe_show_notice' ] );
		add_action( 'admin_footer',                     [ __CLASS__, 'output_dismiss_script' ] );
		add_action( 'wp_ajax_echs_dismiss_broadcast',    [ __CLASS__, 'ajax_dismiss' ] );
	}

	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'twicedaily', self::CRON_HOOK );
		}
	}

	public static function fetch_and_cache(): void {
		$response = wp_remote_get( self::SOURCE_URL, [
			'timeout'    => 8,
			'user-agent' => 'Stride-Analytics/' . ECHS_VERSION . '; ' . home_url(),
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['id'] ) || empty( $data['message'] ) ) {
			return;
		}

		set_transient( self::TRANSIENT, $data, self::CACHE_SECS );
	}

	public static function get_message(): ?array {
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return $cached ?: null;
		}
		self::fetch_and_cache();
		$cached = get_transient( self::TRANSIENT );
		return $cached ?: null;
	}

	private static function is_visible( array $msg ): bool {
		// Expired?
		if ( ! empty( $msg['expires'] ) && strtotime( $msg['expires'] ) < time() ) {
			return false;
		}
		// Already dismissed by this user?
		$dismissed = get_user_meta( get_current_user_id(), self::DISMISS_KEY, true );
		if ( $dismissed === $msg['id'] ) {
			return false;
		}
		return true;
	}

	public static function maybe_show_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msg = self::get_message();
		if ( ! $msg || ! self::is_visible( $msg ) ) {
			return;
		}

		$allowed_types = [ 'info', 'warning', 'success', 'error' ];
		$type          = in_array( $msg['type'] ?? '', $allowed_types, true ) ? $msg['type'] : 'info';
		$title_html    = ! empty( $msg['title'] )
			? '<strong>' . esc_html( $msg['title'] ) . '</strong> &mdash; '
			: '';
		$link_html = '';
		if ( ! empty( $msg['link'] ) ) {
			$link_text = ! empty( $msg['link_text'] ) ? $msg['link_text'] : 'Learn more';
			$link_html = ' <a href="' . esc_url( $msg['link'] ) . '" target="_blank" rel="noopener">'
				. esc_html( $link_text ) . ' &rarr;</a>';
		}

		printf(
			'<div class="notice notice-%s echs-broadcast-notice" data-id="%s">'
				. '<p>%s%s%s</p>'
				. '<button type="button" class="notice-dismiss echs-dismiss-broadcast">'
				. '<span class="screen-reader-text">Dismiss</span></button>'
				. '</div>',
			esc_attr( $type ),
			esc_attr( $msg['id'] ),
			$title_html,
			esc_html( $msg['message'] ),
			$link_html
		);
	}

	public static function ajax_dismiss(): void {
		check_ajax_referer( 'echs_broadcast_dismiss', 'nonce' );
		$id = sanitize_text_field( wp_unslash( $_POST['id'] ?? '' ) );
		if ( $id ) {
			update_user_meta( get_current_user_id(), self::DISMISS_KEY, $id );
		}
		wp_send_json_success();
	}

	public static function output_dismiss_script(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// Only output if a broadcast notice is actually visible on this page load.
		$msg = self::get_message();
		if ( ! $msg || ! self::is_visible( $msg ) ) {
			return;
		}
		?>
		<script>
		(function($){
			$(document).on('click','.echs-dismiss-broadcast',function(){
				var $notice=$(this).closest('.echs-broadcast-notice');
				var id=$notice.data('id');
				$notice.fadeTo(200,0,function(){ $notice.slideUp(150); });
				$.post(ajaxurl,{
					action:'echs_dismiss_broadcast',
					nonce:'<?php echo esc_js( wp_create_nonce( 'echs_broadcast_dismiss' ) ); ?>',
					id:id
				});
			});
		})(jQuery);
		</script>
		<?php
	}

	/** Render the broadcast section inside the dashboard widget. */
	public static function render_widget_section(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$msg = self::get_message();
		if ( ! $msg || ! self::is_visible( $msg ) ) {
			return;
		}

		$allowed_types = [ 'info', 'warning', 'success', 'error' ];
		$type          = in_array( $msg['type'] ?? '', $allowed_types, true ) ? $msg['type'] : 'info';
		$color_map     = [
			'info'    => '#2271b1',
			'warning' => '#dba617',
			'success' => '#00a32a',
			'error'   => '#d63638',
		];
		$color = $color_map[ $type ] ?? $color_map['info'];

		$link_html = '';
		if ( ! empty( $msg['link'] ) ) {
			$link_text = ! empty( $msg['link_text'] ) ? $msg['link_text'] : 'Learn more';
			$link_html = ' <a href="' . esc_url( $msg['link'] ) . '" target="_blank" rel="noopener">'
				. esc_html( $link_text ) . ' &rarr;</a>';
		}

		echo '<div class="echs-broadcast-widget" style="border-left:4px solid ' . esc_attr( $color ) . ';">';
		echo '<h3 class="echs-widget-section-title"><span class="dashicons dashicons-megaphone"></span> '
			. ( ! empty( $msg['title'] ) ? esc_html( $msg['title'] ) : 'From Digital Stride' ) . '</h3>';
		echo '<p>' . esc_html( $msg['message'] ) . $link_html . '</p>';
		echo '</div>';
	}
}
