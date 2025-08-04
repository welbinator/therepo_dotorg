<?php

namespace TheRepo\Shortcode\Form;

function handle_plugin_repo_submission()
{
    if (!isset($_POST['plugin_repo_nonce']) || !wp_verify_nonce($_POST['plugin_repo_nonce'], 'plugin_repo_submission')) {
        wp_die('Error: Invalid form submission.');
    }

    if (!is_user_logged_in()) {
        wp_die('Error: You must be logged in to submit a plugin or theme.');
    }

    // Gather input values with sanitization
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
    $hosting_platform = isset($_POST['hosting_platform']) ? sanitize_text_field($_POST['hosting_platform']) : '';
    $wporg_slug = isset($_POST['wporg_slug']) ? sanitize_text_field($_POST['wporg_slug']) : '';
    $github_username = isset($_POST['github_username']) ? sanitize_text_field($_POST['github_username']) : '';
    $github_repo = isset($_POST['github_repo']) ? sanitize_text_field($_POST['github_repo']) : '';
    $markdown_file_name = isset($_POST['markdown_file_name']) ? sanitize_text_field($_POST['markdown_file_name']) : '';
    $landing_page_content = isset($_POST['landing_page_content']) ? sanitize_text_field($_POST['landing_page_content']) : '';
    $download_url = isset($_POST['download_url']) ? esc_url_raw($_POST['download_url']) : '';
    $categories = isset($_POST['categories']) ? array_map('sanitize_text_field', (array) $_POST['categories']) : [];
    $tags = isset($_POST['tags']) ? array_map('sanitize_text_field', (array) $_POST['tags']) : [];
    $post_content = 'Add your plugin/theme information here!';
    $short_description = isset($_POST['short_description']) ? sanitize_text_field($_POST['short_description']) : '';
    $latest_release_url = "https://downloads.wordpress.org/plugin/" . $wporg_slug . ".zip";
    $post_type = $type === 'plugin_repo' ? 'plugin_repo' : 'theme_repo';

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
    if ($landing_page_content === 'import_from_github') {
        if (!empty($markdown_file_name)) {
            // Restrict file extensions
            $allowed_extensions = ['md', 'html', 'htm', 'txt'];
            $file_extension = strtolower(pathinfo($markdown_file_name, PATHINFO_EXTENSION));

            if (!in_array($file_extension, $allowed_extensions, true)) {
                wp_die('Error: Unsupported file type. Please provide a Markdown (.md), HTML (.html), HTM (.htm), or text (.txt) file.');
            }

            $repo_api_url = "https://api.github.com/repos/" . urlencode($github_username) . "/" . urlencode($github_repo);
            $repo_info = wp_remote_get($repo_api_url, ['headers' => ['User-Agent' => 'TheRepo-Plugin']]);

            if (!is_wp_error($repo_info) && wp_remote_retrieve_response_code($repo_info) === 200) {
                $repo_data = json_decode(wp_remote_retrieve_body($repo_info), true);
                $default_branch = $repo_data['default_branch'] ?? 'main';
            } else {
                error_log('GitHub API Error: ' . print_r($repo_info, true));
                wp_die('Error: Unable to fetch repository details from GitHub.');
            }

            $readme_url = "https://raw.githubusercontent.com/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/" . $default_branch . "/" . urlencode($markdown_file_name);

            $response = wp_remote_get($readme_url, ['headers' => ['User-Agent' => 'TheRepo-Plugin']]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $markdown = wp_remote_retrieve_body($response);

                if (!empty($markdown)) {
                    $parsedown = new \Parsedown();
                    $post_content = $parsedown->text($markdown);
                    $post_content = wp_kses_post(wp_slash($post_content));
                } else {
                    error_log('Markdown File Error: File content is empty. URL: ' . $readme_url);
                    wp_die('Error: The Markdown file from GitHub is empty.');
                }
            } else {
                error_log('Markdown Fetch Error: ' . print_r($response, true) . ' URL: ' . $readme_url);
                wp_die('Error: Unable to retrieve the Markdown file from GitHub.');
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
                $allowed_extensions = ['md', 'html', 'htm', 'txt'];

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
    if ($hosting_platform === 'other') {
        if (empty($post_content)) {
            wp_die('Error: You must upload a valid file to populate the post content.');
        }
        if (empty($download_url)) {
            wp_die('Error: Download URL is required when not hosted on GitHub.');
        }
        $latest_release_url = $download_url;
    } elseif ($hosting_platform === 'github') {
        $latest_release_url = "https://api.github.com/repos/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/releases/latest";
        $support_url = "https://github.com/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/issues";
    } elseif ($hosting_platform === 'wordpress') {
        if (empty($wporg_slug)) {
            wp_die('Error: Please enter the WordPress.org plugin slug.');
        }
        $latest_release_url = "https://downloads.wordpress.org/plugin/" . $wporg_slug . ".zip";
    }

    // Handle file upload for featured image
    $featured_image_id = null;
    if (!empty($_FILES['featured_image']['name'])) {
        $upload = wp_handle_upload($_FILES['featured_image'], ['test_form' => false]);

        if ($upload && !isset($upload['error'])) {
            $file_path = $upload['file'];
            $file_url = $upload['url'];
            $filetype = wp_check_filetype($file_path);

            $attachment = [
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_file_name($_FILES['featured_image']['name']),
                'post_content' => '',
                'post_status' => 'inherit',
            ];

            $featured_image_id = wp_insert_attachment($attachment, $file_path);

            if (!is_wp_error($featured_image_id)) {
                require_once ABSPATH . 'wp-admin/includes/image.php';
                $attach_data = wp_generate_attachment_metadata($featured_image_id, $file_path);
                wp_update_attachment_metadata($featured_image_id, $attach_data);
            } else {
                wp_die('Error creating attachment for featured image.');
            }
        } else {
            wp_die('Error uploading featured image: ' . $upload['error']);
        }
    }

    // Handle file upload for cover image or use a default image
    $cover_image_url = null;
    if (!empty($_FILES['cover_image_url']['name'])) {
        $upload = wp_handle_upload($_FILES['cover_image_url'], ['test_form' => false]);
        if ($upload && !isset($upload['error'])) {
            $cover_image_url = $upload['url'];
        } else {
            wp_die('Error uploading cover image: ' . $upload['error']);
        }
    } else {
        // Use default cover image if no image is uploaded
        $default_image_path = plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/img/therepo-default-banner.jpg';
        $cover_image_url = esc_url_raw($default_image_path);
    }

    // Determine post type and taxonomy
    $taxonomy = $type === 'plugin_repo' ? 'plugin-category' : 'theme-category';
    $tags_taxonomy = $type === 'plugin_repo' ? 'plugin-tag' : 'theme-tag';

    // Create the post
    $post_id = wp_insert_post([
        'post_title' => $name,
        'post_content' => $post_content,
        'post_excerpt' => $short_description,
        'post_type' => $post_type,
        'post_status' => 'pending',
        'post_author' => get_current_user_id(),
    ]);

    if ($post_id) {
        update_post_meta($post_id, 'latest_release_url', $latest_release_url);
        update_post_meta($post_id, 'hosting_platform', $hosting_platform);
        if ($cover_image_url) {
            update_post_meta($post_id, 'cover_image_url', $cover_image_url);
        }

        if ($hosting_platform === 'github') {
            update_post_meta($post_id, 'github_username', $github_username);
            update_post_meta($post_id, 'github_repo', $github_repo);
            update_post_meta($post_id, 'support_url', $support_url);
        } elseif ($hosting_platform === 'wordpress') {
            update_post_meta($post_id, 'wporg_slug', $wporg_slug);

            // Validate the slug
            if (empty($wporg_slug) || !preg_match('/^[a-z0-9-]+$/i', $wporg_slug)) {
                error_log('Plugin Info API Error: Invalid plugin slug provided: ' . $wporg_slug);
            } else {
                // Use WordPress plugins_api() for reliability
                if (!function_exists('plugins_api')) {
                    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
                }

                $request_args = [
                    'slug'   => $wporg_slug,
                    'fields' => [
                        'icons' => true,
                        'tags' => true,
                        'description' => false,
                        'sections' => false,
                    ],
                ];

                $api_response = plugins_api('plugin_information', $request_args);

                if (is_wp_error($api_response)) {
                    error_log('Plugin Info API Error: ' . $api_response->get_error_message());
                } else {
                    // Handle plugin icon
                    if (!empty($api_response->icons) && is_array($api_response->icons)) {
                        // Prefer SVG, then 2x, then 1x, then default
                        $icon_url = !empty($api_response->icons['svg']) ? $api_response->icons['svg'] : 
                                    (!empty($api_response->icons['2x']) ? $api_response->icons['2x'] : 
                                    (!empty($api_response->icons['1x']) ? $api_response->icons['1x'] : 
                                    (!empty($api_response->icons['default']) ? $api_response->icons['default'] : '')));

                        if ($icon_url) {
                            update_post_meta($post_id, 'wporg_plugin_icon', esc_url_raw($icon_url));

                            // Download the icon
                            $tmp = download_url($icon_url);

                            if (!is_wp_error($tmp)) {
                                // Strip query string from filename
                                $parsed_url = parse_url($icon_url, PHP_URL_PATH);
                                $filename = basename($parsed_url);
                                $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                                // Use the original filename for valid extensions
                                if (!in_array($extension, ['png', 'svg', 'jpg', 'jpeg'], true)) {
                                    error_log('Invalid icon extension: ' . $extension . '. Falling back to plugin slug with appropriate extension for URL: ' . $icon_url);
                                    $filename = $wporg_slug . ($api_response->icons['svg'] ? '.svg' : '.png');
                                    $extension = $api_response->icons['svg'] ? 'svg' : 'png';
                                }

                                // Ensure the temporary file has the correct extension
                                $tmp_base = preg_replace('/\.\w+$/', '', $tmp); // Strip any existing extension
                                $tmp_with_ext = $tmp_base . '.' . $extension;
                                if (!rename($tmp, $tmp_with_ext)) {
                                    error_log('Error renaming temporary file to: ' . $tmp_with_ext);
                                    unlink($tmp);
                                } else {
                                    $tmp = $tmp_with_ext;

                                    $file_array = [
                                        'name' => sanitize_file_name($filename),
                                        'tmp_name' => $tmp,
                                        'type' => $extension === 'svg' ? 'image/svg+xml' : 'image/png',
                                    ];

                                    // Debug: Log file array and temporary file content
                                    error_log('File Array: ' . print_r($file_array, true));
                                    $file_content = file_get_contents($tmp);
                                    error_log('Temporary File Content (first 100 chars): ' . substr($file_content, 0, 100));

                                    // Explicitly define allowed MIME types
                                    $allowed_mime_types = array_merge(wp_get_mime_types(), ['svg' => 'image/svg+xml']);

                                    // Check file type with explicit MIME types
                                    $filetype = wp_check_filetype($file_array['name'], $allowed_mime_types);
                                    if (empty($filetype['ext']) || empty($filetype['type'])) {
                                        // Fallback: Manually set filetype for SVG
                                        if ($extension === 'svg') {
                                            $filetype = ['ext' => 'svg', 'type' => 'image/svg+xml'];
                                            error_log('Fallback: Manually set filetype for SVG: ' . print_r($filetype, true));
                                        }
                                    }

                                    error_log('File Details: Name=' . $file_array['name'] . ', Temp Path=' . $tmp . ', MIME=' . $filetype['type'] . ', Extension=' . $filetype['ext']);

                                    // Validate file type
                                    if (
                                        !isset($allowed_mime_types[$filetype['ext']]) ||
                                        !in_array($filetype['type'], ['image/png', 'image/svg+xml', 'image/jpeg'], true)
                                    ) {
                                        error_log('File Type Validation Error: File extension or MIME type not allowed. Extension=' . $filetype['ext'] . ', MIME=' . $filetype['type']);
                                        unlink($tmp); // Clean up temporary file
                                    } else {
                                        // Validate SVG content
                                        if ($extension === 'svg') {
                                            $file_content = file_get_contents($tmp);
                                            if (strpos($file_content, '<svg') === false) {
                                                error_log('Error: Downloaded file is not a valid SVG for URL: ' . $icon_url);
                                                unlink($tmp);
                                            } else {
                                                // Ensure media handling functions are available
                                                if (!function_exists('media_handle_sideload')) {
                                                    require_once ABSPATH . 'wp-admin/includes/image.php';
                                                    require_once ABSPATH . 'wp-admin/includes/file.php';
                                                    require_once ABSPATH . 'wp-admin/includes/media.php';
                                                }

                                                // Debug: Log allowed MIME types
                                                error_log('Allowed MIME Types: ' . print_r($allowed_mime_types, true));

                                                // Sideload the file
                                                $attachment_id = media_handle_sideload($file_array, $post_id, sanitize_file_name($filename));

                                                if (!is_wp_error($attachment_id)) {
                                                    // Debug: Log successful attachment
                                                    error_log('Featured Image Set Successfully: Attachment ID=' . $attachment_id);
                                                    $featured_image_id = $attachment_id; // Set featured_image_id for later use
                                                } else {
                                                    error_log('Error setting featured image: ' . $attachment_id->get_error_message());
                                                    unlink($tmp); // Clean up temporary file
                                                }
                                            }
                                        } else {
                                            // For PNG files
                                            if (!function_exists('media_handle_sideload')) {
                                                require_once ABSPATH . 'wp-admin/includes/image.php';
                                                require_once ABSPATH . 'wp-admin/includes/file.php';
                                                require_once ABSPATH . 'wp-admin/includes/media.php';
                                            }

                                            // Debug: Log allowed MIME types
                                            error_log('Allowed MIME Types: ' . print_r($allowed_mime_types, true));

                                            // Sideload the file
                                            $attachment_id = media_handle_sideload($file_array, $post_id, sanitize_file_name($filename));

                                            if (!is_wp_error($attachment_id)) {
                                                // Debug: Log successful attachment
                                                error_log('Featured Image Set Successfully: Attachment ID=' . $attachment_id);
                                                $featured_image_id = $attachment_id; // Set featured_image_id for later use
                                            } else {
                                                error_log('Error setting featured image: ' . $attachment_id->get_error_message());
                                                unlink($tmp); // Clean up temporary file
                                            }
                                        }
                                    }
                                }
                            } else {
                                error_log('Error downloading image: ' . $tmp->get_error_message());
                            }
                        }
                    } else {
                        error_log('Plugin Info API Error: No valid icon URL found for slug ' . $wporg_slug);
                    }

                    // Handle WordPress.org tags
                    if (!empty($api_response->tags) && is_array($api_response->tags)) {
                        // Log the raw tags for debugging
                        error_log('WordPress.org Tags for slug ' . $wporg_slug . ': ' . print_r($api_response->tags, true));
                        // Extract tag slugs or names
                        $wporg_tags = array_map('sanitize_title', array_keys($api_response->tags));
                        // Ensure terms exist in the plugin-tag taxonomy
                        $term_ids = [];
                        foreach ($wporg_tags as $tag) {
                            if (!empty($tag)) {
                                $term = term_exists($tag, 'plugin-tag');
                                if (!$term) {
                                    $term = wp_insert_term($tag, 'plugin-tag');
                                    if (is_wp_error($term)) {
                                        error_log('Error creating term ' . $tag . ': ' . $term->get_error_message());
                                        continue;
                                    }
                                    $term_ids[] = (int) $term['term_id'];
                                } else {
                                    $term_ids[] = (int) $term['term_id'];
                                }
                            }
                        }
                        // Combine with user-submitted tags
                        $combined_tags = array_unique(array_merge($tags, $wporg_tags));
                        // Log the combined tags for debugging
                        error_log('Combined Tags for post ' . $post_id . ': ' . print_r($combined_tags, true));
                        // Set terms, appending to existing ones
                        $result = wp_set_object_terms($post_id, $combined_tags, 'plugin-tag', true);
                        if (is_wp_error($result)) {
                            error_log('Error setting plugin-tag terms: ' . $result->get_error_message());
                        } else {
                            error_log('Successfully set plugin-tag terms for post ' . $post_id);
                        }
                    } else {
                        error_log('Plugin Info API Error: No tags found for slug ' . $wporg_slug);
                    }
                }
            }
        } else {
            update_post_meta($post_id, 'download_url', $download_url);
        }

        wp_set_object_terms($post_id, $categories, $taxonomy);
        // Removed redundant wp_set_object_terms for tags since it's handled above

        if ($cover_image_url) {
            update_post_meta($post_id, 'cover_image_url', $cover_image_url);
        }

        if ($featured_image_id) {
            set_post_thumbnail($post_id, $featured_image_id);
        }

        wp_redirect(add_query_arg('submission', 'success', home_url()));
        exit();
    } else {
        wp_die('Error: Unable to create the submission.');
    }
}

add_action('admin_post_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission');
add_action('admin_post_nopriv_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission');

// Form shortcode (unchanged, included for completeness)
function plugin_repo_submission_form_shortcode()
{
    // Debug: Log shortcode execution
    error_log('Shortcode [plugin_repo_form] executed');

    if (!is_user_logged_in()) {
        error_log('Shortcode: User not logged in');
        return '<p>You must be logged in to submit a plugin or theme. <a href="/login">Log in</a> or <a href="/register">Register</a>.</p>';
    }

    ob_start();
    ?>
    <div class="submission-form__wrapper">
        <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="submission-form__form" id="repo_submission_form">
            <input type="hidden" name="action" value="plugin_repo_submission">
            <?php wp_nonce_field('plugin_repo_submission', 'plugin_repo_nonce'); ?>
            
            <!-- Type -->
            <div class="submission-form__field">
                <label for="type" class="submission-form__label">Type</label>
                <select name="type" id="type" required class="submission-form__select">
                    <option value="plugin_repo">Plugin</option>
                    <option value="theme">Theme</option>
                </select>
            </div>

            <!-- Name -->
            <div class="submission-form__field">
                <label for="name" class="submission-form__label">Plugin/Theme Name</label>
                <input 
                    type="text" 
                    name="name" 
                    id="name" 
                    placeholder="Enter name" 
                    required 
                    class="submission-form__input"
                />
            </div>

            <!-- Hosting Platform -->
            <div class="submission-form__field">
                <label for="hosting_platform" class="submission-form__label">Where is your plugin hosted?</label>
                <select name="hosting_platform" id="hosting_platform" class="submission-form__select">
                    <option value="github" selected>GitHub</option>
                    <option value="wordpress">WordPress.org</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <!-- WordPress.org Slug -->
            <div id="wporg-slug-field" class="submission-form__field" style="display: none;">
                <label for="wporg_slug" class="submission-form__label">WordPress.org Plugin Slug</label>
                <input 
                    type="text" 
                    name="wporg_slug" 
                    id="wporg_slug" 
                    placeholder="e.g. contact-form-7" 
                    class="submission-form__input"
                />
            </div>

            <!-- GitHub Username and Repo -->
            <div id="github-fields" class="submission-form__github">
                <div class="submission-form__github-column">
                    <label for="github_username" class="submission-form__label">GitHub Username</label>
                    <input 
                        type="text" 
                        name="github_username" 
                        id="github_username" 
                        placeholder="GitHub username" 
                        required 
                        class="submission-form__input"
                    />
                </div>

                <div class="github-column">
                    <label for="github_repo" class="submission-form__label">GitHub Repo</label>
                    <input 
                        type="text" 
                        name="github_repo" 
                        id="github_repo" 
                        placeholder="GitHub repository name" 
                        required 
                        class="submission-form__input"
                    />
                </div>
            </div>

            <!-- Landing Page Content -->
            <div id="landing-page-field">
                <label for="landing_page_content" class="submission-form__label">Landing Page Content</label>
                <select name="landing_page_content" id="landing_page_content" class="submission-form__input">
                    <option value="import_from_github" selected>Import markdown/txt file from GitHub</option>
                    <option value="upload_markdown">Upload markdown/html/txt file</option>
                    <option value="manual_edit">Edit manually using block editor</option>
                </select>
            </div>

            <div id="markdown-fields">
                <!-- Markdown File Name -->
                <div id="markdown-file-name-field">
                    <label for="markdown_file_name" class="submission-form__label">Markdown File Name</label>
                    <input 
                        type="text" 
                        name="markdown_file_name" 
                        id="markdown_file_name" 
                        placeholder="Enter markdown file name (e.g., readme.md)" 
                        class="submission-form__input"
                    />
                </div>

                <!-- Markdown File Upload -->
                <div id="upload-markdown-field" style="display:none;" class="submission-form__field">
                    <label for="markdown_file" class="submission-form__label">Upload Markdown File</label>
                    <input 
                        type="file" 
                        name="markdown_file" 
                        id="markdown_file" 
                        accept=".md,.html,.htm,.txt"
                        class="submission-form__input submission-form__input--file"
                    />
                </div>
            </div>

            <!-- Download URL -->
            <div id="download-url-field" style="display: none;" class="submission-form__field">
                <label for="download_url" class="submission-form__label">Download URL</label>
                <input 
                    type="url" 
                    name="download_url" 
                    id="download_url" 
                    placeholder="Enter download URL" 
                    class="submission-form__input"
                />
                <p class="submission-form__hint">Provide the direct URL to download your plugin/theme.</p>
            </div>

            <!-- Short Description -->
            <div class="submission-form__field">
                <label for="short_description" class="submission-form__label">Short Description</label>
                <textarea 
                    name="short_description" 
                    id="short_description" 
                    rows="3" 
                    placeholder="Write a short description of your plugin or theme"
                    class="submission-form__input"
                    required
                ></textarea>
            </div>

            <!-- Categories -->
            <div class="submission-form__field">
                <label for="categories" class="submission-form__label">Categories</label>
                <select name="categories[]" id="categories" multiple="multiple" class="submission-form__select">
                    <!-- Dynamic options will be loaded via Select2 -->
                </select>
                <p class="submission-form__hint">Start typing to search for existing categories or add new ones.</p>
            </div>

            <!-- Tags -->
            <div class="submission-form__field">
                <label for="tags" class="submission-form__label">Tags</label>
                <select name="tags[]" id="tags" multiple="multiple" class="submission-form__select">
                    <!-- Dynamic options will be loaded via Select2 -->
                </select>
                <p class="submission-form__hint">Start typing to search for existing tags or add new ones.</p>
            </div>

            <!-- Featured Image -->
            <div class="submission-form__field">
                <label for="featured_image" class="submission-form__label">Profile Image</label>
                <input 
                    type="file" 
                    name="featured_image" 
                    id="featured_image" 
                    accept="image/*" 
                    required 
                    class="submission-form__input submission-form__input--file"
                />
                <p class="submission-form__hint">Profile picture should be a square or a circle</p>
            </div>

            <!-- Cover Image -->
            <div class="submission-form__field">
                <label for="cover_image_url" class="submission-form__label">Cover Image</label>
                <input 
                    type="file" 
                    name="cover_image_url" 
                    id="cover_image_url" 
                    accept="image/jpg,image/jpeg,image/png" 
                    class="submission-form__input submission-form__input--file"
                />
                <p class="submission-form__hint">16:4 aspect ratio</p>
            </div>

            <!-- Submit Button -->
            <button 
                type="submit" 
                class="submission-form__submit"
            >
                Submit Plugin/Theme
            </button>
        </form>
    </div>

    <!-- JavaScript -->
    <script>
        // Toggle Name Label Based on Type
        document.getElementById("type").addEventListener("change", function () {
            const type = this.value;
            const nameLabel = document.querySelector('label[for="name"]');
            nameLabel.textContent = type === "plugin_repo" ? "Plugin Name" : "Theme Name";
        });

        // Validate markdown file extension on submit
        document.getElementById("repo_submission_form").addEventListener("submit", function (event) {
            const allowedExtensions = ["md", "html", "htm", "txt"];
            const markdownFileName = document.getElementById("markdown_file_name").value.trim();

            if (markdownFileName) {
                const fileExtension = markdownFileName.split(".").pop().toLowerCase();
                if (!allowedExtensions.includes(fileExtension)) {
                    event.preventDefault();
                    alert("Error: Unsupported file type. Please provide a Markdown (.md), HTML (.html), HTM (.htm), or text (.txt) file.");
                }
            }
        });

        // Toggle fields based on hosting platform selection
        document.addEventListener("DOMContentLoaded", function () {
            const platformSelect = document.getElementById("hosting_platform");
            const githubFields = document.getElementById("github-fields");
            const wporgSlugField = document.getElementById("wporg-slug-field");
            const downloadUrlField = document.getElementById("download-url-field");
            const githubUsernameField = document.getElementById("github_username");
            const githubRepoField = document.getElementById("github_repo");
            const landingPageContent = document.getElementById("landing_page_content");
            const markdownFileNameField = document.getElementById("markdown-file-name-field");
            const markdownFileUploadField = document.getElementById("upload-markdown-field");
            const importOption = landingPageContent.querySelector('option[value="import_from_github"]');
            const featuredImageField = document.querySelector('label[for="featured_image"]');

            if (!featuredImageField) {
                console.error('Featured image field not found');
                return;
            }

            const featuredImageFieldParent = featuredImageField.closest(".submission-form__field");

            function toggleFieldsBasedOnHosting() {
                const platform = platformSelect.value;

                githubFields.style.display = platform === "github" ? "flex" : "none";
                wporgSlugField.style.display = platform === "wordpress" ? "block" : "none";
                downloadUrlField.style.display = platform === "other" ? "block" : "none";
                const featuredImageInput = document.getElementById("featured_image");

                if (platform === "wordpress") {
                    featuredImageInput.disabled = true;
                    featuredImageInput.removeAttribute("required");
                    featuredImageFieldParent.style.display = "none";
                } else {
                    featuredImageInput.disabled = false;
                    featuredImageInput.setAttribute("required", "required");
                    featuredImageFieldParent.style.display = "block";
                }

                githubUsernameField.required = platform === "github";
                githubRepoField.required = platform === "github";
                document.getElementById("download_url").required = platform === "other";

                // Adjust landing page content options
                if (platform === "github") {
                    importOption.style.display = "block";
                } else {
                    importOption.style.display = "none";
                    if (landingPageContent.value === "import_from_github") {
                        landingPageContent.value = "upload_markdown";
                    }
                }

                toggleMarkdownFields();
            }

            function toggleMarkdownFields() {
                const selectedValue = landingPageContent.value;
                markdownFileNameField.style.display = selectedValue === "import_from_github" ? "block" : "none";
                markdownFileUploadField.style.display = selectedValue === "upload_markdown" ? "block" : "none";
            }

            platformSelect.addEventListener("change", toggleFieldsBasedOnHosting);
            landingPageContent.addEventListener("change", toggleMarkdownFields);

            toggleFieldsBasedOnHosting(); // Initialize
        });
    </script>
    <?php
    $output = ob_get_clean();
    
    return $output;
}

add_shortcode('plugin_repo_form', __NAMESPACE__ . '\\plugin_repo_submission_form_shortcode');

// Display success message
add_action('wp_footer', __NAMESPACE__ . '\\display_success_message');

function display_success_message() {
    if (isset($_GET['submission']) && $_GET['submission'] === 'success') {
        echo '<div class="notice success">Thank you! Your submission has been received and is pending approval.</div>';
    }
}