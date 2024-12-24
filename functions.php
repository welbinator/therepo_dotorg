<?php

namespace TheRepo\Functions;

function allow_subscribers_to_edit_own_posts() {
    $subscriber_role = get_role('subscriber');
    if ($subscriber_role) {
        $subscriber_role->add_cap('edit_posts');
        $subscriber_role->add_cap('edit_published_posts');
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
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    $user = wp_get_current_user();

    if (in_array('subscriber', (array) $user->roles)) {
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'post.php') !== false) {
            return;
        }

        wp_redirect(home_url());
        exit;
    }
});

function fetch_github_data($url, $cache_key, $expiration = DAY_IN_SECONDS) {
    delete_transient($cache_key);
    $cached_data = get_transient($cache_key);
   
    if ($cached_data !== false) {
        return $cached_data;
    }

    $response = wp_remote_get($url, [
        'headers' => [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WordPress GitHub Fetcher',
            
        ],
    ]);
    
    if (is_wp_error($response)) {
        error_log('GitHub API Error: ' . $response->get_error_message());
        return null;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    
    if (!empty($data)) {
        set_transient($cache_key, $data, $expiration);
    }

    return $data;
}

function display_latest_version($atts) {
    $post_id = get_the_ID();
    $github_url = get_post_meta($post_id, 'latest_release_url', true);

    if (empty($github_url) || !filter_var($github_url, FILTER_VALIDATE_URL)) {
        return 'Invalid or missing GitHub URL.';
    }

    $cache_key = 'latest_version_' . md5($github_url);
    $data = fetch_github_data($github_url, $cache_key);

    if (isset($data['tag_name'])) {
        return '<p>' . esc_html($data['tag_name']) . '</p>';
    }

    return 'Version information could not be retrieved.';
}
add_shortcode('latest_version', __NAMESPACE__ . '\\display_latest_version');

function fetch_latest_release_date($atts) {
    $post_id = get_the_ID();
    $github_url = get_post_meta($post_id, 'latest_release_url', true);

    if (empty($github_url)) {
        return '<p>No GitHub URL provided.</p>';
    }

    $cache_key = 'latest_release_date_' . md5($github_url);
    $data = fetch_github_data($github_url, $cache_key);

    if (isset($data['created_at'])) {
        $release_date = date('F j, Y', strtotime($data['created_at']));
        return '<p>' . esc_html($release_date) . '</p>';
    }

    return '<p>Release date information not available.</p>';
}
add_shortcode('latest_release_date', __NAMESPACE__ . '\\fetch_latest_release_date');

function fetch_latest_release_download_count($atts) {
    $post_id = get_the_ID();

    // Retrieve owner and repo directly from meta
    $github_owner = get_post_meta($post_id, 'github_username', true);
    $github_repo = get_post_meta($post_id, 'github_repo', true);

    if (empty($github_owner) || empty($github_repo)) {
        error_log('GitHub owner or repo is missing.');
        return '<p>No GitHub owner or repository provided.</p>';
    }

    
    // Construct API URL
    $api_url = "https://api.github.com/repos/{$github_owner}/{$github_repo}/releases";
   

    // Fetch data
    $cache_key = 'all_releases_downloads_' . md5($api_url);
    $data = fetch_github_data($api_url, $cache_key);

    if (is_array($data)) {
        $total_downloads = 0;

        foreach ($data as $release) {
            if (isset($release['assets']) && is_array($release['assets'])) {
                $release_downloads = array_reduce($release['assets'], function ($carry, $item) {
                    return $carry + ($item['download_count'] ?? 0);
                }, 0);

                $total_downloads += $release_downloads;
            }
        }

        return '<p>' . esc_html(number_format($total_downloads)) . '</p>';
    }

    return '<p>No download information available.</p>';
}

add_shortcode('number_of_downloads', __NAMESPACE__ . '\\fetch_latest_release_download_count');