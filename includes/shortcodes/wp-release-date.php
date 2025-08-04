<?php

namespace TheRepo\Shortcode\WordPressReleaseDate;

function display_wp_release_date($atts) {
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

    $cache_key = 'wp_release_date_' . md5($wporg_slug);
    $cached_data = get_transient($cache_key);

    if ($cached_data !== false) {
        $data = $cached_data;
    } else {
        $api_response = plugins_api('plugin_information', [
            'slug' => $wporg_slug,
            'fields' => [
                'last_updated' => true,
            ],
        ]);

        if (is_wp_error($api_response)) {
            error_log('WordPress API Error: ' . $api_response->get_error_message());
            return '<p>Release date information not available.</p>';
        }

        $data = $api_response;
        // Cache for 1 hour
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
    }

    if (isset($data->last_updated) && !empty($data->last_updated)) {
        $time_diff = human_time_diff(strtotime($data->last_updated), current_time('timestamp'));
        return '<p>' . esc_html($time_diff) . ' ago</p>';
    }

    return '<p>Release date information not available.</p>';
}
add_shortcode('wp_release_date', __NAMESPACE__ . '\\display_wp_release_date');