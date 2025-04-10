<?php
if (!defined('ABSPATH')) { exit; }

function tracking_plugin_comments_off($open, $post_id) {
    if (get_option('disable_comments')) {
        return false;
    }
    return $open;
}
add_filter('comments_open', 'tracking_plugin_comments_off', 20, 2);
add_filter('pings_open', 'tracking_plugin_comments_off', 20, 2);

// Optionally hide existing comments
function tracking_plugin_hide_existing_comments($comments) {
    return get_option('disable_comments') ? [] : $comments;
}
add_filter('comments_array', 'tracking_plugin_hide_existing_comments', 10, 2);
