<?php

namespace TheRepo\Shortcode\GitHubDownloads;

// Import the fetch_github_data function from the TheRepo\Functions namespace
use TheRepo\Functions\fetch_github_data;

function fetch_latest_release_download_count($atts) {
    $post_id = get_the_ID();
    // Check if the hosting platform is GitHub
    $hosting_platform = get_post_meta($post_id, 'hosting_platform', true);
    
    if ($hosting_platform !== 'github') {
        return ''; // Return empty string if not hosted on GitHub
    }

    $github_owner = get_post_meta($post_id, 'github_username', true);
    $github_repo = get_post_meta($post_id, 'github_repo', true);

    if (empty($github_owner) || empty($github_repo)) {
        error_log('GitHub owner or repo is missing.');
        return '<p>No GitHub owner or repository provided.</p>';
    }

    $api_url = "https://api.github.com/repos/{$github_owner}/{$github_repo}/releases";
    $cache_key = 'all_releases_downloads_' . md5($api_url);
    // Call the function with the fully qualified namespace as a fallback
    $data = \TheRepo\Functions\fetch_github_data($api_url, $cache_key);

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