<?php
/**
 * Grab channel details from WP
 */
add_action('rest_api_init', 'youtube_channel_data_api');
function youtube_channel_data_api() {
    register_rest_route('youtube/v1',
        'channels/data',
        array(
            'methods' => 'GET',
            'callback' => 'youtube_channel_data_callback',
        )
    );
}
function youtube_channel_data_callback($request) {

    $data = array();
    $channel_id = ($request['channel_id']) ? esc_sql($request['channel_id']) : false;

    // If there's no channel ID
    if(!$channel_id) {
        return api_error('Forneça um canal válido.');
    }

    // If channel doesn't exist
    $wp_channel_id = get_wp_channel_id($channel_id);
    if(!$wp_channel_id) {
        return api_error('Este canal não existe. Entre em contato através do link "Contato" no menu.');
    }

    // Get channel data
    $channel_data = youtube_channel_data_from_wordpress_id($wp_channel_id);

    // Success
    $data['validation']['status'] = true;
    $data['data'] = $channel_data;
    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;
    
}