<?php
/**
 * Custom statuses
 */
add_action('init', function() {
    register_post_status('paused', array(
        'label'                     => 'Paused',
        'public'                    => true,
        'exclude_from_search'       => false,
        'show_in_admin_all_list'    => true,
        'show_in_admin_status_list' => true,
        'label_count'               => _n_noop('Paused <span class="count">(%s)</span>', 'Paused <span class="count">(%s)</span>')
    ));
});

/**
 * Add new options to dropdown and update button text
 */
add_action('admin_footer', function() { ?>
<script>
    jQuery(document).ready(function() {
        var statusSelect = jQuery('select[name*="_status"]');
        statusSelect.find('option[value="draft"]').remove();
        statusSelect.find('option[value="pending"]').remove();
        statusSelect.append(`
            <option value="paused">Paused</option>
        `);
    });
    </script>
<?php });

/**
 * Add label in front of titles
 */
add_filter('display_post_states', function($post_states, $post) {
    $is_video = ('youtube_videos' === get_post_type($post));
    $is_channel = ('youtube_channels' === get_post_type($post));
    if ($is_video && get_post_status($post) == 'paused' || $is_channel && get_post_status($post) == 'paused') {
        $post_states['paused'] = 'Paused';
    }
    return $post_states;
}, 10, 2);