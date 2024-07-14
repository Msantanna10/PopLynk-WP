<?php
/**
 * Add new channel
 */
add_action('rest_api_init', 'youtube_channels_add_api');
function youtube_channels_add_api() {
  register_rest_route('youtube/v1',
      'channels/add',
      array(
          'methods' => 'POST',
          'callback' => 'youtube_channels_add_callback',
      )
  );
}
function youtube_channels_add_callback($request) {

  $data = array();
  $user_token = ($request['token']) ? esc_sql($request['token']) : null;
  $channel_id = ($request['channel_id']) ? esc_sql($request['channel_id']) : null;
  $validation_code = ($request['code_to_remove']) ? esc_sql($request['code_to_remove']) : null;

  // If values are empty
  if(empty($user_token) || empty($channel_id)) {
    return api_error('Todas as informações necessárias estão vazias.');
  }

  // If there's no user with this token
  $user_id = get_user_by_token($user_token);
  if(!$user_id) {
    return api_error('Você está usando uma conta inválida.');
  }

  // If channel already exists
  if(get_wp_channel_id($channel_id)) {
    return api_error('Este canal já está em uso.');
  }

  // Get channel data
  $channel_data = youtube_data_from_youtube_channel_id($channel_id);
  if(!$channel_data) {
    return api_error('Houve um erro para encontrar o canal.');
  }

  $channel_name = $channel_data['title'];
  $channel_etag = $channel_data['etag'];
  $channel_image = $channel_data['image'];
  $channel_description = $channel_data['description'];
  $channel_custom_url = $channel_data['custom_url'];
  $channel_country = $channel_data['country'];
  $channel_published = $channel_data['published'];
  $channel_views = $channel_data['views'];
  $channel_subscribers = $channel_data['subscribers'];
  $channel_videos = $channel_data['videos'];

  if(empty($channel_name)) {
    return api_error('Nome do canal está vazio.');
  }

  $channel_description = str_replace($validation_code, '', $channel_data['description']);
  $channel_wp_id = wp_insert_post(
    array(
      'post_title' => $channel_name,
      'post_type' => 'youtube_channels', 
      'post_status' => 'publish', 
      'post_author' => $user_id,
      'meta_input' => array(
          'youtube_channel_id' => $channel_id,
          'youtube_channel_etag' => $channel_etag,
          'youtube_channel_image' => $channel_image,
          'youtube_channel_description' => standard_line_breaks($channel_description),
          'youtube_channel_custom_url' => $channel_custom_url,
          'youtube_channel_country' => $channel_country,
          'youtube_channel_published' => $channel_published,
          'youtube_channel_views' => $channel_views,
          'youtube_channel_subscribers' => $channel_subscribers,
          'youtube_channel_videos' => $channel_videos
      ),
    )
  );

  // Success
  $data['validation']['status'] = true;
  $response = new WP_REST_Response($data);
  $response->set_status(200);
  return $response;

}