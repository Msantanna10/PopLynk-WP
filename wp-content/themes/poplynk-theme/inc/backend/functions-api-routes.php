<?php
/**
 * Block all possible endpoints to allow specific ones
 */
add_filter('rest_pre_dispatch', 'block_unauthorized_endpoints', 10, 3);
function block_unauthorized_endpoints($result, $wp_rest_server, $request) {
    if ( ! is_user_logged_in() ) {
        $allowed_endpoint = array(            
            '/auth/v1/login',
            '/registration/v1/email',
            '/youtube/v1/channels/add',
            '/youtube/v1/channels/list',
            '/youtube/v1/channels/data',
            '/youtube/v1/channels/exists',
            '/youtube/v1/channels/subscribers/goal',
            '/youtube/v1/videos/update',
            '/youtube/v1/videos/list',
            '/youtube/v1/videos/data',
            '/auth/v1/login',
            '/auth/v1/register',
            '/auth/v1/validate',
            '/auth/v1/forgotpassword',
            '/contact-form-7/v1/contact-forms/331/feedback',
        );
        if (!in_array($request->get_route(), $allowed_endpoint)) {
            return new WP_Error(
                'rest_forbidden',
                esc_html__('You do not have permission to access this endpoint.'),
                array('status' => 403)
            );
        }
        return $result;
    }
}