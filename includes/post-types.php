<?php
namespace TheRepo\CustomPosts;

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
    $cover_image_url = get_post_meta($post->ID, 'cover_image_url', true); // Retrieve the cover image URL

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
        <strong>Free or Pro:</strong><br>
        <label>
            <input type="radio" name="free_or_pro" value="Free" <?php checked($free_or_pro, 'Free'); ?>> Free
        </label>
        <label>
            <input type="radio" name="free_or_pro" value="Pro" <?php checked($free_or_pro, 'Pro'); ?>> Pro
        </label>
    </p>
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
    if (!isset($_POST['repo_meta_box_nonce']) || !wp_verify_nonce($_POST['repo_meta_box_nonce'], 'repo_meta_box_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save latest_release_url
    if (isset($_POST['latest_release_url'])) {
        update_post_meta($post_id, 'latest_release_url', sanitize_text_field($_POST['latest_release_url']));
    }

    // Save free_or_pro
    $free_or_pro = isset($_POST['free_or_pro']) ? sanitize_text_field($_POST['free_or_pro']) : 'Free';
    update_post_meta($post_id, 'free_or_pro', $free_or_pro);

    // Handle file upload for cover_image
    if (!empty($_FILES['cover_image_url']['name'])) {
        $file = $_FILES['cover_image_url'];

        // Check the file type
        $allowed_types = ['image/jpeg', 'image/png'];
        if (in_array($file['type'], $allowed_types)) {
            // Upload the file
            $upload = wp_handle_upload($file, ['test_form' => false]);

            if ($upload && !isset($upload['error'])) {
                // Store the file URL in the meta field
                update_post_meta($post_id, 'cover_image_url', esc_url_raw($upload['url']));
            } else {
                // Log the error for debugging
                error_log('Cover image upload error: ' . $upload['error']);
            }
        }
    }
});


// Register REST API support for custom fields
add_action('init', function () {
    register_post_meta('plugin_repo', 'cover_image_url', [
        'type' => 'string',
        'description' => 'URL of the cover image',
        'single' => true,
        'show_in_rest' => true,
    ]);
    
    register_post_meta('theme_repo', 'cover_image_url', [
        'type' => 'string',
        'description' => 'URL of the cover image',
        'single' => true,
        'show_in_rest' => true,
    ]);


    register_post_meta('plugin_repo', 'latest_release_url', [
        'type' => 'string',
        'description' => 'URL of the latest release',
        'single' => true,
        'show_in_rest' => true,
    ]);

    register_post_meta('plugin_repo', 'free_or_pro', [
        'type' => 'string',
        'description' => 'Indicates if the submission is free or pro',
        'single' => true,
        'show_in_rest' => true,
        'default' => 'Free',
    ]);

    register_post_meta('theme_repo', 'latest_release_url', [
        'type' => 'string',
        'description' => 'URL of the latest release',
        'single' => true,
        'show_in_rest' => true,
    ]);

    register_post_meta('theme_repo', 'free_or_pro', [
        'type' => 'string',
        'description' => 'Indicates if the submission is free or pro',
        'single' => true,
        'show_in_rest' => true,
        'default' => 'Free',
    ]);
});

// Register custom taxonomies
add_action('init', function () {
    // Register Plugin Categories
    register_taxonomy('plugin-category', ['plugin_repo'], [
        'labels' => [
            'name' => 'Plugin Categories',
            'singular_name' => 'Plugin Category',
            'menu_name' => 'Plugin Categories',
            'all_items' => 'All Plugin Categories',
            'edit_item' => 'Edit Plugin Category',
            'view_item' => 'View Plugin Category',
            'add_new_item' => 'Add New Plugin Category',
            'new_item_name' => 'New Plugin Category Name',
            'search_items' => 'Search Plugin Categories',
            'not_found' => 'No plugin categories found',
        ],
        'public' => true,
        'show_in_rest' => true,
    ]);

    // Register Theme Categories
    register_taxonomy('theme-category', ['theme_repo'], [
        'labels' => [
            'name' => 'Theme Categories',
            'singular_name' => 'Theme Category',
            'menu_name' => 'Theme Categories',
            'all_items' => 'All Theme Categories',
            'edit_item' => 'Edit Theme Category',
            'view_item' => 'View Theme Category',
            'add_new_item' => 'Add New Theme Category',
            'new_item_name' => 'New Theme Category Name',
            'search_items' => 'Search Theme Categories',
            'not_found' => 'No theme categories found',
        ],
        'public' => true,
        'show_in_rest' => true,
    ]);

    // Register Plugin Tags
    register_taxonomy('plugin-tag', ['plugin_repo'], [
        'labels' => [
            'name' => 'Plugin Tags',
            'singular_name' => 'Plugin Tag',
            'menu_name' => 'Plugin Tags',
            'all_items' => 'All Plugin Tags',
            'edit_item' => 'Edit Plugin Tag',
            'view_item' => 'View Plugin Tag',
            'add_new_item' => 'Add New Plugin Tag',
            'new_item_name' => 'New Plugin Tag Name',
            'search_items' => 'Search Plugin Tags',
            'not_found' => 'No plugin Tags found',
        ],
        'public' => true,
        'show_in_rest' => true,
    ]);

    // Register Theme Tags
    register_taxonomy('theme-tag', ['theme_repo'], [
        'labels' => [
            'name' => 'Theme Tags',
            'singular_name' => 'Theme Tag',
            'menu_name' => 'Theme Tags',
            'all_items' => 'All Theme Tags',
            'edit_item' => 'Edit Theme Tag',
            'view_item' => 'View Theme Tag',
            'add_new_item' => 'Add New Theme Tag',
            'new_item_name' => 'New Theme Tag Name',
            'search_items' => 'Search Theme Tags',
            'not_found' => 'No theme Tags found',
        ],
        'public' => true,
        'show_in_rest' => true,
    ]);

   
});


// Register custom post types
add_action('init', function () {
    register_post_type('plugin_repo', [
        'labels' => [
            'name' => 'Plugins',
            'singular_name' => 'Plugin',
            'menu_name' => 'Plugins',
            'all_items' => 'All Plugins',
            'add_new_item' => 'Add New Plugin',
        ],
        'public' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-admin-post',
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'author'],
        'taxonomies' => ['plugin-category'],
    ]);

    register_post_type('theme_repo', [
        'labels' => [
            'name' => 'Themes',
            'singular_name' => 'Theme',
            'menu_name' => 'Themes',
            'all_items' => 'All Themes',
            'add_new_item' => 'Add New Theme',
        ],
        'public' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-admin-post',
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'author'],
        'taxonomies' => ['theme-category'],
    ]);
});
