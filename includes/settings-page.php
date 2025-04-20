<?php
if (!defined('ABSPATH')) {
    exit;
}
//Fix: Allow any logged-in user to access plugin page
function tracking_plugin_menu() {
    add_options_page(
        'Tracking Manager',
        'Tracking Manager',
        'read', //changed from 'manage_options'
        'tracking-plugin',
        'tracking_plugin_settings_page'
    );
}
add_action('admin_menu', 'tracking_plugin_menu');

// Register your plugin settings
function tracking_plugin_register_settings() {
    register_setting('tracking-plugin-settings', 'tracking_gtm_id');
    register_setting('tracking-plugin-settings', 'tracking_facebook_pixel_id');
    register_setting('tracking-plugin-settings', 'tracking_linkedin_partner_id');
    register_setting('tracking-plugin-settings', 'utm_form_field');
    register_setting('tracking-plugin-settings', 'disable_comments');
    register_setting('tracking-plugin-settings', 'disable_pingbacks');
    register_setting('tracking-plugin-settings', 'remove_ga_scripts');
    register_setting('tracking-plugin-settings', 'gtm_approved');

}
add_action('admin_init', 'tracking_plugin_register_settings');
// 🔐 Handle Login/Registration/Logout Logic
if (isset($_POST['tracking_plugin_login']) && check_admin_referer('tracking_plugin_login')) {
    $creds = [
        'user_login' => sanitize_user($_POST['tracking_username']),
        'user_password' => $_POST['tracking_password'],
        'remember' => true
    ];
    $user = wp_signon($creds, false);

    if (is_wp_error($user)) {
        echo '<div class="notice notice-error"><p><strong>Error:</strong> ' . $user->get_error_message() . '</p></div>';
    } else {
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID);
        wp_redirect(admin_url('options-general.php?page=tracking-plugin'));
        exit;
    }
}

if (isset($_POST['tracking_plugin_register']) && check_admin_referer('tracking_plugin_login')) {
    $username = sanitize_user($_POST['tracking_username']);
    $password = $_POST['tracking_password'];
    $email = is_email($username) ? $username : $username . '@example.com';

    if (username_exists($username) || email_exists($email)) {
        echo '<div class="notice notice-error"><p><strong>Error:</strong> User already exists.</p></div>';
    } else {
        $user_id = wp_create_user($username, $password, $email);
        if (!is_wp_error($user_id)) {
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            wp_redirect(admin_url('options-general.php?page=tracking-plugin'));
            exit;
        }
    }
}

if (isset($_POST['tracking_plugin_logout']) && check_admin_referer('tracking_plugin_logout')) {
    wp_logout();
    wp_redirect(admin_url('options-general.php?page=tracking-plugin'));
    exit;
}
add_action('admin_init', function () {
    if (isset($_POST['approve_gtm']) && check_admin_referer('tracking-plugin-settings')) {
        update_option('gtm_approved', true);
        add_action('admin_notices', function () {
            echo '<div class="notice notice-success"><p>✅ GTM Tag approved and connected!</p></div>';
        });
    }
});



function tracking_plugin_settings_page()
{
    $ga_detected = false;
    $ga_type = '';

    $response = wp_remote_get(home_url());
    if (!is_wp_error($response)) {
        $html = wp_remote_retrieve_body($response);

        if (strpos($html, 'googletagmanager.com/gtag/js') !== false) {
            $ga_detected = true;
            $ga_type = 'Google Analytics 4 (gtag.js)';
        } elseif (strpos($html, 'google-analytics.com/analytics.js') !== false) {
            $ga_detected = true;
            $ga_type = 'Universal Analytics (analytics.js)';
        }
    }
?>
    <div class="wrap">
        <h1>Universal Tracking Manager (Basic)</h1>

        <?php if ($ga_detected): ?>
            <div class="notice notice-warning">
                <p><strong>⚠️ Warning:</strong> <?= esc_html($ga_type); ?> script is already detected on your homepage. Avoid duplicate tracking by removing other GA plugins or header scripts.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php">
            <?php settings_fields('tracking-plugin-settings'); ?>
            <?php do_settings_sections('tracking-plugin-settings'); ?>
            <table class="form-table">
            <?php if (is_user_logged_in()): ?>
                <!-- <tr>
                    <th><label for="tracking_gtm_id">Google Tag Manager ID:</label></th>
                    <td><input type="text" name="tracking_gtm_id" value="<?php echo esc_attr(get_option('tracking_gtm_id')); ?>" /></td>
                </tr> -->
                <tr>
                    <th><label for="tracking_facebook_pixel_id">Facebook Pixel ID:</label></th>
                    <td><input type="text" name="tracking_facebook_pixel_id" value="<?php echo esc_attr(get_option('tracking_facebook_pixel_id')); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="tracking_linkedin_partner_id">LinkedIn Partner ID:</label></th>
                    <td><input type="text" name="tracking_linkedin_partner_id" value="<?php echo esc_attr(get_option('tracking_linkedin_partner_id')); ?>" /></td>
                </tr>
            <?php else: ?>
                <tr>
                    <td colspan="2">
                        <div class="notice notice-info">
                            <p>🔒 <strong>Login required:</strong> Please log in to unlock advanced tracking features (GTM, Pixel, LinkedIn).</p>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
                <tr>
                    <th>Form Field for UTM Tracking:</th>
                    <td><input type="text" name="utm_form_field" value="<?php echo esc_attr(get_option('utm_form_field')); ?>" placeholder="Enter form field name for UTM tracking" /></td>
                </tr>
                <tr>
                    <th><label for="disable_comments">Disable All Comments:</label></th>
                    <td><input type="checkbox" name="disable_comments" value="1" <?php checked(1, get_option('disable_comments'), true); ?> /></td>
                </tr>
                <tr>
                    <th><label for="disable_pingbacks">Disable Pingbacks:</label></th>
                    <td><input type="checkbox" name="disable_pingbacks" value="1" <?php checked(1, get_option('disable_pingbacks'), true); ?> /></td>
                </tr>
                <tr>
                <th><label for="tracking_gtm_id">Google Tag Manager ID:</label></th>
                <td>
                    <input type="text" name="tracking_gtm_id" value="<?php echo esc_attr(get_option('tracking_gtm_id')); ?>" />
                    <?php if (get_option('tracking_gtm_id') && !get_option('gtm_approved')): ?>
                   <form method="post">
                    <?php wp_nonce_field('tracking-plugin-settings'); ?>
                    <input type="hidden" name="approve_gtm" value="1" />
                    <p><button type="submit" class="button">Connect GTM</button></p>
                </form>
                    <?php elseif (get_option('gtm_approved')): ?>
                    <p>✅ GTM Connected</p>
                    <?php endif; ?>
                </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <h2>Upgrade to Universal Tracking Manager Premium!</h2>
        <p><strong>Now only $14.99/month (Was $49.99)</strong></p>
        <p>Unlock the full power of tracking automation with our premium version:</p>
        <ul>
            <li>✅ Automatic GTM Event Creation</li>
            <li>✅ Pre-Configured E-commerce Tracking</li>
            <li>✅ Advanced Facebook Conversion API</li>
            <li>✅ No Duplicate Events</li>
            <li>✅ Track Form Submissions, Thank You Pages, and Contact Interactions</li>
        </ul>
        <a href="https://yourwebsite.com/premium-upgrade" class="button button-primary">Upgrade to Premium</a>

        <hr>
        <h2>Sign up or Log in for Advanced Features</h2>

        <?php if (is_user_logged_in()): ?>
            <p>You are logged in as <strong><?php echo wp_get_current_user()->display_name; ?></strong>.</p>
            <form method="post">
                <?php wp_nonce_field('tracking_plugin_logout'); ?>
                <input type="submit" name="tracking_plugin_logout" class="button" value="Log Out of Premium" />
            </form>
        <?php else: ?>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th><label for="tracking_username">Username or Email:</label></th>
                        <td><input type="text" name="tracking_username" required /></td>
                    </tr>
                    <tr>
                        <th><label for="tracking_password">Password:</label></th>
                        <td><input type="password" name="tracking_password" required /></td>
                    </tr>
                </table>
                <?php wp_nonce_field('tracking_plugin_login'); ?>
                <p>
                    <input type="submit" name="tracking_plugin_login" class="button-primary" value="Log In" />
                    or
                    <input type="submit" name="tracking_plugin_register" class="button" value="Register" />
                </p>
            </form>
        <?php endif; ?>
    </div>
<?php
}
?>
