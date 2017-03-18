<?php

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
