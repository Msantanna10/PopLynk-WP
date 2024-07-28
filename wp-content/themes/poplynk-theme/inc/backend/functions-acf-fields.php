<?php
/**
 * ACF custom folder
 */
add_filter('acf/settings/save_json', 'acf_custom_folder');
function acf_custom_folder( $path ) {
    $path = get_stylesheet_directory() . '/acf-json';
    return $path;
}

/**
 * Close all accordions by default
 */
add_action('admin_head', function(){ ?>
<script type="text/javascript">
jQuery(function($){
    // Close all acf layout by default
    $('.acf-flexible-content .layout').addClass('-collapsed');
    // Close all accordions
    $('.acf-field-accordion').each(function(){
        $(this).removeClass('-open');
        $(this).find('.acf-accordion-content').hide();
    });

    // Disable sorting for specific repeaters
    $('[data-name="youtube_channel_subscriber_goals"] table.acf-table td.order').removeClass("order");

    // Block adding or removing rows
    // $('[data-name="youtube_channel_subscriber_goals"] .acf-actions, [data-name="youtube_channel_subscriber_goals"] .remove').remove();
});
</script>
<?php });

/**
 * ACF readonly fields
 */
add_filter( 'acf/load_field', 'acf_read_only_fields' );
function acf_read_only_fields( $field ) {
    $fields = array('youtube_video_id', 'youtube_channel_etag', 'youtube_channel_custom_url', 'youtube_channel_published', 'youtube_channel_published', 'youtube_channel_country', 'youtube_channel_views', 'youtube_channel_subscribers', 'youtube_channel_videos', 'youtube_channel_description', 'youtube_channel_image');
    if( in_array($field['name'], $fields) ) {
        $field['readonly'] = true;
    }
    else {
        $field['readonly'] = false;
    }
    return $field;
}

/**
 * CSS for ACF
 */
add_action('admin_footer', 'custom_admin_css_js_acf');
function custom_admin_css_js_acf() { ?>
<style>
    .acf-repeater .acf-actions .acf-button {float: left !important}
    .acf-checkbox-list label {cursor: pointer;}
    button[disabled=""], button[disabled="disabled"] {cursor: no-drop !important;}
    .acf-postbox .acf-hndle-cog {height: 34px;}
    .acf-postbox .postbox-header .handle-actions {display: flex;align-items: center;}
</style>
<script>
jQuery(function($){
    $(document).ready(function() {
        function updateLabels() {
            var goals = $('[data-name="youtube_video_goals"], [data-name="youtube_channel_subscriber_goals"]');
            if(goals.length > 0) {
                goals.find('table tr.acf-row:visible').each(function(index, item) {
                    var accordion_label = $(item).find('.acf-accordion-title > label');

                    // Ensure the label text is cleared of any existing numbering
                    var labelText = accordion_label.text().replace(/#\d+/, '#').replace(/ \|.*/, ''); // Remove existing number and any text after |

                    accordion_label.html('Goal #');

                    // Add the correct sequence number only to "#"
                    labelText = labelText.replace(/#$/, '#' + (index + 1));

                    // Count the number of "|" characters in the label text
                    var pipeCount = (labelText.match(/\|/g) || []).length;

                    // Append progress and subscribers text based on the pipe count
                    if (pipeCount === 0) {
                        var progressText = $(item).find('[data-name="progress"] select option:selected').text();
                        if (progressText) {
                            labelText += ' | ' + progressText;
                            pipeCount++; // Increment pipe count since we added one
                        }
                    }

                    if (pipeCount === 1) {
                        var subscribersText = $(item).find('[data-name="subscribers"] select option:selected').text();
                        if (subscribersText) {
                            labelText += ' | ' + subscribersText;
                        }
                    }

                    accordion_label.html(labelText);
                });
            }
        }

        function checkAndDisableOptions() {
            var channelSubscribers = $('[data-name="youtube_channel_subscribers"] input').val();
            if (channelSubscribers) {
                channelSubscribers = parseInt(channelSubscribers, 10);

                $('[data-name="subscribers"] select').each(function() {
                    var select = $(this);
                    select.find('option').each(function() {
                        var option = $(this);
                        var optionValue = parseInt(option.val(), 10);
                        if (optionValue <= channelSubscribers) {
                            option.prop('disabled', true);
                            if (!option.text().includes(" - Meta alcançada")) {
                                option.text(option.text() + " - Meta alcançada");
                            }
                        } else {
                            option.prop('disabled', false);
                            option.text(option.text().replace(" - Meta alcançada", ""));
                        }
                    });
                });
            }
        }

        updateLabels();
        checkAndDisableOptions();

        // Listen to click events on the add button and update labels and check options after adding a new row
        $('[data-name="youtube_video_goals"] .acf-actions a.button, [data-name="youtube_channel_subscriber_goals"] .acf-actions a.button').on('click', function(){
            setTimeout(() => {
                updateLabels();
                checkAndDisableOptions();
            }, 0); // Sufficient delay to ensure the new row is added
        });

        // Listen to changes in the youtube_channel_subscribers input and update options accordingly
        $('[data-name="youtube_channel_subscribers"] input').on('input', function() {
            checkAndDisableOptions();
        });

        // Listen to changes in the subscribers select fields and re-run updateLabels
        $('body').on('change', '[data-name="progress"] select, [data-name="subscribers"] select', function() {
            updateLabels();
        });
    });
});
</script>
<?php }

/**
 * Hide ACF fields from the admin
 */
add_filter("acf/prepare_field/name=chat_pending", "acf_hide_fields_admin");
add_filter("acf/prepare_field/name=chat_last_message", "acf_hide_fields_admin");
add_filter("acf/prepare_field/name=user_last_message_type", "acf_hide_fields_admin");
function acf_hide_fields_admin($field) {
    return false;
}