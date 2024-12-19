<?php
/**
 * Plugin Name: The Repo
 * Description: A plugin to manage and display plugins and themes from GitHub repositories.
 * Version: 1.0.3
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
require_once plugin_dir_path(__FILE__) . 'includes/shortcodes/edit-submission-shortcode.php';
require_once plugin_dir_path(__FILE__) . 'functions.php';

// Enqueue scripts and styles.
add_action('wp_enqueue_scripts', function () {
    // Enqueue CSS
    wp_enqueue_style(
        'the-repo-main-css', // Handle for the stylesheet
        plugin_dir_url(__FILE__) . 'build/index.css', // Path to the CSS file
        [], // Dependencies
        '1.0.3'
    );

    // Enqueue JS
    wp_enqueue_script(
        'repo-categories',
        plugin_dir_url(__FILE__) . 'build/index.js', // Directly reference the correct directory
        array('jquery'), 
        '1.0.3', 
        true
    );

    // Localize the script
    wp_localize_script('repo-categories', 'RepoCategories', array(
        'ajax_url' => admin_url('admin-ajax.php'),
    ));
});


