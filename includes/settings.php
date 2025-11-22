<?php

namespace TheRepo\Settings;

// Register settings page
add_action('admin_menu', __NAMESPACE__ . '\\register_settings_page');

function register_settings_page() {
    add_options_page(
        'The Repo Settings',           // Page title
        'The Repo',                     // Menu title
        'manage_options',               // Capability
        'the-repo-settings',           // Menu slug
        __NAMESPACE__ . '\\render_settings_page'  // Callback
    );
}

// Render settings page
function render_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'therepo'));
    }

    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="card">
            <h2>GitHub Cache Management</h2>
            <p>Clear the GitHub API cache to force the plugin to fetch the latest data from GitHub. This is useful when you've published a new release and want it to appear immediately.</p>
            
            <div id="cache-clear-message" style="display: none; padding: 10px; margin: 10px 0; border-left: 4px solid #00a32a; background: #f0f6fc;"></div>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Clear All GitHub Cache</th>
                    <td>
                        <button type="button" id="clear-all-cache" class="button button-primary">
                            Clear All Cache
                        </button>
                        <p class="description">This will clear all cached GitHub data (releases, stars, downloads) for all plugins and themes.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">Clear Release Cache Only</th>
                    <td>
                        <button type="button" id="clear-release-cache" class="button">
                            Clear Release Cache
                        </button>
                        <p class="description">This will only clear cached release/version data, forcing fresh downloads.</p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="card" style="margin-top: 20px;">
            <h2>Plugin Information</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">Plugin Version</th>
                    <td><?php echo esc_html(THE_REPO_VERSION); ?></td>
                </tr>
                <tr>
                    <th scope="row">GitHub API Token</th>
                    <td>
                        <?php if (defined('GITHUB_API_TOKEN') && GITHUB_API_TOKEN): ?>
                            <span style="color: #00a32a;">✓ Configured</span>
                        <?php else: ?>
                            <span style="color: #d63638;">✗ Not configured (API rate limits apply)</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        function showMessage(message, isError = false) {
            const messageDiv = $('#cache-clear-message');
            messageDiv.css({
                'border-left-color': isError ? '#d63638' : '#00a32a',
                'background': isError ? '#fcf0f1' : '#f0f6fc'
            });
            messageDiv.text(message).fadeIn();
            
            setTimeout(function() {
                messageDiv.fadeOut();
            }, 3000);
        }

        $('#clear-all-cache').on('click', function() {
            const button = $(this);
            button.prop('disabled', true).text('Clearing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'clear_github_cache',
                    cache_type: 'all',
                    nonce: '<?php echo wp_create_nonce('clear_github_cache'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('✓ All GitHub cache cleared successfully!');
                    } else {
                        showMessage('Error: ' + (response.data?.message || 'Unknown error'), true);
                    }
                },
                error: function() {
                    showMessage('Error: Failed to clear cache', true);
                },
                complete: function() {
                    button.prop('disabled', false).text('Clear All Cache');
                }
            });
        });

        $('#clear-release-cache').on('click', function() {
            const button = $(this);
            button.prop('disabled', true).text('Clearing...');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'clear_github_cache',
                    cache_type: 'releases',
                    nonce: '<?php echo wp_create_nonce('clear_github_cache'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showMessage('✓ Release cache cleared successfully!');
                    } else {
                        showMessage('Error: ' + (response.data?.message || 'Unknown error'), true);
                    }
                },
                error: function() {
                    showMessage('Error: Failed to clear cache', true);
                },
                complete: function() {
                    button.prop('disabled', false).text('Clear Release Cache');
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handler for clearing cache
add_action('wp_ajax_clear_github_cache', __NAMESPACE__ . '\\ajax_clear_github_cache');

function ajax_clear_github_cache() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'clear_github_cache')) {
        wp_send_json_error(['message' => 'Invalid security token']);
    }

    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions']);
    }

    $cache_type = isset($_POST['cache_type']) ? sanitize_text_field($_POST['cache_type']) : 'all';
    
    global $wpdb;
    $deleted = 0;

    if ($cache_type === 'releases') {
        // Clear only release-related cache
        $patterns = [
            '_transient_latest_version_%',
            '_transient_timeout_latest_version_%',
            '_transient_latest_release_date_%',
            '_transient_timeout_latest_release_date_%',
            '_transient_all_releases_downloads_%',
            '_transient_timeout_all_releases_downloads_%'
        ];
        
        foreach ($patterns as $pattern) {
            $deleted += $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                )
            );
        }
        
        wp_send_json_success([
            'message' => "Cleared {$deleted} release cache entries",
            'count' => $deleted
        ]);
    } else {
        // Clear all GitHub-related cache
        $patterns = [
            '_transient_latest_version_%',
            '_transient_timeout_latest_version_%',
            '_transient_latest_release_date_%',
            '_transient_timeout_latest_release_date_%',
            '_transient_github_repo_meta_%',
            '_transient_timeout_github_repo_meta_%',
            '_transient_all_releases_downloads_%',
            '_transient_timeout_all_releases_downloads_%',
            '_transient_github_downloads_%',
            '_transient_timeout_github_downloads_%'
        ];
        
        foreach ($patterns as $pattern) {
            $deleted += $wpdb->query(
                $wpdb->prepare(
                    "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                    $pattern
                )
            );
        }
        
        wp_send_json_success([
            'message' => "Cleared {$deleted} GitHub cache entries",
            'count' => $deleted
        ]);
    }
}
