<?php

namespace TheRepo\Functions;

// AJAX actions for get_release_data
add_action('wp_ajax_get_release_data', __NAMESPACE__ . '\\ajax_get_release_data');
add_action('wp_ajax_nopriv_get_release_data', __NAMESPACE__ . '\\ajax_get_release_data');

// Allow SVG uploads
add_filter('wp_check_filetype_and_ext', __NAMESPACE__ . '\\allow_svg_filetype', 10, 4);

function allow_svg_filetype($data, $file, $filename, $mimes) {
    $filetype = wp_check_filetype($filename, $mimes);

    return [
        'ext'             => $filetype['ext'],
        'type'            => $filetype['type'],
        'proper_filename' => $data['proper_filename']
    ];
}

add_filter('upload_mimes', __NAMESPACE__ . '\\cc_mime_types');

function cc_mime_types($mimes) {
    $mimes['svg'] = 'image/svg+xml';
    $mimes['jpg|jpeg|jpe'] = 'image/jpeg';
    $mimes['gif'] = 'image/gif';
    $mimes['webp'] = 'image/webp';
    // Debug: Log MIME types
    
    return $mimes;
}

add_action('admin_head', __NAMESPACE__ . '\\fix_svg');

function fix_svg() {
    echo '<style type="text/css">
        .attachment-266x266, .thumbnail img, .wp-post-image {
            width: 100% !important;
            height: auto !important;
            object-fit: contain;
        }
        img[src$=".svg"], img[src$=".jpg"], img[src$=".jpeg"], img[src$=".gif"], img[src$=".webp"] {
            max-width: 100%;
            height: auto;
        }
    </style>';
}

add_filter('upload_mimes', __NAMESPACE__ . '\\restrict_subscriber_mimes');

function restrict_subscriber_mimes($mimes) {
    if (current_user_can('subscriber')) {
        $restricted_mimes = [
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png'          => 'image/png',
            'gif'          => 'image/gif',
            'svg'          => 'image/svg+xml',
            'webp'         => 'image/webp',
        ];
        // Debug: Log restricted MIME types
        error_log('restrict_subscriber_mimes: Restricted Mimes=' . print_r($restricted_mimes, true));
        return $restricted_mimes;
    }
    return $mimes;
}

add_filter('ajax_query_attachments_args', __NAMESPACE__ . '\\restrict_subscriber_attachments');

function restrict_subscriber_attachments($query) {
    if (current_user_can('subscriber')) {
        $query['author'] = get_current_user_id();
    }
    return $query;
}

add_action('admin_init', __NAMESPACE__ . '\\restrict_subscriber_admin_access');

function restrict_subscriber_admin_access() {
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }

    $user = wp_get_current_user();

    if (in_array('subscriber', (array) $user->roles)) {
        if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'post.php') !== false) {
            return;
        }

        wp_redirect(home_url());
        exit;
    }
}

function ajax_get_release_data() {
    $url = isset($_GET['url']) ? esc_url_raw($_GET['url']) : '';

    if (empty($url)) {
        wp_send_json_error(['message' => 'Missing GitHub API URL']);
    }

    $cache_key = 'latest_version_' . md5($url);
    $data = fetch_github_data($url, $cache_key);

    if (!$data || !isset($data['tag_name'])) {
        wp_send_json_error(['message' => 'Invalid release data']);
    }

    if (!empty($data['assets'][0]['browser_download_url'])) {
        wp_send_json_success([
            'download_url' => $data['assets'][0]['browser_download_url'],
        ]);
    }

    if (isset($data['html_url'])) {
        preg_match('#github\.com/([^/]+/[^/]+)/#', $data['html_url'], $matches);
        if (!empty($matches[1])) {
            $repo = $matches[1];
            $tag = $data['tag_name'];
            $source_zip_url = "https://github.com/{$repo}/archive/refs/tags/{$tag}.zip";

            wp_send_json_success([
                'download_url' => $source_zip_url,
            ]);
        }
    }

    wp_send_json_error(['message' => 'No downloadable assets or source zip found.']);
}

function allow_subscribers_to_edit_own_posts() {
    $subscriber_role = get_role('subscriber');
    if ($subscriber_role) {
        $subscriber_role->add_cap('edit_posts');
        $subscriber_role->add_cap('edit_published_posts');
        $subscriber_role->add_cap('upload_files');
        $subscriber_role->add_cap('publish_posts');
    }
}
add_action('init', __NAMESPACE__ . '\\allow_subscribers_to_edit_own_posts');

function restrict_subscriber_posts($query) {
    if (!is_admin()) {
        return;
    }

    $user = wp_get_current_user();
    if (in_array('subscriber', $user->roles) && $query->is_main_query()) {
        $post_types = ['post', 'plugin_repo', 'theme_repo'];
        if (in_array($query->get('post_type'), $post_types)) {
            $query->set('author', $user->ID);
        }
    }
}
add_action('pre_get_posts', __NAMESPACE__ . '\\restrict_subscriber_posts');

function restrict_subscriber_capabilities() {
    $subscriber_role = get_role('subscriber');
    if ($subscriber_role) {
        $subscriber_role->remove_cap('delete_posts');
    }
}
add_action('init', __NAMESPACE__ . '\\restrict_subscriber_capabilities');

function fetch_github_data($url, $cache_key, $expiration = DAY_IN_SECONDS) {
    $cached_data = get_transient($cache_key);

    if ($cached_data !== false) {
        return $cached_data;
    }

    $headers = [
        'Accept'     => 'application/vnd.github.v3+json',
        'User-Agent' => 'WordPress GitHub Fetcher',
    ];

    if (defined('GITHUB_API_TOKEN') && GITHUB_API_TOKEN) {
        $headers['Authorization'] = 'token ' . GITHUB_API_TOKEN;
    } else {
        error_log('GitHub API: No token provided');
    }

    $response = wp_remote_get($url, [
        'headers' => $headers,
    ]);

    if (is_wp_error($response)) {
        error_log('GitHub API Error: ' . $response->get_error_message());
        return null;
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($data)) {
        set_transient($cache_key, $data, $expiration);
    }

    return $data;
}

add_action( 'login_form_register', __NAMESPACE__ . '\\custom_redirect_register' );

function custom_redirect_register() {
    wp_redirect( home_url( '/register' ) );
    exit;
}

add_filter( 'register_users', '__return_false' );





