<?php
if (!defined('ABSPATH')) { exit; }

function tracking_plugin_insert_scripts() {
    $gtm_id = get_option('tracking_gtm_id');
    $facebook_pixel_id = get_option('tracking_facebook_pixel_id');
    $linkedin_partner_id = get_option('tracking_linkedin_partner_id');

    echo "<!-- Universal Tracking Manager (Basic) -->";

    if ($gtm_id) {
        echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});
            var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id=' + i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','" . esc_js($gtm_id) . "');</script>";
    }

    if ($facebook_pixel_id) {
        echo "<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
            n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
            n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}
            (window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', '" . esc_js($facebook_pixel_id) . "');
            fbq('track', 'PageView');</script>";
    }

    if ($linkedin_partner_id) {
        echo "<script>_linkedin_partner_id = '" . esc_js($linkedin_partner_id) . "';
            window._linkedin_data_partner_ids = window._linkedin_data_partner_ids || [];
            window._linkedin_data_partner_ids.push(_linkedin_partner_id);
            (function(l){if(!l){window.lintrk=function(a,b){window.lintrk.q.push([a,b])};window.lintrk.q=[]}
            var s=document.getElementsByTagName('script')[0],b=document.createElement('script');
            b.type='text/javascript';b.async=true;b.src='https://snap.licdn.com/li.lms-analytics/insight.min.js';
            s.parentNode.insertBefore(b,s);})(window.lintrk);</script>";
    }
}
add_action('wp_head', 'tracking_plugin_insert_scripts');
?>
