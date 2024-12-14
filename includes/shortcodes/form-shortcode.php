<?php

namespace TheRepo\Shortcode\Form;

function handle_plugin_repo_submission() {
    if (!isset($_POST['plugin_repo_nonce']) || !wp_verify_nonce($_POST['plugin_repo_nonce'], 'plugin_repo_submission')) {
        wp_die('Error: Invalid form submission.');
    }

    $type = sanitize_text_field($_POST['type']);
    $name = sanitize_text_field($_POST['name']);
    $github_username = sanitize_text_field($_POST['github_username']);
    $github_repo = sanitize_text_field($_POST['github_repo']);
    $description = sanitize_textarea_field($_POST['description']);
    $categories = sanitize_text_field($_POST['categories']);

    if (empty($type) || empty($name) || empty($github_username) || empty($github_repo) || empty($description)) {
        wp_die('Error: All fields are required.');
    }

    // Construct the GitHub Latest Release API URL
    $latest_release_url = "https://api.github.com/repos/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/releases/latest";

    // Handle file upload
    $featured_image_id = null;
    if (!empty($_FILES['featured_image']['name'])) {
        $upload = wp_handle_upload($_FILES['featured_image'], array('test_form' => false));

        if ($upload && !isset($upload['error'])) {
            // Insert attachment
            $filetype = wp_check_filetype($upload['file']);
            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name($_FILES['featured_image']['name']),
                'post_content'   => '',
                'post_status'    => 'inherit'
            );

            $featured_image_id = wp_insert_attachment($attachment, $upload['file']);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($featured_image_id, $upload['file']);
            wp_update_attachment_metadata($featured_image_id, $attach_data);
        } else {
            wp_die('Error uploading featured image: ' . $upload['error']);
        }
    }

    // Determine post type and taxonomy
    $post_type = $type === 'plugin' ? 'plugin' : 'theme_repo';
    $taxonomy = $type === 'plugin' ? 'plugin-category' : 'theme-category';

    // Create the post
    $post_id = wp_insert_post(array(
        'post_title'   => $name,
        'post_content' => $description,
        'post_excerpt' => wp_trim_words($description, 55),
        'post_type'    => $post_type,
        'post_status'  => 'pending', // Set to pending for moderation
    ));

    if ($post_id) {
        // Add custom field for GitHub Latest Release URL
        update_post_meta($post_id, 'latest_release_url', $latest_release_url);

        // Add categories to the appropriate taxonomy
        $category_list = array_map('trim', explode(',', $categories));
        wp_set_object_terms($post_id, $category_list, $taxonomy);

        // Set the featured image for the post
        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
        }

        wp_redirect(add_query_arg('submission', 'success', home_url())); // Redirect after success
        exit;
    } else {
        wp_die('Error: Unable to create the submission.');
    }
}

add_action('admin_post_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission');
add_action('admin_post_nopriv_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission'); // For non-logged-in users

// Form shortcode
function plugin_repo_submission_form_shortcode() {
    ob_start(); ?>
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-2xl mx-auto">
                <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="space-y-6" id="repo_submission_form">
                    <input type="hidden" name="action" value="plugin_repo_submission">
                    <?php wp_nonce_field('plugin_repo_submission', 'plugin_repo_nonce'); ?>
                    
                    <!-- Type -->
                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                        <select name="type" id="type" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="plugin">Plugin</option>
                            <option value="theme">Theme</option>
                        </select>
                    </div>

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Plugin/Theme Name</label>
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            placeholder="Enter name" 
                            required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <!-- GitHub Username -->
                    <div>
                        <label for="github_username" class="block text-sm font-medium text-gray-700">GitHub Username</label>
                        <input 
                            type="text" 
                            name="github_username" 
                            id="github_username" 
                            placeholder="Enter GitHub username" 
                            required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <!-- GitHub Repo -->
                    <div>
                        <label for="github_repo" class="block text-sm font-medium text-gray-700">GitHub Repo</label>
                        <input 
                            type="text" 
                            name="github_repo" 
                            id="github_repo" 
                            placeholder="Enter GitHub repository name" 
                            required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea 
                            name="description" 
                            id="description" 
                            placeholder="Enter description" 
                            required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        ></textarea>
                    </div>

                    <!-- Categories -->
                    <div>
                        <label for="categories" class="block text-sm font-medium text-gray-700">Categories</label>
                        <input 
                            type="text" 
                            name="categories" 
                            id="categories" 
                            placeholder="Enter categories" 
                            required 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                        <p class="text-sm text-gray-500 mt-1">Enter a comma-separated list of categories.</p>
                    </div>

                    <!-- Featured Image -->
                    <div>
                        <label for="featured_image" class="block text-sm font-medium text-gray-700">Featured Image</label>
                        <input 
                            type="file" 
                            name="featured_image" 
                            id="featured_image" 
                            accept="image/*" 
                            required 
                            class="mt-1 block w-full text-sm text-gray-500 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                    </div>

                    <!-- Submit Button -->
                    <button 
                        type="submit" 
                        class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                    >
                        Submit Plugin/Theme
                    </button>
                </form>
            </div>
        </div>
    </section>
    <script>
        document.getElementById('type').addEventListener('change', function () {
            const type = this.value;
            const nameLabel = document.querySelector('label[for="name"]');
            nameLabel.textContent = type === 'plugin' ? 'Plugin Name' : 'Theme Name';
        });
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('plugin_repo_form', __NAMESPACE__ . '\\plugin_repo_submission_form_shortcode');

// Display success message
add_action('wp_footer', function () {
    if (isset($_GET['submission']) && $_GET['submission'] === 'success') {
        echo '<div class="notice success">Thank you! Your submission has been received and is pending approval.</div>';
    }
});