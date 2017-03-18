<?php
/*
Plugin Name: Easy Digital Downloads - External Products Register
Plugin URI:
Description: Automatically creates a WP user account and redirects the users to external location.
Version: 1.5
Author: Tousif Osman
Author URI: mailto:tousifosman@gmail.com
@author Tousif Osman <tousifosmans@gmail.com>
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Include library files
 */

foreach (glob( dirname(__FILE__) . '/functions/*.php' ) as $filename) {
  include_once $filename;
}

foreach (glob( dirname(__FILE__) . '/actions/*.php' ) as $filename) {
  include_once $filename;
}

foreach (glob( dirname(__FILE__) . '/views/*.php' ) as $filename) {
  include_once $filename;
}

/**
 * Admin Includes
 */

foreach (glob( dirname(__FILE__) . '/admin/*.php' ) as $filename) {
  include_once $filename;
}

foreach (glob( dirname(__FILE__) . '/admin/*/*.php' ) as $filename) {
  include_once $filename;
}


//Rmove Session Flag so that the recipt page doesnt alters incase of non-external product
add_action('edd_pre_process_purchase', function() {
		unset($_SESSION['edd_external_receipt']);
});


function edd_external_product_init() {

  // Process Checkout-----------------------
  if(isset($_POST['edd_external_product_submit']) && isset($_GET['payment-mode'])) {

    /**
     * From here just like edd_process_purchase_form function in process-purchase.php
     * However here the idea is when there will be any external products in the cart
     * the checkout process will be interrupted then the External product will
     * be separated, handaled manually and then regular checkpot process will be
     * performed.
     */
  	do_action( 'edd_pre_process_purchase' );

    $_SESSION['edd_external_receipt'] = true;

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

    //Starts Processing Checkout

    //Process External product manually
    global $edd_options;

    $contents = edd_get_cart_contents();
    $internalItemOptions = array();
    $externalItems = array();

    //Remove Non external products from the cart
    foreach ($contents as $item) {
      if(edd_get_download_type( $item['id'] ) !== 'external') {
        $internalItemOptions[ $item[id] ] = $item['options'];
        $internalItemOptions[ $item[id] ]['quantity'] = $item['quantity'];

        edd_remove_from_cart(edd_get_item_position_in_cart( $item[id] ));
      } else {
        $externalItems[$item[id]] = $item;
      }
    }

    $_SESSION['externalItems'] = $externalItems;

    /**
    * Manual checkout external products in the cart.
    * like the manual.php
    */

    $payment_data = array(
  		'price' 		=> edd_get_cart_total(),
  		'date' 			=> date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
  		'user_email' 	=> $user['user_email'],
  		'purchase_key' 	=> strtolower( md5( $user['user_email'] . date( 'Y-m-d H:i:s' ) . $auth_key . uniqid( 'edd', true ) ) ),
  		'currency' 		=> edd_get_currency(),
  		'downloads' 	=> edd_get_cart_contents(),
  		'user_info' 	=> stripslashes_deep( $user_info ),
  		'cart_details' 	=> edd_get_cart_content_details(),
      'gateway'   => 'external',
  		'status' 		=> 'pending'
  	);

    // Record the pending payment
  	$payment = edd_insert_payment( $payment_data );

  	if ( $payment ) {
  		edd_update_payment_status( $payment, 'publish' );
  		// Empty the shopping cart
  		edd_empty_cart();
  		//edd_send_to_success_page();
  	} else {
  		edd_record_gateway_error( __( 'Payment Error', 'easy-digital-downloads' ), sprintf( __( 'Payment creation failed while processing a manual (free or test) purchase. Payment data: %s', 'easy-digital-downloads' ), json_encode( $payment_data ) ), $payment );
  		// If errors are present, send the user back to the purchase page so they can be corrected
  		edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
  	}

    //Process non-external products

    //Add The Non external Products Back to cart
    foreach ($internalItemOptions as $id => $option) {
        edd_add_to_cart( $id , $option);
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

    $card_country = isset( $valid_data['cc_info']['card_country'] ) ? $valid_data['cc_info']['card_country'] : false;
  	$card_state   = isset( $valid_data['cc_info']['card_state'] )   ? $valid_data['cc_info']['card_state']   : false;
  	$card_zip     = isset( $valid_data['cc_info']['card_zip'] )     ? $valid_data['cc_info']['card_zip']     : false;

  	// Set up the unique purchase key. If we are resuming a payment, we'll overwrite this with the existing key.
  	$purchase_key     = strtolower( md5( $user['user_email'] . date( 'Y-m-d H:i:s' ) . $auth_key . uniqid( 'edd', true ) ) );
  	$existing_payment = EDD()->session->get( 'edd_resume_payment' );

  	if ( ! empty( $existing_payment ) ) {
  		$payment = new EDD_Payment( $existing_payment );
  		if( $payment->is_recoverable() && ! empty( $payment->key ) ) {
  			$purchase_key = $payment->key;
  		}
  	}

  	// Setup purchase information
  	$purchase_data = array(
  		'downloads'    => edd_get_cart_contents(),
  		'fees'         => edd_get_cart_fees(),        // Any arbitrary fees that have been added to the cart
  		'subtotal'     => edd_get_cart_subtotal(),    // Amount before taxes and discounts
  		'discount'     => edd_get_cart_discounted_amount(), // Discounted amount
  		'tax'          => edd_get_cart_tax(),               // Taxed amount
  		'price'        => edd_get_cart_total(),    // Amount after taxes
  		'purchase_key' => $purchase_key,  // Unique key
  		'user_email'   => $user['user_email'],
  		'date'         => date( 'Y-m-d H:i:s', current_time( 'timestamp' ) ),
  		'user_info'    => stripslashes_deep( $user_info ),
  		'post_data'    => $_POST,
  		'cart_details' => edd_get_cart_content_details(),
  		'gateway'      => $valid_data['gateway'],
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

		//Unset the flag to unset external flag on next submission
		//unset($_SESSION['edd_external_receipt_new']);

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
			$contents = $_SESSION['externalItems'];
			$payment   = get_post( $edd_receipt_args['id'] );
			?>
      <h3>Click the links on the third to complete the purchase</h3>
			<strong><h3>Third party paid courses</h3></strong>
      <h6><i>Note that payment for these courses will be made on the third party site(s)</i></h6>
			<table>
				<thead>
					<tr>
						<th>Name</th>
						<th>Price</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($contents as $content): ?>
						<?php $download = edd_get_download($content[id]); ?>
						<tr>
							<td>
                <div><b><?= $download->post_title ?></b></div>
                <i>First <a href="<?= edd_external_getURL( $content[id] ) ?>">click on this link</a> to enable you to check out on the third party site</i>
              </td>
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
			return 'Free courses or those provided directly by the Clean Energy Academy';
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
 * Font View
 * -----------------------------------------------------------------------------
 */

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
