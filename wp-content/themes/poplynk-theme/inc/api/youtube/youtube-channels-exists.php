<?php
/**
 * Add new channel
 */
add_action('rest_api_init', 'youtube_channel_exists_api');
function youtube_channel_exists_api() {
  register_rest_route('youtube/v1',
      'channels/exists',
      array(
          'methods' => 'GET',
          'callback' => 'youtube_channel_exists_callback',
      )
  );
}
function youtube_channel_exists_callback($request) {

  $data = array();
  $channel_id = ($request['channel_id']) ? esc_sql($request['channel_id']) : false;

  // If there's no channel ID
  if(!$channel_id) {
    return api_error('Forneça um canal válido.');
  }

  // If channel already exists
  $channel_exists = get_wp_channel_id($channel_id);
  if($channel_exists) {
    return api_error('Este canal já está em uso.');
  }

  // Success
  $data['validation']['status'] = true;
  $response = new WP_REST_Response($data);
  $response->set_status(200);
  return $response;

}