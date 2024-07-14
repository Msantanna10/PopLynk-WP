<?php
/**
 * Allow requests
 */
function is_request_allowed($url) {
    $list = array(WEB_APP_URL);
    if(is_localhost()) {
        $list[] = 'http://localhost:3000';
    }
    if(!in_array($url, $list)) {
        return new WP_Error('rest_forbidden', __('Invalid request.'), array('status' => 403));
    }
}

/**
 * Check if it's localhost or production
 */
function is_localhost() {
    $server_name = $_SERVER['SERVER_NAME'];
    if (strpos($server_name, 'localhost') !== false || strpos($server_name, '127.0.0.1') !== false || strpos($server_name, '.local') !== false) {
        return true; // It's localhost
    } else {
        return false; // It's not localhost
    }
}

/**
 * Get Youtube data from API
 * Can get channel details from a Youtube channel ID or update an existing WP channel if $post_id is provided
 */
function youtube_data_from_youtube_channel_id($channel_id, $post_id = false) {
    // Define the API key and URL
    $api_key = YOUTUBE_API;
    $url = "https://www.googleapis.com/youtube/v3/channels?part=snippet,contentDetails,statistics&id={$channel_id}&key={$api_key}";

    // Make the HTTP request using wp_remote_get
    $response = wp_remote_get($url);

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return false;
    }

    // Get the response body and decode it
    $response_body = wp_remote_retrieve_body($response);
    $response_data = json_decode($response_body, true);

    // Check if items are present in the response
    if (isset($response_data['items']) && count($response_data['items']) > 0) {
        $channel = $response_data['items'][0];

        // Extract and clean up the description
        $description = isset($channel['snippet']['description']) ? $channel['snippet']['description'] : '';
        $description = standard_line_breaks($description);

        // Parse the publishedAt date
        $publishedAt = isset($channel['snippet']['publishedAt']) ? $channel['snippet']['publishedAt'] : '';
        $datePart = substr($publishedAt, 0, 10);
        $timePart = substr($publishedAt, 11, 8);
        $internalFormattedDate = "{$datePart} {$timePart}";

        // Format the date for display
        $dateObject = new DateTime($publishedAt);
        $monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        $hours = $dateObject->format('H');
        $minutes = $dateObject->format('i');
        $period = $hours >= 12 ? 'pm' : 'am';
        $displayHours = $hours % 12 ?: 12;
        $displayFormattedDate = $monthNames[$dateObject->format('n') - 1] . ' ' . $dateObject->format('j') . ', ' . $dateObject->format('Y') . ' ' . $displayHours . ':' . $minutes . ' ' . $period;

        // Values
        $channel_title = isset($channel['snippet']['title']) ? $channel['snippet']['title'] : '';
        $channel_etag = isset($channel['etag']) ? $channel['etag'] : '';
        $channel_image = isset($channel['snippet']['thumbnails']['medium']['url']) ? $channel['snippet']['thumbnails']['medium']['url'] : '';
        $channel_description = $description;
        $channel_custom_url = isset($channel['snippet']['customUrl']) ? $channel['snippet']['customUrl'] : '';
        $channel_country = isset($channel['snippet']['country']) ? $channel['snippet']['country'] : '';
        $channel_published = $internalFormattedDate;
        $channel_views = isset($channel['statistics']['viewCount']) ? $channel['statistics']['viewCount'] : 0;
        $channel_subscribers = isset($channel['statistics']['subscriberCount']) ? $channel['statistics']['subscriberCount'] : 0;
        $channel_videos = isset($channel['statistics']['videoCount']) ? $channel['statistics']['videoCount'] : 0;

        // Update channel details if there's a post ID
        if($post_id) {
            wp_update_post(array('ID' => $post_id, 'post_title' => ucfirst($channel_title)));
            update_field('youtube_channel_id', $channel_id, $post_id);
            update_field('youtube_channel_etag', $channel_etag, $post_id);
            update_field('youtube_channel_image', $channel_image, $post_id);
            update_field('youtube_channel_description', text_line_breaks($channel_description), $post_id);
            update_field('youtube_channel_custom_url', $channel_custom_url, $post_id);
            update_field('youtube_channel_country', $channel_country, $post_id);
            update_field('youtube_channel_published', $channel_published, $post_id);
            update_field('youtube_channel_views', $channel_views, $post_id);
            update_field('youtube_channel_subscribers', $channel_subscribers, $post_id);
            update_field('youtube_channel_videos', $channel_videos, $post_id);
            return;
        }

        // Return the array of fields
        return array(
            'title' => ucfirst($channel_title),
            'etag' => $channel_etag,
            'image' => $channel_image,
            'description' => text_line_breaks($channel_description),
            'custom_url' => $channel_custom_url,
            'country' => $channel_country,
            'published' => $channel_published,
            'views' => $channel_views,
            'subscribers' => $channel_subscribers,
            'videos' => $channel_videos
        );
    } else {
        return false;
    }
}

/**
 * Get Youtube channel data from WordPress
 */
function youtube_channel_data_from_wordpress_id($wp_channel_id) {

    if(empty($wp_channel_id)) {
        return false;
    }

    $channel_fields = get_fields($wp_channel_id);
    $channel_name = get_the_title($wp_channel_id);
    $channel_custom_url = $channel_fields['youtube_channel_custom_url'] ?? '';
    $channel_country = $channel_fields['youtube_channel_country'] ?? '';
    $channel_published = $channel_fields['youtube_channel_published'] ?? '';
    $channel_image = $channel_fields['youtube_channel_image'] ?? '';
    $channel_description = $channel_fields['youtube_channel_description'] ?? '';
    $channel_views = $channel_fields['youtube_channel_views'] ?? '';
    $channel_subscribers = $channel_fields['youtube_channel_subscribers'] ?? '';
    $channel_videos = $channel_fields['youtube_channel_videos'] ?? '';
    $channel_subscriber_goals = (is_array($channel_fields['youtube_channel_subscriber_goals']))? $channel_fields['youtube_channel_subscriber_goals'] : array();

    // Organize data
    if($channel_subscriber_goals) {
        foreach($channel_subscriber_goals as $goal) {

            // File
            $channel_subscriber_goals_file = $goal['file'];
            $channel_subscriber_goals_file_url = '';
            $channel_subscriber_goals_file_name = '';
            if ($channel_subscriber_goals_file) {
                $channel_subscriber_goals_file_url = wp_get_attachment_url($channel_subscriber_goals_file) ?: '';
                $channel_subscriber_goals_file_name = get_the_title($channel_subscriber_goals_file) ?: '';
            }

            $channel_subscriber_goals_organized[] = array(
                'progress' => (string) $goal['progress'],
                'goal' => (int) $goal['subscribers'],
                'reward_type' => (string) $goal['reward_type'],
                'reward_description' => (string) $goal['description'],
                'reward_file' => array(
                    'name' => (string) $channel_subscriber_goals_file_name,
                    'url' => (string) $channel_subscriber_goals_file_url
                ),
                'reward_cta' => array(
                    'link' => (string) $goal['cta_link'],
                    'text' => (string) $goal['cta_text']
                )
            );
        }
        $channel_subscriber_goals = $channel_subscriber_goals_organized;
    }

    $channel_data = array(
        'post_id' => $wp_channel_id,
        'channel_title' => ucfirst($channel_name),
        'channel_image' => $channel_image,
        'channel_description' => text_line_breaks($channel_description),
        'channel_custom_url' => $channel_custom_url,
        'channel_country' => $channel_country,
        'channel_published' => $channel_published,
        'channel_views' => (int) $channel_views,
        'channel_subscribers' => (int) $channel_subscribers,
        'channel_videos' => (int) $channel_videos,
        'channel_subscriber_goals' => $channel_subscriber_goals
    );

    return $channel_data;

}

/**
 * To Title Case: First letters uppercased
 */
function to_title_case($str) {
    // Convert the entire string to lowercase using mb_strtolower
    $str = mb_strtolower($str, 'UTF-8');
    $words = explode(" ", $str);

    // Helper function to capitalize the first character of a multibyte string
    $mb_ucfirst = function($str, $encoding = 'UTF-8') {
        $firstChar = mb_substr($str, 0, 1, $encoding);
        $rest = mb_substr($str, 1, null, $encoding);
        return mb_strtoupper($firstChar, $encoding) . $rest;
    };

    // Ensure the first word is always capitalized
    if (isset($words[0])) {
        $words[0] = $mb_ucfirst($words[0]);
    }

    // Capitalize other words if they are at least 3 characters long
    for ($i = 1; $i < count($words); $i++) {
        if (strlen($words[$i]) >= 3) {
            $words[$i] = $mb_ucfirst($words[$i]);
        }
    }

    return implode(" ", $words);
}

/**
 * Check if taxonomy has a valid term ID
 */
function is_valid_term_id($term_id, $taxonomy) {
    $term = term_exists((int)$term_id, $taxonomy);
    if ($term !== 0 && $term !== null) {
        return true;
    } else {
        return false;
    }
}

/**
 * Converts a provided date into a human-readable relative time format.
 * This function calculates the time difference between the current time and the given date,
 * then returns a string representing how long ago that date was, in terms of minutes, hours, days, weeks, months, or years.
 */
function when_from_date($date) {
    $timezone = new DateTimeZone(get_option('timezone_string'));
    $current_time = new DateTime('now', $timezone);
    $date_time = strtotime($date);
    $time_difference = $current_time - $date_time;

    if ($time_difference < 60) {
        return 'Agora mesmo';
    } elseif ($time_difference < 60 * 60) {
        $minutes = floor($time_difference / 60);
        return $minutes . ' minuto' . ($minutes > 1 ? 's' : '') . ' atrás';
    } elseif ($time_difference < 60 * 60 * 24) {
        $hours = floor($time_difference / (60 * 60));
        return $hours . ' hora' . ($hours > 1 ? 's' : '') . ' atrás';
    } elseif ($time_difference < 60 * 60 * 24 * 7) {
        $days = floor($time_difference / (60 * 60 * 24));
        if($days == 1) {
            return 'Ontem';
        }
        return $days . ' dia' . ($days > 1 ? 's' : '') . ' atrás';
    } elseif ($time_difference < 60 * 60 * 24 * 7 * 4) {
        $weeks = floor($time_difference / (60 * 60 * 24 * 7));
        return $weeks . ' semana' . ($weeks > 1 ? 's' : '') . ' atrás';
    } elseif ($time_difference < 60 * 60 * 24 * 365) {
        $months = floor($time_difference / (60 * 60 * 24 * 30.44));
        if ($months == 0) { $months = 1; }
        return $months . ' ' . ($months > 1 ? 'meses' : 'mês') . ' atrás';
    } else {
        $years = floor($time_difference / (60 * 60 * 24 * 365));
        return $years . ' ano' . ($years > 1 ? 's' : '') . ' atrás';
    }
}

/**
 * Make sure a text only always contains 2 line breaks at most
 */
function standard_line_breaks($str) {
    $str = trim($str);
    $str = preg_replace("/\n/", "\n\n", $str);
    $str = preg_replace("/(\n\n)+/", "\n\n", $str);
    return $str;
}

/**
 * Get user ID by token
 */
function get_user_by_token($token) {
    $user = get_users(array(
        // 'role__in' => array('member'),
        'fields' => 'ids',
        'number' => 1,
        'meta_query' => array(
            array(
                'key'     => 'user_token',
                'value'   => $token,
                'compare' => 'EQUALS',
            )
        ),
    ));
    if($user) {
        return (integer) $user[0];
    }
    return false;
}

/**
 * Get channel WordPress ID
 */
function get_wp_channel_id($youtube_channel_id) {
    $args = array(
        'post_type'      => 'youtube_channels',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'     => 'youtube_channel_id',
                'value'   => $youtube_channel_id,
                'compare' => '='
            )
        )
    );
    
    $channels = get_posts($args);

    if (!empty($channels)) {
        return $channels[0];
    }

    return $channels;
}

/**
 * Update profile
 */
/*function profile_update_field($user_id = false, $field, $value) {
    $allow_empty = array('state', 'province');
    if(!$user_id || empty($value) && !in_array($field, $allow_empty)) { return; }

    // Password
    elseif($field == 'password') {
        if(strlen($value) >= 5) {
            wp_set_password( $value, $user_id );
        }
    }

}*/

/**
 * Check if date is old
 * YYYY-MM-DD HH:mm:ss
 */
function is_before_todays_date($field) {
    if (empty($field)) {
        return false;
    }
    $timezone = new DateTimeZone(get_option('timezone_string'));
    $today = new DateTime('now', $timezone);
    $nextUpdateDate = new DateTime($field, $timezone);
    return ($nextUpdateDate < $today);
}

/**
 * Update user details based on IP
 */
function update_user_ip_details($user_id, $ip) {

    if (empty($ip)) { return; }
    
    $json = file_get_contents("http://ip-api.com/json/{$ip}");
    $infos = json_decode($json, false);

    $country = $infos->country ?? '';
    $city = $infos->city ?? '';
    $region = $infos->regionName ?? '';
    $isp = $infos->isp ?? '';
    $org = $infos->org ?? '';

    date_default_timezone_set(get_option('timezone_string'));
    $date = date('Y/m/d');
    $time = date('H:i:s');

    $existing_details = get_field('user_ip_details', "user_$user_id");

    $new_ip_data = 'IP: ' . $ip . '
Country: ' . $country . '
Region: ' . $region . '
City: ' . $city . '
Network: ' . $isp . ' - ' . $org . '
Date: ' . $date . ' - ' . $time;

    if(empty($existing_details)) {
        update_field('user_ip_details', $new_ip_data, "user_$user_id");
    }
    else {
        $updated_info = "$new_ip_data
##############################
$existing_details";
        update_field('user_ip_details', $updated_info, "user_$user_id");
    }

}

/**
 * Remove emoji from string
 */
function remove_emoji($text) {
    return preg_replace('/\x{1F3F4}\x{E0067}\x{E0062}(?:\x{E0077}\x{E006C}\x{E0073}|\x{E0073}\x{E0063}\x{E0074}|\x{E0065}\x{E006E}\x{E0067})\x{E007F}|(?:\x{1F9D1}\x{1F3FF}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FF}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FF}\x{200D}\x{1FAF2})[\x{1F3FB}-\x{1F3FE}]|(?:\x{1F9D1}\x{1F3FE}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FE}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FE}\x{200D}\x{1FAF2})[\x{1F3FB}-\x{1F3FD}\x{1F3FF}]|(?:\x{1F9D1}\x{1F3FD}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FD}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FD}\x{200D}\x{1FAF2})[\x{1F3FB}\x{1F3FC}\x{1F3FE}\x{1F3FF}]|(?:\x{1F9D1}\x{1F3FC}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FC}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FC}\x{200D}\x{1FAF2})[\x{1F3FB}\x{1F3FD}-\x{1F3FF}]|(?:\x{1F9D1}\x{1F3FB}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F9D1}|\x{1F469}\x{1F3FB}\x{200D}\x{1F91D}\x{200D}[\x{1F468}\x{1F469}]|\x{1FAF1}\x{1F3FB}\x{200D}\x{1FAF2})[\x{1F3FC}-\x{1F3FF}]|\x{1F468}(?:\x{1F3FB}(?:\x{200D}(?:\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}])|\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}]))|\x{1F91D}\x{200D}\x{1F468}[\x{1F3FC}-\x{1F3FF}]|[\x{2695}\x{2696}\x{2708}]\x{FE0F}|[\x{2695}\x{2696}\x{2708}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]))?|[\x{1F3FC}-\x{1F3FF}]\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}])|\x{200D}(?:\x{1F48B}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FF}]|\x{1F468}[\x{1F3FB}-\x{1F3FF}]))|\x{200D}(?:\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D})?|\x{200D}(?:\x{1F48B}\x{200D})?)\x{1F468}|[\x{1F468}\x{1F469}]\x{200D}(?:\x{1F466}\x{200D}\x{1F466}|\x{1F467}\x{200D}[\x{1F466}\x{1F467}])|\x{1F466}\x{200D}\x{1F466}|\x{1F467}\x{200D}[\x{1F466}\x{1F467}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FF}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FE}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FE}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}-\x{1F3FD}\x{1F3FF}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FD}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}\x{1F3FC}\x{1F3FE}\x{1F3FF}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FC}\x{200D}(?:\x{1F91D}\x{200D}\x{1F468}[\x{1F3FB}\x{1F3FD}-\x{1F3FF}]|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])\x{FE0F}|\x{200D}(?:[\x{1F468}\x{1F469}]\x{200D}[\x{1F466}\x{1F467}]|[\x{1F466}\x{1F467}])|\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{200D}[\x{2695}\x{2696}\x{2708}])?|(?:\x{1F469}(?:\x{1F3FB}\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])|\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}]))|[\x{1F3FC}-\x{1F3FF}]\x{200D}\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])|\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])))|\x{1F9D1}[\x{1F3FB}-\x{1F3FF}]\x{200D}\x{1F91D}\x{200D}\x{1F9D1})[\x{1F3FB}-\x{1F3FF}]|\x{1F469}\x{200D}\x{1F469}\x{200D}(?:\x{1F466}\x{200D}\x{1F466}|\x{1F467}\x{200D}[\x{1F466}\x{1F467}])|\x{1F469}(?:\x{200D}(?:\x{2764}(?:\x{FE0F}\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}])|\x{200D}(?:\x{1F48B}\x{200D}[\x{1F468}\x{1F469}]|[\x{1F468}\x{1F469}]))|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FF}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FE}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FD}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FC}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FB}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F9D1}(?:\x{200D}(?:\x{1F91D}\x{200D}\x{1F9D1}|[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F3FF}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FE}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FD}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FC}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}]|\x{1F3FB}\x{200D}[\x{1F33E}\x{1F373}\x{1F37C}\x{1F384}\x{1F393}\x{1F3A4}\x{1F3A8}\x{1F3EB}\x{1F3ED}\x{1F4BB}\x{1F4BC}\x{1F527}\x{1F52C}\x{1F680}\x{1F692}\x{1F9AF}-\x{1F9B3}\x{1F9BC}\x{1F9BD}])|\x{1F469}\x{200D}\x{1F466}\x{200D}\x{1F466}|\x{1F469}\x{200D}\x{1F469}\x{200D}[\x{1F466}\x{1F467}]|\x{1F469}\x{200D}\x{1F467}\x{200D}[\x{1F466}\x{1F467}]|(?:\x{1F441}\x{FE0F}?\x{200D}\x{1F5E8}|\x{1F9D1}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F469}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F636}\x{200D}\x{1F32B}|\x{1F3F3}\x{FE0F}?\x{200D}\x{26A7}|\x{1F43B}\x{200D}\x{2744}|(?:[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}][\x{1F3FB}-\x{1F3FF}]|[\x{1F46F}\x{1F9DE}\x{1F9DF}])\x{200D}[\x{2640}\x{2642}]|[\x{26F9}\x{1F3CB}\x{1F3CC}\x{1F575}](?:[\x{FE0F}\x{1F3FB}-\x{1F3FF}]\x{200D}[\x{2640}\x{2642}]|\x{200D}[\x{2640}\x{2642}])|\x{1F3F4}\x{200D}\x{2620}|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93C}-\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}]\x{200D}[\x{2640}\x{2642}]|[\xA9\xAE\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}\x{21AA}\x{231A}\x{231B}\x{2328}\x{23CF}\x{23ED}-\x{23EF}\x{23F1}\x{23F2}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}\x{25AB}\x{25B6}\x{25C0}\x{25FB}\x{25FC}\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}\x{2615}\x{2618}\x{2620}\x{2622}\x{2623}\x{2626}\x{262A}\x{262E}\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}\x{2660}\x{2663}\x{2665}\x{2666}\x{2668}\x{267B}\x{267E}\x{267F}\x{2692}\x{2694}-\x{2697}\x{2699}\x{269B}\x{269C}\x{26A0}\x{26A7}\x{26AA}\x{26B0}\x{26B1}\x{26BD}\x{26BE}\x{26C4}\x{26C8}\x{26CF}\x{26D1}\x{26D3}\x{26E9}\x{26F0}-\x{26F5}\x{26F7}\x{26F8}\x{26FA}\x{2702}\x{2708}\x{2709}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2733}\x{2734}\x{2744}\x{2747}\x{2763}\x{27A1}\x{2934}\x{2935}\x{2B05}-\x{2B07}\x{2B1B}\x{2B1C}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F170}\x{1F171}\x{1F17E}\x{1F17F}\x{1F202}\x{1F237}\x{1F321}\x{1F324}-\x{1F32C}\x{1F336}\x{1F37D}\x{1F396}\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}\x{1F39F}\x{1F3CD}\x{1F3CE}\x{1F3D4}-\x{1F3DF}\x{1F3F5}\x{1F3F7}\x{1F43F}\x{1F4FD}\x{1F549}\x{1F54A}\x{1F56F}\x{1F570}\x{1F573}\x{1F576}-\x{1F579}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F5A5}\x{1F5A8}\x{1F5B1}\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}\x{1F6CB}\x{1F6CD}-\x{1F6CF}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6F0}\x{1F6F3}])\x{FE0F}|\x{1F441}\x{FE0F}?\x{200D}\x{1F5E8}|\x{1F9D1}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F469}(?:\x{1F3FF}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FE}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FD}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FC}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{1F3FB}\x{200D}[\x{2695}\x{2696}\x{2708}]|\x{200D}[\x{2695}\x{2696}\x{2708}])|\x{1F3F3}\x{FE0F}?\x{200D}\x{1F308}|\x{1F469}\x{200D}\x{1F467}|\x{1F469}\x{200D}\x{1F466}|\x{1F636}\x{200D}\x{1F32B}|\x{1F3F3}\x{FE0F}?\x{200D}\x{26A7}|\x{1F635}\x{200D}\x{1F4AB}|\x{1F62E}\x{200D}\x{1F4A8}|\x{1F415}\x{200D}\x{1F9BA}|\x{1FAF1}(?:\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{1F3FB})?|\x{1F9D1}(?:\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{1F3FB})?|\x{1F469}(?:\x{1F3FF}|\x{1F3FE}|\x{1F3FD}|\x{1F3FC}|\x{1F3FB})?|\x{1F43B}\x{200D}\x{2744}|(?:[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}][\x{1F3FB}-\x{1F3FF}]|[\x{1F46F}\x{1F9DE}\x{1F9DF}])\x{200D}[\x{2640}\x{2642}]|[\x{26F9}\x{1F3CB}\x{1F3CC}\x{1F575}](?:[\x{FE0F}\x{1F3FB}-\x{1F3FF}]\x{200D}[\x{2640}\x{2642}]|\x{200D}[\x{2640}\x{2642}])|\x{1F3F4}\x{200D}\x{2620}|\x{1F1FD}\x{1F1F0}|\x{1F1F6}\x{1F1E6}|\x{1F1F4}\x{1F1F2}|\x{1F408}\x{200D}\x{2B1B}|\x{2764}(?:\x{FE0F}\x{200D}[\x{1F525}\x{1FA79}]|\x{200D}[\x{1F525}\x{1FA79}])|\x{1F441}\x{FE0F}?|\x{1F3F3}\x{FE0F}?|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93C}-\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}]\x{200D}[\x{2640}\x{2642}]|\x{1F1FF}[\x{1F1E6}\x{1F1F2}\x{1F1FC}]|\x{1F1FE}[\x{1F1EA}\x{1F1F9}]|\x{1F1FC}[\x{1F1EB}\x{1F1F8}]|\x{1F1FB}[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1EE}\x{1F1F3}\x{1F1FA}]|\x{1F1FA}[\x{1F1E6}\x{1F1EC}\x{1F1F2}\x{1F1F3}\x{1F1F8}\x{1F1FE}\x{1F1FF}]|\x{1F1F9}[\x{1F1E6}\x{1F1E8}\x{1F1E9}\x{1F1EB}-\x{1F1ED}\x{1F1EF}-\x{1F1F4}\x{1F1F7}\x{1F1F9}\x{1F1FB}\x{1F1FC}\x{1F1FF}]|\x{1F1F8}[\x{1F1E6}-\x{1F1EA}\x{1F1EC}-\x{1F1F4}\x{1F1F7}-\x{1F1F9}\x{1F1FB}\x{1F1FD}-\x{1F1FF}]|\x{1F1F7}[\x{1F1EA}\x{1F1F4}\x{1F1F8}\x{1F1FA}\x{1F1FC}]|\x{1F1F5}[\x{1F1E6}\x{1F1EA}-\x{1F1ED}\x{1F1F0}-\x{1F1F3}\x{1F1F7}-\x{1F1F9}\x{1F1FC}\x{1F1FE}]|\x{1F1F3}[\x{1F1E6}\x{1F1E8}\x{1F1EA}-\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F4}\x{1F1F5}\x{1F1F7}\x{1F1FA}\x{1F1FF}]|\x{1F1F2}[\x{1F1E6}\x{1F1E8}-\x{1F1ED}\x{1F1F0}-\x{1F1FF}]|\x{1F1F1}[\x{1F1E6}-\x{1F1E8}\x{1F1EE}\x{1F1F0}\x{1F1F7}-\x{1F1FB}\x{1F1FE}]|\x{1F1F0}[\x{1F1EA}\x{1F1EC}-\x{1F1EE}\x{1F1F2}\x{1F1F3}\x{1F1F5}\x{1F1F7}\x{1F1FC}\x{1F1FE}\x{1F1FF}]|\x{1F1EF}[\x{1F1EA}\x{1F1F2}\x{1F1F4}\x{1F1F5}]|\x{1F1EE}[\x{1F1E8}-\x{1F1EA}\x{1F1F1}-\x{1F1F4}\x{1F1F6}-\x{1F1F9}]|\x{1F1ED}[\x{1F1F0}\x{1F1F2}\x{1F1F3}\x{1F1F7}\x{1F1F9}\x{1F1FA}]|\x{1F1EC}[\x{1F1E6}\x{1F1E7}\x{1F1E9}-\x{1F1EE}\x{1F1F1}-\x{1F1F3}\x{1F1F5}-\x{1F1FA}\x{1F1FC}\x{1F1FE}]|\x{1F1EB}[\x{1F1EE}-\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1F7}]|\x{1F1EA}[\x{1F1E6}\x{1F1E8}\x{1F1EA}\x{1F1EC}\x{1F1ED}\x{1F1F7}-\x{1F1FA}]|\x{1F1E9}[\x{1F1EA}\x{1F1EC}\x{1F1EF}\x{1F1F0}\x{1F1F2}\x{1F1F4}\x{1F1FF}]|\x{1F1E8}[\x{1F1E6}\x{1F1E8}\x{1F1E9}\x{1F1EB}-\x{1F1EE}\x{1F1F0}-\x{1F1F5}\x{1F1F7}\x{1F1FA}-\x{1F1FF}]|\x{1F1E7}[\x{1F1E6}\x{1F1E7}\x{1F1E9}-\x{1F1EF}\x{1F1F1}-\x{1F1F4}\x{1F1F6}-\x{1F1F9}\x{1F1FB}\x{1F1FC}\x{1F1FE}\x{1F1FF}]|\x{1F1E6}[\x{1F1E8}-\x{1F1EC}\x{1F1EE}\x{1F1F1}\x{1F1F2}\x{1F1F4}\x{1F1F6}-\x{1F1FA}\x{1F1FC}\x{1F1FD}\x{1F1FF}]|[#\*0-9]\x{FE0F}?\x{20E3}|\x{1F93C}[\x{1F3FB}-\x{1F3FF}]|\x{2764}\x{FE0F}?|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}][\x{1F3FB}-\x{1F3FF}]|[\x{26F9}\x{1F3CB}\x{1F3CC}\x{1F575}][\x{FE0F}\x{1F3FB}-\x{1F3FF}]?|\x{1F3F4}|[\x{270A}\x{270B}\x{1F385}\x{1F3C2}\x{1F3C7}\x{1F442}\x{1F443}\x{1F446}-\x{1F450}\x{1F466}\x{1F467}\x{1F46B}-\x{1F46D}\x{1F472}\x{1F474}-\x{1F476}\x{1F478}\x{1F47C}\x{1F483}\x{1F485}\x{1F48F}\x{1F491}\x{1F4AA}\x{1F57A}\x{1F595}\x{1F596}\x{1F64C}\x{1F64F}\x{1F6C0}\x{1F6CC}\x{1F90C}\x{1F90F}\x{1F918}-\x{1F91F}\x{1F930}-\x{1F934}\x{1F936}\x{1F977}\x{1F9B5}\x{1F9B6}\x{1F9BB}\x{1F9D2}\x{1F9D3}\x{1F9D5}\x{1FAC3}-\x{1FAC5}\x{1FAF0}\x{1FAF2}-\x{1FAF6}][\x{1F3FB}-\x{1F3FF}]|[\x{261D}\x{270C}\x{270D}\x{1F574}\x{1F590}][\x{FE0F}\x{1F3FB}-\x{1F3FF}]|[\x{261D}\x{270A}-\x{270D}\x{1F385}\x{1F3C2}\x{1F3C7}\x{1F408}\x{1F415}\x{1F43B}\x{1F442}\x{1F443}\x{1F446}-\x{1F450}\x{1F466}\x{1F467}\x{1F46B}-\x{1F46D}\x{1F472}\x{1F474}-\x{1F476}\x{1F478}\x{1F47C}\x{1F483}\x{1F485}\x{1F48F}\x{1F491}\x{1F4AA}\x{1F574}\x{1F57A}\x{1F590}\x{1F595}\x{1F596}\x{1F62E}\x{1F635}\x{1F636}\x{1F64C}\x{1F64F}\x{1F6C0}\x{1F6CC}\x{1F90C}\x{1F90F}\x{1F918}-\x{1F91F}\x{1F930}-\x{1F934}\x{1F936}\x{1F93C}\x{1F977}\x{1F9B5}\x{1F9B6}\x{1F9BB}\x{1F9D2}\x{1F9D3}\x{1F9D5}\x{1FAC3}-\x{1FAC5}\x{1FAF0}\x{1FAF2}-\x{1FAF6}]|[\x{1F3C3}\x{1F3C4}\x{1F3CA}\x{1F46E}\x{1F470}\x{1F471}\x{1F473}\x{1F477}\x{1F481}\x{1F482}\x{1F486}\x{1F487}\x{1F645}-\x{1F647}\x{1F64B}\x{1F64D}\x{1F64E}\x{1F6A3}\x{1F6B4}-\x{1F6B6}\x{1F926}\x{1F935}\x{1F937}-\x{1F939}\x{1F93D}\x{1F93E}\x{1F9B8}\x{1F9B9}\x{1F9CD}-\x{1F9CF}\x{1F9D4}\x{1F9D6}-\x{1F9DD}]|[\x{1F46F}\x{1F9DE}\x{1F9DF}]|[\xA9\xAE\x{203C}\x{2049}\x{2122}\x{2139}\x{2194}-\x{2199}\x{21A9}\x{21AA}\x{231A}\x{231B}\x{2328}\x{23CF}\x{23ED}-\x{23EF}\x{23F1}\x{23F2}\x{23F8}-\x{23FA}\x{24C2}\x{25AA}\x{25AB}\x{25B6}\x{25C0}\x{25FB}\x{25FC}\x{25FE}\x{2600}-\x{2604}\x{260E}\x{2611}\x{2614}\x{2615}\x{2618}\x{2620}\x{2622}\x{2623}\x{2626}\x{262A}\x{262E}\x{262F}\x{2638}-\x{263A}\x{2640}\x{2642}\x{2648}-\x{2653}\x{265F}\x{2660}\x{2663}\x{2665}\x{2666}\x{2668}\x{267B}\x{267E}\x{267F}\x{2692}\x{2694}-\x{2697}\x{2699}\x{269B}\x{269C}\x{26A0}\x{26A7}\x{26AA}\x{26B0}\x{26B1}\x{26BD}\x{26BE}\x{26C4}\x{26C8}\x{26CF}\x{26D1}\x{26D3}\x{26E9}\x{26F0}-\x{26F5}\x{26F7}\x{26F8}\x{26FA}\x{2702}\x{2708}\x{2709}\x{270F}\x{2712}\x{2714}\x{2716}\x{271D}\x{2721}\x{2733}\x{2734}\x{2744}\x{2747}\x{2763}\x{27A1}\x{2934}\x{2935}\x{2B05}-\x{2B07}\x{2B1B}\x{2B1C}\x{2B55}\x{3030}\x{303D}\x{3297}\x{3299}\x{1F004}\x{1F170}\x{1F171}\x{1F17E}\x{1F17F}\x{1F202}\x{1F237}\x{1F321}\x{1F324}-\x{1F32C}\x{1F336}\x{1F37D}\x{1F396}\x{1F397}\x{1F399}-\x{1F39B}\x{1F39E}\x{1F39F}\x{1F3CD}\x{1F3CE}\x{1F3D4}-\x{1F3DF}\x{1F3F5}\x{1F3F7}\x{1F43F}\x{1F4FD}\x{1F549}\x{1F54A}\x{1F56F}\x{1F570}\x{1F573}\x{1F576}-\x{1F579}\x{1F587}\x{1F58A}-\x{1F58D}\x{1F5A5}\x{1F5A8}\x{1F5B1}\x{1F5B2}\x{1F5BC}\x{1F5C2}-\x{1F5C4}\x{1F5D1}-\x{1F5D3}\x{1F5DC}-\x{1F5DE}\x{1F5E1}\x{1F5E3}\x{1F5E8}\x{1F5EF}\x{1F5F3}\x{1F5FA}\x{1F6CB}\x{1F6CD}-\x{1F6CF}\x{1F6E0}-\x{1F6E5}\x{1F6E9}\x{1F6F0}\x{1F6F3}]|[\x{23E9}-\x{23EC}\x{23F0}\x{23F3}\x{25FD}\x{2693}\x{26A1}\x{26AB}\x{26C5}\x{26CE}\x{26D4}\x{26EA}\x{26FD}\x{2705}\x{2728}\x{274C}\x{274E}\x{2753}-\x{2755}\x{2757}\x{2795}-\x{2797}\x{27B0}\x{27BF}\x{2B50}\x{1F0CF}\x{1F18E}\x{1F191}-\x{1F19A}\x{1F201}\x{1F21A}\x{1F22F}\x{1F232}-\x{1F236}\x{1F238}-\x{1F23A}\x{1F250}\x{1F251}\x{1F300}-\x{1F320}\x{1F32D}-\x{1F335}\x{1F337}-\x{1F37C}\x{1F37E}-\x{1F384}\x{1F386}-\x{1F393}\x{1F3A0}-\x{1F3C1}\x{1F3C5}\x{1F3C6}\x{1F3C8}\x{1F3C9}\x{1F3CF}-\x{1F3D3}\x{1F3E0}-\x{1F3F0}\x{1F3F8}-\x{1F407}\x{1F409}-\x{1F414}\x{1F416}-\x{1F43A}\x{1F43C}-\x{1F43E}\x{1F440}\x{1F444}\x{1F445}\x{1F451}-\x{1F465}\x{1F46A}\x{1F479}-\x{1F47B}\x{1F47D}-\x{1F480}\x{1F484}\x{1F488}-\x{1F48E}\x{1F490}\x{1F492}-\x{1F4A9}\x{1F4AB}-\x{1F4FC}\x{1F4FF}-\x{1F53D}\x{1F54B}-\x{1F54E}\x{1F550}-\x{1F567}\x{1F5A4}\x{1F5FB}-\x{1F62D}\x{1F62F}-\x{1F634}\x{1F637}-\x{1F644}\x{1F648}-\x{1F64A}\x{1F680}-\x{1F6A2}\x{1F6A4}-\x{1F6B3}\x{1F6B7}-\x{1F6BF}\x{1F6C1}-\x{1F6C5}\x{1F6D0}-\x{1F6D2}\x{1F6D5}-\x{1F6D7}\x{1F6DD}-\x{1F6DF}\x{1F6EB}\x{1F6EC}\x{1F6F4}-\x{1F6FC}\x{1F7E0}-\x{1F7EB}\x{1F7F0}\x{1F90D}\x{1F90E}\x{1F910}-\x{1F917}\x{1F920}-\x{1F925}\x{1F927}-\x{1F92F}\x{1F93A}\x{1F93F}-\x{1F945}\x{1F947}-\x{1F976}\x{1F978}-\x{1F9B4}\x{1F9B7}\x{1F9BA}\x{1F9BC}-\x{1F9CC}\x{1F9D0}\x{1F9E0}-\x{1F9FF}\x{1FA70}-\x{1FA74}\x{1FA78}-\x{1FA7C}\x{1FA80}-\x{1FA86}\x{1FA90}-\x{1FAAC}\x{1FAB0}-\x{1FABA}\x{1FAC0}-\x{1FAC2}\x{1FAD0}-\x{1FAD9}\x{1FAE0}-\x{1FAE7}]/u', '', $text);
}

/**
 * Custom WP file upload
 */
function wp_upload_file($file, $type, $extensions, $author) {

    // Include required files for media handling
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Function to generate a random string
    function generate_random_string($length = 25) {
        return substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, $length);
    }

    // Check file type and set allowed extensions
    $allowed_extensions = array_map('strtolower', $extensions);

    if ($type === 'image') {
        if (strpos($file, 'data:image') === 0) {
            // Handle base64 encoded image
            $split = explode(',', substr($file, 5), 2);
            $mime = $split[0];
            $imageString = $split[1];
            $mime_split_without_base64 = explode(';', $mime, 2);
            $mime_split = explode('/', $mime_split_without_base64[0], 2);
            $imageExtension = $mime_split[1];

            if (!in_array($imageExtension, $allowed_extensions)) {
                return false;
            }

            // Valid image, starts uploading
            $upload_dir  = wp_upload_dir();
            $upload_path_base = str_replace('/', DIRECTORY_SEPARATOR, $upload_dir['basedir'] . '/temp') . DIRECTORY_SEPARATOR;

            // Check if the directory exists, if not create it
            if (!file_exists($upload_path_base)) {
                mkdir($upload_path_base, 0755, true);
            }

            $img = str_replace(' ', '+', $imageString);
            $decoded = base64_decode($img);
            $filename = generate_random_string() . '.' . $imageExtension;

            // Save the image in the 'uploads/temp' folder.
            $upload_file = file_put_contents($upload_path_base . $filename, $decoded);

            if ($upload_file === false) {
                return false;
            }

            // In the 'uploads' folder
            $uploaded = $upload_dir['baseurl'] . '/temp/' . basename($filename);

            $attach_url = media_sideload_image($uploaded, null, null, 'src');
            $attach_id = attachment_url_to_postid($attach_url);
            unlink($upload_path_base . $filename);

            if ($attach_id) {
                wp_update_post(array('ID' => $attach_id, 'post_author' => $author));
                return array(
                    'file_id' => $attach_id,
                    'file_url' => wp_get_attachment_url($attach_id),
                );
            } else {
                return false;
            }

        } else {
            // Handle external image URL
            $file_info = pathinfo($file);
            $extension = strtolower($file_info['extension']);

            if (!in_array($extension, $allowed_extensions)) {
                return false;
            }

            // Download the external image
            $tmp_file = download_url($file);

            if (is_wp_error($tmp_file)) {
                return false;
            }

            // Get the mime type
            $mime_type = wp_check_filetype($tmp_file)['type'];

            // Generate random filename
            $filename = generate_random_string() . '.' . $extension;

            // Set variables for media
            $file_array = array(
                'name' => $filename,
                'tmp_name' => $tmp_file,
                'type' => $mime_type
            );
        }
    } elseif ($type === 'document') {
        // Handle file upload from request
        if (!isset($file['name'])) {
            return false;
        }

        $file_info = pathinfo($file['name']);
        $extension = strtolower($file_info['extension']);

        if (!in_array($extension, $allowed_extensions)) {
            return false;
        }

        $file_array = $file;
    } else {
        return false;
    }

    // Check for upload errors
    if (is_wp_error($file_array)) {
        return false;
    }

    // Use wp_handle_sideload instead of wp_handle_upload for externally downloaded images
    if ($type === 'image' && isset($file_array['tmp_name'])) {
        $uploaded_file = wp_handle_sideload($file_array, array('test_form' => false));
    } else {
        $uploaded_file = wp_handle_upload($file_array, array('test_form' => false));
    }

    if ($uploaded_file && !isset($uploaded_file['error'])) {
        // Create an attachment
        $attachment = array(
            'post_mime_type' => $uploaded_file['type'],
            'post_title' => sanitize_file_name($file_array['name']),
            'post_content' => '',
            'post_status' => 'inherit',
            'post_author' => $author,
        );

        // Insert the attachment
        $attach_id = wp_insert_attachment($attachment, $uploaded_file['file']);

        // Generate metadata for the attachment
        $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);

        // Return the file ID and URL
        return array(
            'file_id' => $attach_id,
            'file_url' => wp_get_attachment_url($attach_id),
        );
    }

    return false;
}

/**
 * Clean content by trimming spaces and ensuring no more than 2 line breaks per paragraph.
 *
 * @param string $content The content to clean.
 * @return string The cleaned content.
 */
function text_line_breaks($content) {
    // Trim spaces from the beginning and end
    $content = trim($content);
    // Replace multiple line breaks with two line breaks
    $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
    return ucfirst($content);
}

/**
 * Get Youtube video data
 * Can get video details from a Youtube video ID or update an existing WP video
 */
function youtube_data_from_video_id($video_id, $post_id = false) {
    $api_key = YOUTUBE_API;
    $api_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,statistics&id={$video_id}&key={$api_key}";
    $response = wp_remote_get($api_url);

    // Check for errors
    if (is_wp_error($response)) {
        return false;
    }

    // Parse the response
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    // Check if video data is available
    if (isset($data['items']) && count($data['items']) > 0) {
        $video = $data['items'][0];

        // Video details
        $video_id = $video['id'];
        $video_title = isset($video['snippet']['title']) ? $video['snippet']['title'] : '';
        $video_description = isset($video['snippet']['description']) ? $video['snippet']['description'] : '';
        $video_likes = isset($video['statistics']['likeCount']) ? intval($video['statistics']['likeCount']) : 0;
        $video_views = isset($video['statistics']['viewCount']) ? intval($video['statistics']['viewCount']) : 0;
        $video_comments = isset($video['statistics']['commentCount']) ? intval($video['statistics']['commentCount']) : 0;

        // Update video details if there's a post ID
        if($post_id) {
            wp_update_post(array('ID' => $post_id, 'post_title' => ucfirst($video_title)));
            update_field('youtube_video_id', $video_id, $post_id);
            update_field('youtube_video_description', text_line_breaks($video_description), $post_id);
            update_field('youtube_video_likes', $video_likes, $post_id);
            update_field('youtube_video_views', $video_views, $post_id);
            update_field('youtube_video_comments', $video_comments, $post_id);
            return;
        }

        // Final result
        $video_details = array(
            'id' => $video_id,
            'title' => ucfirst($video_title),
            'description' => text_line_breaks($video_description),
            'likes' => $video_likes,
            'views' => $video_views,
            'comments' => $video_comments,
        );
        return $video_details;
    } else {
        return false;
    }
}

/**
 * Retrieves detailed information about a specific YouTube video campaign from WordPress posts.
 * This function fetches data based on the YouTube video ID, and optionally filters by channel and user ID.
 * It returns structured data including video details, campaign metrics, and associated rewards.
 * If the necessary IDs are not provided or valid, it returns appropriate error messages.
 *
 * @param string|bool $video_youtube_id    The YouTube ID of the video.
 * @param string|bool $channel_youtube_id  The YouTube ID of the channel (optional).
 * @param int|bool $user_id                The WordPress user ID associated with the video (optional).
 * @return array                           An array containing either the video campaign data or error messages.
 */
function wp_video_data($video_youtube_id = false, $channel_youtube_id = false, $user_id = false) {

    $data = array();

    if (!$video_youtube_id) {
        $data['validation']['status'] = false;
        $data['validation']['error_message'] = 'É necessário o ID do vídeo do Youtube.';
        return $data;
    }
    
    // Initialize args array for WP_Query
    $args = array(
        'post_type'      => 'youtube_videos',
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'     => 'youtube_video_id',
                'value'   => $video_youtube_id,
                'compare' => '='
            )
        )
    );

    // If channel_youtube_id is provided, get the WordPress channel ID
    if ($channel_youtube_id) {
        $channel_wp_id = get_wp_channel_id($channel_youtube_id);
        if (!$channel_wp_id || !$user_id) {
            $data['validation']['status'] = false;
            $data['validation']['error_message'] = 'Este canal não pertence a sua conta.';
            return $data;
        }
        // Add channel condition to meta_query
        $args['meta_query'][] = array(
            array(
                'key'     => 'youtube_video_channel',
                'value'   => $channel_wp_id,
                'compare' => '='
            )
        );
        // If user_id is provided, add author condition to query
        if ($user_id) {
            $args['author'] = $user_id;
        }
    }

    // Get posts based on the query args
    $videos = get_posts($args);

    // If no posts are found, return true but with an empty array so the user can create a new campaign
    if (empty($videos)) {
        $data['validation']['status'] = true;
        $data['data'] = array();
        return $data;
    }

    // Return the first video ID found
    $video_wp_id = $videos[0];

    // Variables
    $video_title = get_the_title($video_wp_id);
    $video_title = str_replace('&#8211;', '-', $video_title);    
    $video_campaign_name = get_field('youtube_video_campaign_name', $video_wp_id);
    $video_views = (int) get_field('youtube_video_views', $video_wp_id);
    $video_likes = (int) get_field('youtube_video_likes', $video_wp_id);
    $video_comments = (int) get_field('youtube_video_comments', $video_wp_id);
    $video_description = get_field('youtube_video_description', $video_wp_id);
    $video_progress = get_field('youtube_video_progress', $video_wp_id);
    $video_reward_type = get_field('youtube_video_reward_type', $video_wp_id);
    $video_reward_description = get_field('youtube_video_campaign_description', $video_wp_id);    
    $video_reward_image = get_field('youtube_video_reward_image', $video_wp_id);
    // Image
    $video_reward_image_url = '';
    $video_reward_image_name = '';
    if ($video_reward_image) {
        $video_reward_image_url = wp_get_attachment_url($video_reward_image) ?: '';
        $video_reward_image_name = basename(get_attached_file($video_reward_image)) ?: '';
    }
    // File
    $video_reward_file = get_field('youtube_video_reward_file', $video_wp_id);
    $video_reward_file_url = '';
    $video_reward_file_name = '';
    if ($video_reward_file) {
        $video_reward_file_url = wp_get_attachment_url($video_reward_file) ?: '';
        $video_reward_file_name = get_the_title($video_reward_file) ?: '';
    }
    $video_reward_cta_link = get_field('youtube_video_reward_cta_link', $video_wp_id);
    $video_reward_cta_text = get_field('youtube_video_reward_cta_text', $video_wp_id);
    $video_reward_goals = get_field('youtube_video_goals', $video_wp_id);

    // Updated expiration
    $video_updated_goals = [];
    if ($video_reward_goals) {
        $today = new DateTime();
        $today->setTimestamp(current_time('timestamp'));
        foreach ($video_reward_goals as $goal) {
            if (!empty($goal['expiration'])) {
                $expiration_date = new DateTime($goal['expiration']);
                $interval = $today->diff($expiration_date);
                $days_left = $interval->format('%r%a'); // %r gives a sign prefix, %a gives the total number of days
                $goal['expiration'] = ($days_left > 0) ? (int) $days_left : '';
            }
            $video_updated_goals[] = $goal;
        }
    }

    // Final result
    $video_details = array(
        'post_id' => $video_wp_id,
        'video_title' => (string) $video_title,
        'video_campaign_name' => (string) $video_campaign_name,
        'video_views' => $video_views,
        'video_likes' => $video_likes,
        'video_comments' => $video_comments,
        'video_progress' => (string) $video_progress,
        'video_description' => (string) $video_description,
        'video_reward_type' => (string) $video_reward_type,
        'video_reward_description' => (string) $video_reward_description,
        'video_reward_cta' => array(
            'link' => (string) $video_reward_cta_link,
            'text' => (string) $video_reward_cta_text
        ),
        'video_reward_image' => array(
            'name' => (string) $video_reward_image_name,
            'url' => (string) $video_reward_image_url
        ),
        'video_reward_file' => array(
            'name' => (string) $video_reward_file_name,
            'url' => (string) $video_reward_file_url
        ),
        'video_reward_goals' => $video_updated_goals
    );

    $data['validation']['status'] = true;
    $data['data'] = $video_details;

    return $data;

}

/**
 * Check if a user is the author of a specific post
 *
 * @param int $post_id The ID of the post
 * @param int $user_id The ID of the user
 * @return bool True if the user is the author, false otherwise
 */
function is_user_post_author($post_id, $user_id) {
    $post = get_post($post_id);
    if ($post && $post->post_author == $user_id) {
        return true;
    }
    return false;
}

/**
 * Optimized function to create error responses
 */
function api_error($message, $action_helper = null) {
    $data['validation']['status'] = false;
    $data['validation']['error_message'] = $message;
    if(!empty($action_helper)) {
        $data['validation']['action_helper'] = $action_helper;
    }
    $response = new WP_REST_Response($data);
    $response->set_status(200);
    return $response;
}

/**
 * Update user's last activity based on ID or username
 */
function update_user_last_activity($user_id_or_username) {
    if (is_int($user_id_or_username)) {
        $user_id = $user_id_or_username;
    } elseif (is_string($user_id_or_username)) {
        $user = get_user_by('login', $user_id_or_username);
        if ($user) {
            $user_id = $user->ID;
        } else {
            return;
        }
    } else {
        return;
    }
    $current_time = current_time('Y-m-d H:i:s');
    update_field('user_last_activity', $current_time, "user_$user_id");
}

/**
 * Return user's data
 */
function user_data_profile($user_id) {
    $data = array();
    $data['token'] = get_field('user_token', "user_$user_id");
    $data['username'] = get_user_by('id', $user_id)->user_login;
    $data['email'] = get_user_by('id', $user_id)->user_email;
    return $data;
}