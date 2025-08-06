<?php
namespace TheRepo\CustomPosts;

// Utility function to sanitize input
function sanitize_post_input($key, $sanitize_callback = 'sanitize_text_field', $default = '') {
    return isset($_POST[$key]) ? call_user_func($sanitize_callback, $_POST[$key]) : $default;
}

// Register custom meta boxes for custom fields
add_action('add_meta_boxes', function () {
    add_meta_box(
        'repo_meta_box',                // Unique ID
        'Repository Details',           // Meta box title
        __NAMESPACE__ . '\\render_repo_meta_box', // Callback function
        ['plugin_repo', 'theme_repo'],       // Post types
        'normal',                       // Context
        'default'                       // Priority
    );
});

// Render the meta box
function render_repo_meta_box($post) {
    $latest_release_url = get_post_meta($post->ID, 'latest_release_url', true);
    $free_or_pro = get_post_meta($post->ID, 'free_or_pro', true);
    $cover_image_url = get_post_meta($post->ID, 'cover_image_url', true);
    $support_url = get_post_meta($post->ID, 'support_url', true);
    $hosting_platform = get_post_meta($post->ID, 'hosting_platform', true);

    if ($free_or_pro === '') {
        $free_or_pro = 'Free';
    }

    wp_nonce_field('repo_meta_box_nonce', 'repo_meta_box_nonce');
    ?>
    <p>
        <label for="latest_release_url"><strong>Latest Release URL:</strong></label>
        <input type="url" id="latest_release_url" name="latest_release_url"
               value="<?php echo esc_attr($latest_release_url); ?>"
               class="widefat">
    </p>
    <p>
        <label for="support_url"><strong>Support URL:</strong></label>
        <input type="url" id="support_url" name="support_url"
               value="<?php echo esc_attr($support_url); ?>"
               class="widefat">
        <small>Enter the URL for support (e.g., GitHub Issues page).</small>
    </p>
    <!-- <p>
        <strong>Free or Pro:</strong><br>
        <label>
            <input type="radio" name="free_or_pro" value="Free" <?php checked($free_or_pro, 'Free'); ?>> Free
        </label>
        <label>
            <input type="radio" name="free_or_pro" value="Pro" <?php checked($free_or_pro, 'Pro'); ?>> Pro
        </label>
    </p> -->
    <!-- <p>
        <label for="hosting_platform"><strong>Hosting Platform:</strong></label>
        <select id="hosting_platform" name="hosting_platform" class="widefat">
            <option value="github" <?php selected($hosting_platform, 'github'); ?>>GitHub</option>
            <option value="wordpress" <?php selected($hosting_platform, 'wordpress'); ?>>WordPress.org</option>
            <option value="other" <?php selected($hosting_platform, 'other'); ?>>Other</option>
        </select>
    </p> -->
    <p>
        <label for="cover_image_url"><strong>Cover Image:</strong></label>
        <input type="file" id="cover_image_url" name="cover_image_url" accept="image/jpeg,image/png" class="widefat">
        <?php if ($cover_image_url): ?>
            <p>Current Image:</p>
            <img src="<?php echo esc_url($cover_image_url); ?>" alt="Cover Image" style="max-width: 100%; height: auto;">
        <?php endif; ?>
    </p>
    <?php
}

// Save meta box data
add_action('save_post', function ($post_id) {
    // Ensure the nonce is set and valid
    if (!isset($_POST['repo_meta_box_nonce']) || !wp_verify_nonce($_POST['repo_meta_box_nonce'], 'repo_meta_box_nonce')) {
        return; // Stop execution if the nonce is invalid
    }

    // Prevent auto-saves from running this function
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Ensure the user has permission to edit this post
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Sanitize and save 'latest_release_url'
    if (isset($_POST['latest_release_url'])) {
        $latest_release_url = esc_url_raw($_POST['latest_release_url']);
        update_post_meta($post_id, 'latest_release_url', $latest_release_url);
    }

     // Sanitize and save 'support_url'
     if (isset($_POST['support_url'])) {
        $support_url = esc_url_raw($_POST['support_url']);
        update_post_meta($post_id, 'support_url', $support_url);
    }

    // Sanitize and save 'free_or_pro'
    if (isset($_POST['free_or_pro'])) {
        $allowed_values = ['Free', 'Pro'];
        $free_or_pro = in_array($_POST['free_or_pro'], $allowed_values, true) ? $_POST['free_or_pro'] : 'Free';
        update_post_meta($post_id, 'free_or_pro', $free_or_pro);
    }

     // Sanitize and save 'hosting_platform'
    if (isset($_POST['hosting_platform'])) {
        $allowed_platforms = ['github', 'wordpress', 'other'];
        $hosting_platform = in_array($_POST['hosting_platform'], $allowed_platforms, true) ? $_POST['hosting_platform'] : '';
        update_post_meta($post_id, 'hosting_platform', $hosting_platform);
    }

    // Handle file upload for 'cover_image_url'
    if (!empty($_FILES['cover_image_url']['name'])) {
        $file = $_FILES['cover_image_url'];

        // Validate file type and size
        $allowed_types = ['image/jpeg', 'image/png'];
        $max_file_size = 2 * 1024 * 1024; // 2 MB
        $file_type = wp_check_filetype_and_ext($file['tmp_name'], $file['name']);
        $file_size = filesize($file['tmp_name']);

        if (in_array($file_type['type'], $allowed_types, true) && $file_size <= $max_file_size) {
            $upload = wp_handle_upload($file, ['test_form' => false]);

            if ($upload && !isset($upload['error'])) {
                update_post_meta($post_id, 'cover_image_url', esc_url_raw($upload['url']));
            } else {
                error_log('Cover image upload error: ' . $upload['error']);
            }
        } else {
            error_log('Invalid file type or file too large.');
        }
    }
});


// Register REST API support for custom fields
add_action('init', function () {
    $meta_fields = [
        'cover_image_url' => 'URL of the cover image',
        'latest_release_url' => 'URL of the latest release',
        'support_url' => 'URL of the support page',
        'free_or_pro' => 'Indicates if the submission is free or pro',
        'hosting_platform' => 'Hosting platform for the plugin (github, wordpress, or other)'
        
    ];

    foreach (['plugin_repo', 'theme_repo'] as $post_type) {
        foreach ($meta_fields as $meta_key => $description) {
            register_post_meta($post_type, $meta_key, [
                'type' => 'string',
                'description' => $description,
                'single' => true,
                'show_in_rest' => true,
                'sanitize_callback' => ($meta_key === 'latest_release_url') ? 'esc_url_raw' : 'sanitize_text_field',
                'default' => ($meta_key === 'free_or_pro') ? 'Free' : ''
            ]);
        }
    }
});

// Register custom taxonomies
add_action('init', function () {
    $taxonomies = [
        'plugin-category' => ['plugin_repo', 'Plugin Categories', 'Plugin Category'],
        'theme-category' => ['theme_repo', 'Theme Categories', 'Theme Category'],
        'plugin-tag' => ['plugin_repo', 'Plugin Tags', 'Plugin Tag'],
        'theme-tag' => ['theme_repo', 'Theme Tags', 'Theme Tag']
    ];

    foreach ($taxonomies as $taxonomy => $details) {
        register_taxonomy($taxonomy, [$details[0]], [
            'labels' => [
                'name' => $details[1],
                'singular_name' => $details[2],
                'menu_name' => $details[1],
                'all_items' => "All {$details[1]}",
                'edit_item' => "Edit {$details[2]}",
                'view_item' => "View {$details[2]}",
                'add_new_item' => "Add New {$details[2]}",
                'new_item_name' => "New {$details[2]} Name",
                'search_items' => "Search {$details[1]}",
                'not_found' => "No {$details[1]} found",
            ],
            'public' => true,
            'show_in_rest' => true,
        ]);
    }
});

// Register custom post types
add_action('init', function () {
    $post_types = [
        'plugin_repo' => ['Plugins', 'Plugin'],
        'theme_repo' => ['Themes', 'Theme']
    ];

    foreach ($post_types as $post_type => $details) {
        register_post_type($post_type, [
            'labels' => [
                'name' => $details[0],
                'singular_name' => $details[1],
                'menu_name' => $details[0],
                'all_items' => "All {$details[0]}",
                'add_new_item' => "Add New {$details[1]}",
            ],
            'public' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-admin-post',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'author', 'comments', 'excerpt'],
            'taxonomies' => [$post_type . '-category'],
        ]);
    }
});
