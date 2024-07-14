<?php

/**
 * Admin CSS & JS
 */
add_action('admin_head', 'custom_admin_css_js');
function custom_admin_css_js() { ?>
<style>
</style>

<script type="text/javascript">
jQuery(function($){
    $(document).ready(function() {
        // Youtube API
        function get_channel_data(channel_id, button) {
            const api_key = '<?php echo YOUTUBE_API; ?>';
            const url = `https://www.googleapis.com/youtube/v3/channels?part=snippet,contentDetails,statistics&id=${channel_id}&key=${api_key}`;
            button.attr('disabled', 'disabled');
            $.ajax({
                url: url,
                method: 'GET',
                success: function(response) {
                    if (response.items && response.items.length > 0) {
                        const channel = response.items[0];
                        $('#titlediv #title').val(channel.snippet.title);
                        $('#titlediv #title-prompt-text').addClass('screen-reader-text');
                        $('[data-name="youtube_channel_etag"] input').val(channel.etag);
                        $('[data-name="youtube_channel_image"] input').val(channel.snippet.thumbnails.medium.url);

                        /**
                         * Clean up the description
                         */
                        let description = channel.snippet.description;
                        // Trim leading and trailing line breaks
                        description = description.replace(/^\n+|\n+$/g, '');
                        // Replace single line breaks with two
                        description = description.replace(/\n/g, '\n\n');
                        // Replace more than two consecutive line breaks with two
                        description = description.replace(/(\n\n)+/g, '\n\n');
                        $('[data-name="youtube_channel_description"] textarea').val(description);
                        $('[data-name="youtube_channel_custom_url"] input').val(channel.snippet.customUrl);
                        $('[data-name="youtube_channel_country"] input').val(channel.snippet.country);
                        
                        /**
                         * Parse the publishedAt date
                         */
                        const publishedAt = channel.snippet.publishedAt;
                        const [datePart, timePart] = publishedAt.split('T');
                        const time = timePart.replace('Z', '');
                        // Format for internal storage: YYYY-MM-DD HH:MM:SS
                        const internalFormattedDate = `${datePart} ${time}`;
                        // Format for display: MM d, yy h:mm tt
                        const dateObject = new Date(publishedAt);
                        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
                        const hours = dateObject.getUTCHours();
                        const minutes = String(dateObject.getUTCMinutes()).padStart(2, '0');
                        const period = hours >= 12 ? 'pm' : 'am';
                        const displayHours = hours % 12 || 12; // Convert 0 to 12 for 12 AM
                        const displayFormattedDate = `${monthNames[dateObject.getUTCMonth()]} ${dateObject.getUTCDate()}, ${dateObject.getUTCFullYear()} ${displayHours}:${minutes} ${period}`;
                        // Set the value of the ACF field for internal storage
                        $('[data-name="youtube_channel_published"] input').val(displayFormattedDate);

                        $('[data-name="youtube_channel_views"] input').val(channel.statistics.viewCount);
                        $('[data-name="youtube_channel_subscribers"] input').val(channel.statistics.subscriberCount);                        
                        $('[data-name="youtube_channel_videos"] input').val(channel.statistics.videoCount);
                    } else {
                        alert('Channel NOT FOUND');
                    }
                    button.removeAttr('disabled');
                },
                error: function(err) {
                    alert('Error fetching channel data:', err);
                    button.removeAttr('disabled');
                }
            });
        }
        // Revalidate Channel on Click
        $('.post-type-youtube_channels button#ajax-revalidate').on('click', function(){
            var channel_id = $('[data-name="youtube_channel_id"] input').val();
            get_channel_data(channel_id, $(this));
        });
    });
});
</script>
<?php }