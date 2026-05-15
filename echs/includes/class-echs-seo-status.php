<?php
/**
 * Google Search status feed: fetches incidents via WP-Cron, shows dashboard
 * widget section and an admin notice when an active incident is detected.
 *
 * @package Stride_Analytics
 */

defined( 'ABSPATH' ) || exit;

class ECHS_SEO_Status {

	const FEED_URL   = 'https://status.search.google.com/feed.atom';
	const TRANSIENT  = 'echs_google_seo_status';
	const CACHE_SECS = 6 * HOUR_IN_SECONDS;
	const CRON_HOOK  = 'echs_fetch_seo_status';

	public static function init(): void {
		add_action( self::CRON_HOOK,      [ __CLASS__, 'fetch_and_cache' ] );
		add_action( 'admin_init',         [ __CLASS__, 'schedule_cron' ] );
		add_action( 'admin_notices',      [ __CLASS__, 'maybe_show_notice' ] );
	}

	public static function schedule_cron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'echs_sixhours', self::CRON_HOOK );
		}
	}

	public static function fetch_and_cache(): void {
		$response = wp_remote_get( self::FEED_URL, [
			'timeout'    => 10,
			'user-agent' => 'Stride-Analytics/' . ECHS_VERSION . '; ' . home_url(),
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		$items = self::parse_atom( wp_remote_retrieve_body( $response ) );
		set_transient( self::TRANSIENT, $items, self::CACHE_SECS );
	}

	private static function parse_atom( string $xml ): array {
		libxml_use_internal_errors( true );
		$doc = simplexml_load_string( $xml );
		if ( ! $doc ) {
			return [];
		}

		$items = [];
		foreach ( $doc->entry as $entry ) {
			$title   = wp_strip_all_tags( (string) $entry->title );
			$summary = wp_strip_all_tags( (string) ( $entry->summary ?: $entry->content ) );
			$updated = (string) $entry->updated;

			$link = '';
			foreach ( $entry->link as $l ) {
				$rel = (string) $l['rel'];
				if ( ! $rel || 'alternate' === $rel ) {
					$link = (string) $l['href'];
					break;
				}
			}

			$lower    = strtolower( $title . ' ' . $summary );
			$is_active = str_contains( $lower, 'ongoing' )
				|| str_contains( $lower, 'investigating' )
				|| str_contains( $lower, 'disruption' )
				|| str_contains( $lower, 'outage' );

			$items[] = compact( 'title', 'summary', 'updated', 'link', 'is_active' );

			if ( count( $items ) >= 5 ) {
				break;
			}
		}

		return $items;
	}

	public static function get_items(): array {
		$cached = get_transient( self::TRANSIENT );
		if ( false !== $cached ) {
			return $cached;
		}
		self::fetch_and_cache();
		return get_transient( self::TRANSIENT ) ?: [];
	}

	public static function maybe_show_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		foreach ( self::get_items() as $item ) {
			if ( ! $item['is_active'] ) {
				continue;
			}
			$link = $item['link']
				? ' <a href="' . esc_url( $item['link'] ) . '" target="_blank" rel="noopener">View incident &rarr;</a>'
				: '';
			echo '<div class="notice notice-warning"><p>'
				. '<strong>Google Search Status:</strong> '
				. esc_html( $item['title'] )
				. $link
				. '</p></div>';
			break; // only the first active incident
		}
	}

	/** Render the Google status section inside the dashboard widget. */
	public static function render_widget_section(): void {
		$items = self::get_items();

		echo '<h3 class="echs-widget-section-title">'
			. '<span class="dashicons dashicons-search"></span> Google Search Status</h3>';

		if ( empty( $items ) ) {
			echo '<p class="echs-widget-empty">Unable to load status feed. '
				. '<a href="' . esc_url( 'https://status.search.google.com/' ) . '" target="_blank" rel="noopener">Check manually &rarr;</a></p>';
			return;
		}

		echo '<ul class="echs-status-list">';
		foreach ( $items as $item ) {
			$dot_class = $item['is_active'] ? 'echs-dot-warning' : 'echs-dot-ok';
			$date      = $item['updated'] ? wp_date( 'M j, Y', strtotime( $item['updated'] ) ) : '';
			$link_open = $item['link'] ? '<a href="' . esc_url( $item['link'] ) . '" target="_blank" rel="noopener">' : '';
			$link_close = $item['link'] ? '</a>' : '';
			printf(
				'<li><span class="echs-dot %s"></span>%s<span class="echs-status-title">%s</span>%s%s</li>',
				esc_attr( $dot_class ),
				$link_open,
				esc_html( $item['title'] ),
				$link_close,
				$date ? ' <span class="echs-status-date">' . esc_html( $date ) . '</span>' : ''
			);
		}
		echo '</ul>';

		echo '<p class="echs-widget-footer">'
			. '<a href="https://status.search.google.com/products/rGHU1u87FJnkP6W2GwMi/history" target="_blank" rel="noopener">Full history &rarr;</a>'
			. '</p>';
	}
}
