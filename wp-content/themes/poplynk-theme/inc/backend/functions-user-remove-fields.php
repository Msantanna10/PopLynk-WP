<?php
/**
 * Remove fields on the user edit page
 */
add_action('admin_head', 'admin_color_scheme');
function admin_color_scheme() {
    global $_wp_admin_css_colors;
    $_wp_admin_css_colors = array();
}

/**
 * Remove Application Passwords
 */
add_filter( 'wp_is_application_passwords_available', '__return_false' );

/**
 * Custom CSS to hidden fields
 */
add_action('admin_head', function() { ?>
<style>
    table .user-rich-editing-wrap {display: none;}
    table .user-syntax-highlighting-wrap {display: none;}
    table .user-comment-shortcuts-wrap {display: none;}
    table .user-admin-bar-front-wrap {display: none;}
    table .user-language-wrap {display: none;}
    table .user-last-name-wrap {display: none;}
    table .user-nickname-wrap {display: none;}
    table .user-display-name-wrap {display: none;}
    table .user-url-wrap {display: none;}
    table .user-description-wrap {display: none;}
    table .user-profile-picture {display: none;}
    table .user-generate-reset-link-wrap {display: none;}
    table .user-sessions-wrap {display: none;}
</style>
<?php });

/**
 * Remove "additional capabilities" added by plguin from user edit page
 */
add_filter('ure_show_additional_capabilities_section', 'ure_show_additional_capabilities_section');
function ure_show_additional_capabilities_section( $show ) {
	return false;
}

/**
 * Disable bulk grant roles on user listing
 */
add_filter('ure_bulk_grant_roles', 'ure_bulk_grant_roles');
function ure_bulk_grant_roles( $show ) {
	return false;
}