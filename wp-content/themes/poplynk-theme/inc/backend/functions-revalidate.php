<?php

/**
 * On "Channel" or "Videos" update
 */
add_action('save_post', function($post_ID) {

    $post = get_post($post_ID);
    $author_id = $post->post_author;
    $token = get_field('user_token', "user_$author_id");
    $channel_wp_id = get_field('youtube_video_channel', $post_ID);
    $channel_youtube_id = get_field('youtube_channel_id', $channel_wp_id);
    if ($post->post_type == 'youtube_channels') {
        // Update user's channels list
        app_revalidate("channel_videos_$channel_youtube_id");
    }

    if ($post->post_type == 'youtube_videos') {
        $video_id = get_field('youtube_video_id', $post_ID);                
        // Update user's channels list
        app_revalidate("channel_videos_$channel_youtube_id");
        // Update rewards page
        app_revalidate("channel_rewards_$video_id");
    }

});


/**
 * On "Channel" creation
 */
add_action('save_post_youtube_channels', function($post_id, $post, $update) {
    // Check if this is a new post and not an update
    if (!$update) {
        $author_id = $post->post_author;
        $token = get_field('user_token', "user_$author_id");
        app_revalidate("channels_$token");
    }
}, 10, 3);

/**
 * On "Channel" permanent deletion or moving to trash
 */
add_action('before_delete_post', 'revalidate_channels_upon_deletion');
add_action('wp_trash_post', 'revalidate_channels_upon_deletion');
function revalidate_channels_upon_deletion($post_id) {
    if (get_post_type($post_id) === 'youtube_channels') {
        $author_id = get_post_field('post_author', $post_id);
        $token = get_field('user_token', "user_$author_id");
        // Update user's channels list
        app_revalidate("channels_$token");        
    }
}

/**
 * Revalidate App
 */
function app_revalidate($tag) {
    $values = array(
        'tag' => $tag
    );
    $request_args = array(
        'body' => json_encode($values),
        'headers' => array('Content-Type' => 'application/json'),
        'timeout' => 15,
    );
    $url = (is_localhost()) ? 'http://localhost:3000/api/revalidate' : WEB_APP_URL."/api/revalidate";
    wp_remote_post($url, $request_args);
}