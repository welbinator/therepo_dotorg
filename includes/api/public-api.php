<?php
namespace TheRepo\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_Post;

add_action('rest_api_init', function () {
    register_rest_route('the-repo/v1', '/plugin/(?P<slug>[a-zA-Z0-9\-_]+)', [
        'methods'  => 'GET',
        'callback' => __NAMESPACE__ . '\\get_plugin_data',
        'permission_callback' => '__return_true',
        'args' => [
            'slug' => [
                'required' => true
            ]
        ]
    ]);
});

function get_plugin_data(WP_REST_Request $request) {
    $slug = sanitize_title($request['slug']);

    $post = get_page_by_path($slug, OBJECT, ['plugin_repo']);
    if (!$post instanceof WP_Post) {
        return new WP_REST_Response(['error' => 'Plugin not found.'], 404);
    }

    $post_id = $post->ID;

    // Basic info
    $title       = get_the_title($post);
    $description = wp_strip_all_tags($post->post_content);
    $permalink   = get_permalink($post);

    // GitHub info
    $owner = get_post_meta($post_id, 'github_username', true);
    $repo  = get_post_meta($post_id, 'github_repo', true);
    $repo_url = "https://github.com/{$owner}/{$repo}";

    // GitHub API: latest release
    $release_url = get_post_meta($post_id, 'latest_release_url', true);
    $release_data = \TheRepo\Functions\fetch_github_data($release_url, 'latest_version_' . md5($release_url));
    $downloads_data = \TheRepo\Functions\fetch_github_data("https://api.github.com/repos/{$owner}/{$repo}/releases", 'all_releases_downloads_' . md5("https://api.github.com/repos/{$owner}/{$repo}/releases"));
    $repo_meta = \TheRepo\Functions\fetch_github_data("https://api.github.com/repos/{$owner}/{$repo}", 'github_repo_meta_' . md5("https://api.github.com/repos/{$owner}/{$repo}"));

    $tag = $release_data['tag_name'] ?? null;
    $updated = $release_data['published_at'] ?? null;
    $stars = $repo_meta['stargazers_count'] ?? 0;

    // Total downloads
    $download_count = 0;
    if (is_array($downloads_data)) {
        foreach ($downloads_data as $release) {
            if (!empty($release['assets'])) {
                foreach ($release['assets'] as $asset) {
                    $download_count += $asset['download_count'] ?? 0;
                }
            }
        }
    }

    // Generate download URL
    $download_url = null;
    if (!empty($release_data['assets'][0]['browser_download_url'])) {
        $download_url = $release_data['assets'][0]['browser_download_url'];
    } elseif (!empty($release_data['html_url']) && $tag) {
        preg_match('#github\.com/(repos/)?([^/]+/[^/]+)#', $release_data['html_url'], $matches);
        $repo_path = $matches[2] ?? "{$owner}/{$repo}";
        $download_url = "https://github.com/{$repo_path}/archive/refs/tags/{$tag}.zip";
    }

    return new WP_REST_Response([
        'name'          => $title,
        'slug'          => $slug,
        'owner'         => $owner,
        'repo'          => $repo,
        'repo_url'      => $repo_url,
        'homepage'      => $permalink,
        'description'   => $description,
        'download_url'  => $download_url,
        'stars'         => $stars,
        'latest_tag'    => $tag,
        'last_updated'  => $updated,
        'downloads'     => $download_count,
    ]);
}
