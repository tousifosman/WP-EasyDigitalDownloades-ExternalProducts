<?php
/**
 * Remove Default Purchase section if there existts any external products in the
 * cart.
 * Calls:
 *  add_external_product_view -> edd_purchase_form_after_cc_form - 9999
 */
function edd_external_product_checkout_remove_default() {

  $hook_name = 'edd_purchase_form_before_submit';

  global $wp_filter, $edd_options;

  $contents = edd_get_cart_contents();

  //Action taken if there exissts any external product
  foreach ($contents as $content) {

    //Bypass standrad purchase
    if(edd_get_download_type( $content['id'] ) === 'external') {
      remove_action( 'edd_purchase_form_after_cc_form', 'edd_checkout_submit', 9999 );
      add_action('edd_purchase_form_after_cc_form', 'edd_external_product_new_checkout_submit', 9999);
      break;
    }
  }
}
add_action('edd_checkout_form_top', 'edd_external_product_checkout_remove_default');
