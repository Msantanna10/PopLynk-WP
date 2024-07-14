<?php
/**
 * Provides a list of all videos added by the user
 */
add_action('rest_api_init', 'youtube_my_videos_api');
function youtube_my_videos_api() {
  register_rest_route('youtube/v1',
      'videos/list',
      array(
          'methods' => 'GET',
          'callback' => 'youtube_my_videos_callback',
      )
  );
}
function youtube_my_videos_callback($request) {

  $data = array();
  $token = ($request['token']) ? esc_sql($request['token']) : null;
  $channel_youtube_id = ($request['channel_id']) ? esc_sql($request['channel_id']) : false;

  // If there's no user with this token
  $user_id = get_user_by_token($token);
  if(!$user_id) {
    return api_error('Você está usando uma conta inválida.');
  }

  // If there's no channel with this Youtube ID
  $channel_wp_id = get_wp_channel_id($channel_youtube_id);
  if(!$channel_wp_id) {
    return api_error('Este canal não foi encontrado.');
  }

  // Grab all videos
  $args = array(
    'post_type'      => 'youtube_videos',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'author'         => $user_id,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids',
    'meta_query'     => array(
      array(
          'key'     => 'youtube_video_channel',
          'value'   => $channel_wp_id,
          'compare' => '='
      )
    )
  );

  $query = new WP_Query($args);
  $videos = array();

  if ($query->have_posts()) {
    $post_ids = $query->posts;
    $count = 0;
    foreach ($post_ids as $post_id) {
      $video_title = get_the_title($post_id);
      $video_title = str_replace('&#8211;', '-', $video_title);
      $videos[$count] = array(
        'id' => get_field('youtube_video_id', $post_id),
        'title' => $video_title,        
        'campaign_name' => get_field('youtube_video_campaign_name', $post_id),
        'views' => (int) get_field('youtube_video_views', $post_id),
        'likes' => (int) get_field('youtube_video_likes', $post_id),
        'comments' => (int) get_field('youtube_video_comments', $post_id),
        'campaign_status' => get_field('youtube_video_progress', $post_id),
        'description' => get_field('youtube_video_description', $post_id),
      );
      $count++;
    }
  }

  // Success
  $data['validation']['status'] = true;
  $data['videos'] = $videos;
  $response = new WP_REST_Response($data);
  $response->set_status(200);
  return $response;

}