<?php

namespace TheRepo\Shortcode\GitHubLatestVersion;

// Import the fetch_github_data function from the TheRepo\Functions namespace
use TheRepo\Functions\fetch_github_data;

function display_latest_version($atts) {
    $post_id = get_the_ID();
    // Check if the hosting platform is GitHub
    $hosting_platform = get_post_meta($post_id, 'hosting_platform', true);
    
    if ($hosting_platform !== 'github') {
        return ''; // Return empty string if not hosted on GitHub
    }

    $github_url = get_post_meta($post_id, 'latest_release_url', true);

    if (empty($github_url) || !filter_var($github_url, FILTER_VALIDATE_URL)) {
        return 'Invalid or missing GitHub URL.';
    }

    $cache_key = 'latest_version_' . md5($github_url);
    // Call the function with the fully qualified namespace as a fallback
    $data = \TheRepo\Functions\fetch_github_data($github_url, $cache_key);

    if (isset($data['tag_name'])) {
        $version = esc_html($data['tag_name']);
        return "<p>{$version}</p>";
    }

    return 'Version information could not be retrieved.';
}
add_shortcode('latest_version', __NAMESPACE__ . '\\display_latest_version');