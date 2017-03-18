<?php

/**
 * Admin Panal
 * -----------------------------------------------------------------------------
 */


/**
 * Update Email tags
 * Adds external product list in the email
 */

add_filter('edd_email_tags' ,'edd_external_add_email_tag');
