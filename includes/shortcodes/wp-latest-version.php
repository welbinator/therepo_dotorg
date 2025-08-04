<?php

namespace TheRepo\Shortcode\WordPressLatestVersion;

function display_wp_latest_version($atts) {
    $post_id = get_the_ID();
    // Check if the hosting platform is WordPress
    $hosting_platform = get_post_meta($post_id, 'hosting_platform', true);
    
    if ($hosting_platform !== 'wordpress') {
        return ''; // Return empty string if not hosted on WordPress.org
    }

    $wporg_slug = get_post_meta($post_id, 'wporg_slug', true);

    if (empty($wporg_slug)) {
        return '<p>No WordPress.org plugin slug provided.</p>';
    }

    // Create a cache key based on the slug
    $cache_key = 'wp_latest_version_' . md5($wporg_slug);
    $cached_data = get_transient($cache_key);

    if (false !== $cached_data) {
        $data = $cached_data;
    } else {
        // Include the plugin-install.php file for plugins_api()
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        $request_args = [
            'slug'   => $wporg_slug,
            'fields' => [
                'version' => true, // Request only the version field
            ],
        ];

        $data = plugins_api('plugin_information', $request_args);

        // Cache the result for 1 hour
        if (!is_wp_error($data)) {
            set_transient($cache_key, $data, HOUR_IN_SECONDS);
        }
    }

    if (!is_wp_error($data) && isset($data->version)) {
        $version = esc_html($data->version);
        return "<p>{$version}</p>";
    }

    return '<p>Version information could not be retrieved.</p>';
}
add_shortcode('wp_latest_version', __NAMESPACE__ . '\\display_wp_latest_version');