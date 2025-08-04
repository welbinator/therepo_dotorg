<?php

namespace TheRepo\Shortcode\GitHubReleaseDate;

// Import the fetch_github_data function from the TheRepo\Functions namespace
use TheRepo\Functions\fetch_github_data;

function fetch_latest_release_date($atts) {
    $post_id = get_the_ID();
    // Check if the hosting platform is GitHub
    $hosting_platform = get_post_meta($post_id, 'hosting_platform', true);
    
    if ($hosting_platform !== 'github') {
        return ''; // Return empty string if not hosted on GitHub
    }

    $github_url = get_post_meta($post_id, 'latest_release_url', true);

    if (empty($github_url)) {
        return '<p>No GitHub URL provided.</p>';
    }

    $cache_key = 'latest_release_date_' . md5($github_url);
    // Call the function with the fully qualified namespace as a fallback
    $data = \TheRepo\Functions\fetch_github_data($github_url, $cache_key);

    if (isset($data['created_at'])) {
        $release_date = date('F j, Y', strtotime($data['created_at']));
        return '<p>' . esc_html($release_date) . '</p>';
    }

    return '<p>Release date information not available.</p>';
}
add_shortcode('latest_release_date', __NAMESPACE__ . '\\fetch_latest_release_date');