<?php
/**
 * Tracking scripts output and site behaviour controls.
 *
 * Merges features from the old "Stride Analytics - Basic" tracking plugin:
 *   - Google Tag Manager (GTM)
 *   - Facebook Pixel
 *   - LinkedIn Insight Tag
 *   - Comments / pingbacks disable toggles
 *
 * Settings are stored under the original option names so data already saved
 * by the standalone tracking plugin is preserved without migration.
 *
 * @package Stride_Analytics
 */

defined( 'ABSPATH' ) || exit;

class HSM_Tracking {

	public static function init(): void {
		// Front-end script injection.
		add_action( 'wp_head', [ __CLASS__, 'output_tracking_scripts' ], 2 );

		// Comments / pingbacks controls — runs early on init.
		add_action( 'init',           [ __CLASS__, 'maybe_disable_pingback_header' ] );
		add_filter( 'comments_open',  [ __CLASS__, 'maybe_disable_comments' ], 20, 2 );
		add_filter( 'comments_array', [ __CLASS__, 'maybe_hide_comments' ],    10, 2 );
		add_filter( 'pings_open',     [ __CLASS__, 'maybe_disable_pings' ],    20, 2 );
	}

	// ------------------------------------------------------------------
	// Tracking script output
	// ------------------------------------------------------------------

	public static function output_tracking_scripts(): void {
		$gtm_id      = get_option( 'tracking_gtm_id' );
		$pixel_id    = get_option( 'tracking_facebook_pixel_id' );
		$linkedin_id = get_option( 'tracking_linkedin_partner_id' );

		if ( ! $gtm_id && ! $pixel_id && ! $linkedin_id ) {
			return;
		}

		echo '<!-- Stride Analytics Tracking -->' . "\n";

		// Google Tag Manager — only fires after admin approval (Connect GTM).
		if ( $gtm_id && get_option( 'gtm_approved' ) ) {
			$gtm_id_esc = esc_js( $gtm_id );
			echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':\n";
			echo "new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],\n";
			echo "j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=\n";
			echo "'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);\n";
			echo "})(window,document,'script','dataLayer','" . $gtm_id_esc . "');</script>\n";
		}

		// Facebook Pixel.
		if ( $pixel_id ) {
			$pixel_id_esc = esc_js( $pixel_id );
			echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){\n";
			echo "n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};\n";
			echo "if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';\n";
			echo "n.queue=[];t=b.createElement(e);t.async=!0;\n";
			echo "t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}\n";
			echo "(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');\n";
			echo "fbq('init','" . $pixel_id_esc . "');fbq('track','PageView');</script>\n";
		}

		// LinkedIn Insight Tag.
		if ( $linkedin_id ) {
			$li_id_esc = esc_js( $linkedin_id );
			echo "<script>_linkedin_partner_id='" . $li_id_esc . "';\n";
			echo "window._linkedin_data_partner_ids=window._linkedin_data_partner_ids||[];\n";
			echo "window._linkedin_data_partner_ids.push(_linkedin_partner_id);\n";
			echo "(function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};\n";
			echo "window.lintrk.q=[]}var s=document.getElementsByTagName('script')[0],\n";
			echo "b=document.createElement('script');b.type='text/javascript';b.async=true;\n";
			echo "b.src='https://snap.licdn.com/li.lms-analytics/insight.min.js';\n";
			echo "s.parentNode.insertBefore(b,s);})(window.lintrk);</script>\n";
		}
	}

	// ------------------------------------------------------------------
	// Comments / pingbacks
	// ------------------------------------------------------------------

	public static function maybe_disable_pingback_header(): void {
		if ( get_option( 'disable_pingbacks' ) ) {
			remove_action( 'wp_head', 'rsd_link' );
		}
	}

	public static function maybe_disable_comments( bool $open, int $post_id ): bool {
		return get_option( 'disable_comments' ) ? false : $open;
	}

	public static function maybe_hide_comments( array $comments, int $post_id ): array {
		return get_option( 'disable_comments' ) ? [] : $comments;
	}

	public static function maybe_disable_pings( bool $open, int $post_id ): bool {
		return get_option( 'disable_pingbacks' ) ? false : $open;
	}
}
