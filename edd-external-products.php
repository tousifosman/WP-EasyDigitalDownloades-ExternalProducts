<?php
/*
Plugin Name: Easy Digital Downloads - External Products Register
Plugin URI:
Description: Automatically creates a WP user account and redirects the users to external location.
Version: 1
Author: Tousif Osman
Author URI: mailto:tousifosman@gmail.com
@author Tousif Osman <tousifosmans@gmail.com>
*/


//include_once dirname(__FILE__) . '/functions.php';

//functions
function edd_external_getURL($id) {
  return get_post_meta( $id, 'edd_external_url', true );
}

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action('edd_checkout_before_gateway', function() {
	if (empty($_SESSION['edd_external_receipt_new'])) {
		//Rmove Session Flag so that the recipt page doesnt alters incase of non-external product
		unset($_SESSION['edd_external_receipt']);
	}
});

function edd_external_product_init() {

  // Process Checkout-----------------------
  if(isset($_POST['edd_external_product_submit']) && isset($_GET['payment-mode'])) {

    global $edd_options;

    $contents = edd_get_cart_contents();
    $itemIDList = array();

    //Remove Non external products from the cart
    foreach ($contents as $item) {
      if(edd_get_download_type( $item['id'] ) !== 'external') {
        edd_remove_from_cart($key);
        $itemIDList[] = $item[id];
        $listOPtions[$item[id]] = $item['options'];
        $listOPtions[$item[id]]['quantity'] = $item['quantity'];
      }
    }
    foreach ($itemIDList as $key) {
      edd_remove_from_cart(edd_get_item_position_in_cart($key));
    }
    $_SESSION['itemIDList'] = $itemIDList;
    $_SESSION['listOPtions'] = $listOPtions;
    $_SESSION['edd_external_receipt'] = true;
		$_SESSION['edd_external_receipt_new'] = true;

  	do_action( 'edd_pre_process_purchase' );

  	// Make sure the cart isn't empty
  	if ( ! edd_get_cart_contents() && ! edd_cart_has_fees() ) {
  		$valid_data = false;
  		edd_set_error( 'empty_cart', __( 'Your cart is empty', 'easy-digital-downloads' ) );
  	} else {
  		// Validate the form $_POST data
  		$valid_data = edd_purchase_form_validate_fields();

  		// Allow themes and plugins to hook to errors
  		do_action( 'edd_checkout_error_checks', $valid_data, $_POST );
  	}

  	$is_ajax = isset( $_POST['edd_ajax'] );

  	// Process the login form
  	if ( isset( $_POST['edd_login_submit'] ) ) {
  		edd_process_purchase_login();
  	}

  	// Validate the user
  	$user = edd_get_purchase_form_user( $valid_data );

  	if ( false === $valid_data || edd_get_errors() || ! $user ) {
  		if ( $is_ajax ) {
  			do_action( 'edd_ajax_checkout_errors' );
  			edd_die();
  		} else {
  			return false;
  		}
  	}

  	if ( $is_ajax ) {
  		echo 'success';
  		edd_die();
  	}

  	// Setup user information
  	$user_info = array(
  		'id'         => $user['user_id'],
  		'email'      => $user['user_email'],
  		'first_name' => $user['user_first'],
  		'last_name'  => $user['user_last'],
  		'discount'   => $valid_data['discount'],
  		'address'    => $user['address']
  	);

  	$auth_key = defined( 'AUTH_KEY' ) ? AUTH_KEY : '';

  	// Setup purchase information
  	$purchase_data = array(
  		'downloads'    => edd_get_cart_contents(),
  		'fees'         => edd_get_cart_fees(),        // Any arbitrary fees that have been added to the cart
  		'subtotal'     => edd_get_cart_subtotal(),    // Amount before taxes and discounts
  		'discount'     => edd_get_cart_discounted_amount(), // Discounted amount
  		'tax'          => edd_get_cart_tax(),               // Taxed amount
  		'price'        => edd_get_cart_total(),    // Amount after taxes
  		'purchase_key' => strtolower( md5( $user['user_email'] . date( 'Y-m-d H:i:s' ) . $auth_key . uniqid( 'edd', true ) ) ),  // Unique key
  		'user_email'   => $user['user_email'],
  		'date'         => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
  		'user_info'    => stripslashes_deep( $user_info ),
  		'post_data'    => $_POST,
  		'cart_details' => edd_get_cart_content_details(),
  		'gateway'      => 'manual',
  		'card_info'    => $valid_data['cc_info']
  	);

  	// Add the user data for hooks
  	$valid_data['user'] = $user;

  	// Allow themes and plugins to hook before the gateway
  	do_action( 'edd_checkout_before_gateway', $_POST, $user_info, $valid_data );

  	// If the total amount in the cart is 0, send to the manual gateway. This emulates a free download purchase
  	if ( !$purchase_data['price'] ) {
  		// Revert to manual
  		$purchase_data['gateway'] = 'manual';
  		$_POST['edd-gateway'] = 'manual';
  	}

  	// Allow the purchase data to be modified before it is sent to the gateway
  	$purchase_data = apply_filters(
  		'edd_purchase_data_before_gateway',
  		$purchase_data,
  		$valid_data
  	);

  	// Setup the data we're storing in the purchase session
  	$session_data = $purchase_data;

  	// Make sure credit card numbers are never stored in sessions
  	unset( $session_data['card_info']['card_number'] );

  	// Used for showing download links to non logged-in users after purchase, and for other plugins needing purchase data.
  	edd_set_purchase_session( $session_data );


  	// Send info to the gateway for payment processing
  	edd_send_to_gateway( $purchase_data['gateway'], $purchase_data );
  	edd_die();

    // End of checkout

  }
  //Recipt Page ----------------------------------------------------------------
  if (is_page($edd_options['success_page']) && $_SESSION['edd_external_receipt'] === true) {

    global $edd_receipt_args, $edd_options, $edd_receipt_args;

    //Add The Non external Products Back to cart
    if(isset($_SESSION['itemIDList'])) {
      foreach ($_SESSION['itemIDList'] as $id) {
        edd_add_to_cart( $id , $_SESSION['listOPtions'][$id]);
      }
    }

    unset($_SESSION['itemIDList']);
    unset($_SESSION['listOPtions']);

		//Unset the flag to unset external flag on next submission
		unset($_SESSION['edd_external_receipt_new']);

		//Process External Products as a manaula purchase
    $session = edd_get_purchase_session();
    $payment_id = edd_get_purchase_id_by_key( $session['purchase_key'] );

    //$payment   = get_post( $edd_receipt_args['id'] );

    $payment = new EDD_Payment( $payment_id );
    $payment->getway = 'external';
    $payment->save();

    $cart = edd_get_payment_meta_cart_details( $payment_id, true );

		function edd_external_receipt_remove_purchase_table() {
			?>
			<style>#edd_purchase_receipt {display: none}</style>
			<?php
		}
		add_action('wp_head', 'edd_external_receipt_remove_purchase_table');
		/*
    function edd_external_receipt_method_label() {
      return "External";
    }
    add_action('edd_gateway_checkout_label', 'edd_external_receipt_method_label');
		*/
    function edd_external_recipt_note() {

      global $edd_options, $edd_receipt_args;
			$contents = edd_get_cart_contents();
			$payment   = get_post( $edd_receipt_args['id'] );
			?>
      <h3>Click On the links below to complete the purchase</h3>
			<strong><h3>Free courses or those provided directly by the Clean Energy Academy</h3></strong>
			<table>
				<thead>
					<tr>
						<td>Name</td>
						<td>Price</td>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($contents as $content): ?>
						<?php $download = edd_get_download($content[id]); ?>
						<tr>
							<td><?= $download->post_title ?></td>
							<td><?= edd_price( $content[id] ) ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
      <strong><a href="<?= get_permalink($edd_options['purchase_page']) ?>" target="_blank">Click here to access the free courses/pay for those provided directly by the Clean Energy Academy</a></string><br /><br />
			<?php
    }
    add_action('edd_payment_receipt_after_table', 'edd_external_recipt_note');

		//Update the Title
		function edd_external_payment_receipt_products_title ($title) {
			return 'Third party paid courses<br /><h6><i>Note that payment for these courses will be made on the third party site(s)</i></h6>';
		}
		add_filter('edd_payment_receipt_products_title', 'edd_external_payment_receipt_products_title');

		//Add URL to external site with the Product
    function edd_external_receipt_no_file_update($constant, $id) {

      $url = edd_external_getURL( $id );

      return "<a href='$url' target='_blank'>Click Here</a>";
    }
    add_filter('edd_receipt_no_files_found_text', 'edd_external_receipt_no_file_update', '', 2);

  }

}
add_action('wp', 'edd_external_product_init');

/**
 * Admin Panal
 * -----------------------------------------------------------------------------
 */


//Add Product Field Selecter

function add_product_type_selector_external( $type ) {
  $type['external'] = 'External';
  return $type;
}

add_action( 'edd_download_types', 'add_product_type_selector_external');

//Saving External Link

function add_field_name_save( $fields ) {
  $fields[] = 'edd_external_url';
  return $fields;
}

add_action('edd_metabox_fields_save', 'add_field_name_save');

//Add Product Field

function add_product_type_external( $post_id ) {

  $type             = edd_get_download_type( $post_id );
  $value            = get_post_meta ( $post_id, 'edd_external_url', true);
  $display          = $type == 'external' ? '' : ' style="display:none;"';

  if($type == 'external')
    echo '<style>#edd_download_files {display: none;}</style>';

  ?>
  <div id="edd_download_external" <?= $display ?>>
    <p>
      <strong>External Downloades:</strong>
    </p>

    <table class="widefat edd_repeatable_table" width="100%" cellpadding="0" cellspacing="0">
      <thead>
        <tr>
          <th><?= 'External Url'  ?></th>
          <?php do_action( 'edd_download_external_table_head', $post_id ); ?>
        </tr>
      </thead>
      <tbody>
        <td>
      		<input type="text" placeholder="URL" name="edd_external_url" style="width: 100%" value="<?= $value ?>"/>
      	</td>
      </tbody>
    </table>
  </div>
  <script>
  jQuery(document).ready(function ($) {

    var edd_download_external = $("#edd_download_external");
    var edd_download_files = jQuery("#edd_download_files");
    $( document.body ).on( 'change', '#_edd_product_type', function() {
      if(this.selectedOptions[0].value == "external") {
        edd_download_external.show();
        edd_download_files.hide();
      } else {
        edd_download_external.hide();
      }
    });

  });
  </script>
  <?php
}

add_action('edd_meta_box_files_fields', 'add_product_type_external', 11);

// Update Email tags

function edd_external_add_email_tag($email_tags) {
	array_splice($email_tags, 1, 0,
		array(array(
			'tag'         => 'externl_download_list',
			'description' => __( 'A list of download links for each registered External Products', 'edd' ),
			'function'    => 'edd_external_email_tag_download_list'
		)
	));
	return $email_tags;
}
add_filter('edd_email_tags' ,'edd_external_add_email_tag');

function edd_external_email_tag_download_list( $payment_id ) {

	$payment_data  = edd_get_payment_meta( $payment_id );
	$cart_items    = edd_get_payment_meta_cart_details( $payment_id );
	$email         = edd_get_payment_user_email( $payment_id );

	$download_list = '<h3>External Prodicts:</h3>'
										. '<strong>Click on the links below to Register for the external products,</strong>'
										. '<table cellspacing="10">'
										. '<thead><tr>'
											. '<td style="border-bottom: 1px solid black;">Product Name & URL</td>'
											. '<td style="border-bottom: 1px solid black;">Price</td>'
										. '</tr></thead>';

	if ( $cart_items ) {

		foreach ( $cart_items as $item ) {

			if(edd_get_download_type( $item['id'] ) === 'external') {
				$download_list .= '<tr>';

				$price_id = edd_get_cart_item_price_id( $item );
				$title = get_the_title( $item['id'] );
				$url = edd_external_getURL( $item['id'] );

				$download_list .= "<td><a href='$url'>$title</a></td>";
				$download_list .= "<td style='text-align: right'>" . $item['price'] . "</td>";

				$download_list .= '</tr>';
				$has_external = true;
			}
		}
	}
	$download_list .= '</table>';

	return isset($has_external) ? $download_list : '';
	//return $payment_id;
}

/**
 * Font View
 * -----------------------------------------------------------------------------
 */

 //Remove Default Purchase section

function add_external_product_remove_default() {

  $hook_name = 'edd_purchase_form_before_submit';

  global $wp_filter, $edd_options;

  $contents = edd_get_cart_contents();

  //Action taken if there exissts any external product
  foreach ($contents as $content) {

    //Bypass standrad purchase
    if(edd_get_download_type( $content['id'] ) === 'external') {
      remove_action( 'edd_purchase_form_after_cc_form', 'edd_checkout_submit', 9999 );
      add_action('edd_purchase_form_after_cc_form', 'add_external_product_view', 9999);
      break;
    }
  }
}
add_action('edd_checkout_form_top', 'add_external_product_remove_default');

//New Perchese Section
function add_external_product_view () {
  ?>

  <fieldset id="edd_purchase_submit">

    <input type="hidden" name="edd_external_product_submit" />

    <?php do_action( 'edd_purchase_form_before_submit' ); ?>

    <?php echo edd_checkout_button_purchase(); ?>

    <?php do_action( 'edd_purchase_form_after_submit' ); ?>

  </fieldset>

  <?php
}

//Customize Tempate
function edd_checkout_final_total_remove() {
	remove_action('edd_purchase_form_before_submit', 'edd_checkout_final_total', 999);
}
add_action( 'edd_purchase_form_before_submit', 'edd_checkout_final_total_remove');

function edd_checkout_final_total_new() {
?>
<p id="edd_final_total_wrap">
	<strong><?php _e( 'Total value of courses:', 'easy-digital-downloads' ); ?></strong>
	<span class="edd_cart_amount" data-subtotal="<?php echo edd_get_cart_subtotal(); ?>" data-total="<?php echo edd_get_cart_subtotal(); ?>"><?php edd_cart_total(); ?></span>
</p>
<?php
}
add_action( 'edd_purchase_form_before_submit', 'edd_checkout_final_total_new', 999 );
