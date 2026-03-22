<?php
/**
 * WooCommerce Integration (Phase 6).
 *
 * Auto-populates Product schema fields from WooCommerce data on product
 * edit screens, and outputs ItemList schema on product category archives.
 *
 * @package HomeRite_Schema_Manager
 */

defined( 'ABSPATH' ) || exit;

class HSM_WooCommerce {

	public static function init(): void {
		if ( ! class_exists( 'WooCommerce' ) ) return;

		// Pre-fill meta box fields with WooCommerce data via JS globals.
		add_action( 'admin_footer-post.php',     [ __CLASS__, 'inject_woo_data' ] );
		add_action( 'admin_footer-post-new.php', [ __CLASS__, 'inject_woo_data' ] );

		// Auto-save WooCommerce product data into our meta on product save.
		add_action( 'woocommerce_process_product_meta', [ __CLASS__, 'sync_from_woocommerce' ] );
	}

	/**
	 * On product edit screen, inject WooCommerce product data as JS variables
	 * so the admin.js can pre-fill our meta box fields if they are empty.
	 */
	public static function inject_woo_data(): void {
		global $post;

		if ( ! $post || 'product' !== $post->post_type ) return;

		$product = wc_get_product( $post->ID );
		if ( ! $product ) return;

		$price    = $product->get_price();
		$currency = get_woocommerce_currency();
		$sku      = $product->get_sku();
		$desc     = $product->get_description();
		$in_stock = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';

		// Only output script if any data available.
		if ( '' === $price && '' === $sku ) return;
		?>
		<script>
		(function($){
			$(function(){
				// Pre-fill price if our field is empty.
				var $price = $('#hsm_product_price');
				if ( $price.length && '' === $price.val() ) {
					$price.val( <?php echo wp_json_encode( $price ); ?> ).attr( 'placeholder', 'Auto from WooCommerce' );
				}

				// Pre-fill currency if empty.
				var $currency = $('#hsm_product_currency');
				if ( $currency.length && '' === $currency.val() ) {
					$currency.val( <?php echo wp_json_encode( $currency ); ?> );
				}

				// Pre-fill availability.
				var $avail = $('#hsm_product_availability');
				if ( $avail.length && '' === $avail.val() ) {
					$avail.val( <?php echo wp_json_encode( $in_stock ); ?> );
				}

				// Pre-fill description if empty.
				var $desc = $('#hsm_product_description');
				if ( $desc.length && '' === $desc.val() ) {
					$desc.val( <?php echo wp_json_encode( wp_strip_all_tags( $desc ) ); ?> );
				}
			});
		})(jQuery);
		</script>
		<?php
	}

	/**
	 * When a WooCommerce product is saved, sync key data into our meta fields
	 * only if the admin has NOT manually overridden them.
	 *
	 * @param int $post_id WooCommerce product post ID.
	 */
	public static function sync_from_woocommerce( int $post_id ): void {
		$product = wc_get_product( $post_id );
		if ( ! $product ) return;

		// Only auto-fill if our field is still empty (don't overwrite manual entries).
		if ( '' === get_post_meta( $post_id, 'hsm_product_price', true ) ) {
			update_post_meta( $post_id, 'hsm_product_price', $product->get_price() );
		}

		if ( '' === get_post_meta( $post_id, 'hsm_product_currency', true ) ) {
			update_post_meta( $post_id, 'hsm_product_currency', get_woocommerce_currency() );
		}

		if ( '' === get_post_meta( $post_id, 'hsm_product_availability', true ) ) {
			$avail = $product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock';
			update_post_meta( $post_id, 'hsm_product_availability', $avail );
		}

		if ( '' === get_post_meta( $post_id, 'hsm_product_name', true ) ) {
			update_post_meta( $post_id, 'hsm_product_name', $product->get_name() );
		}

		if ( '' === get_post_meta( $post_id, 'hsm_product_description', true ) ) {
			update_post_meta( $post_id, 'hsm_product_description', wp_strip_all_tags( $product->get_description() ) );
		}

		// Ensure Product schema type is enabled.
		$enabled = get_post_meta( $post_id, 'hsm_schema_enabled_types', true ) ?: [];
		if ( ! in_array( 'Product', $enabled, true ) ) {
			$enabled[] = 'Product';
			update_post_meta( $post_id, 'hsm_schema_enabled_types', $enabled );
		}
	}
}
