<?php
/**
 * Disable Gutenberg on the back end
 */
add_filter( 'use_block_editor_for_post', '__return_false' );

/**
 * Disable Gutenberg for widgets
 */
add_filter( 'use_widgets_blog_editor', '__return_false' );

/**
 * Remove CSS on the front end
 */
add_action( 'wp_enqueue_scripts', function() {
    wp_dequeue_style( 'wp-block-library' );
    wp_dequeue_style( 'global-styles' );
}, 20 );

/**
 * Remove classic editor
 */
add_action('admin_init', 'page_remove_editor');
function page_remove_editor() {
    remove_post_type_support('page', 'editor');
    remove_post_type_support('post', 'editor');
}

/**
 * Remove menu items
 */
add_action( 'admin_menu', 'remove_default_post_type' );
function remove_default_post_type() {
    remove_menu_page( 'edit.php' );
}

/**
 * Remove tags support from posts
 */
add_action('init', 'unregister_post_tags');
function unregister_post_tags() {
    unregister_taxonomy_for_object_type('post_tag', 'post');
}

/**
 * Disable tags for posts
 */
add_action('init', 'ilang_unregister_tags');
function ilang_unregister_tags() {
    unregister_taxonomy_for_object_type('post_tag', 'post');
}

/**
 * Disable comments from post types
 */
add_action('admin_init', 'disable_comments');
function disable_comments() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if(post_type_supports($post_type,'comments')) {
            remove_post_type_support($post_type,'comments');
            remove_post_type_support($post_type,'trackbacks');
        }
    }
}

/**
 * Disable comments in the admin
 */
add_action('admin_menu', 'remove_comments_admin_menu');
function remove_comments_admin_menu() {
    remove_menu_page('edit-comments.php');
}

/**
 * Disable notification on email change
 */
add_filter( 'send_email_change_email', '__return_false' );

/**
 * Disable core update emails
 */
add_filter( 'auto_core_update_send_email', '__return_false' );

/**
 * Disable plugin update emails
 */
add_filter( 'auto_plugin_update_send_email', '__return_false' );

/**
 * Disable theme update emails
 */
add_filter( 'auto_theme_update_send_email', '__return_false' );

/**
 * Disable support for comments
 */
add_action('admin_init', 'disable_comments_post_types_support');
function disable_comments_post_types_support() {
    $post_types = get_post_types();
    foreach ($post_types as $post_type) {
        if(post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
}

/**
 * Close comments on the front-end
 */
add_filter('comments_open', 'disable_comments_status', 20, 2);
add_filter('pings_open', 'disable_comments_status', 20, 2);
function disable_comments_status() {
    return false;
}

/**
 * Hide existing comments
 */
add_filter('comments_array', 'disable_comments_hide_existing_comments', 10, 2);
function disable_comments_hide_existing_comments($comments) {
    $comments = array();
    return $comments;
}

/**
 * Remove comment form from display
 */
add_action('init', 'disable_comments_remove_comment_form');
function disable_comments_remove_comment_form() {
    remove_action('comment_form', 'comment_form');
}

/**
 * Disable language switcher on login page
 */
add_action('login_display_language_dropdown', '__return_false');