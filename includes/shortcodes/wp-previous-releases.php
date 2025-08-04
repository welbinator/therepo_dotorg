<?php
namespace TheRepo\Shortcode\WordPressPreviousReleases;

function display_wp_previous_releases($atts) {
    $GLOBALS['the_repo_should_enqueue_assets'] = true;

    $post_id = get_the_ID();
    // Check if the hosting platform is WordPress
    $hosting_platform = get_post_meta($post_id, 'hosting_platform', true);
    
    if ($hosting_platform !== 'wordpress') {
        return ''; // Return empty string if not hosted on WordPress.org
    }

    $wporg_slug = get_post_meta($post_id, 'wporg_slug', true);

    if (empty($wporg_slug)) {
        return '<p>Invalid or missing WordPress.org slug.</p>';
    }

    // Ensure plugins_api is available
    if (!function_exists('plugins_api')) {
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    }

    $cache_key = 'wp_previous_releases_' . md5($wporg_slug);
    $cached_data = get_transient($cache_key);

    if ($cached_data !== false) {
        $data = $cached_data;
    } else {
        $api_response = plugins_api('plugin_information', [
            'slug' => $wporg_slug,
            'fields' => [
                'versions' => true,
            ],
        ]);

        if (is_wp_error($api_response)) {
            error_log('WordPress API Error: ' . $api_response->get_error_message());
            return '<p>No previous releases found.</p>';
        }

        $data = $api_response;
        // Cache for 1 hour
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
    }

    if (!isset($data->versions) || !is_array($data->versions) || count($data->versions) < 2) {
        return '<p>No previous releases found.</p>';
    }

    // Sort versions in descending order
    $versions = (array) $data->versions;
    uksort($versions, function($a, $b) {
        return version_compare($b, $a);
    });

    // Skip the latest version and take the next 5
    $previous_versions = array_slice($versions, 1, 5, true);

    ob_start();
    ?>
    <div class="previous-releases">
        <ul class="space-y-2 !list-none !m-0">
            <?php
            foreach ($previous_versions as $version => $download_url) {
                if (!empty($version) && !empty($download_url)) {
                    // Use last_updated for the date if available, or fallback to no date
                    
                    $version = esc_html($version);
                    $download_url = esc_url($download_url);
                    echo "<li><a href=\"{$download_url}\" class=\"text-blue-600 hover:underline\">Download Version {$version}</a>";
                    
                    echo "</li>";
                }
            }
            ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('wp_previous_releases', __NAMESPACE__ . '\\display_wp_previous_releases');