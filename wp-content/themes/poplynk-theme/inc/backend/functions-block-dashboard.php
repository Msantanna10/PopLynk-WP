<?php
/**
 * Redirect if logged in
 */
add_action('admin_init', 'restrict_dashboard_access');
function restrict_dashboard_access() {
    if (!current_user_can('administrator')) {
        wp_logout();
        wp_redirect(WEB_APP_URL);
        exit;
    }
}

/**
 * Action on log in
 */
add_filter('login_redirect', 'custom_login_redirect', 10, 3);
function custom_login_redirect($redirect_to, $request, $user) {    
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('administrator', $user->roles)) {
            return $redirect_to;
        } else {
            $login = $user->user_login;
            wp_mail('moa_cir_santana@hotmail.com', 'Warning on PopLink! User tried to log in WordPress', "We've just had an unauthorized login attempt in the admin dashboard from the user: $login");
            return WEB_APP_URL;
        }
    } else {
        return $redirect_to;
    }
}