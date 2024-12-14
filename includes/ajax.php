<?php
namespace TheRepo\Ajax;

function filter_plugins() {
    $search = sanitize_text_field($_GET['search'] ?? '');
    $type = sanitize_text_field($_GET['type'] ?? '');
    $category = sanitize_text_field($_GET['category'] ?? '');

    $args = array(
        'post_type' => array('plugin', 'theme_repo'), // Default: both plugins and themes
        'posts_per_page' => -1,
        's' => $search,
    );

    // If a specific type is selected, filter by post type
    if ($type) {
        $args['post_type'] = $type;
    }

    // Handle category filter
    if ($category) {
        $taxonomies = array('plugin-category', 'theme-category');
        if ($type === 'plugin') {
            $taxonomies = array('plugin-category');
        } elseif ($type === 'theme_repo') {
            $taxonomies = array('theme-category');
        }

        $args['tax_query'] = array(
            'relation' => 'OR', // Allow matching across multiple taxonomies
        );

        foreach ($taxonomies as $taxonomy) {
            $args['tax_query'][] = array(
                'taxonomy' => $taxonomy,
                'field'    => 'slug',
                'terms'    => $category,
            );
        }
    }

    $query = new \WP_Query($args);
    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();
            $post_type = get_post_type() === 'plugin' ? 'Plugin' : 'Theme';
            $categories = get_the_terms(get_the_ID(), $post_type === 'Plugin' ? 'plugin-category' : 'theme-category');
            $category_names = $categories ? wp_list_pluck($categories, 'name') : [];
            $featured_image = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') ?: 'https://via.placeholder.com/60'; // Fallback thumbnail image
            $latest_release_url = get_post_meta(get_the_ID(), 'latest_release_url', true);
            $free_or_pro = get_post_meta(get_the_ID(), 'free_or_pro', true); // Get the value of the custom field
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
                        </div>
                        <a href="#" id="<?php echo esc_attr($latest_release_url); ?>" class="!bg-blue-500 !hover:bg-blue-600 !text-white !px-4 !py-2 !rounded-full !text-sm !transition !duration-300 !whitespace-nowrap">
                            Download Latest
                        </a>
                    </div>
                </div>
                <?php if ($free_or_pro === 'pro') : // Only display if the post is "pro" ?>
                    <div class="pro">Pro</div>
                <?php endif; ?>
            </div>
            <?php
        endwhile;
    else :
        echo '<p class="!text-gray-600 !text-center">No results found.</p>';
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

    // Ensure the post exists and belongs to the logged-in user
    if (!$post || $post->post_author != get_current_user_id()) {
        wp_send_json_error('Unauthorized access to this submission.', 403);
    }

    // Get submission details
    $featured_image_id = get_post_thumbnail_id($submission_id); // Get the featured image ID
    $featured_image_url = $featured_image_id ? wp_get_attachment_url($featured_image_id) : ''; // Get the image URL

    $data = [
        'name' => $post->post_title,
        'github_username' => get_post_meta($submission_id, 'github_username', true),
        'github_repo' => get_post_meta($submission_id, 'github_repo', true),
        'description' => $post->post_content,
        'categories' => implode(', ', wp_get_post_terms($submission_id, get_post_type($submission_id) === 'plugin' ? 'plugin-category' : 'theme-category', ['fields' => 'names'])),
        'featured_image' => $featured_image_url, // Add the image URL to the data
    ];


    wp_send_json_success($data);
});

