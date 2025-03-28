<?php
if (!defined('ABSPATH')) { exit; }

function tracking_plugin_menu() {
    add_options_page('Tracking Manager', 'Tracking Manager', 'manage_options', 'tracking-plugin', 'tracking_plugin_settings_page');
}
add_action('admin_menu', 'tracking_plugin_menu');

function tracking_plugin_register_settings() {
    register_setting('tracking-plugin-settings', 'tracking_gtm_id');
    register_setting('tracking-plugin-settings', 'tracking_facebook_pixel_id');
    register_setting('tracking-plugin-settings', 'tracking_linkedin_partner_id');
    register_setting('tracking-plugin-settings', 'utm_form_field');
}
add_action('admin_init', 'tracking_plugin_register_settings');

function tracking_plugin_settings_page() {
    ?>
    <div class="wrap">
        <h1>Universal Tracking Manager (Basic)</h1>
        <form method="post" action="options.php">
            <?php settings_fields('tracking-plugin-settings'); ?>
            <?php do_settings_sections('tracking-plugin-settings'); ?>
            <table class="form-table">
                <tr><th><label for="tracking_gtm_id">Google Tag Manager ID:</label></th>
                    <td><input type="text" name="tracking_gtm_id" value="<?php echo esc_attr(get_option('tracking_gtm_id')); ?>" /></td></tr>
                <tr><th><label for="tracking_facebook_pixel_id">Facebook Pixel ID:</label></th>
                    <td><input type="text" name="tracking_facebook_pixel_id" value="<?php echo esc_attr(get_option('tracking_facebook_pixel_id')); ?>" /></td></tr>
                <tr><th><label for="tracking_linkedin_partner_id">LinkedIn Partner ID:</label></th>
                    <td><input type="text" name="tracking_linkedin_partner_id" value="<?php echo esc_attr(get_option('tracking_linkedin_partner_id')); ?>" /></td></tr>
                <tr><th>Form Field for UTM Tracking:</th>
                    <td><input type="text" name="utm_form_field" value="<?php echo esc_attr(get_option('utm_form_field')); ?>" placeholder="Enter form field name for UTM tracking" /></td></tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <h2>Upgrade to Universal Tracking Manager Premium!</h2>
        <p><strong>Now only $14.99/month (Was $49.99)</strong></p>
        <p>Unlock the full power of tracking automation with our premium version:</p>
        <ul>
            <li>✅ **Automatic GTM Event Creation** – No manual setup required.</li>
            <li>✅ **Pre-Configured E-commerce Tracking** – Track Add to Cart, Checkout, and Purchases effortlessly.</li>
            <li>✅ **Advanced Facebook Conversion API** – Server-side tracking for better ad performance.</li>
            <li>✅ **No Duplicate Events** – Ensures existing tracking is not overridden.</li>
            <li>✅ **Track Form Submissions, Thank You Pages, and Contact Interactions.**</li>
        </ul>
        <a href="https://yourwebsite.com/premium-upgrade" class="button button-primary">Upgrade to Premium</a>
    </div>
    <?php
}
?>
