<?php

namespace TheRepo\Functions;

/**
 * Allow subscribers to edit their own posts and upload files.
 */
function allow_subscribers_to_edit_own_posts() {
    $subscriber_role = get_role('subscriber');
    if ($subscriber_role) {
        $subscriber_role->add_cap('edit_posts');
        $subscriber_role->add_cap('edit_published_posts');
        $subscriber_role->add_cap('upload_files');
    }
}
add_action('init', __NAMESPACE__ . '\\allow_subscribers_to_edit_own_posts');

/**
 * Redirect subscribers away from wp-admin, except for specific pages.
 */
add_action('admin_init', function () {
    $user = wp_get_current_user();

    if (in_array('subscriber', (array) $user->roles)) {
        // Allow access to post.php for editing posts
        $allowed_scripts = [
            'post.php', // Post editor
        ];

        if (!defined('DOING_AJAX') && !wp_doing_ajax()) {
            $script_name = basename($_SERVER['SCRIPT_NAME']);

            if (!in_array($script_name, $allowed_scripts)) {
                wp_redirect(home_url());
                exit;
            }
        }
    }
});

/**
 * Remove admin bar for subscribers.
 */
add_action('after_setup_theme', function () {
    if (current_user_can('subscriber')) {
        show_admin_bar(false);
    }
});

/**
 * Restrict subscribers to only view their own posts in the admin area.
 */
function restrict_subscriber_posts($query) {
    if (is_admin() && $query->is_main_query()) {
        $user = wp_get_current_user();
        if (in_array('subscriber', $user->roles)) {
            $query->set('author', $user->ID);
        }
    }
}
add_action('pre_get_posts', __NAMESPACE__ . '\\restrict_subscriber_posts');

/**
 * Ensure subscribers can edit published posts but can't publish new ones.
 */
function enforce_post_editing_rules($data, $postarr) {
    $user = wp_get_current_user();

    if (in_array('subscriber', $user->roles)) {
        $original_post = !empty($postarr['ID']) ? get_post($postarr['ID']) : null;

        if ($original_post && $original_post->post_status === 'publish') {
            // Allow updates to published posts.
            $data['post_status'] = 'publish';
        } else {
            // For new posts or other cases, require review.
            $data['post_status'] = 'pending';
        }
    }

    return $data;
}
add_filter('wp_insert_post_data', __NAMESPACE__ . '\\enforce_post_editing_rules', 10, 2);

/**
 * Restrict media library access to only the subscriber's uploads.
 */
add_filter('ajax_query_attachments_args', function ($query) {
    if (current_user_can('subscriber')) {
        $query['author'] = get_current_user_id();
    }
    return $query;
});
