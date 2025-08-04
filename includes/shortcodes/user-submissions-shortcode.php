<?php 

namespace TheRepo\Shortcode\Account;

function user_submissions_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to view your submissions. <a href="' . wp_login_url(get_permalink()) . '">Log in</a> or <a href="' . wp_registration_url() . '">Register</a>.</p>';
    }

    $current_user_id = get_current_user_id();

    ob_start();
    ?>
    <!-- <section class="plugin-list">
        <div class="plugin-list__container"> -->
            <div id="account-page-grid" class="plugin-list__grid">
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
                        <div class="plugin-list__card">
                            <div class="plugin-list__card-content">
                                <div class="plugin-list__card-header">
                                    <a href="<?php the_permalink(); ?>" class="plugin-list__card-image-link">
                                        <img src="<?php echo esc_url($featured_image); ?>" alt="<?php the_title_attribute(); ?>" width="60" height="60" class="plugin-list__card-image">
                                    </a>
                                    <div class="plugin-list__card-info">
                                        <div class="plugin-list__card-title-wrapper">
                                            <a href="<?php the_permalink(); ?>" class="plugin-list__card-title">
                                                <?php the_title(); ?>
                                            </a>
                                            <span class="plugin-list__card-badge">
                                                <?php echo esc_html($post_type); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="plugin-list__card-footer">
                                    
                                    <a href="<?php echo esc_url($edit_link); ?>" class="plugin-list__card-button">
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
        <!-- </div>
    </section> -->
    <?php
    return ob_get_clean();
}

add_shortcode('user_submissions', __NAMESPACE__ . '\\user_submissions_shortcode');
