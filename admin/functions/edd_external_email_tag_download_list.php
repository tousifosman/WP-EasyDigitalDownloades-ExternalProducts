<?php

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
