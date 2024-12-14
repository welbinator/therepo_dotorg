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

// Enqueue scripts and styles.
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_script(
        'the-repo-main',
        plugin_dir_url(__FILE__) . 'assets/js/main.js',
        ['jquery'],
        '1.0.0',
        true
    );
    wp_localize_script('the-repo-main', 'theRepoAjax', [
        'ajax_url' => admin_url('admin-ajax.php'),
    ]);
});
