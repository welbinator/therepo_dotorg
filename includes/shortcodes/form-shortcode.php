<?php

namespace TheRepo\Shortcode\Form;

use WP_Error;

const ALLOWED_FILE_EXTENSIONS = ['md', 'html', 'htm', 'txt'];
const ALLOWED_IMAGE_EXTENSIONS = ['png', 'svg', 'jpg', 'jpeg'];
const ALLOWED_IMAGE_MIME_TYPES = ['image/png', 'image/svg+xml', 'image/jpeg'];

/**
 * Process and create terms for tags in the specified taxonomy.
 *
 * @param int $post_id Post ID.
 * @param array $tags Array of tag slugs.
 * @param string $taxonomy Taxonomy name.
 * @return array Term IDs.
 */
function process_tags($post_id, $tags, $taxonomy) {
    $term_ids = [];
    foreach ((array) $tags as $tag) {
        if (empty($tag)) {
            continue;
        }
        $term = term_exists($tag, $taxonomy);
        if (!$term) {
            $term = wp_insert_term($tag, $taxonomy, ['slug' => sanitize_title($tag)]);
            if (is_wp_error($term)) {
                error_log('Error creating term ' . $tag . ': ' . $term->get_error_message());
                continue;
            }
            $term_ids[] = (int) $term['term_id'];
        } else {
            $term_ids[] = (int) $term['term_id'];
        }
    }
    return $term_ids;
}

/**
 * Handle file uploads for images.
 *
 * @param array $file $_FILES array for the uploaded file.
 * @param int $post_id Post ID for attachment.
 * @param string $meta_key Meta key to store the URL (optional).
 * @return array|WP_Error Array with attachment_id and url, or WP_Error on failure.
 */
function handle_image_upload($file, $post_id, $meta_key = '') {
    if (empty($file['name'])) {
        return new WP_Error('upload_error', esc_html__('No file uploaded.', 'therepo'));
    }

    $upload = wp_handle_upload($file, ['test_form' => false]);
    if (!$upload || isset($upload['error'])) {
        return new WP_Error('upload_error', esc_html__('File upload failed: ' . ($upload['error'] ?? 'Unknown error'), 'therepo'));
    }

    $file_path = $upload['file'];
    $file_url = $upload['url'];
    $filetype = wp_check_filetype($file_path);

    if (!in_array($filetype['type'], ALLOWED_IMAGE_MIME_TYPES, true)) {
        unlink($file_path);
        return new WP_Error('upload_error', esc_html__('Invalid file type. Allowed: png, svg, jpg, jpeg.', 'therepo'));
    }

    if (filesize($file_path) > wp_max_upload_size()) {
        unlink($file_path);
        return new WP_Error('upload_error', esc_html__('File size exceeds maximum allowed.', 'therepo'));
    }

    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title' => sanitize_file_name($file['name']),
        'post_content' => '',
        'post_status' => 'inherit',
    ];

    $attachment_id = wp_insert_attachment($attachment, $file_path, $post_id);
    if (is_wp_error($attachment_id)) {
        unlink($file_path);
        return $attachment_id;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attachment_id, $file_path);
    wp_update_attachment_metadata($attachment_id, $attach_data);

    if ($meta_key) {
        update_post_meta($post_id, $meta_key, esc_url_raw($file_url));
    }

    return ['attachment_id' => $attachment_id, 'url' => $file_url];
}

function handle_plugin_repo_submission() {
    // Use a more specific nonce action
    if (!isset($_POST['plugin_repo_nonce']) || !wp_verify_nonce($_POST['plugin_repo_nonce'], 'therepo_plugin_submission_' . get_current_user_id())) {
        wp_die(esc_html__('Error: Invalid form submission.', 'therepo'), esc_html__('Form Error', 'therepo'), ['response' => 403]);
    }

    if (!is_user_logged_in()) {
        wp_die(esc_html__('Error: You must be logged in to submit a plugin.', 'therepo'), esc_html__('Authentication Error', 'therepo'), ['response' => 401]);
    }

    // Gather and sanitize input values
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
    $import_sections = isset($_POST['import_sections']) ? array_map('sanitize_text_field', (array) $_POST['import_sections']) : [];
    $post_content = esc_html__('Add your plugin information here!', 'therepo');
    $short_description = $hosting_platform === 'wordpress' ? '' : (isset($_POST['short_description']) ? sanitize_textarea_field($_POST['short_description']) : '');
    $post_type = 'plugin_repo';
    $taxonomy = 'plugin-category';
    $tags_taxonomy = 'plugin-tag';

    // Early validation
    $errors = new WP_Error();
    if (empty($name)) {
        $errors->add('missing_name', esc_html__('Plugin name is required.', 'therepo'));
    }
    if ($hosting_platform === 'wordpress' && empty($wporg_slug)) {
        $errors->add('missing_slug', esc_html__('WordPress.org plugin slug is required.', 'therepo'));
    }
    if ($hosting_platform === 'github' && (empty($github_username) || empty($github_repo))) {
        $errors->add('missing_github', esc_html__('GitHub username and repository are required.', 'therepo'));
    }
    if ($hosting_platform === 'other' && empty($download_url)) {
        $errors->add('missing_url', esc_html__('Download URL is required for other platforms.', 'therepo'));
    }
    if ($hosting_platform !== 'wordpress' && empty($short_description)) {
        $errors->add('missing_description', esc_html__('Short description is required.', 'therepo'));
    }
    if ($hosting_platform === 'wordpress' && empty($import_sections)) {
        $errors->add('missing_sections', esc_html__('At least one import section must be selected for WordPress.org plugins.', 'therepo'));
    }
    if ($hosting_platform !== 'wordpress' && $landing_page_content === 'import_from_github' && empty($markdown_file_name)) {
        $errors->add('missing_markdown', esc_html__('Markdown file name is required.', 'therepo'));
    }
    if ($hosting_platform !== 'wordpress' && $landing_page_content === 'upload_markdown' && empty($_FILES['markdown_file']['name'])) {
        $errors->add('missing_file', esc_html__('A markdown file must be uploaded.', 'therepo'));
    }
    if ($errors->has_errors()) {
        wp_die($errors->get_error_message(), esc_html__('Form Error', 'therepo'), ['response' => 400]);
    }

    // Define allowed HTML for sanitization
    $allowed_html = [
        'img' => ['src' => [], 'alt' => []],
        'p' => [], 'a' => ['href' => []], 'ul' => [], 'ol' => [], 'li' => [],
        'strong' => [], 'em' => [], 'h1' => [], 'h2' => [], 'h3' => [],
        'h4' => [], 'h5' => [], 'h6' => [], 'blockquote' => [], 'code' => [],
        'pre' => [], 'br' => [], 'hr' => [], 'iframe' => ['src' => [], 'title' => [], 'width' => [], 'height' => [], 'frameborder' => [], 'allow' => [], 'allowfullscreen' => []],
    ];

    // Handle "Import Markdown file from GitHub"
    if ($hosting_platform !== 'wordpress' && $landing_page_content === 'import_from_github') {
        $file_extension = strtolower(pathinfo($markdown_file_name, PATHINFO_EXTENSION));
        if (!in_array($file_extension, ALLOWED_FILE_EXTENSIONS, true)) {
            $errors->add('invalid_file_type', esc_html__('Unsupported file type. Please provide a Markdown (.md), HTML (.html), HTM (.htm), or text (.txt) file.', 'therepo'));
            wp_die($errors->get_error_message(), esc_html__('Form Error', 'therepo'), ['response' => 400]);
        }

        $repo_api_url = "https://api.github.com/repos/" . urlencode($github_username) . "/" . urlencode($github_repo);
        $headers = ['User-Agent' => 'TheRepo-Plugin'];
        if (defined('GITHUB_API_TOKEN') && GITHUB_API_TOKEN) {
            $headers['Authorization'] = 'token ' . GITHUB_API_TOKEN;
        }
        $repo_info = wp_remote_get($repo_api_url, ['headers' => $headers]);

        if (is_wp_error($repo_info) || wp_remote_retrieve_response_code($repo_info) !== 200) {
            error_log('GitHub API Error: ' . (is_wp_error($repo_info) ? $repo_info->get_error_message() : wp_remote_retrieve_body($repo_info)));
            $errors->add('github_api_error', esc_html__('Unable to fetch repository details from GitHub.', 'therepo'));
            wp_die($errors->get_error_message(), esc_html__('API Error', 'therepo'), ['response' => 500]);
        }

        $repo_data = json_decode(wp_remote_retrieve_body($repo_info), true);
        $default_branch = $repo_data['default_branch'] ?? 'main';

        $readme_url = "https://raw.githubusercontent.com/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/" . $default_branch . "/" . urlencode($markdown_file_name);
        $response = wp_remote_get($readme_url, ['headers' => $headers]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            error_log('Markdown Fetch Error: ' . (is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)) . ' URL: ' . $readme_url);
            $errors->add('markdown_fetch_error', esc_html__('Unable to retrieve the Markdown file from GitHub.', 'therepo'));
            wp_die($errors->get_error_message(), esc_html__('API Error', 'therepo'), ['response' => 500]);
        }

        $markdown = wp_remote_retrieve_body($response);
        if (empty($markdown)) {
            error_log('Markdown File Error: File content is empty. URL: ' . $readme_url);
            $errors->add('empty_markdown', esc_html__('The Markdown file from GitHub is empty.', 'therepo'));
            wp_die($errors->get_error_message(), esc_html__('Form Error', 'therepo'), ['response' => 400]);
        }

        $parsedown = new \Parsedown();
        $post_content = $parsedown->text($markdown);
        $post_content = wp_kses_post(wp_slash($post_content));
    }

    // Handle "Upload Markdown file"
    if ($hosting_platform !== 'wordpress' && $landing_page_content === 'upload_markdown' && isset($_FILES['markdown_file'])) {
        $result = handle_image_upload($_FILES['markdown_file'], 0); // No post_id yet, no meta_key
        if (is_wp_error($result)) {
            wp_die($result->get_error_message(), esc_html__('Form Error', 'therepo'), ['response' => 400]);
        }

        $file_tmp_path = $result['attachment_id'] ? '' : $result['url']; // Use URL if no attachment_id
        $file_name = basename($file_tmp_path);
        $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        if (!in_array($file_extension, ALLOWED_FILE_EXTENSIONS, true)) {
            if (file_exists($file_tmp_path)) {
                unlink($file_tmp_path);
            }
            $errors->add('invalid_file_type', esc_html__('Unsupported file type. Please upload a Markdown (.md), HTML (.html), or HTM (.htm) file.', 'therepo'));
            wp_die($errors->get_error_message(), esc_html__('Form Error', 'therepo'), ['response' => 400]);
        }

        $file_content = file_get_contents($file_tmp_path);
        if (empty($file_content)) {
            if (file_exists($file_tmp_path)) {
                unlink($file_tmp_path);
            }
            $errors->add('empty_file', esc_html__('The uploaded file is empty.', 'therepo'));
            wp_die($errors->get_error_message(), esc_html__('Form Error', 'therepo'), ['response' => 400]);
        }

        if ($file_extension === 'md') {
            $parsedown = new \Parsedown();
            $post_content = $parsedown->text($file_content);
        } else {
            $post_content = wp_kses($file_content, $allowed_html);
        }
        $post_content = wp_kses_post(wp_slash($post_content));
        if (file_exists($file_tmp_path)) {
            unlink($file_tmp_path);
        }
    }

    // Handle hosting platform
    $latest_release_url = '';
    $support_url = '';
    if ($hosting_platform === 'other') {
        if (empty($post_content)) {
            $errors->add('missing_content', esc_html__('You must upload a valid file to populate the post content.', 'therepo'));
            wp_die($errors->get_error_message(), esc_html__('Form Error', 'therepo'), ['response' => 400]);
        }
        $latest_release_url = $download_url;
    } elseif ($hosting_platform === 'github') {
        $latest_release_url = "https://api.github.com/repos/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/releases/latest";
        $support_url = "https://github.com/" . urlencode($github_username) . "/" . urlencode($github_repo) . "/issues";
    } elseif ($hosting_platform === 'wordpress') {
        $latest_release_url = "https://downloads.wordpress.org/plugin/" . $wporg_slug . ".zip";
        $support_url = "https://wordpress.org/support/plugin/" . urlencode($wporg_slug) . "/";

        if (!empty($wporg_slug) && preg_match('/^[a-z0-9-]+$/i', $wporg_slug)) {
            if (!function_exists('plugins_api')) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }

            $cache_key = 'wp_plugin_info_' . md5($wporg_slug);
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $api_response = $cached_data;
            } else {
                $api_response = plugins_api('plugin_information', [
                    'slug' => $wporg_slug,
                    'fields' => [
                        'icons' => true,
                        'tags' => true,
                        'description' => true,
                        'sections' => true,
                    ],
                ]);
                set_transient($cache_key, $api_response, HOUR_IN_SECONDS);
            }

            if (is_wp_error($api_response)) {
                error_log('Plugin Info API Error: ' . $api_response->get_error_message());
            } else {
                // Log full API response for debugging
                error_log('WordPress.org API Response for slug ' . $wporg_slug . ': ' . print_r($api_response, true));

                // Set short description for excerpt
                if (!empty($api_response->description)) {
                    $short_description = wp_kses(substr($api_response->description, 0, 160), $allowed_html);
                    error_log('WordPress.org Short Description for slug ' . $wporg_slug . ': ' . $short_description);
                } else {
                    error_log('Plugin Info API Error: No short description found for slug ' . $wporg_slug);
                    $short_description = '';
                }

                // Set post content from selected sections
                if (!empty($api_response->sections) && is_array($api_response->sections)) {
                    $sections_content = [];
                    $available_sections = ['description', 'installation', 'faq', 'changelog', 'reviews', 'other_notes'];
                    foreach ($available_sections as $section) {
                        if (in_array($section, $import_sections, true) && !empty($api_response->sections[$section])) {
                            $sections_content[] = '<h2>' . esc_html(ucfirst($section)) . '</h2>' . wp_kses($api_response->sections[$section], $allowed_html);
                        }
                    }
                    error_log('WordPress.org Sections for slug ' . $wporg_slug . ': ' . print_r($sections_content, true));
                    if (!empty($sections_content)) {
                        $post_content = implode("\n\n", $sections_content);
                        $post_content = wp_kses_post(wp_slash($post_content));
                    } else {
                        error_log('Plugin Info API Error: No selected sections found for slug ' . $wporg_slug);
                        $post_content = wp_kses($api_response->description, $allowed_html); // Fallback to short description
                    }
                } else {
                    error_log('Plugin Info API Error: No sections found for slug ' . $wporg_slug);
                    $post_content = wp_kses($api_response->description, $allowed_html); // Fallback to short description
                }
            }
        } else {
            error_log('Plugin Info API Error: Invalid plugin slug provided: ' . $wporg_slug);
            $post_content = wp_kses($api_response->description, $allowed_html); // Fallback to short description
        }
    }

    // Create the post
    $post_id = wp_insert_post([
        'post_title' => $name,
        'post_content' => $post_content,
        'post_excerpt' => $short_description,
        'post_type' => $post_type,
        'post_status' => 'pending',
        'post_author' => get_current_user_id(),
    ]);

    if (!$post_id || is_wp_error($post_id)) {
        $errors->add('post_creation_failed', esc_html__('Unable to create the submission.', 'therepo'));
        wp_die($errors->get_error_message(), esc_html__('Submission Error', 'therepo'), ['response' => 500]);
    }

    // Handle file uploads
    $featured_image_id = null;
    if (!empty($_FILES['featured_image']['name']) && $hosting_platform !== 'wordpress') {
        $result = handle_image_upload($_FILES['featured_image'], $post_id);
        if (is_wp_error($result)) {
            wp_die($result->get_error_message(), esc_html__('Form Error', 'therepo'), ['response' => 400]);
        }
        $featured_image_id = $result['attachment_id'];
    }

    $cover_image_url = null;
    if (!empty($_FILES['cover_image_url']['name'])) {
        $result = handle_image_upload($_FILES['cover_image_url'], $post_id, 'cover_image_url');
        if (is_wp_error($result)) {
            wp_die($result->get_error_message(), esc_html__('Form Error', 'therepo'), ['response' => 400]);
        }
        $cover_image_url = $result['url'];
    } else {
        $default_image_path = plugin_dir_url(dirname(dirname(__FILE__))) . 'assets/img/therepo-default-banner.jpg';
        $cover_image_url = esc_url_raw($default_image_path);
        update_post_meta($post_id, 'cover_image_url', $cover_image_url);
    }

    // Update post meta
    update_post_meta($post_id, 'latest_release_url', $latest_release_url);
    update_post_meta($post_id, 'hosting_platform', $hosting_platform);

    // Handle platform-specific meta and tags
    $term_ids = [];
    if ($hosting_platform === 'github') {
        update_post_meta($post_id, 'github_username', $github_username);
        update_post_meta($post_id, 'github_repo', $github_repo);
        update_post_meta($post_id, 'support_url', $support_url);
        $term_ids = process_tags($post_id, $tags, $tags_taxonomy);
    } elseif ($hosting_platform === 'wordpress') {
        update_post_meta($post_id, 'wporg_slug', $wporg_slug);
        update_post_meta($post_id, 'support_url', $support_url);

        if (!empty($wporg_slug) && preg_match('/^[a-z0-9-]+$/i', $wporg_slug)) {
            if (!function_exists('plugins_api')) {
                require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            }

            $cache_key = 'wp_plugin_info_' . md5($wporg_slug);
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $api_response = $cached_data;
            } else {
                $api_response = plugins_api('plugin_information', [
                    'slug' => $wporg_slug,
                    'fields' => [
                        'icons' => true,
                        'tags' => true,
                        'description' => true,
                        'sections' => true,
                    ],
                ]);
                set_transient($cache_key, $api_response, HOUR_IN_SECONDS);
            }

            if (is_wp_error($api_response)) {
                error_log('Plugin Info API Error: ' . $api_response->get_error_message());
            } else {
                if (!empty($api_response->icons) && is_array($api_response->icons)) {
                    $icon_url = !empty($api_response->icons['svg']) ? $api_response->icons['svg'] :
                                (!empty($api_response->icons['2x']) ? $api_response->icons['2x'] :
                                (!empty($api_response->icons['1x']) ? $api_response->icons['1x'] :
                                (!empty($api_response->icons['default']) ? $api_response->icons['default'] : '')));

                    if ($icon_url) {
                        update_post_meta($post_id, 'wporg_plugin_icon', esc_url_raw($icon_url));
                        $tmp = download_url($icon_url);
                        if (!is_wp_error($tmp)) {
                            $parsed_url = parse_url($icon_url, PHP_URL_PATH);
                            $filename = basename($parsed_url);
                            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                            if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS, true)) {
                                error_log('Invalid icon extension: ' . $extension . '. Falling back to plugin slug with appropriate extension for URL: ' . $icon_url);
                                $filename = $wporg_slug . ($api_response->icons['svg'] ? '.svg' : '.png');
                                $extension = $api_response->icons['svg'] ? 'svg' : 'png';
                            }

                            $tmp_base = preg_replace('/\.\w+$/', '', $tmp);
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

                                $allowed_mime_types = array_merge(wp_get_mime_types(), ['svg' => 'image/svg+xml']);
                                $filetype = wp_check_filetype($file_array['name'], $allowed_mime_types);
                                if (empty($filetype['ext']) || empty($filetype['type'])) {
                                    if ($extension === 'svg') {
                                        $filetype = ['ext' => 'svg', 'type' => 'image/svg+xml'];
                                        error_log('Fallback: Manually set filetype for SVG: ' . print_r($filetype, true));
                                    }
                                }

                                if (
                                    !isset($allowed_mime_types[$filetype['ext']]) ||
                                    !in_array($filetype['type'], ALLOWED_IMAGE_MIME_TYPES, true)
                                ) {
                                    error_log('File Type Validation Error: Extension=' . $filetype['ext'] . ', MIME=' . $filetype['type']);
                                    unlink($tmp);
                                } else {
                                    if ($extension === 'svg') {
                                        $file_content = file_get_contents($tmp);
                                        if (strpos($file_content, '<svg') === false) {
                                            error_log('Error: Downloaded file is not a valid SVG for URL: ' . $icon_url);
                                            unlink($tmp);
                                        } else {
                                            if (!function_exists('media_handle_sideload')) {
                                                require_once ABSPATH . 'wp-admin/includes/image.php';
                                                require_once ABSPATH . 'wp-admin/includes/file.php';
                                                require_once ABSPATH . 'wp-admin/includes/media.php';
                                            }

                                            $attachment_id = media_handle_sideload($file_array, $post_id, sanitize_file_name($filename));
                                            if (!is_wp_error($attachment_id)) {
                                                error_log('Featured Image Set Successfully: Attachment ID=' . $attachment_id);
                                                $featured_image_id = $attachment_id;
                                            } else {
                                                error_log('Error setting featured image: ' . $attachment_id->get_error_message());
                                                unlink($tmp);
                                            }
                                        }
                                    } else {
                                        if (!function_exists('media_handle_sideload')) {
                                            require_once ABSPATH . 'wp-admin/includes/image.php';
                                            require_once ABSPATH . 'wp-admin/includes/file.php';
                                            require_once ABSPATH . 'wp-admin/includes/media.php';
                                        }

                                        $attachment_id = media_handle_sideload($file_array, $post_id, sanitize_file_name($filename));
                                        if (!is_wp_error($attachment_id)) {
                                            error_log('Featured Image Set Successfully: Attachment ID=' . $attachment_id);
                                            $featured_image_id = $attachment_id;
                                        } else {
                                            error_log('Error setting featured image: ' . $attachment_id->get_error_message());
                                            unlink($tmp);
                                        }
                                    }
                                }
                            }
                        } else {
                            error_log('Error downloading image: ' . $tmp->get_error_message());
                        }
                    }
                }

                if (!empty($api_response->tags) && is_array($api_response->tags)) {
                    error_log('WordPress.org Tags for slug ' . $wporg_slug . ': ' . print_r($api_response->tags, true));
                    $wporg_tags = array_map('sanitize_title', array_keys($api_response->tags));
                    $wporg_tags = array_filter($wporg_tags);
                    $term_ids = process_tags($post_id, array_merge($tags, $wporg_tags), $tags_taxonomy);
                } else {
                    error_log('Plugin Info API Error: No tags found for slug ' . $wporg_slug);
                    $term_ids = process_tags($post_id, $tags, $tags_taxonomy);
                }
            }
        }
    } else {
        update_post_meta($post_id, 'download_url', $download_url);
        $term_ids = process_tags($post_id, $tags, $tags_taxonomy);
    }

    // Set terms and categories
    if (!empty($term_ids)) {
        $result = wp_set_object_terms($post_id, $term_ids, $tags_taxonomy, false);
        if (is_wp_error($result)) {
            error_log('Error setting ' . $tags_taxonomy . ' terms: ' . $result->get_error_message());
        }
    }

    if (!empty($categories)) {
        $result = wp_set_object_terms($post_id, $categories, $taxonomy, false);
        if (is_wp_error($result)) {
            error_log('Error setting ' . $taxonomy . ' terms: ' . $result->get_error_message());
        }
    }

    if ($featured_image_id) {
        set_post_thumbnail($post_id, $featured_image_id);
    }

    wp_redirect(add_query_arg('submission', 'success', home_url()));
    exit();
}

add_action('admin_post_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission');
add_action('admin_post_nopriv_plugin_repo_submission', __NAMESPACE__ . '\\handle_plugin_repo_submission');

function plugin_repo_submission_form_shortcode() {
    error_log('Shortcode [plugin_repo_form] executed');
    if (!is_user_logged_in()) {
        error_log('Shortcode: User not logged in');
        return '<p>' . esc_html__('You must be logged in to submit a plugin.', 'therepo') . ' <a href="' . esc_url(wp_login_url()) . '">' . esc_html__('Log in', 'therepo') . '</a> ' . esc_html__('or', 'therepo') . ' <a href="' . esc_url(wp_registration_url()) . '">' . esc_html__('Register', 'therepo') . '</a>.</p>';
    }


    ob_start();
    ?>
    <div class="submission-form__wrapper">
        <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="submission-form__form" id="repo_submission_form">
            <input type="hidden" name="action" value="plugin_repo_submission">
            <?php wp_nonce_field('therepo_plugin_submission_' . get_current_user_id(), 'plugin_repo_nonce'); ?>
            
            <!-- Name -->
            <div class="submission-form__field">
                <label for="name" class="submission-form__label"><?php esc_html_e('Plugin Name', 'therepo'); ?></label>
                <input 
                    type="text" 
                    name="name" 
                    id="name" 
                    placeholder="<?php esc_attr_e('Enter plugin name', 'therepo'); ?>" 
                    required 
                    class="submission-form__input"
                />
            </div>

            <!-- Hosting Platform -->
            <div class="submission-form__field">
                <label for="hosting_platform" class="submission-form__label"><?php esc_html_e('Where is your plugin hosted?', 'therepo'); ?></label>
                <select name="hosting_platform" id="hosting_platform" class="submission-form__select">
                    <option value="github" selected><?php esc_html_e('GitHub', 'therepo'); ?></option>
                    <option value="wordpress"><?php esc_html_e('WordPress.org', 'therepo'); ?></option>
                    <option value="other"><?php esc_html_e('Other', 'therepo'); ?></option>
                </select>
            </div>

            <!-- WordPress.org Slug -->
            <div id="wporg-slug-field" class="submission-form__field" style="display: none;">
                <label for="wporg_slug" class="submission-form__label"><?php esc_html_e('WordPress.org Plugin Slug', 'therepo'); ?></label>
                <input 
                    type="text" 
                    name="wporg_slug" 
                    id="wporg_slug" 
                    placeholder="<?php esc_attr_e('e.g. contact-form-7', 'therepo'); ?>" 
                    class="submission-form__input"
                />
            </div>

            <!-- GitHub Username and Repo -->
            <div id="github-fields" class="submission-form__github">
                <div class="submission-form__github-column">
                    <label for="github_username" class="submission-form__label"><?php esc_html_e('GitHub Username', 'therepo'); ?></label>
                    <input 
                        type="text" 
                        name="github_username" 
                        id="github_username" 
                        placeholder="<?php esc_attr_e('GitHub username', 'therepo'); ?>" 
                        required 
                        class="submission-form__input"
                    />
                </div>

                <div class="github-column">
                    <label for="github_repo" class="submission-form__label"><?php esc_html_e('GitHub Repo', 'therepo'); ?></label>
                    <input 
                        type="text" 
                        name="github_repo" 
                        id="github_repo" 
                        placeholder="<?php esc_attr_e('GitHub repository name', 'therepo'); ?>" 
                        required 
                        class="submission-form__input"
                    />
                </div>
            </div>

            <!-- Landing Page Content -->
            <div id="landing-page-field" class="submission-form__field">
                <label for="landing_page_content" class="submission-form__label"><?php esc_html_e('Landing Page Content', 'therepo'); ?></label>
                <select name="landing_page_content" id="landing_page_content" class="submission-form__input">
                    <option value="import_from_github" selected><?php esc_html_e('Import markdown/txt file from GitHub', 'therepo'); ?></option>
                    <option value="upload_markdown"><?php esc_html_e('Upload markdown/html/txt file', 'therepo'); ?></option>
                    <option value="manual_edit"><?php esc_html_e('Edit manually using block editor', 'therepo'); ?></option>
                </select>
            </div>

            <!-- Markdown Fields -->
            <div id="markdown-fields">
                <!-- Markdown File Name -->
                <div id="markdown-file-name-field" class="submission-form__field">
                    <label for="markdown_file_name" class="submission-form__label"><?php esc_html_e('Markdown File Name', 'therepo'); ?></label>
                    <input 
                        type="text" 
                        name="markdown_file_name" 
                        id="markdown_file_name" 
                        placeholder="<?php esc_attr_e('Enter markdown file name (e.g., readme.md)', 'therepo'); ?>" 
                        class="submission-form__input"
                    />
                </div>

                <!-- Markdown File Upload -->
                <div id="upload-markdown-field" class="submission-form__field" style="display:none;">
                    <label for="markdown_file" class="submission-form__label"><?php esc_html_e('Upload Markdown File', 'therepo'); ?></label>
                    <input 
                        type="file" 
                        name="markdown_file" 
                        id="markdown_file" 
                        accept=".md,.html,.htm,.txt"
                        class="submission-form__input submission-form__input--file"
                    />
                </div>
            </div>

            <!-- Import Sections for WordPress.org -->
            <div id="import-sections-field" class="submission-form__field" style="display: none;">
                <label class="submission-form__label"><?php esc_html_e('Import Sections', 'therepo'); ?></label>
                <div class="submission-form__checkbox-group">
                    <label><input type="checkbox" name="import_sections[]" value="description"> <?php esc_html_e('Description', 'therepo'); ?></label>
                    <label><input type="checkbox" name="import_sections[]" value="installation"> <?php esc_html_e('Installation', 'therepo'); ?></label>
                    <label><input type="checkbox" name="import_sections[]" value="faq"> <?php esc_html_e('FAQ', 'therepo'); ?></label>
                    <label><input type="checkbox" name="import_sections[]" value="changelog"> <?php esc_html_e('Changelog', 'therepo'); ?></label>
                    <label><input type="checkbox" name="import_sections[]" value="reviews"> <?php esc_html_e('Reviews', 'therepo'); ?></label>
                    <label><input type="checkbox" name="import_sections[]" value="other_notes"> <?php esc_html_e('Other Notes', 'therepo'); ?></label>
                </div>
                <p class="submission-form__hint"><?php esc_html_e('Select at least one section to import from WordPress.org.', 'therepo'); ?></p>
            </div>

            <!-- Download URL -->
            <div id="download-url-field" class="submission-form__field" style="display: none;">
                <label for="download_url" class="submission-form__label"><?php esc_html_e('Download URL', 'therepo'); ?></label>
                <input 
                    type="url" 
                    name="download_url" 
                    id="download_url" 
                    placeholder="<?php esc_attr_e('Enter download URL', 'therepo'); ?>" 
                    class="submission-form__input"
                />
                <p class="submission-form__hint"><?php esc_html_e('Provide the direct URL to download your plugin.', 'therepo'); ?></p>
            </div>

            <!-- Short Description -->
            <div id="short-description-field" class="submission-form__field">
                <label for="short_description" class="submission-form__label"><?php esc_html_e('Short Description', 'therepo'); ?></label>
                <textarea 
                    name="short_description" 
                    id="short_description" 
                    rows="3" 
                    placeholder="<?php esc_attr_e('Write a short description of your plugin', 'therepo'); ?>"
                    class="submission-form__input"
                    required
                ></textarea>
            </div>

            <!-- Categories -->
            <div class="submission-form__field">
                <label for="categories" class="submission-form__label"><?php esc_html_e('Categories', 'therepo'); ?></label>
                <select name="categories[]" id="categories" multiple="multiple" class="submission-form__select">
                    <!-- Dynamic options will be loaded via Select2 -->
                </select>
                <p class="submission-form__hint"><?php esc_html_e('Start typing to search for existing categories or add new ones.', 'therepo'); ?></p>
            </div>

            <!-- Tags -->
            <div class="submission-form__field">
                <label for="tags" class="submission-form__label"><?php esc_html_e('Tags', 'therepo'); ?></label>
                <select name="tags[]" id="tags" multiple="multiple" class="submission-form__select">
                    <!-- Dynamic options will be loaded via Select2 -->
                </select>
                <p class="submission-form__hint"><?php esc_html_e('Start typing to search for existing tags or add new ones.', 'therepo'); ?></p>
            </div>

            <!-- Featured Image -->
            <div class="submission-form__field">
                <label for="featured_image" class="submission-form__label"><?php esc_html_e('Profile Image', 'therepo'); ?></label>
                <input 
                    type="file" 
                    name="featured_image" 
                    id="featured_image" 
                    accept="image/*" 
                    required 
                    class="submission-form__input submission-form__input--file"
                />
                <p class="submission-form__hint"><?php esc_html_e('Profile picture should be a square or a circle', 'therepo'); ?></p>
            </div>

            <!-- Cover Image -->
            <div class="submission-form__field">
                <label for="cover_image_url" class="submission-form__label"><?php esc_html_e('Cover Image', 'therepo'); ?></label>
                <input 
                    type="file" 
                    name="cover_image_url" 
                    id="cover_image_url" 
                    accept="image/jpg,image/jpeg,image/png" 
                    class="submission-form__input submission-form__input--file"
                />
                <p class="submission-form__hint"><?php esc_html_e('16:4 aspect ratio', 'therepo'); ?></p>
            </div>

            <!-- Submit Button -->
            <button 
                type="submit" 
                class="submission-form__submit"
            >
                <?php esc_html_e('Submit Plugin', 'therepo'); ?>
            </button>
        </form>
    </div>
    <?php
    $output = ob_get_clean();
    return $output;
}

add_shortcode('plugin_repo_form', __NAMESPACE__ . '\\plugin_repo_submission_form_shortcode');

function display_success_message() {
    if (isset($_GET['submission']) && $_GET['submission'] === 'success') {
        echo '<div class="notice success">' . esc_html__('Thank you! Your plugin submission has been received and is pending approval.', 'therepo') . '</div>';
    }
}

add_action('wp_footer', __NAMESPACE__ . '\\display_success_message');