<?php
/**
 * Plugin Name: The Repo
 * Description: A plugin to manage and display plugins and themes from GitHub repositories.
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: the-repo
 */

namespace TheRepo;

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Include necessary files.
require_once plugin_dir_path(__FILE__) . 'includes/post-types.php';
require_once plugin_dir_path(__FILE__) . 'includes/ajax.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/browse-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/form-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/user-submissions-shortcode.php';

// Enqueue scripts and styles.
add_action('wp_enqueue_scripts', function () {
    // Enqueue CSS
    wp_enqueue_style(
        'the-repo-main-css', // Handle for the stylesheet
        plugin_dir_url(__FILE__) . 'assets/css/main.css', // Path to the CSS file
        [], // Dependencies (empty array if none)
        '1.0.0' // Version (update this as needed for cache-busting)
    );

    // Enqueue JS (if not already enqueued)
    wp_enqueue_script(
        'the-repo-main-js',
        plugin_dir_url(__FILE__) . 'assets/js/main.js',
        ['jquery'], // Dependencies (e.g., jQuery)
        '1.0.0',
        true // Load in the footer
    );
});

