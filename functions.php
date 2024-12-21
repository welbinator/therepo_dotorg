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

add_action('admin_init', function () {
    // Allow AJAX requests for all users
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    // Get the current user
    $user = wp_get_current_user();

    // Check if the user is a subscriber
    if (in_array('subscriber', (array) $user->roles)) {
        // Allow access to wp-admin/post.php
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'post.php') !== false) {
            return;
        }

        // Redirect all other wp-admin access to the homepage
        wp_redirect(home_url());
        exit;
    }
});


function display_latest_version($atts) {
    $post_id = get_the_ID();
    $github_url = get_post_meta($post_id, 'latest_release_url', true);

    if (empty($github_url) || !filter_var($github_url, FILTER_VALIDATE_URL)) {
        return 'Invalid or missing GitHub URL.';
    }

    $response = wp_remote_get($github_url, [
        'headers' => [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress GitHub Version Fetcher',
        ],
    ]);

    if (is_wp_error($response)) {
        return 'Error: ' . $response->get_error_message();
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (isset($data['tag_name'])) {
        return '<p>' . esc_html($data['tag_name']) . '</p>';
    }
    

    return 'Version information could not be retrieved.';
}

add_shortcode('latest_version', __NAMESPACE__ . '\\display_latest_version');

function fetch_latest_release_date($atts) {
    // Get the repository URL from the custom field
    $github_url = get_post_meta(get_the_ID(), 'latest_release_url', true);

    if (empty($github_url)) {
        return '<p>No GitHub URL provided.</p>';
    }

    // Fetch data from the GitHub API
    $response = wp_remote_get($github_url, [
        'headers' => [
            'Accept' => 'application/vnd.github.v3+json',
        ]
    ]);

    if (is_wp_error($response)) {
        return '<p>Failed to fetch release date information.</p>';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    // Check for created_at in the API response
    if (isset($data['created_at'])) {
        // Format the date
        $release_date = date('F j, Y', strtotime($data['created_at']));

        return '<p>' . esc_html($release_date) . '</p>';
    } else {
        return '<p>Release date information not available.</p>';
    }
}
add_shortcode('latest_release_date', __NAMESPACE__ . '\\fetch_latest_release_date');

function fetch_latest_release_download_count($atts) {
    $post_id = get_the_ID();
    $github_url = get_post_meta($post_id, 'latest_release_url', true);

    if (empty($github_url)) {
        return '<p>No GitHub URL provided.</p>';
    }

    $response = wp_remote_get($github_url, [
        'headers' => [
            'Accept' => 'application/vnd.github.v3+json',
        ]
    ]);

    if (is_wp_error($response)) {
        error_log('GitHub API Error: ' . $response->get_error_message());
        return '<p>Failed to fetch download count information.</p>';
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($data['assets']) && is_array($data['assets'])) {
        $total_downloads = 0;

        foreach ($data['assets'] as $asset) {
            $total_downloads += $asset['download_count'];
        }

        return '<p>' . esc_html(number_format($total_downloads)) . '</p>';
    } else {
        error_log('GitHub API Data Error: ' . print_r($data, true));
        return '<p>No download information available.</p>';
    }
}
add_shortcode('latest_release_downloads', __NAMESPACE__ . '\\fetch_latest_release_download_count');




