<?php
/**
 * Provides a list of all channels added by the user
 */
add_action('rest_api_init', 'youtube_my_channels_api');
function youtube_my_channels_api() {
  register_rest_route('youtube/v1',
      'channels/list',
      array(
          'methods' => 'GET',
          'callback' => 'youtube_my_channels_callback',
      )
  );
}
function youtube_my_channels_callback($request) {

  $data = array();
  $token = ($request['token']) ? esc_sql($request['token']) : null;

  // If there's no user with this token
  $user_id = get_user_by_token($token);
  if(!$user_id) {
    return api_error('Você está usando uma conta inválida');
  }

  // Grab all channels
  $args = array(
    'post_type'      => 'youtube_channels',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'author'         => $user_id,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids'
  );

  $query = new WP_Query($args);
  $channels = array();

  if ($query->have_posts()) {
    $post_ids = $query->posts;
    $count = 0;
    foreach ($post_ids as $post_id) {
      $channels[$count] = array(
        'id' => get_field('youtube_channel_id', $post_id),
        'title' => get_the_title($post_id),
        'image' => get_field('youtube_channel_image', $post_id),
        'description' => get_field('youtube_channel_description', $post_id),
        'subscribers' => get_field('youtube_channel_subscribers', $post_id),
        'views' => get_field('youtube_channel_views', $post_id),
        'videos' => get_field('youtube_channel_videos', $post_id)        
      );
      $count++;
    }
  }

  // Success
  $data['validation']['status'] = true;
  $data['channels'] = $channels;
  $response = new WP_REST_Response($data);
  $response->set_status(200);
  return $response;

}