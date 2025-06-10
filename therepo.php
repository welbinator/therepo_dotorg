<?php
/**
 * Plugin Name: The Repo
 * Description: A plugin to manage and display plugins and themes from GitHub repositories.
 * Version: 1.1.6
 * Author: James Welbes
 * Text Domain: the-repo-dot-org
 */

namespace TheRepo;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define the plugin version as a constant.
define('THE_REPO_VERSION', '1.1.6');

// Include necessary files.
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'includes/post-types.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/browse-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/form-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/user-submissions-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/previous-releases.php';
require_once plugin_dir_path(__FILE__) . 'functions.php';


// Enqueue scripts and styles that can be determined early
add_action('wp_enqueue_scripts', function () {
    global $post;

    $should_enqueue = false;

    // Check if post content has known shortcodes
    if (
        is_a($post, 'WP_Post') && (
            has_shortcode($post->post_content, 'plugin_repo_form') ||
            has_shortcode($post->post_content, 'plugin_repo_grid')
        )
    ) {
        $should_enqueue = true;
    }

    if ($should_enqueue) {
        wp_enqueue_style(
            'eventswp-frontend',
            plugin_dir_url(__FILE__)  . 'assets/css/style.css',
            [],
            THE_REPO_VERSION
        );

        wp_enqueue_style(
            'the-repo-main-css',
            plugin_dir_url(__FILE__) . 'build/index.css',
            [],
            THE_REPO_VERSION
        );

        wp_enqueue_script(
            'repo-categories',
            plugin_dir_url(__FILE__) . 'build/index.js',
            ['jquery'],
            THE_REPO_VERSION,
            true
        );

        wp_localize_script('repo-categories', 'RepoCategories', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }

    // Always enqueue this for single plugin/theme pages
    if (is_singular(['plugin_repo', 'theme_repo'])) {
        wp_enqueue_script(
            'download-button',
            plugin_dir_url(__FILE__) . 'assets/js/download-button.js',
            ['jquery'],
            THE_REPO_VERSION,
            true
        );
    }
});


// Enqueue assets triggered by shortcodes that may run after wp_enqueue_scripts (e.g., via page builders)
add_action('wp_footer', function () {
    if (!empty($GLOBALS['the_repo_should_enqueue_assets'])) {
        wp_enqueue_style(
            'eventswp-frontend',
            plugin_dir_url(__FILE__)  . 'assets/css/style.css',
            [],
            THE_REPO_VERSION
        );

        wp_enqueue_style(
            'the-repo-main-css',
            plugin_dir_url(__FILE__) . 'build/index.css',
            [],
            THE_REPO_VERSION
        );

        wp_enqueue_script(
            'repo-categories',
            plugin_dir_url(__FILE__) . 'build/index.js',
            ['jquery'],
            THE_REPO_VERSION,
            true
        );

        wp_localize_script('repo-categories', 'RepoCategories', [
            'ajax_url' => admin_url('admin-ajax.php'),
        ]);
    }
});
