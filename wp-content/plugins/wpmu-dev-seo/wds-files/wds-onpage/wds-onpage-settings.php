<?php

/* Add settings page */
function wds_seoopt_settings() {
	$name = 'wds_onpage';
	$title =  __( 'Title & Meta' , 'wds' );
	$description = sprintf( __( '<p>Modify the fields below to customize the titles and meta descriptions of your site pages. <a href="#contextual-help" class="toggle-contextual-help">Click here to see the list of the supported macros.</a></p>
	<p>Search engines read the title and description for each element of your site.  The fields below are set by macros to fill in default information.  You can customize them as you wish and refer to the supported macros, by clicking the Help button.</p>
	<p>It seems to be generally agreed that the "title" and the "description" meta tags are important to write effectively, since several major search engines use them in their indices.   Use relevant keywords in your title, and vary the titles on the different pages that make up your website, in order to target as many keywords as possible.  As for the "description" meta tag, some search engines will use it as their short summary of your url, so make sure your description is one that will entice surfers to your site.</p>
	<p>The "description" meta tag is generally held to be the most valuable, and the most likely to be indexed, so pay special attention to this one.</p>
	<p>Here\'s an example of how it\'s been customized on WPMU DEV.</p>
	<p>The site description (tagline) is:
	<blockquote>WPMU DEV WordPress, Multisite & BuddyPress</blockquote>
	but it has been customized so that the Home Meta Description is WPMU DEV Premium is:
	<blockquote>devoted to plugins, themes, resources and support to assist you in creating the absolute best WordPress MU (WPMU) site you can.</blockquote>
	<p><img src="%s" alt="title and description sample" /></p>
	<p>This plugin also adds a WPMU DEV SEO module below the Write Post / Page editor which you can use to customise SEO options for individual posts and pages.</p>' , 'wds' ), WDS_PLUGIN_URL . 'images/onpagesample.png' );

	$fields = array();
	if ( 'posts' == get_option('show_on_front') ) {
		$fields['home'] = array(
			'title' =>  __( 'Home' , 'wds' ),
			'intro' =>  '',
			'options' => array(
				array(
					'type' => 'text',
					'name' => 'title-home',
					'title' =>  __( 'Home Title' , 'wds' ),
					'description' => ''
				),
				array(
					'type' => 'textarea',
					'name' => 'metadesc-home',
					'title' =>  __( 'Home Meta Description' , 'wds' ),
					'description' => ''
				)
			)
		);
	}
	foreach (get_post_types() as $posttype) {
		if ( in_array($posttype, array('revision','nav_menu_item') ) )
			continue;
		if (isset($wds_options['redirectattachment']) && $wds_options['redirectattachment'] && $posttype == 'attachment')
			continue;
		$fields[$posttype] = array(
			'title' => ucfirst( $posttype ),
			'intro' => '',
			'options' => array(
				array(
					'type' => 'text',
					'name' => 'title-' . $posttype,
					'title' => sprintf( __( '%s Title' , 'wds'), ucfirst( $posttype ) ),
					'description' => ''
				),
				array(
					'type' => 'textarea',
					'name' => 'metadesc-' . $posttype,
					'title' => sprintf( __( '%s Meta Description' , 'wds'), ucfirst( $posttype ) ),
					'description' => ''
				)
			)
		);
	}
	foreach (get_taxonomies() as $taxonomy) {
		if ( in_array($taxonomy, array( 'link_category','nav_menu', 'post_format' ) ) )
			continue;
		$fields[$taxonomy] = array(
			'title' => ucfirst( $taxonomy ),
			'intro' => '',
			'options' => array(
				array(
					'type' => 'text',
					'name' => 'title-' . $taxonomy,
					'title' => sprintf( __( '%s Title' , 'wds'), ucfirst( $taxonomy ) ),
					'description' => ''
				),
				array(
					'type' => 'textarea',
					'name' => 'metadesc-' . $taxonomy,
					'title' => sprintf( __( '%s Meta Description' , 'wds'), ucfirst( $taxonomy ) ),
					'description' => ''
				)
			)
		);
	}
	$fields['author'] = array(
		'title' => __( 'Author Archive' , 'wds'),
		'intro' => '',
		'options' => array(
			array(
				'type' => 'text',
				'name' => 'title-author',
				'title' => __( 'Author Archive Title' , 'wds'),
				'description' => ''
			),
			array(
				'type' => 'textarea',
				'name' => 'metadesc-author',
				'title' => __( 'Author Archive Meta Description' , 'wds'),
				'description' => ''
			)
		)
	);
	$fields['date'] = array(
		'title' => __( 'Date Archives' , 'wds'),
		'intro' => '',
		'options' => array(
			array(
				'type' => 'text',
				'name' => 'title-date',
				'title' => __( 'Date Archives Title' , 'wds'),
				'description' => ''
			),
			array(
				'type' => 'textarea',
				'name' => 'metadesc-date',
				'title' => __( 'Date Archives Description' , 'wds'),
				'description' => ''
			)
		)
	);
	$fields['search'] = array(
		'title' => __( 'Search Page' , 'wds'),
		'intro' => '',
		'options' => array(
			array(
				'type' => 'text',
				'name' => 'title-search',
				'title' => __( 'Search Page Title' , 'wds'),
				'description' => ''
			),
			array(
				'type' => 'textarea',
				'name' => 'metadesc-search',
				'title' => __( 'Search Page Description' , 'wds'),
				'description' => ''
			)
		)
	);
	$fields['404'] = array(
		'title' => __( '404 Page' , 'wds'),
		'intro' => '',
		'options' => array(
			array(
				'type' => 'text',
				'name' => 'title-404',
				'title' => __( '404 Page Title' , 'wds'),
				'description' => ''
			),
			array(
				'type' => 'textarea',
				'name' => 'metadesc-404',
				'title' => __( '404 Page Description' , 'wds'),
				'description' => ''
			)
		)
	);

	$contextual_help = __( '
<p>The following macros are supported:</p>
<table class="widefat">
	<thead>
		<tr>
			<th>Tag</th>
			<th>Description</th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th>Tag</th>
			<th>Description</th>
		</tr>
	</tfoot>
	<tbody>
		<tr>
			<th>%%date%%</th>
			<td>Replaced with the date of the post/page</td>
		</tr>
		<tr class="alt">
			<th>%%title%%</th>
			<td>Replaced with the title of the post/page</td>
		</tr>
		<tr>
			<th>%%sitename%%</th>
			<td>The site\'s name</td>
		</tr>
		<tr class="alt">
			<th>%%sitedesc%%</th>
			<td>The site\'s tagline / description</td>
		</tr>
		<tr>
			<th>%%excerpt%%</th>
			<td>Replaced with the post/page excerpt (or auto-generated if it does not exist)</td>
		</tr>
		<tr class="alt">
			<th>%%excerpt_only%%</th>
			<td>Replaced with the post/page excerpt (without auto-generation)</td>
		</tr>
		<tr>
			<th>%%tag%%</th>
			<td>Replaced with the current tag/tags</td>
		</tr>
		<tr class="alt">
			<th>%%category%%</th>
			<td>Replaced with the post categories (comma separated)</td>
		</tr>
		<tr>
			<th>%%category_description%%</th>
			<td>Replaced with the category description</td>
		</tr>
		<tr class="alt">
			<th>%%tag_description%%</th>
			<td>Replaced with the tag description</td>
		</tr>
		<tr>
			<th>%%term_description%%</th>
			<td>Replaced with the term description</td>
		</tr>
		<tr class="alt">
			<th>%%term_title%%</th>
			<td>Replaced with the term name</td>
		</tr>
		<tr>
			<th>%%modified%%</th>
			<td>Replaced with the post/page modified time</td>
		</tr>
		<tr class="alt">
			<th>%%id%%</th>
			<td>Replaced with the post/page ID</td>
		</tr>
		<tr>
			<th>%%name%%</th>
			<td>Replaced with the post/page author\'s \'nicename\'</td>
		</tr>
		<tr class="alt">
			<th>%%userid%%</th>
			<td>Replaced with the post/page author\'s userid</td>
		</tr>
		<tr>
			<th>%%searchphrase%%</th>
			<td>Replaced with the current search phrase</td>
		</tr>
		<tr class="alt">
			<th>%%currenttime%%</th>
			<td>Replaced with the current time</td>
		</tr>
		<tr>
			<th>%%currentdate%%</th>
			<td>Replaced with the current date</td>
		</tr>
		<tr class="alt">
			<th>%%currentmonth%%</th>
			<td>Replaced with the current month</td>
		</tr>
		<tr>
			<th>%%currentyear%%</th>
			<td>Replaced with the current year</td>
		</tr>
		<tr class="alt">
			<th>%%page%%</th>
			<td>Replaced with the current page number (i.e. page 2 of 4)</td>
		</tr>
		<tr>
			<th>%%pagetotal%%</th>
			<td>Replaced with the current page total</td>
		</tr>
		<tr class="alt">
			<th>%%pagenumber%%</th>
			<td>Replaced with the current page number</td>
		</tr>
		<tr>
			<th>%%caption%%</th>
			<td>Attachment caption</td>
		</tr>
	</tbody>
</table>' , 'wds');

	if ( wds_is_wizard_step( '3' ) )
		$settings = new WDS_Core_Admin_Tab( $name, $title, $description, $fields, 'wds', $contextual_help );
}
add_action( 'init', 'wds_seoopt_settings' );

/* Default settings */
function wds_seoopt_defaults() {
	if( is_multisite() && WDS_SITEWIDE == true ) {
		$onpage_options = get_site_option( 'wds_onpage_options' );
	} else {
		$onpage_options = get_option( 'wds_onpage_options' );
	}

	if ( empty($onpage_options['title-home']) )
		$onpage_options['title-home'] = '%%sitename%%';

	if ( empty($onpage_options['metadesc-home']) )
		$onpage_options['metadesc-home'] = '%%sitedesc%%';

	if ( empty($onpage_options['title-post']) )
		$onpage_options['title-post'] = '%%title%% | %%sitename%%';

	if ( empty($onpage_options['metadesc-post']) )
		$onpage_options['metadesc-post'] = '%%excerpt%%';

	if ( empty($onpage_options['title-page']) )
		$onpage_options['title-page'] = '%%title%% | %%sitename%%';

	if ( empty($onpage_options['metadesc-page']) )
		$onpage_options['metadesc-page'] = '%%excerpt%%';

	if ( empty($onpage_options['title-attachment']) )
		$onpage_options['title-attachment'] = '%%title%% | %%sitename%%';

	if ( empty($onpage_options['metadesc-attachment']) )
		$onpage_options['metadesc-attachment'] = '%%caption%%';

	if ( empty($onpage_options['title-category']) )
		$onpage_options['title-category'] = '%%category%% | %%sitename%%';

	if ( empty($onpage_options['metadesc-category']) )
		$onpage_options['metadesc-category'] = '%%category_description%%';

	if ( empty($onpage_options['title-post_tag']) )
		$onpage_options['title-post_tag'] = '%%tag%% | %%sitename%%';

	if ( empty($onpage_options['metadesc-post_tag']) )
		$onpage_options['metadesc-post_tag'] = '%%tag_description%%';

	if ( empty($onpage_options['title-author']) )
		$onpage_options['title-author'] = '%%name%% | %%sitename%%';

	if ( empty($onpage_options['metadesc-author']) )
		$onpage_options['metadesc-author'] = '';

	if ( empty($onpage_options['title-date']) )
		$onpage_options['title-date'] = '%%currentdate%% | %%sitename%%';

	if ( empty($onpage_options['metadesc-date']) )
		$onpage_options['metadesc-date'] = '';

	if ( empty($onpage_options['title-search']) )
		$onpage_options['title-search'] = '%%searchphrase%% | %%sitename%%';

	if ( empty($onpage_options['metadesc-search']) )
		$onpage_options['metadesc-search'] = '';

	if ( empty($onpage_options['title-404']) )
		$onpage_options['title-404'] = 'Page not found | %%sitename%%';

	if ( empty($onpage_options['metadesc-404']) )
		$onpage_options['metadesc-404'] = '';

	if( is_multisite() && WDS_SITEWIDE == true ) {
		update_site_option( 'wds_onpage_options', $onpage_options );
	} else {
		update_option( 'wds_onpage_options', $onpage_options );
	}

}
add_action( 'init', 'wds_seoopt_defaults' );
