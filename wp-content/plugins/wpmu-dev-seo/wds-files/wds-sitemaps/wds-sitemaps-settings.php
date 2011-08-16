<?php

/* Add settings page */
function wds_sitemaps_settings() {
	//$name = 'wds_sitemaps'; // Removed plural
	$name = 'wds_sitemap'; // Added singular
	$title = __( 'Sitemaps' , 'wds');
	$description = __( '<p>Here we will help you create a site map which are used to help search engines find all of the information on your site.</p>
	<p>This is one of the basics of SEO. A sitemap helps search engines like Google, Bing, Yahoo and Ask.com to better index your blog. Search engines are better able to crawl through your site with a structured sitemap of where your content leads. This plugin supports all kinds of WordPress-generated pages as well as custom URLs. Whenever you create a new post, it will notify major search engines to come crawl your new content.</p>
	<p>You may also choose to not include posts, pages, custom post types, categories, or tags from your sitemap - but most sitiuations you will want to leave these in.</p>
	<p>(Leaving these off of a sitemap won\'t guarantee that a search engine won\'t find the information by other means!)</p>', 'wds' );

	$sitemap_options = get_option( 'wds_sitemap_options' );

	$fields = array();
	$fields['sitemap'] = array(
		'title' => __( 'XML Sitemap' , 'wds'),
		'intro' => '',
		'options' => array(
			array(
				'type' => 'text',
				'class' => 'widefat',
				'name' => 'sitemappath',
				'title' => __( 'Path to the XML Sitemap' , 'wds'),
				'description' => '',
				'text' => '<p><code>' . $sitemap_options['sitemappath'] . '</code></p>'
			),
			array(
				'type' => 'content',
				'name' => 'sitemapurl',
				'title' => __( 'URL to the XML Sitemap' , 'wds'),
				'description' => '',
				'text' => '<p><a href="' . $sitemap_options['sitemapurl'] . '" target="_blank">' . $sitemap_options['sitemapurl'] . '</a></p>' // Removed plain content type
			)
		)
	);

	foreach (get_post_types() as $post_type) {
		if ( !in_array( $post_type, array('revision', 'nav_menu_item', 'attachment') ) ) {
			$pt = get_post_type_object($post_type);
			$post_types['post_types-' . $post_type . '-not_in_sitemap'] = $pt->labels->name;
		}
	}
	foreach (get_taxonomies() as $taxonomy) {
		if ( !in_array( $taxonomy, array( 'nav_menu', 'link_category', 'post_format' ) ) ) {
			$tax = get_taxonomy($taxonomy);
			$taxonomies['taxonomies-' . $taxonomy . '-not_in_sitemap'] = $tax->labels->name;
		}
	}
	$fields['exclude'] = array(
		'title' => __( 'Exclude' , 'wds'),
		'intro' => '',
		'options' => array(
			array(
				'type' => 'checkbox',
				'name' => 'exclude_post_types',
				'title' => __( 'Exclude post types' , 'wds'),
				'items' => $post_types
			),
			array(
				'type' => 'checkbox',
				'name' => 'exclude_taxonomies',
				'title' => __( 'Exclude taxonomies' , 'wds'),
				'items' => $taxonomies
			)
		)
	);

	$contextual_help = '';

	if ( wds_is_wizard_step( '2' ) )
		$settings = new WDS_Core_Admin_Tab( $name, $title, $description, $fields, 'wds', $contextual_help );

	require_once ( WDS_PLUGIN_DIR . 'wds-sitemaps/wds-sitemaps.php' );
}
add_action( 'init', 'wds_sitemaps_settings' );

/* Default settings */
function wds_sitemaps_defaults() {
	$sitemap_options = get_option( 'wds_sitemap_options' );

	$dir = wp_upload_dir();
	$path = trailingslashit( $dir['basedir'] );

	if ( empty($sitemap_options['sitemappath']) )
		$sitemap_options['sitemappath'] = $path . 'sitemap.xml';

	if ( empty($sitemap_options['sitemapurl']) )
		$sitemap_options['sitemapurl'] = get_bloginfo( 'url' ) . '/sitemap.xml';

	if ( empty($sitemap_options['newssitemappath']) )
		$sitemap_options['newssitemappath'] = $path . 'news_sitemap.xml';

	if ( empty($sitemap_options['newssitemapurl']) )
		$sitemap_options['newssitemapurl'] = get_bloginfo( 'url' ) . '/news_sitemap.xml';

	if ( empty($sitemap_options['enablexmlsitemap']) )
		$sitemap_options['enablexmlsitemap'] = 1;

	update_option( 'wds_sitemap_options', $sitemap_options );
	/*
	if( is_multisite() && WDS_SITEWIDE == true ) {
		update_site_option( 'wds_sitemap_options', $sitemap_options );
	} else {
		update_option( 'wds_sitemap_options', $sitemap_options );
	}
	*/
}
add_action( 'init', 'wds_sitemaps_defaults' );
