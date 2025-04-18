<?php 

namespace TheRepo\Shortcode\Account;

function user_submissions_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your submissions. <a href="' . wp_login_url(get_permalink()) . '">Log in</a> or <a href="' . wp_registration_url() . '">Register</a>.</p>';
    }

    $current_user_id = get_current_user_id();

    ob_start();
    ?>
    <section class="!py-16">
        <div class="!container !mx-auto !px-4">
            <div id="account-page-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php
                $query = new \WP_Query([
                    'post_type' => ['plugin_repo', 'theme_repo'],
                    'posts_per_page' => -1,
                    'post_status' => ['publish', 'pending'], // Include pending posts
                    'author' => $current_user_id, // Filter by logged-in user
                ]);

                if ($query->have_posts()) :
                    while ($query->have_posts()) : $query->the_post();
                        $post_type = get_post_type() === 'plugin_repo' ? 'Plugin' : 'Theme';
                        $categories = get_the_terms(get_the_ID(), $post_type === 'Plugin' ? 'plugin-category' : 'theme-category');
                        $category_names = $categories ? wp_list_pluck($categories, 'name') : [];
                        $featured_image = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') ?: 'https://via.placeholder.com/60';
                        $edit_link = get_edit_post_link(get_the_ID());
                        ?>
                        <div class="!bg-white !rounded-lg !shadow-md !overflow-hidden">
                            <div class="!p-6">
                                <div class="!flex gap-4 !items-start space-x-4 !mb-4">
                                    <a href="<?php the_permalink(); ?>" class="!rounded-md !block">
                                        <img src="<?php echo esc_url($featured_image); ?>" alt="<?php the_title_attribute(); ?>" width="60" height="60" class="!rounded-md !object-cover">
                                    </a>
                                    <div class="!flex-grow">
                                        <div class="!flex !justify-between !items-start">
                                            <a href="<?php the_permalink(); ?>" class="!text-xl !font-semibold !text-blue-500 hover:!underline">
                                                <?php the_title(); ?>
                                            </a>
                                            <span class="!px-2 !py-1 !text-xs !font-semibold !rounded !whitespace-nowrap <?php echo $post_type === 'Plugin' ? '!bg-blue-100 !text-blue-800' : '!bg-green-100 !text-green-800'; ?>">
                                                <?php echo esc_html($post_type); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="!flex flex-wrap !justify-end !items-center github-download-button">
                                    
                                    <a href="<?php echo esc_url($edit_link); ?>" class="!bg-yellow-500 !hover:bg-yellow-600 !text-white !px-4 !py-2 !rounded-full !text-sm !transition !duration-300 !whitespace-nowrap">
                                        Edit
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php
                    endwhile;
                else :
                    echo '<p class="!text-gray-600 !text-center">You haven\'t submitted any plugins or themes yet.</p>';
                endif;

                wp_reset_postdata();
                ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

add_shortcode('user_submissions', __NAMESPACE__ . '\\user_submissions_shortcode');
