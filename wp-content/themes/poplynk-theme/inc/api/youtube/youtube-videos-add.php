<?php
/**
 * Add or update video campaign
 */
add_action('rest_api_init', 'youtube_videos_add_update_api');
function youtube_videos_add_update_api() {
    register_rest_route('youtube/v1', 'videos/update', array(
        'methods' => 'POST',
        'callback' => 'youtube_videos_add_update_callback',
    ));
}

function youtube_videos_add_update_callback($request) {
    $data = array();
    $user_token = esc_sql($request['token'] ?? null);
    $channel_youtube_id = esc_sql($request['channel_id'] ?? false);
    $video_title = esc_sql($request['video_title'] ?? null);
    $video_youtube_id = esc_sql($request['video_id'] ?? null);
    $video_views = esc_sql($request['video_views'] ?? 0);
    $video_likes = esc_sql($request['video_likes'] ?? 0);
    $video_comments = esc_sql($request['video_comments'] ?? 0);
    $video_description = sanitize_textarea_field(str_replace('\\n', "\n", $request['video_description'] ?? ''));
    $campaign_name = esc_sql($request['campaign_name'] ?? null);
    $video_goals = json_decode(stripslashes($request['goals'] ?? ''), true) ?? null;
    $reward_description = sanitize_textarea_field(str_replace('\\n', "\n", $request['reward_description'] ?? ''));    
    $video_wp_id = esc_sql($request['update_post_id'] ?? false);
    $reward_file = !empty($_FILES['reward_file']['name']) ? $_FILES['reward_file'] : null;

    // If it's an invalid user
    $user_id = get_user_by_token($user_token);
    if (!$user_id) {
        return api_error('Você está usando uma conta inválida.');
    }

    // If user can't edit the existing post ID
    if ($video_wp_id && !is_user_post_author($video_wp_id, $user_id)) {
        return api_error('Você não tem permissões para editar este vídeo.');
    }

    // If channel doesn't exist in WP
    $channel_wp_id = get_wp_channel_id($channel_youtube_id);
    if (!$channel_wp_id) {
        return api_error('Este canal não foi encontrado.');
    }

    // Validation if it's an existing post id
    if (!$video_wp_id) {
        if (!is_array($video_goals) || empty($video_goals)) {
            return api_error('Houve um erro com seus objetivos! Atualize a página e tente novamente.');
        }
    }

    // Upload file and return its ID
    $upload_document = null;
    if ($reward_file) {
        $upload_document = wp_upload_file($_FILES['reward_file'], 'document', array('pdf', 'xls', 'xlsx', 'zip'), $user_id);
        if (!$upload_document) {
            return api_error('Houve um erro ao enviar seu arquivo! Verifique os tipos permitidos e tente novamente.');
        }
        $reward_file = $upload_document['file_id'];
    }
    // No file name or file has been removed
    else if (isset($_POST['reward_file']) && $_POST['reward_file'] === '') {
        $reward_file = '';
    }

    // Array of field values
    $meta_input = array();
    if ($campaign_name) {
        $meta_input['youtube_video_campaign_name'] = to_title_case($campaign_name);
    }
    if ($video_views) {
        $meta_input['youtube_video_views'] = $video_views;
    }
    if ($video_likes) {
        $meta_input['youtube_video_likes'] = $video_likes;
    }
    if ($video_comments) {
        $meta_input['youtube_video_comments'] = $video_comments;
    }
    if ($video_description) {
        $meta_input['youtube_video_description'] = text_line_breaks($video_description);
    }
    if ($video_youtube_id) {
        $meta_input['youtube_video_id'] = $video_youtube_id;
    }
    if ($channel_wp_id) {
        $meta_input['youtube_video_channel'] = $channel_wp_id;
    }
    if ($reward_description) {
        $meta_input['youtube_video_campaign_description'] = text_line_breaks($reward_description);
    }
    if (!$video_wp_id) {
        $meta_input['youtube_video_status'] = 'progress';
    }
    if ($reward_file !== null) {
        $meta_input['youtube_video_reward_file'] = $reward_file;
    }

    // Update existing post
    if ($video_wp_id) {
        wp_update_post(array(
            'ID' => $video_wp_id,
            'meta_input' => $meta_input,
        ));
    } 
    // Insert new campaign
    else {
        $video_wp_id = wp_insert_post(array(
            'post_title' => ucfirst($video_title),
            'post_type' => 'youtube_videos',
            'post_status' => 'publish',
            'post_author' => $user_id,
            'meta_input' => $meta_input,
        ));
    }

    // Update goals
    if ($video_wp_id && is_array($video_goals) && !empty($video_goals)) {
        delete_post_meta($video_wp_id, 'youtube_video_goals');
        foreach ($video_goals as $goal) {
            $expiration_date = $goal['expiration']['value'] ?? '';
            // Validate date format YYYY-MM-DD
            if (!empty($expiration_date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiration_date)) {
                $date = new DateTime($expiration_date);
                $date->setTime(23, 59, 0); // Set time to 23:59:00
                $expiration_date = $date->format('Y-m-d H:i:s');
            } else {
                $expiration_date = '';
            }
            $goal_row = array(
                'views' => sanitize_text_field($goal['views']['value'] ?? ''),
                'likes' => sanitize_text_field($goal['likes']['value'] ?? ''),
                'comments' => sanitize_text_field($goal['comments']['value'] ?? ''),
                'expiration' => $expiration_date,
            );
            add_row('youtube_video_goals', $goal_row, $video_wp_id);
        }        
        
    }

    // Success
    $data['validation']['status'] = true;
    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;
}
