<?php

namespace TheRepo\Functions;

function allow_subscribers_to_edit_own_posts() {
    $subscriber_role = get_role('subscriber');
    if ($subscriber_role) {
        // Grant the capabilities to edit their own posts
        $subscriber_role->add_cap('edit_posts');
        $subscriber_role->add_cap('edit_published_posts');
    }
}
add_action('init', __NAMESPACE__ . '\\allow_subscribers_to_edit_own_posts');

function restrict_subscriber_posts($query) {
    if (!is_admin()) {
        return;
    }

    $user = wp_get_current_user();
    if (in_array('subscriber', $user->roles) && $query->is_main_query() && $query->get('post_type') === 'post') {
        $query->set('author', $user->ID);
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


