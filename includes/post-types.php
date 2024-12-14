<?php
namespace TheRepo\CustomPosts;

// Register custom meta boxes for custom fields
add_action('add_meta_boxes', function () {
    add_meta_box(
        'repo_meta_box',                // Unique ID
        'Repository Details',           // Meta box title
        __NAMESPACE__ . '\\render_repo_meta_box', // Callback function
        ['plugin', 'theme_repo'],       // Post types
        'normal',                       // Context
        'default'                       // Priority
    );
});

// Render the meta box
function render_repo_meta_box($post) {
    // Retrieve existing values or set defaults
    $latest_release_url = get_post_meta($post->ID, 'latest_release_url', true);
    $free_or_pro = get_post_meta($post->ID, 'free_or_pro', true);

    // Default to 'free' if no value exists in the database
    if ($free_or_pro === '') {
        $free_or_pro = 'free';
    }

    // Add a nonce field for security
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
            <input type="radio" name="free_or_pro" value="free"
                   <?php checked($free_or_pro, 'free'); ?>>
            Free
        </label>
        <label>
            <input type="radio" name="free_or_pro" value="pro"
                   <?php checked($free_or_pro, 'pro'); ?>>
            Pro
        </label>
    </p>
    <?php
}

// Save meta box data
add_action('save_post', function ($post_id) {
    // Verify the nonce
    if (!isset($_POST['repo_meta_box_nonce']) || !wp_verify_nonce($_POST['repo_meta_box_nonce'], 'repo_meta_box_nonce')) {
        return;
    }

    // Prevent autosave from overwriting
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save latest_release_url
    if (isset($_POST['latest_release_url'])) {
        update_post_meta($post_id, 'latest_release_url', sanitize_text_field($_POST['latest_release_url']));
    }

    // Save free_or_pro field with a default of 'free'
    $free_or_pro = isset($_POST['free_or_pro']) ? sanitize_text_field($_POST['free_or_pro']) : 'free';
    update_post_meta($post_id, 'free_or_pro', $free_or_pro);
});

// Register REST API support for custom fields
add_action('init', function () {
    register_post_meta('plugin', 'latest_release_url', [
        'type' => 'string',
        'description' => 'URL of the latest release',
        'single' => true,
        'show_in_rest' => true,
    ]);

    register_post_meta('plugin', 'free_or_pro', [
        'type' => 'string',
        'description' => 'Indicates if the submission is free or pro',
        'single' => true,
        'show_in_rest' => true,
        'default' => 'free',
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
        'default' => 'free',
    ]);
});

// Register custom taxonomies
add_action('init', function () {
    register_taxonomy('plugin-category', ['plugin'], [
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
});

// Register custom post types
add_action('init', function () {
    register_post_type('plugin', [
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
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
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
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'taxonomies' => ['theme-category'],
    ]);
});
