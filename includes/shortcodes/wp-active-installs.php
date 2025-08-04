<?php

namespace TheRepo\Shortcode\WordPressActiveInstallations;

function display_wp_active_installations($atts) {
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

    $cache_key = 'wp_active_installs_' . md5($wporg_slug);
    $cached_data = get_transient($cache_key);

    if ($cached_data !== false) {
        $data = $cached_data;
    } else {
        $api_response = plugins_api('plugin_information', [
            'slug' => $wporg_slug,
            'fields' => [
                'active_installs' => true,
            ],
        ]);

        if (is_wp_error($api_response)) {
            error_log('WordPress API Error: ' . $api_response->get_error_message());
            return '<p>Active installations information not available.</p>';
        }

        $data = $api_response;
        // Cache for 1 hour
        set_transient($cache_key, $data, HOUR_IN_SECONDS);
    }

    if (isset($data->active_installs) && is_numeric($data->active_installs)) {
        $installs = $data->active_installs;
        $formatted_installs = format_active_installs($installs);
        return '<p>' . esc_html($formatted_installs) . '</p>';
    }

    return '<p>Active installations information not available.</p>';
}

/**
 * Format active installations into WordPress.org style (e.g., "10+", "100,000+").
 *
 * @param int $installs The number of active installations.
 * @return string The formatted string.
 */
function format_active_installs($installs) {
    if ($installs < 10) {
        return 'Fewer than 10';
    } elseif ($installs < 100) {
        return '10+';
    } elseif ($installs < 1000) {
        return '100+';
    } elseif ($installs < 10000) {
        return number_format(floor($installs / 1000) * 1000) . '+';
    } elseif ($installs < 100000) {
        return number_format(floor($installs / 10000) * 10000) . '+';
    } elseif ($installs < 1000000) {
        return number_format(floor($installs / 100000) * 100000) . '+';
    } else {
        return '1,000,000+';
    }
}

add_shortcode('wp_active_installations', __NAMESPACE__ . '\\display_wp_active_installations');