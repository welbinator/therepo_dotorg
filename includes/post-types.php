<?php
namespace TheRepo\CustomPosts;

add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_675c854e4a4f8',
	'title' => 'Repo Submission',
	'fields' => array(
		array(
			'key' => 'field_675c859716b61',
			'label' => 'Latest Release URL',
			'name' => 'latest_release_url',
			'aria-label' => '',
			'type' => 'url',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'default_value' => '',
			'allow_in_bindings' => 0,
			'placeholder' => '',
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'plugin',
			),
		),
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'theme_repo',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );
} );

add_action( 'init', function() {
	register_taxonomy( 'plugin-category', array(
	0 => 'plugin',
), array(
	'labels' => array(
		'name' => 'Plugin Categories',
		'singular_name' => 'Plugin Category',
		'menu_name' => 'Plugin Categories',
		'all_items' => 'All Plugin Categories',
		'edit_item' => 'Edit Plugin Category',
		'view_item' => 'View Plugin Category',
		'update_item' => 'Update Plugin Category',
		'add_new_item' => 'Add New Plugin Category',
		'new_item_name' => 'New Plugin Category Name',
		'search_items' => 'Search Plugin Categories',
		'popular_items' => 'Popular Plugin Categories',
		'separate_items_with_commas' => 'Separate plugin categories with commas',
		'add_or_remove_items' => 'Add or remove plugin categories',
		'choose_from_most_used' => 'Choose from the most used plugin categories',
		'not_found' => 'No plugin categories found',
		'no_terms' => 'No plugin categories',
		'items_list_navigation' => 'Plugin Categories list navigation',
		'items_list' => 'Plugin Categories list',
		'back_to_items' => '← Go to plugin categories',
		'item_link' => 'Plugin Category Link',
		'item_link_description' => 'A link to a plugin category',
	),
	'public' => true,
	'show_in_menu' => true,
	'show_in_rest' => true,
) );

	register_taxonomy( 'theme-category', array(
	0 => 'theme_repo',
), array(
	'labels' => array(
		'name' => 'Theme Categories',
		'singular_name' => 'Theme Category',
		'menu_name' => 'Theme Categories',
		'all_items' => 'All Theme Categories',
		'edit_item' => 'Edit Theme Category',
		'view_item' => 'View Theme Category',
		'update_item' => 'Update Theme Category',
		'add_new_item' => 'Add New Theme Category',
		'new_item_name' => 'New Theme Category Name',
		'search_items' => 'Search Theme Categories',
		'popular_items' => 'Popular Theme Categories',
		'separate_items_with_commas' => 'Separate theme categories with commas',
		'add_or_remove_items' => 'Add or remove theme categories',
		'choose_from_most_used' => 'Choose from the most used theme categories',
		'not_found' => 'No theme categories found',
		'no_terms' => 'No theme categories',
		'items_list_navigation' => 'Theme Categories list navigation',
		'items_list' => 'Theme Categories list',
		'back_to_items' => '← Go to theme categories',
		'item_link' => 'Theme Category Link',
		'item_link_description' => 'A link to a theme category',
	),
	'public' => true,
	'show_in_menu' => true,
	'show_in_rest' => true,
) );
} );

add_action( 'init', function() {
	register_post_type( 'plugin', array(
	'labels' => array(
		'name' => 'Plugins',
		'singular_name' => 'Plugin',
		'menu_name' => 'Plugin',
		'all_items' => 'All Plugin',
		'edit_item' => 'Edit Plugin',
		'view_item' => 'View Plugin',
		'view_items' => 'View Plugin',
		'add_new_item' => 'Add New Plugin',
		'add_new' => 'Add New Plugin',
		'new_item' => 'New Plugin',
		'parent_item_colon' => 'Parent Plugin:',
		'search_items' => 'Search Plugin',
		'not_found' => 'No plugin found',
		'not_found_in_trash' => 'No plugin found in Trash',
		'archives' => 'Plugin Archives',
		'attributes' => 'Plugin Attributes',
		'insert_into_item' => 'Insert into plugin',
		'uploaded_to_this_item' => 'Uploaded to this plugin',
		'filter_items_list' => 'Filter plugin list',
		'filter_by_date' => 'Filter plugin by date',
		'items_list_navigation' => 'Plugin list navigation',
		'items_list' => 'Plugin list',
		'item_published' => 'Plugin published.',
		'item_published_privately' => 'Plugin published privately.',
		'item_reverted_to_draft' => 'Plugin reverted to draft.',
		'item_scheduled' => 'Plugin scheduled.',
		'item_updated' => 'Plugin updated.',
		'item_link' => 'Plugin Link',
		'item_link_description' => 'A link to a plugin.',
	),
	'public' => true,
	'show_in_rest' => true,
	'menu_icon' => 'dashicons-admin-post',
	'supports' => array(
		0 => 'title',
		1 => 'editor',
		2 => 'thumbnail',
		3 => 'custom-fields',
	),
	'taxonomies' => array(
		0 => 'plugin-category',
	),
	'delete_with_user' => false,
) );

	register_post_type( 'theme_repo', array(
	'labels' => array(
		'name' => 'Themes',
		'singular_name' => 'Theme',
		'menu_name' => 'Themes',
		'all_items' => 'All Themes',
		'edit_item' => 'Edit Theme',
		'view_item' => 'View Theme',
		'view_items' => 'View Themes',
		'add_new_item' => 'Add New Theme',
		'add_new' => 'Add New Theme',
		'new_item' => 'New Theme',
		'parent_item_colon' => 'Parent Theme:',
		'search_items' => 'Search Themes',
		'not_found' => 'No themes found',
		'not_found_in_trash' => 'No themes found in Trash',
		'archives' => 'Theme Archives',
		'attributes' => 'Theme Attributes',
		'insert_into_item' => 'Insert into theme',
		'uploaded_to_this_item' => 'Uploaded to this theme',
		'filter_items_list' => 'Filter themes list',
		'filter_by_date' => 'Filter themes by date',
		'items_list_navigation' => 'Themes list navigation',
		'items_list' => 'Themes list',
		'item_published' => 'Theme published.',
		'item_published_privately' => 'Theme published privately.',
		'item_reverted_to_draft' => 'Theme reverted to draft.',
		'item_scheduled' => 'Theme scheduled.',
		'item_updated' => 'Theme updated.',
		'item_link' => 'Theme Link',
		'item_link_description' => 'A link to a theme.',
	),
	'public' => true,
	'show_in_rest' => true,
	'menu_icon' => 'dashicons-admin-post',
	'supports' => array(
		0 => 'title',
		1 => 'editor',
		2 => 'thumbnail',
		3 => 'custom-fields',
	),
	'taxonomies' => array(
		0 => 'theme-category',
	),
	'delete_with_user' => false,
) );
} );

