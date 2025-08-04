<?php

namespace TheRepo\Shortcode\GitHubStars;

// Import the fetch_github_data function from the TheRepo\Functions namespace
use TheRepo\Functions\fetch_github_data;

function github_star_count_shortcode($atts) {
    $post_id = get_the_ID();
    // Check if the hosting platform is GitHub
    $hosting_platform = get_post_meta($post_id, 'hosting_platform', true);
    
    if ($hosting_platform !== 'github') {
        return ''; // Return empty string if not hosted on GitHub
    }

    $github_owner = get_post_meta($post_id, 'github_username', true);
    $github_repo = get_post_meta($post_id, 'github_repo', true);

    if (empty($github_owner) || empty($github_repo)) {
        return '<p>Missing GitHub owner or repo name.</p>';
    }

    $api_url = "https://api.github.com/repos/{$github_owner}/{$github_repo}";
    $cache_key = 'github_repo_meta_' . md5($api_url);
    // Call the function with the fully qualified namespace as a fallback
    $data = \TheRepo\Functions\fetch_github_data($api_url, $cache_key);

    if (isset($data['stargazers_count'])) {
        $stars = number_format($data['stargazers_count']);
        $star_link = "https://github.com/{$github_owner}/{$github_repo}";

        return '<div class="github-star-widget">
                    <p>⭐' . esc_html($stars) . '</p>
                    <a href="' . esc_url($star_link) . '" target="_blank" rel="noopener" class="github-star-button">Star on GitHub</a>
                </div>';
    }

    return '<p>Unable to fetch star count.</p>';
}
add_shortcode('github_star_count', __NAMESPACE__ . '\\github_star_count_shortcode');