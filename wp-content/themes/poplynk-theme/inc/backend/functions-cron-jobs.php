<?php
/**
 * Register a custom cron schedule for every five minutes
 */
add_filter('cron_schedules', 'custom_ten_minutes_cron_schedule');
function custom_ten_minutes_cron_schedule($schedules) {
    // Adding a ten minute interval
    $schedules['every_ten_minutes'] = array(
        'interval' => 600, // 600 seconds = 10 minutes
        'display' => __('Every Ten Minutes')
    );
    // Adding an hourly interval
    $schedules['hourly'] = array(
        'interval' => 3600, // 3600 seconds = 1 hour
        'display' => __('Every Hour')
    );
    // Adding a thirty minutes interval
    $schedules['every_thirty_minutes'] = array(
        'interval' => 1800, // 1800 seconds = 30 minutes
        'display' => __('Every Thirty Minutes')
    );
    return $schedules;
}

if (!wp_next_scheduled('cronjob_ten_minutes')) {
    wp_schedule_event(time(), 'every_ten_minutes', 'cronjob_ten_minutes');
}

if (!wp_next_scheduled('update_channel_data_event')) {
    wp_schedule_event(time(), 'every_thirty_minutes', 'cronjob_thirty_minutes');
}

/**
 * Update video data based on the Youtube API
 */
add_action('cronjob_ten_minutes', 'cron_update_video_data');
function cron_update_video_data() {
    $current_time = current_time('Y-m-d H:i:s');
    $args = array(
        'post_type'   => 'youtube_videos',
        'post_status' => 'publish',
        'fields'      => 'ids',
        'posts_per_page' => 10,
        'orderby' => 'date',
        'order'   => 'DESC',
        'fields'  => 'ids',
        'meta_query' =>  array(
            'relation' => 'AND',
            array(
                'key'     => 'youtube_video_status',
                'value'   => 'progress',
                'compare' => '='
            ),
            array(
                'relation' => 'OR',
                array(
                    'key'     => 'youtube_video_next_revalidation',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => 'youtube_video_next_revalidation',
                    'value'   => '',
                    'compare' => '=='
                ),
                array(
                    'key'     => 'youtube_video_next_revalidation',
                    'value'   => current_time('Y-m-d H:i:s'),
                    'compare' => '<', // Find dates older than today's current time
                    'type'    => 'DATETIME'
                )
            )
        )
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        $post_ids = $query->posts;
        foreach ($post_ids as $post_id) {
            $minutes = 60;
            $date = new DateTime($current_time);
            $date->modify("+{$minutes} minutes");
            $new_time = $date->format('Y-m-d H:i:s');
            $video_id = get_field('youtube_video_id', $post_id);
            $update_youtube_data = youtube_data_from_video_id($video_id, $post_id);
            update_field('youtube_video_next_revalidation', $new_time, $post_id);
        }
    }
}

/**
 * Update channel data based on the Youtube API
 */
add_action('cronjob_ten_minutes', 'cron_update_channel_data');
function cron_update_channel_data() {
    $current_time = current_time('Y-m-d H:i:s');
    $args = array(
        'post_type'   => 'youtube_channels',
        'post_status' => 'publish',
        'fields'      => 'ids',
        'posts_per_page' => 10,
        'orderby' => 'date',
        'order'   => 'DESC',
        'fields'  => 'ids',
        'meta_query' =>  array(
            'relation' => 'OR',
            array(
                'key'     => 'youtube_channel_next_revalidation',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => 'youtube_channel_next_revalidation',
                'value'   => '',
                'compare' => '=='
            ),
            array(
                'key'     => 'youtube_channel_next_revalidation',
                'value'   => current_time('Y-m-d H:i:s'),
                'compare' => '<', // Find dates older than today's current time
                'type'    => 'DATETIME'
            )
        )
    );
    $query = new WP_Query($args);
    if ($query->have_posts()) {
        $post_ids = $query->posts;
        foreach ($post_ids as $post_id) {
            $minutes = 60;
            $date = new DateTime($current_time);
            $date->modify("+{$minutes} minutes");
            $new_time = $date->format('Y-m-d H:i:s');
            $video_id = get_field('youtube_channel_id', $post_id);
            $update_youtube_data = youtube_data_from_youtube_channel_id($video_id, $post_id);
            update_field('youtube_channel_next_revalidation', $new_time, $post_id);
        }
    }
}