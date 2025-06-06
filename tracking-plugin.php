<?php
/**
 * Plugin Name: Stride Analytics - Basic
 * Description: Automatically installs Google Tag Manager, Facebook Pixel, and LinkedIn Insight Tag. Includes UTM tracking, comment/pingback toggles, GA warning, and login/register for premium features.
 * Version: 1.1
 * Author: Digital Stride
 */

if (!defined('ABSPATH')) {
    exit;
}

define('TRACKING_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once TRACKING_PLUGIN_DIR . 'includes/settings-page.php';
require_once TRACKING_PLUGIN_DIR . 'includes/enqueue-scripts.php';
require_once TRACKING_PLUGIN_DIR . 'includes/disable-comments.php';

function tracking_plugin_activate() {
    add_option('tracking_gtm_id', '');
    add_option('tracking_facebook_pixel_id', '');
    add_option('tracking_linkedin_partner_id', '');
    add_option('utm_form_field', '');
    add_option('disable_comments', '');
    add_option('disable_pingbacks', '');
    add_option('remove_ga_scripts', '');
}
register_activation_hook(__FILE__, 'tracking_plugin_activate');

function tracking_plugin_uninstall() {
    delete_option('tracking_gtm_id');
    delete_option('tracking_facebook_pixel_id');
    delete_option('tracking_linkedin_partner_id');
    delete_option('utm_form_field');
    delete_option('disable_comments');
    delete_option('disable_pingbacks');
    delete_option('remove_ga_scripts');
}
register_uninstall_hook(__FILE__, 'tracking_plugin_uninstall');
