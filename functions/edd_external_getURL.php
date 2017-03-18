<?php

function edd_external_getURL($id) {
  return get_post_meta( $id, 'edd_external_url', true );
}
