<?php
/**
 * Normalize WP post titles
 */
add_filter( 'the_title', function( $title ) {
    if ( is_admin() ) {
        return to_title_case($title);
    }
    return $title;
});
