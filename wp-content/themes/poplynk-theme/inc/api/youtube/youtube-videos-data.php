<?php
/**
 * Grab data for videos/campaigns
 */
add_action('rest_api_init', 'youtube_my_video_data_api');
function youtube_my_video_data_api() {
  register_rest_route('youtube/v1',
      'videos/data',
      array(
          'methods' => 'GET',
          'callback' => 'youtube_my_video_data_callback',
      )
  );
}
function youtube_my_video_data_callback($request) {

  $data = array();
  $token = ($request['token']) ? esc_sql($request['token']) : null;
  $channel_youtube_id = ($request['channel_id']) ? esc_sql($request['channel_id']) : false;
  $video_youtube_id = ($request['video_id']) ? esc_sql($request['video_id']) : false;

  // If there's no user with this token only if $token exists
  $user_id = false;
  if($token) {
    $user_id = get_user_by_token($token);
    if(!$user_id) {
      return api_error('Você está usando uma conta inválida.');
    }
  }

  $video_data = wp_video_data($video_youtube_id, $channel_youtube_id, $user_id);
  $response = new WP_REST_Response($video_data);
  $response->set_status(200);
  return $response;

}