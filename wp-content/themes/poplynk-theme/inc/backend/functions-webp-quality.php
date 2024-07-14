<?php
/**
 * WebP Quality
 */
function set_webp_quality( $quality, $mime_type ) {
    if ( 'image/webp' === $mime_type ) {
        return 100;
    }
    return $quality;
}
add_filter( 'wp_editor_set_quality', 'set_webp_quality', 10, 2 );