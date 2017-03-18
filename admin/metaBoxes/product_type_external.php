<?php


//Add External Product Field Selecter

function add_product_type_selector_external( $type ) {
  $type['external'] = 'External';
  return $type;
}

add_action( 'edd_download_types', 'add_product_type_selector_external');

//Add External Product URL Field

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

//Saving External Link

function add_field_name_save( $fields ) {
  $fields[] = 'edd_external_url';
  return $fields;
}

add_action('edd_metabox_fields_save', 'add_field_name_save');
