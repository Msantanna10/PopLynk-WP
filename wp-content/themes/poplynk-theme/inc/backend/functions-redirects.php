<?php
/**
 * Redirect URLs
 */
add_action('template_redirect', function() {
    if(is_404() || is_author() || is_category() || is_tax() || is_attachment() || is_author()) {
      wp_redirect( home_url(), 301 );
      exit;
    }
});