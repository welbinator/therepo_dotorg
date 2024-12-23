<?php

namespace TheRepo\Shortcode\Form;


function handle_plugin_repo_submission() {
    if (!isset($_POST['plugin_repo_nonce']) || !wp_verify_nonce($_POST['plugin_repo_nonce'], 'plugin_repo_submission')) {
        wp_die('Error: Invalid form submission.');
    }

    if (!is_user_logged_in()) {
        wp_die('Error: You must be logged in to submit a plugin or theme.');
    }

    // Gather input values
    $type = sanitize_text_field($_POST['type']);
    $name = sanitize_text_field($_POST['name']);
    $hosted_on_github = sanitize_text_field($_POST['hosted_on_github']);
    $github_username = sanitize_text_field($_POST['github_username']);
    $github_repo = sanitize_text_field($_POST['github_repo']);
    $markdown_file_name = sanitize_text_field($_POST['markdown_file_name']);
    $landing_page_content = sanitize_text_field($_POST['landing_page_content']);
    $download_url = esc_url_raw($_POST['download_url']);
    $categories = isset($_POST['categories']) ? array_map('sanitize_text_field', $_POST['categories']) : array();
    $tags = isset($_POST['tags']) ? array_map('sanitize_text_field', $_POST['tags']) : array();
    $post_content = 'Add your plugin/theme information here!';

    // General field validation
    if (empty($type) || empty($name)) {
        wp_die('Error: All fields are required.');
    }

    // Define allowed HTML tags and attributes for sanitization
    $allowed_html = [
        'img' => [
            'src' => [],
            'alt' => [],
        ],
        'p' => [],
        'a' => [
            'href' => [],
        ],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'strong' => [],
        'em' => [],
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'blockquote' => [],
        'code' => [],
        'pre' => [],
        'br' => [],
        'hr' => [],
    ];

    // Handle "Import Markdown file from GitHub"
    if ($hosted_on_github === 'yes' && $landing_page_content === 'import_from_github') {
        if (!empty($markdown_file_name)) {
            // Fetch repository details to determine the default branch
            $repo_api_url = "https://api.github.com/repos/" . urlencode($github_username) . "/" . urlencode($github_repo);
            $repo_info = wp_remote_get($repo_api_url, [
                'headers' => [
                    'User-Agent' => 'TheRepo-Plugin',
                ],
            ]);
        
            if (!is_wp_error($repo_info) && wp_remote_retrieve_response_code($repo_info) === 200) {
                $repo_data = json_decode(wp_remote_retrieve_body($repo_info), true);
                $default_branch = $repo_data['default_branch'] ?? 'main'; // Use 'main' as a fallback
            } else {
                error_log('GitHub API Error: ' . print_r($repo_info, true)); // Log error for debugging
                wp_die('Error: Unable to fetch the repository information from GitHub.');
            }
        
            // Construct the URL for the Markdown file
            $readme_url = "https://raw.githubusercontent.com/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/" . $default_branch . "/" . urlencode($markdown_file_name);
        
            // Fetch the Markdown file
            $response = wp_remote_get($readme_url, [
                'headers' => [
                    'User-Agent' => 'TheRepo-Plugin',
                ],
            ]);
        
            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $markdown = wp_remote_retrieve_body($response);
        
                if (!empty($markdown)) {
                    $parsedown = new \Parsedown();
                    $post_content = $parsedown->text($markdown);
                    $post_content = wp_kses_post(wp_slash($post_content));
                } else {
                    error_log('Markdown File Error: File content is empty. URL: ' . $readme_url);
                    wp_die('Error: Could not fetch the specified Markdown file from GitHub.');
                }
            } else {
                error_log('Markdown Fetch Error: ' . print_r($response, true) . ' URL: ' . $readme_url);
                wp_die('Error: Unable to retrieve the specified Markdown file from GitHub.');
            }
        } else {
            wp_die('Error: Please specify the Markdown file name.');
        }
        
    }

    // Handle "Upload Markdown file"
    if ($landing_page_content === 'upload_markdown' && isset($_FILES['markdown_file'])) {
        if (!empty($_FILES['markdown_file']['name'])) {
            $uploaded_file = $_FILES['markdown_file'];

            if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
                $file_tmp_path = $uploaded_file['tmp_name'];
                $file_name = $uploaded_file['name'];
                $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['md', 'html', 'htm'];

                if (in_array($file_extension, $allowed_extensions, true)) {
                    $file_content = file_get_contents($file_tmp_path);

                    if (!empty($file_content)) {
                        if ($file_extension === 'md') {
                            $parsedown = new \Parsedown();
                            $post_content = $parsedown->text($file_content);
                        } else {
                            $post_content = wp_kses($file_content, $allowed_html);
                        }

                        $post_content = wp_kses_post(wp_slash($post_content));
                    } else {
                        wp_die('Error: The uploaded file is empty.');
                    }
                } else {
                    wp_die('Error: Unsupported file type. Please upload a Markdown (.md), HTML (.html), or HTM (.htm) file.');
                }
            } else {
                wp_die('Error: File upload failed. Please try again.');
            }
        } else {
            wp_die('Error: No file was uploaded.');
        }
    }

    // Handle "Not hosted on GitHub" and fallback
    if ($hosted_on_github === 'no') {
        if (empty($post_content)) {
            wp_die('Error: You must upload a valid file to populate the post content.');
        }

        if (empty($download_url)) {
            wp_die('Error: Download URL is required when not hosted on GitHub.');
        }

        $latest_release_url = $download_url;
    } else {
        $latest_release_url = "https://api.github.com/repos/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/releases/latest";
    }

    // Handle file upload for featured image
    $featured_image_id = null;
    if (!empty($_FILES['featured_image']['name'])) {
        $upload = wp_handle_upload($_FILES['featured_image'], array('test_form' => false));
        if ($upload && !isset($upload['error'])) {
            $filetype = wp_check_filetype($upload['file']);
            $attachment = array(
                'post_mime_type' => $filetype['type'],
                'post_title'     => sanitize_file_name($_FILES['featured_image']['name']),
                'post_content'   => '',
                'post_status'    => 'inherit',
            );

            $featured_image_id = wp_insert_attachment($attachment, $upload['file']);
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attach_data = wp_generate_attachment_metadata($featured_image_id, $upload['file']);
            wp_update_attachment_metadata($featured_image_id, $attach_data);
        } else {
            wp_die('Error uploading featured image: ' . $upload['error']);
        }
    }

    // Handle file upload for cover image
    $cover_image_url = null;
    if (!empty($_FILES['cover_image_url']['name'])) {
        $upload = wp_handle_upload($_FILES['cover_image_url'], array('test_form' => false));
        if ($upload && !isset($upload['error'])) {
            $cover_image_url = $upload['url'];
        } else {
            wp_die('Error uploading cover image: ' . $upload['error']);
        }
    }

    // Determine post type and taxonomy
    $post_type = $type === 'plugin' ? 'plugin' : 'theme_repo';
    $taxonomy = $type === 'plugin' ? 'plugin-category' : 'theme-category';
    $tags_taxonomy = $type === 'plugin' ? 'plugin-tag' : 'theme-tag';

    // Create the post
    $post_id = wp_insert_post(array(
        'post_title'   => $name,
        'post_content' => $post_content,
        'post_type'    => $post_type,
        'post_status'  => 'pending',
        'post_author'  => get_current_user_id(),
    ));

    if ($post_id) {
        update_post_meta($post_id, 'latest_release_url', $latest_release_url);
        update_post_meta($post_id, 'hosted_on_github', $hosted_on_github);

        if ($hosted_on_github === 'yes') {
            update_post_meta($post_id, 'github_username', $github_username);
            update_post_meta($post_id, 'github_repo', $github_repo);
        } else {
            update_post_meta($post_id, 'download_url', $download_url);
        }

        wp_set_object_terms($post_id, $categories, $taxonomy);
        wp_set_object_terms($post_id, $tags, $tags_taxonomy);

        if ($cover_image_url) {
            update_post_meta($post_id, 'cover_image_url', $cover_image_url);
        }

        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
        }

        wp_redirect(add_query_arg('submission', 'success', home_url()));
        exit;
    } else {
        wp_die('Error: Unable to create the submission.');
    }
}

add_action('admin_post_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission');
add_action('admin_post_nopriv_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission');


// Form shortcode
function plugin_repo_submission_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to submit a plugin or theme. <a href="' . wp_login_url(get_permalink()) . '">Log in</a> or <a href="' . wp_registration_url() . '">Register</a>.</p>';
    }
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

                    <!-- Hosted on GitHub? -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Hosted on GitHub?</label>
                        <div class="mt-1">
                            <label>
                                <input type="radio" name="hosted_on_github" value="yes" checked> Yes
                            </label>
                            <label class="ml-4">
                                <input type="radio" name="hosted_on_github" value="no"> No
                            </label>
                        </div>
                    </div>

                    <!-- GitHub Username and Repo -->
                    <div id="github-fields" class="github-wrapper flex gap-5">
                        <div class="github-column">
                            <label for="github_username" class="block text-sm font-medium text-gray-700">GitHub Username</label>
                            <input 
                                type="text" 
                                name="github_username" 
                                id="github_username" 
                                placeholder="GitHub username" 
                                required 
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>

                        <div class="github-column">
                            <label for="github_repo" class="block text-sm font-medium text-gray-700">GitHub Repo</label>
                            <input 
                                type="text" 
                                name="github_repo" 
                                id="github_repo" 
                                placeholder="GitHub repository name" 
                                required 
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <!-- Landing Page Content -->
                    <div id="landing-page-field">
                        <label class="block text-sm font-medium text-gray-700">Landing Page Content</label>
                        <div class="mt-1 space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="landing_page_content" value="import_from_github" checked>
                                <span class="ml-2">Import markdown/txt file from GitHub</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="landing_page_content" value="upload_markdown">
                                <span class="ml-2">Upload markdown/html/txt file</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="landing_page_content" value="manual_edit">
                                <span class="ml-2">Edit manually using block editor</span>
                            </label>
                        </div>
                    </div>

                    <div id="markdown-fields">
                        <!-- Markdown File Name -->
                        <div id="markdown-file-name-field">
                            <label for="markdown_file_name" class="block text-sm font-medium text-gray-700">Markdown File Name</label>
                            <input 
                                type="text" 
                                name="markdown_file_name" 
                                id="markdown_file_name" 
                                placeholder="Enter markdown file name (e.g., readme.md)" 
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>


                        <!-- Markdown File Upload -->
                        <div id="upload-markdown-field" style="display:none;">
                            <label for="markdown_file" class="block text-sm font-medium text-gray-700">Upload Markdown File</label>
                            <input 
                                type="file" 
                                name="markdown_file" 
                                id="markdown_file" 
                                 accept=".md,.html,.htm"
                                class="mt-1 block w-full text-sm text-gray-500 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                            />
                        </div>
                    </div>

                    <!-- Download URL -->
                    <div id="download-url-field" style="display: none;">
                        <label for="download_url" class="block text-sm font-medium text-gray-700">Download URL</label>
                        <input 
                            type="url" 
                            name="download_url" 
                            id="download_url" 
                            placeholder="Enter download URL" 
                            class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                        />
                        <p class="text-sm text-gray-500 mt-1">Provide the direct URL to download your plugin/theme.</p>
                    </div>

                    <!-- Categories -->
                    <div>
                        <label for="categories" class="block text-sm font-medium text-gray-700">Categories</label>
                        <select name="categories[]" id="categories" multiple="multiple" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <!-- Dynamic options will be loaded via Select2 -->
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Start typing to search for existing categories or add new ones.</p>
                    </div>

                    <!-- Tags -->
                    <div>
                        <label for="tags" class="block text-sm font-medium text-gray-700">Tags</label>
                        <select name="tags[]" id="tags" multiple="multiple" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <!-- Dynamic options will be loaded via Select2 -->
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Start typing to search for existing tags or add new ones.</p>
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

                    <!-- Cover Image -->
                    <div>
                        <label for="cover_image_url" class="block text-sm font-medium text-gray-700">Cover Image</label>
                        <input 
                            type="file" 
                            name="cover_image_url" 
                            id="cover_image_url" 
                            accept="image/jpg,image/jpeg,image/png" 
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

    <!-- JavaScript -->
    <script>
    // Toggle Name Label Based on Type
    document.getElementById('type').addEventListener('change', function () {
        const type = this.value;
        const nameLabel = document.querySelector('label[for="name"]');
        nameLabel.textContent = type === 'plugin' ? 'Plugin Name' : 'Theme Name';
    });

    // Toggle Fields Based on Hosted on GitHub
    document.addEventListener('DOMContentLoaded', function () {
    const hostedRadioButtons = document.getElementsByName('hosted_on_github');
    const githubFields = document.getElementById('github-fields');
    const downloadUrlField = document.getElementById('download-url-field');
    const githubUsernameField = document.getElementById('github_username');
    const githubRepoField = document.getElementById('github_repo');
    const landingPageField = document.getElementById('landing-page-field');
    const markdownFileNameField = document.getElementById('markdown-file-name-field');
    const markdownFileUploadField = document.getElementById('upload-markdown-field');
    const landingPageRadios = document.getElementsByName('landing_page_content');
    const markdownFields = document.getElementById('markdown-fields');
    const importFromGithubRadio = document.querySelector('input[value="import_from_github"]');
    const uploadMarkdownRadio = document.querySelector('input[value="upload_markdown"]');

    function toggleGitHubFields() {
        const isHostedOnGitHub = document.querySelector('input[name="hosted_on_github"]:checked').value === 'yes';

        if (isHostedOnGitHub) {
            githubFields.style.display = 'flex';
            downloadUrlField.style.display = 'none';
            githubUsernameField.setAttribute('required', 'required');
            githubRepoField.setAttribute('required', 'required');
            document.getElementById('download_url').removeAttribute('required');
            markdownFields.style.display = 'block';

            // Show the "import_from_github" option and ensure it's selectable
            importFromGithubRadio.parentElement.style.display = 'flex';
        } else {
            githubFields.style.display = 'none';
            downloadUrlField.style.display = 'block';
            githubUsernameField.removeAttribute('required');
            githubRepoField.removeAttribute('required');
            document.getElementById('download_url').setAttribute('required', 'required');
            markdownFields.style.display = 'block';

            // Hide "import_from_github" option and auto-select "upload_markdown"
            importFromGithubRadio.parentElement.style.display = 'none';
            uploadMarkdownRadio.checked = true;

            // Update the fields based on the new selection
            toggleMarkdownFields();
        }
    }

    function toggleMarkdownFields() {
        const selectedValue = document.querySelector('input[name="landing_page_content"]:checked').value;

        // Show or hide the relevant fields
        markdownFileNameField.style.display = selectedValue === 'import_from_github' ? 'block' : 'none';
        markdownFileUploadField.style.display = selectedValue === 'upload_markdown' ? 'block' : 'none';
    }

    function initializeFields() {
        // Ensure the default state is set up correctly on page load
        toggleGitHubFields();

        // Explicitly call toggleMarkdownFields to handle the selected value
        toggleMarkdownFields();
    }

    // Add event listeners
    hostedRadioButtons.forEach(button => {
        button.addEventListener('change', () => {
            toggleGitHubFields();
            toggleMarkdownFields(); // Ensure fields update properly
        });
    });

    landingPageRadios.forEach(button => {
        button.addEventListener('change', toggleMarkdownFields);
    });

    // Initialize fields on page load
    initializeFields();
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
