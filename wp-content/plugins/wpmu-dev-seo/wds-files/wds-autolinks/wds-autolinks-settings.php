<?php

/* Add settings page */
function wds_autolinks_settings() {
	$name = 'wds_autolinks';
	$title = __( 'Automatic Links' , 'wds');
	$description = __( '<p>Sometimes you want to always link certain key words to a page on your blog or even a whole new site all together.</p>
	<p>For example, maybe anytime you write the words \'WordPress news\' you want to automatically create a link to the WordPress news blog, wpmu.org. Without this plugin, you would have to manually create these links each time you write the text in your pages and posts - which can be no fun at all.</p>
	<p>This section lets you set those key words and links. First, choose if you want to automatically link key words in posts, pages, or any custom post types you might be using. Usually when you are using automatic links, you will want to check all of the options here.</p>', 'wds' );

	$fields = array();

	foreach ( get_post_types() as $post_type ) {
		if ( !in_array( $post_type, array('revision', 'nav_menu_item', 'attachment') ) ) {
			$pt = get_post_type_object($post_type);
			$key = strtolower( $pt->labels->name );
			$post_types["l$key"] = $pt->labels->name;
			$insert[$post_type] = $pt->labels->name;
		}
	}
	foreach ( get_taxonomies() as $taxonomy ) {
		if ( !in_array( $taxonomy, array( 'nav_menu', 'link_category', 'post_format' ) ) ) {
			$tax = get_taxonomy($taxonomy);
			$key = strtolower( $tax->labels->name );
			$taxonomies["l$key"] = $tax->labels->name;
		}
	}
	$linkto = array_merge( $post_types, $taxonomies );
	$insert['comment'] = __( 'Comments' , 'wds');
	$fields['internal'] = array(
		'title' => '',
		'intro' => '',
		'options' => array(
			array(
				'type' => 'checkbox',
				'name' => 'insert',
				'title' => __( 'Insert links in' , 'wds'),
				'items' => $insert
			),
			array(
				'type' => 'checkbox',
				'name' => 'linkto',
				'title' => __( 'Link to' , 'wds'),
				'items' => $linkto
			),
			array(
				'type' => 'checkbox',
				'name' => 'excludeheading',
				'title' => __( 'Exclude Headings' , 'wds'),
				'items' => array( 'excludeheading' => __( 'Prevent linking in heading tags' , 'wds') )
			),
			array(
				'type' => 'text',
				'name' => 'ignorepost',
				'title' => __( 'Ignore posts and pages' , 'wds'),
				'description' => 'Paste in the IDs, slugs or titles for the post/pages you wish to exclude and separate them by commas'
			),
			array(
				'type' => 'text',
				'name' => 'ignore',
				'title' => __( 'Ignore keywords' , 'wds'),
				'description' => 'Paste in the keywords you wish to exclude and separate them by commas'
			),
			array(
				'type' => 'textarea',
				'name' => 'customkey',
				'title' => __( 'Custom Keywords' , 'wds'),
				'description' => 'Paste in the extra keywords you want to automaticaly link. Use comma to seperate keywords and add target url at the end. Use a new line for new url and set of keywords.
				<br />Example:<br />
				<code>WPMU DEV, plugins, themes, http://premium.wpmudev.org/<br />
				WordPress News, http://wpmu.org/<br /></code>'
			),
			array(
				'type' => 'checkbox',
				'name' => 'reduceload',
				'title' => __( 'Other settings' , 'wds'),
				'items' => array(
					'onlysingle' => __( 'Process only single posts and pages' , 'wds'),
					'allowfeed' => __( 'Process RSS feeds' , 'wds'),
					'casesens' => __( 'Case sensitive matching' , 'wds')
				)
			)
		)
	);

	$contextual_help = '';

	if ( wds_is_wizard_step( '5' ) )
		$settings = new WDS_Core_Admin_Tab( $name, $title, $description, $fields, 'wds', $contextual_help );
}
add_action( 'init', 'wds_autolinks_settings' );

/* Default settings */
function wds_autolinks_defaults() {
	if( is_multisite() && WDS_SITEWIDE == true ) {
		$options = get_site_option( 'wds_autolinks_options' );
	} else {
		$options = get_option( 'wds_autolinks_options' );
	}

	if( is_multisite() && WDS_SITEWIDE == true ) {
		update_site_option( 'wds_autolinks_options', $options );
	} else {
		update_option( 'wds_autolinks_options', $options );
	}
}
add_action( 'init', 'wds_autolinks_defaults' );
