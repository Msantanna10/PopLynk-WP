<?php
/**
 * GET or POST requests to get or add subscriber goals
 */
add_action('rest_api_init', 'youtube_channel_subscribers_api');
function youtube_channel_subscribers_api() {
  register_rest_route('youtube/v1',
      'channels/subscribers/goal',
      array(
          'methods' => array('GET', 'POST'),
          'callback' => 'youtube_channel_subscribers_callback',
      )
  );
}
function youtube_channel_subscribers_callback($request) {

  $data = array();
  $method = $request->get_method();
  $channel_id = ($request['channel_id']) ? esc_sql($request['channel_id']) : false;
  $user_token = ($request['token']) ? esc_sql($request['token']) : null;

  // Invalid request method
  if (!in_array($method, ['GET', 'POST'])) {
    return api_error('Esta requisição não é válida.');
  }

  // Invalid required data
  if (!$channel_id || !$user_token) {
    return api_error('Dados inválidos.');
  }

  // If it's an invalid user
  $user_id = get_user_by_token($user_token);
  if (!$user_id) {
      return api_error('Você está usando uma conta inválida.');
  }

  // If channel doesn't exist
  $wp_channel_id = get_wp_channel_id($channel_id);
  if(!$wp_channel_id) {
      return api_error('Este canal não existe. Entre em contato através do link "Contato" no menu.');
  }

  // If user can't edit the existing post ID
  if (!is_user_post_author($wp_channel_id, $user_id)) {
    return api_error('Você não tem permissões para editar este canal.');
  }

  // Check if it has a goal in progress
  $channel_subscriber_goals = get_field('youtube_channel_subscriber_goals', $wp_channel_id);
  $has_progress = is_array($channel_subscriber_goals) && !empty($channel_subscriber_goals)
    ? array_reduce($channel_subscriber_goals, function($carry, $item) {
        return $carry || ($item['status'] === 'progress');
    }, false)
    : false;

  // GET request: let's get existing subscriber goals
  if($method == 'GET') {
    $data['validation']['status'] = true;
    $data['data'] = youtube_channel_data_from_wordpress_id($wp_channel_id);
    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;
  }

  // POST request: let's add a new subscriber goal
  if($method == 'POST') {
    $channel_next_goal = ($request['next_goal']) ? esc_sql($request['next_goal']) : '';
    $channel_reward_description = sanitize_textarea_field(str_replace('\\n', "\n", $request['reward_description'] ?? ''));  
    $channel_reward_file = !empty($_FILES['reward_file']['name']) ? $_FILES['reward_file'] : null;
    $channel_reward_expiration = ($request['reward_expiration']) ? esc_sql($request['reward_expiration']) : '';
    // Validate date format YYYY-MM-DD
    if (!empty($channel_reward_expiration) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $channel_reward_expiration)) {
      $exp_date = new DateTime($channel_reward_expiration);
      $exp_date->setTime(23, 59, 0); // Set time to 23:59:00
      $channel_reward_expiration = $exp_date->format('Y-m-d H:i:s');
    } else {
      $channel_reward_expiration = '';
    }

    // Upload file and return its ID
    $upload_document = null;
    if ($channel_reward_file) {
        $upload_document = wp_upload_file($_FILES['reward_file'], 'document', array('pdf', 'xls', 'xlsx', 'zip'), $user_id);
        if (!$upload_document) {
            return api_error('Houve um erro ao enviar seu arquivo! Verifique os tipos permitidos e tente novamente.');
        }
        $upload_document = $upload_document['file_id'];
    }

    /**
     * If the channel has a goal in progress, update it
     */
    if($has_progress) {
      // Initialize a variable to hold the index of the last "progress" item
      $last_progress_index = -1;

      // Loop through the array to find the last "progress" item
      foreach ($channel_subscriber_goals as $index => $goal) {
        if ($goal['status'] === 'progress') {
          $last_progress_index = $index;
        }
      }

      // If a "progress" item was found, update its fields
      if ($last_progress_index !== -1) {
        if($channel_reward_description) {
          $channel_subscriber_goals[$last_progress_index]['description'] = text_line_breaks($channel_reward_description);
        }
        $channel_subscriber_goals[$last_progress_index]['file'] = $upload_document;
        $channel_subscriber_goals[$last_progress_index]['expiration'] = $channel_reward_expiration;
        // Update the field in the database
        update_field('youtube_channel_subscriber_goals', $channel_subscriber_goals, $wp_channel_id);
        // Success
        $data['validation']['status'] = true;
        $response = new WP_REST_Response($data);
        $response->set_status(200);
        return $response;
      }
    }
    else {
      $current_subscribers = get_field('youtube_channel_subscribers', $wp_channel_id);
      $next_possible_goals = SUBSCRIBER_GOALS;
      $next_goal = '';
      foreach ($next_possible_goals as $goal) {
          if ($goal > $current_subscribers) {
              $next_goal = $goal;
              break;
          }
      }
      $channel_subscriber_goals = array(
        'status' => 'progress',
        'subscribers' => $next_goal,
        'description' => text_line_breaks($channel_reward_description),
        'expiration' => $upload_document,
        'file' => $channel_reward_description
      );
      // Add new row
      add_row('youtube_channel_subscriber_goals', $channel_subscriber_goals, $wp_channel_id);
      // Success
      $data['validation']['status'] = true;
      $response = new WP_REST_Response($data);
      $response->set_status(200);
      return $response;
    }

  }

  // No conditions were met
  return api_error('Houve um erro. Entre em contato através do link "Contato" no menu.');

}