<?php
/* Silence is golden */

function is_goal_successful($post_id) {

    // If campaign has ended
    $close_date = get_field('youtube_video_close_date', $post_id);

    // Update Youtube video details on WP if campaign hasn't ended
    $video_id = get_field('youtube_video_id', $post_id);
    if(empty($close_date)) {
        youtube_data_from_video_id($video_id, $post_id);
    }

    // Get the field values    
    $goals = get_field('youtube_video_goals', $post_id);
    $youtube_views = get_field('youtube_video_views', $post_id);
    $youtube_likes = get_field('youtube_video_likes', $post_id);
    $youtube_comments = get_field('youtube_video_comments', $post_id);

    // Initialize an array to hold the successful goal indexes
    $successful_goals = array();

    // Check each goal
    foreach ($goals as $index => $goal) {
        // Assume the goal is successful until proven otherwise
        $is_successful = true;

        // Check views
        if (!empty($goal['views']) && $goal['views'] > $youtube_views) {
            $is_successful = false;
        }

        // Check likes
        if (!empty($goal['likes']) && $goal['likes'] > $youtube_likes) {
            $is_successful = false;
        }

        // Check comments
        if (!empty($goal['comments']) && $goal['comments'] > $youtube_comments) {
            $is_successful = false;
        }

        // Check expiration date
        $timezone = new DateTimeZone(get_option('timezone_string'));
        $today = new DateTime($close_date ? $close_date : 'now', $timezone);
        $dateToCompare = new DateTime($goal['expiration'], $timezone);
        $has_expired = ($dateToCompare < $today);
        if (!empty($goal['expiration']) && $has_expired) {
            $is_successful = false;
        }

        // If the goal is still considered successful, add the index to the array
        if ($is_successful) {
            $successful_goals[] = $index;
        }
    }

    // It's reached the goal, close campaign
    if(empty($close_date) && $successful_goals) {        
        $today = current_time('Y-m-d H:i:s');
        update_field('youtube_video_close_date', $today, $post_id);
        update_field('youtube_video_progress', 'successful', $post_id);
        app_revalidate("campaign_youtube_$video_id");
    }

    // Return the array of successful goal indexes or false if none are successful
    return !empty($successful_goals) ? $successful_goals : false;
}

// Example usage
/*$post_id = 289;
$successful_goal_indexes = is_goal_successful($post_id);
if ($successful_goal_indexes !== false) {
    echo "Successful goals found at indexes: " . implode(", ", $successful_goal_indexes);
} else {
    echo "No successful goals found.";
}*/

$channel_subscriber_goals = get_field('youtube_channel_subscriber_goals', 76);
$has_progress = array_reduce($channel_subscriber_goals, function($carry, $item) {
    return $carry || ($item['progress'] === 'progress');
  }, false);

  if($has_progress) {
    echo "Há metas em progresso. Aguarde até que sejam concluídas.";
  } else {
    echo "Não há metas em progresso.";
  }