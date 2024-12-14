<?php

namespace TheRepo\Shortcode\Edit;

function repo_edit_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to edit your submissions. <a href="' . wp_login_url(get_permalink()) . '">Log in</a> or <a href="' . wp_registration_url() . '">Register</a>.</p>';
    }

    $current_user_id = get_current_user_id();
    $user_submissions = get_posts([
        'post_type' => ['plugin', 'theme_repo'],
        'author' => $current_user_id,
        'post_status' => ['publish', 'pending'],
        'posts_per_page' => -1,
    ]);

    if (empty($user_submissions)) {
        return '<p>You don\'t have any submissions to edit.</p>';
    }

    ob_start(); ?>
    <section class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="max-w-2xl mx-auto">
                <form method="POST" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data" class="space-y-6" id="repo_edit_form">
                    <input type="hidden" name="action" value="repo_edit_submission">
                    <?php wp_nonce_field('repo_edit_submission', 'repo_nonce'); ?>

                    <!-- Select Submission -->
                    <div>
                        <label for="submission_id" class="block text-sm font-medium text-gray-700">Select a Submission to Edit</label>
                        <select name="submission_id" id="submission_id" required class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                            <option value="">-- Select --</option>
                            <?php foreach ($user_submissions as $submission) : ?>
                                <option value="<?php echo esc_attr($submission->ID); ?>"><?php echo esc_html($submission->post_title); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Fields to Edit -->
                    <div id="edit-fields" style="display: none;">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                            <input type="text" name="name" id="name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        </div>

                        <div>
                            <label for="github_username" class="block text-sm font-medium text-gray-700">GitHub Username</label>
                            <input type="text" name="github_username" id="github_username" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        </div>

                        <div>
                            <label for="github_repo" class="block text-sm font-medium text-gray-700">GitHub Repo</label>
                            <input type="text" name="github_repo" id="github_repo" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500" required>
                        </div>

                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea name="description" id="description" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                        </div>

                        <div>
                            <label for="categories" class="block text-sm font-medium text-gray-700">Categories</label>
                            <input type="text" name="categories" id="categories" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                        </div>

                        
                        <div class="flex items-center">
                            <div><label for="featured_image" class="block text-sm font-medium text-gray-700">Featured Image</label>
                            <input type="file" name="featured_image" id="featured_image" class="mt-1 block w-full text-sm text-gray-500 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"></div>
                            <div id="featured-image-preview" class="mb-4"></div><br />
                        </div>
                        

                        <button type="submit" class="w-full inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Update Submission
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>
    <script>
        document.getElementById('submission_id').addEventListener('change', function () {
            const submissionId = this.value;

            if (!submissionId) {
                document.getElementById('edit-fields').style.display = 'none';
                return;
            }

            document.getElementById('edit-fields').style.display = 'block';

            fetch(`<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=get_submission_data&id=${submissionId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const submission = data.data;

                        document.getElementById('name').value = submission.name || '';
                        document.getElementById('github_username').value = submission.github_username || '';
                        document.getElementById('github_repo').value = submission.github_repo || '';
                        document.getElementById('description').value = submission.description || '';
                        document.getElementById('categories').value = submission.categories || '';

                        const imagePreviewContainer = document.getElementById('featured-image-preview');
                        if (submission.featured_image) {
                            imagePreviewContainer.innerHTML = `
                                <img src="${submission.featured_image}" alt="Featured Image" class="mb-4 w-24 h-24 object-cover rounded">
                                
                            `;
                        } else {
                            imagePreviewContainer.innerHTML = '<p>No featured image is currently set. Upload an image to add one.</p>';
                        }
                    } else {
                        console.error('Error:', data.data);
                        alert('Failed to load submission data. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error fetching submission data:', error);
                    alert('An error occurred. Please try again.');
                });
        });
    </script>
    <?php
    return ob_get_clean();
}

add_shortcode('repo_edit_form', __NAMESPACE__ . '\\repo_edit_form_shortcode');
