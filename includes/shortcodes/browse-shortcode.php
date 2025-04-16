<?php 

namespace TheRepo\Shortcode\Browse;

use function TheRepo\Functions\fetch_github_data;

function plugin_repo_grid_shortcode() {
    ob_start();
    ?>
    <section class="!py-16">
        <div class="!container !mx-auto !px-4">
            
            <div class="!mb-8 !flex flex-col md:flex-row !justify-between !items-center gap-4">
                <div class="!relative !w-full md:w-1/3">
                    <input
                        type="text"
                        id="plugin-repo-search"
                        placeholder="Search plugins and themes..."
                        class="!w-full !px-4 !py-2 !text-gray-700 !bg-white !rounded-full !focus:outline-none !focus:ring-2 !focus:ring-blue-500"
                    />
                </div>
                <div class="!flex !items-center !space-x-4">
                    <select id="plugin-repo-type-filter" class="">
                        <option value="">All Types</option>
                        <option value="plugin_repo">Plugins</option>
                        <option value="theme_repo">Themes</option>
                    </select>
                    <select id="plugin-repo-category-filter" class="">
                        <option value="">All Categories</option>
                        <?php
                        $categories = get_terms(array('taxonomy' => array('plugin-category', 'theme-category')));
                        foreach ($categories as $category) {
                            echo '<option value="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</option>';
                        }
                        ?>
                    </select>
                </div>
            </div>
            <div id="plugin-repo-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php
                $query = new \WP_Query(array(
                    'post_type' => array('plugin_repo', 'theme_repo'),
                    'posts_per_page' => -1,
                ));

                if ($query->have_posts()) :
                    while ($query->have_posts()) : $query->the_post();
                        $post_type = get_post_type() === 'plugin_repo' ? 'Plugin' : 'Theme';
                        $categories = get_the_terms(get_the_ID(), $post_type === 'Plugin' ? 'plugin-category' : 'theme-category');
                        $category_names = $categories ? wp_list_pluck($categories, 'name') : [];
                        $featured_image = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail') ?: 'https://via.placeholder.com/60';
                        $latest_release_url = get_post_meta(get_the_ID(), 'latest_release_url', true);
                        $version_badge = '';
                        $version_text = '';
                        $free_or_pro = get_post_meta(get_the_ID(), 'free_or_pro', true);
                        

                        if (!empty($latest_release_url) && filter_var($latest_release_url, FILTER_VALIDATE_URL)) {
                            $cache_key = 'latest_version_' . md5($latest_release_url);
                            $data = fetch_github_data($latest_release_url, $cache_key);
                        
                            if (isset($data['tag_name'])) {
                                $version_text = esc_html($data['tag_name']);
                            }
                        
                            if (isset($data['published_at'])) {
                                $timezone_string = get_option('timezone_string') ?: 'UTC';
                                $local_tz = new \DateTimeZone($timezone_string);

                                $published_date = new \DateTime($data['published_at']);
                                $published_date->setTimezone($local_tz);

                                $now = new \DateTime('now', $local_tz);
                                $today = $now->format('Y-m-d');
                                $yesterday = $now->modify('-1 day')->format('Y-m-d');
                                $published_day = $published_date->format('Y-m-d');

                                error_log('Published date (local): ' . $published_day);
                                error_log('Today: ' . $today);
                                error_log('Yesterday: ' . $yesterday);

                                if ($published_day >= date('Y-m-d', strtotime('-7 days'))) {
                                    if ($published_day === $today) {
                                        $tooltip = "Updated today!";
                                    } elseif ($published_day === $yesterday) {
                                        $tooltip = "Updated yesterday!";
                                    } else {
                                        $days_ago = (new \DateTime($today))->diff(new \DateTime($published_day))->days;
                                        $tooltip = "Updated {$days_ago} days ago!";
                                    }

                                    $version_badge = ' <span title="' . esc_attr($tooltip) . '" style="cursor: help;"><sup>✨</sup></span>';
                                }

                            }
                        }
                        
                        ?>
                        <div class="!bg-white !rounded-lg !shadow-md !overflow-hidden relative">
                            <div class="!p-6 !pb-12">
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
                                        <p class="!text-gray-500 !mt-2">
                                            Author: 
                                            <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'), get_the_author_meta('user_login'))); ?>" class="!text-blue-500 hover:!underline">
                                                <?php echo esc_html(get_the_author_meta('nickname')); ?>
                                            </a>
                                        </p>
                                    </div>
                                </div>

                                <p class="!text-gray-600 !mb-4"><?php echo esc_html(wp_trim_words(get_the_content(), 20)); ?></p>

                                <div class="!flex flex-wrap !justify-between !items-center github-download-button">
                                    <div class="!flex !space-x-2 !gap-3 !mb-5 flex-col">
                                        <div>
                                            <?php if (!empty($category_names)) : ?>
                                                <?php foreach ($category_names as $category_name) : ?>
                                                    <span class="!px-2 !py-1 !bg-gray-100 !text-gray-600 !text-xs !rounded-full !whitespace-nowrap">
                                                        <?php echo esc_html($category_name); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <a 
                                        href="#" 
                                        target="_blank" 
                                        id="<?php echo esc_attr($latest_release_url); ?>" 
                                        class="!bg-blue-500 !hover:bg-blue-600 !text-white !px-4 !py-2 !rounded-full !text-sm !transition !duration-300 !whitespace-nowrap no-underline">
                                        Download<?php echo $version_text ? " Version {$version_text}" : ''; ?><?php echo $version_badge; ?>
                                    </a>
                                </div>
                            </div>
                            <?php if ($free_or_pro === 'Pro') : ?>
                                <div class="pro">Pro</div>
                            <?php endif; ?>
                        </div>
                        <?php
                    endwhile;
                endif;
                wp_reset_postdata();
                ?>
            </div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('plugin_repo_grid', __NAMESPACE__ . '\\plugin_repo_grid_shortcode');
