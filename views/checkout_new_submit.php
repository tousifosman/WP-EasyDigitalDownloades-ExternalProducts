<?php
/**
 * New Perchese Button Section
 */
function edd_external_product_new_checkout_submit () {
  ?>

  <fieldset id="edd_purchase_submit">

    <input type="hidden" name="edd_external_product_submit" />

    <?php do_action( 'edd_purchase_form_before_submit' ); ?>

    <?php echo edd_checkout_button_purchase(); ?>

    <?php do_action( 'edd_purchase_form_after_submit' ); ?>

  </fieldset>

  <?php
}
