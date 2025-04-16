<?php
/**
 * Plugin Name: The Repo
 * Description: A plugin to manage and display plugins and themes from GitHub repositories.
 * Version: 1.1.4
 * Author: James Welbes
 * Text Domain: the-repo-dot-org
 */

namespace TheRepo;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Define the plugin version as a constant.
define('THE_REPO_VERSION', '1.1.4');

// Include necessary files.
require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
require_once plugin_dir_path(__FILE__) . 'includes/post-types.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/browse-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/form-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/user-submissions-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'functions.php';


// Enqueue scripts and styles.
add_action('wp_enqueue_scripts', function () {
    global $post;

     // Check if the current post is of type 'plugin_repo' or 'theme_repo'
     if (is_singular(['plugin_repo', 'theme_repo'])) {

        // Enqueue the new JavaScript file for the download button
        wp_enqueue_script(
            'download-button',
            plugin_dir_url(__FILE__) . 'assets/js/download-button.js',
            array('jquery'),
            THE_REPO_VERSION,
            true
        );
    }

    // Check if the post contains the specific shortcode
    if (
        has_shortcode($post->post_content, 'plugin_repo_form') ||
        has_shortcode($post->post_content, 'plugin_repo_grid')
    ) {
        // Enqueue CSS
        wp_enqueue_style(
            'the-repo-main-css',
            plugin_dir_url(__FILE__) . 'build/index.css',
            [],
            THE_REPO_VERSION
        );

        // Enqueue JS
        wp_enqueue_script(
            'repo-categories',
            plugin_dir_url(__FILE__) . 'build/index.js',
            array('jquery'),
            THE_REPO_VERSION,
            true
        );

        // wp_enqueue_script(
        //     'download-button',
        //     plugin_dir_url(__FILE__) . 'assets/js/download-button.js',
        //     array('jquery'),
        //     THE_REPO_VERSION,
        //     true
        // );

        // Localize the script
        wp_localize_script('repo-categories', 'RepoCategories', array(
            'ajax_url' => admin_url('admin-ajax.php'),
        ));
    }
});



