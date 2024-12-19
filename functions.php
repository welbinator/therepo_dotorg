<?php 

namespace TheRepo\Functions;

function allow_subscribers_to_edit_own_posts() {
    $subscriber_role = get_role('subscriber');
    if ($subscriber_role) {
        // Grant the capabilities to edit their own posts
        $subscriber_role->add_cap('edit_posts');
        $subscriber_role->add_cap('edit_published_posts');

        // Allow subscribers to upload files
        $subscriber_role->add_cap('upload_files');
    }
}
add_action('init', __NAMESPACE__ . '\\allow_subscribers_to_edit_own_posts');

function restrict_subscriber_posts($query) {
    if (!is_admin()) {
        return;
    }

    $user = wp_get_current_user();
    if (in_array('subscriber', $user->roles) && $query->is_main_query()) {
        $post_types = ['post', 'plugin', 'theme_repo'];
        if (in_array($query->get('post_type'), $post_types)) {
            $query->set('author', $user->ID);
        }
    }
}
add_action('pre_get_posts', __NAMESPACE__ . '\\restrict_subscriber_posts');

function restrict_subscriber_capabilities() {
    $subscriber_role = get_role('subscriber');
    if ($subscriber_role) {
        $subscriber_role->remove_cap('publish_posts');
        $subscriber_role->remove_cap('delete_posts');
    }
}
add_action('init', __NAMESPACE__ . '\\restrict_subscriber_capabilities');

add_filter('upload_mimes', function ($mimes) {
    if (current_user_can('subscriber')) {
        return [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
        ];
    }
    return $mimes;
});

add_filter('ajax_query_attachments_args', function ($query) {
    if (current_user_can('subscriber')) {
        $query['author'] = get_current_user_id();
    }
    return $query;
});

