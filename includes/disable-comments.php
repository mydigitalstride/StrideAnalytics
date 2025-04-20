<?php
if (!defined('ABSPATH')) { exit; }
// Remove pingback header from head
add_action('init', function () {
    if (get_option('disable_pingbacks')) {
        remove_action('wp_head', 'rsd_link'); // removes <link rel="pingback" href="...">
    }
});
// Disable comments sitewide
function tracking_plugin_comments_off($open, $post_id) {
    return get_option('disable_comments') ? false : $open;
}
add_filter('comments_open', 'tracking_plugin_comments_off', 20, 2);

// Hide existing comments
function tracking_plugin_hide_existing_comments($comments) {
    return get_option('disable_comments') ? [] : $comments;
}
add_filter('comments_array', 'tracking_plugin_hide_existing_comments', 10, 2);

// Disable pingbacks sitewide
function tracking_plugin_disable_pingbacks($open, $post_id) {
    return get_option('disable_pingbacks') ? false : $open;
}
add_filter('pings_open', 'tracking_plugin_disable_pingbacks', 20, 2);


