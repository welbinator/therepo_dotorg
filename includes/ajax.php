<?php
namespace TheRepo\Ajax;

function filter_plugins() {
    global $wpdb;

    $search = sanitize_text_field($_GET['search'] ?? '');
    $type = sanitize_text_field($_GET['type'] ?? '');
    $category = sanitize_text_field($_GET['category'] ?? '');

    $args = [
        'post_type' => ['plugin_repo', 'theme_repo'], // Default to both plugins and themes
        'posts_per_page' => -1,
        's' => $search,
    ];

    // If a specific type is selected, filter by post type
    if ($type) {
        $args['post_type'] = $type;
    }

    // Handle category filter
    if ($category) {
        $args['tax_query'] = [
            [
                'taxonomy' => $type === 'plugin_repo' ? 'plugin-category' : 'theme-category',
                'field'    => 'slug',
                'terms'    => $category,
            ],
        ];
    }

    // Debugging: Log the query arguments
    error_log('Filter Plugins Args: ' . print_r($args, true));

    // Run the WP_Query
    $query = new \WP_Query($args);

    if (!$query->have_posts() && $search) {
        // Fallback: Direct SQL query for better search capabilities
        $post_types = implode("','", array_map('esc_sql', $args['post_type']));
        $sql = "
            SELECT DISTINCT p.ID
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
            LEFT JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
            LEFT JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
            WHERE p.post_type IN ('$post_types')
            AND (p.post_title LIKE %s OR p.post_content LIKE %s OR t.name LIKE %s)
            AND p.post_status = 'publish'
        ";
        $like = '%' . $wpdb->esc_like($search) . '%';
        $results = $wpdb->get_col($wpdb->prepare($sql, $like, $like, $like));

        // Run a secondary query to fetch these posts
        if (!empty($results)) {
            $query = new \WP_Query([
                'post__in' => $results,
                'post_type' => $args['post_type'],
                'posts_per_page' => -1,
            ]);
        }
    }

    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();
            $post_type = get_post_type() === 'plugin_repo' ? 'Plugin' : 'Theme';

            // Fetch categories and tags
            $categories = get_the_terms(get_the_ID(), $post_type === 'Plugin' ? 'plugin-category' : 'theme-category');
            $category_names = $categories && !is_wp_error($categories) ? wp_list_pluck($categories, 'name') : [];

            $tags = get_the_terms(get_the_ID(), $post_type === 'Plugin' ? 'plugin-tag' : 'theme-tag');
            $tag_names = $tags && !is_wp_error($tags) ? wp_list_pluck($tags, 'name') : [];

            $featured_image = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') ?: 'https://via.placeholder.com/60';
            $cover_image = get_post_meta(get_the_ID(), 'cover_image_url', true) ?: '';
            $latest_release_url = get_post_meta(get_the_ID(), 'latest_release_url', true);
            $free_or_pro = get_post_meta(get_the_ID(), 'free_or_pro', true);

            ?>
            <div class="!bg-white !rounded-lg !shadow-md !overflow-hidden relative">
                <div class="!p-6 !pb-12">
                    <div class="!flex !gap-4 !items-start !space-x-4 !mb-4">
                        <img src="<?php echo esc_url($featured_image); ?>" alt="<?php the_title_attribute(); ?>" width="60" height="60" class="!rounded-md !object-cover">
                        <div class="!flex-grow">
                            <div class="!flex !justify-between !items-start">
                                <h3 class="!text-xl !font-semibold"><?php the_title(); ?></h3>
                                <span class="!px-2 !py-1 !text-xs !font-semibold !rounded <?php echo $post_type === 'Plugin' ? '!bg-blue-100 !text-blue-800' : '!bg-green-100 !text-green-800'; ?>">
                                    <?php echo esc_html($post_type); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <p class="!text-gray-600 !mb-4"><?php echo esc_html(wp_trim_words(get_the_content(), 20)); ?></p>
                    <div class="!flex !flex-wrap !justify-between !items-center github-download-button">
                        <div class="!flex !space-x-2 !gap-3 !mb-5">
                            <?php if (!empty($category_names)) : ?>
                                <?php foreach ($category_names as $category_name) : ?>
                                    <span class="!px-2 !py-1 !bg-gray-100 !text-gray-600 !text-xs !rounded-full !whitespace-nowrap">
                                        <?php echo esc_html($category_name); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <?php if (!empty($tag_names)) : ?>
                                <?php foreach ($tag_names as $tag_name) : ?>
                                    <span class="!px-2 !py-1 !bg-blue-100 !text-blue-600 !text-xs !rounded-full !whitespace-nowrap">
                                        <?php echo esc_html($tag_name); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <a href="#" target="_blank" id="<?php echo esc_attr($latest_release_url); ?>" class="!bg-blue-500 !hover:bg-blue-600 !text-white !px-4 !py-2 !rounded-full !text-sm !transition !duration-300 !whitespace-nowrap">
                            Download
                        </a>
                    </div>
                    
                </div>
                <?php if ($free_or_pro === 'Pro') : ?>
                    <div class="pro">Pro</div>
                <?php endif; ?>
            </div>
            <?php
        endwhile;
    else :
        echo '<p class="!text-gray-600">No results found.</p>';
    endif;

    wp_reset_postdata();

    wp_die();
}


add_action('wp_ajax_filter_plugins', __NAMESPACE__ . '\\filter_plugins');
add_action('wp_ajax_nopriv_filter_plugins', __NAMESPACE__ . '\\filter_plugins');

//get submission data for edit submission form
add_action('wp_ajax_get_submission_data', function () {
    if (!is_user_logged_in() || empty($_GET['id'])) {
        wp_send_json_error('Unauthorized or invalid request.', 401);
    }

    $submission_id = absint($_GET['id']);
    $post = get_post($submission_id);

    if (!$post || $post->post_author != get_current_user_id()) {
        wp_send_json_error('Unauthorized access to this submission.', 403);
    }

    $data = [
        'name' => $post->post_title,
        'github_username' => get_post_meta($submission_id, 'github_username', true),
        'github_repo' => get_post_meta($submission_id, 'github_repo', true),
        'description' => $post->post_content,
        'categories' => implode(', ', wp_get_post_terms($submission_id, get_post_type($submission_id) === 'plugin_repo' ? 'plugin-category' : 'theme-category', ['fields' => 'names'])),
        'download_url' => get_post_meta($submission_id, 'download_url', true),
        'hosted_on_github' => get_post_meta($submission_id, 'hosted_on_github', true) ?: 'yes',
        'featured_image' => wp_get_attachment_url(get_post_thumbnail_id($submission_id)) ?: '',
    ];

    // Log the data being returned for debugging
    error_log('Submission Data: ' . print_r($data, true));

    wp_send_json_success($data);
});




// category select2 ajax
add_action('wp_ajax_get_categories', __NAMESPACE__ . '\\get_categories');
add_action('wp_ajax_nopriv_get_categories', __NAMESPACE__ . '\\get_categories');

function get_categories() {
    if (!isset($_GET['q'])) {
        wp_send_json_error('Missing query parameter.');
        return;
    }

    $search = sanitize_text_field($_GET['q']);
    $taxonomy = 'plugin-category'; // Replace with your desired taxonomy
    $categories = get_terms(array(
        'taxonomy'   => $taxonomy,
        'name__like' => $search,
        'hide_empty' => false,
    ));

    $results = array();
    foreach ($categories as $category) {
        $results[] = array(
            'id'   => $category->slug,
            'text' => $category->name,
        );
    }

    wp_send_json($results);
}

// Tags select2 AJAX
add_action('wp_ajax_get_tags', __NAMESPACE__ . '\\get_tags');
add_action('wp_ajax_nopriv_get_tags', __NAMESPACE__ . '\\get_tags');

function get_tags() {
    if (!isset($_GET['q'])) {
        wp_send_json_error('Missing query parameter.');
        return;
    }

    $search = sanitize_text_field($_GET['q']);
    $taxonomy = 'plugin-tags'; // Replace with your tags taxonomy
    $tags = get_terms(array(
        'taxonomy'   => $taxonomy,
        'name__like' => $search,
        'hide_empty' => false,
    ));

    $results = array();
    foreach ($tags as $tag) {
        $results[] = array(
            'id'   => $tag->slug,
            'text' => $tag->name,
        );
    }

    wp_send_json($results);
}
