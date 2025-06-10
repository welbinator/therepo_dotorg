<?php
namespace TheRepo\Shortcode;
use function TheRepo\Functions\fetch_github_data;

function previous_releases() {
    $GLOBALS['the_repo_should_enqueue_assets'] = true;


    $post_id = get_the_ID();
    $github_url = get_post_meta($post_id, 'latest_release_url', true);

    if (empty($github_url)) {
        return '<p>No GitHub URL provided.</p>';
    }

    // Extract the owner/repo from the GitHub URL
    $repo = '';
    if (preg_match('#github\.com/(repos/)?([^/]+/[^/]+)#', $github_url, $matches)) {
        $repo = $matches[2];
    }

    $api_url = "https://api.github.com/repos/{$repo}/releases?per_page=6";

    $cache_key = 'recent_releases_' . md5($api_url);
    $releases = \TheRepo\Functions\fetch_github_data($api_url, $cache_key);

    if (!is_array($releases) || count($releases) < 2) {
        return '<p>No previous releases found.</p>';
    }

    ob_start();
    ?>
    <div class="previous-releases">
        <h3>Previous Versions</h3>
        <ul class="space-y-2 !list-none !m-0">
            <?php
            // Skip the first release (the latest one already handled by the magic button)
       
            $releases = array_slice($releases, 1, 5);
            foreach ($releases as $release) {
                $tag = esc_html($release['tag_name'] ?? '');
                $published = esc_html(date('F j, Y', strtotime($release['published_at'] ?? '')));
                $download_url = '';

                // Prefer manually uploaded assets
                if (!empty($release['assets'][0]['browser_download_url'])) {
                    $download_url = esc_url($release['assets'][0]['browser_download_url']);
                } elseif (!empty($release['html_url']) && !empty($tag)) {
                    // Fallback to source zip
                    $download_url = "https://github.com/{$repo}/archive/refs/tags/{$tag}.zip";
                }

                if ($download_url) {
                    echo "<li><a href=\"{$download_url}\" class=\"text-blue-600 hover:underline\">Download Version {$tag}</a> <span class=\"text-gray-500 text-sm\">({$published})</span></li>";
                }
            }
            ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('previous_releases', __NAMESPACE__ . '\\previous_releases');
