<?php

/* Add admin settings page */
function wds_settings() {
	$name = 'wds_settings';
	$title = __( 'Settings' , 'wds');
	$description = __( '
		<p>WPMU DEV SEO aims to take care of every SEO option that a site requires, in one easy bundle.</p>
		<p>It is made of several components which you complete as you work through our simple SEO Set Up Wizard:</p>
		<ul>
			<li><b>Step 1 Settings</b>: allows you to choose which steps you want to include in your SEO Set Up Wizard. In most situations you would want to leave all four of the active components below checked.</li>
			<li><b>Step 2 XML Sitemap</b>: generates an xml sitemap which helps search engines to better index your site.</li>
			<li><b>Step 3 Title & Meta Optimization</b>: allows you to optimize title and meta tags on every page of your site.</li>
			<li><b>Step 4 SEOmoz Report</b>: provides detailed and accurate SEO information about your Web pages. It uses the SEOmoz Free API.</li>
			<li><b>Step 5 Automatic Links</b>: allows you to automatically link phrases in your posts, pages, custom post types and comments to corresponding posts, pages, custom post types, categories, tags, custom taxonomies and external urls.</li>
		</ul>
	' , 'wds');
	$fields = array(
		'components' => array(
			'title' => __( 'Active Components' , 'wds'),
			'intro' => __( 'In most situations you would want to leave all four of these components checked.' , 'wds'),
			'options' => array(
				array(
					'type' => 'checkbox',
					'name' => 'active-components',
					'title' => __( 'Check/uncheck the boxes to add/remove a step from the SEO Set Up Wizard' , 'wds'),
					'items' => array(
						'autolinks' => __( 'Automatic Links' , 'wds'),
						'onpage' => __( 'Title & Meta Optimization' , 'wds'),
						'seomoz' => __( 'SEOmoz Report' , 'wds'),
						//'sitemaps' => __( 'XML Sitemap' , 'wds'), // Removed plural
						'sitemap' => __( 'XML Sitemap' , 'wds'), // Added singular
					),
					'description' => ''
				)
			)
		),
		array(
			'title' => __('Show metaboxes to users', 'wds'),
			'intro' => __('This applies to create/edit Post pages', 'wds'),
			'options' => array (
				array(
					'title' => __('Show SEO metabox to role', 'wds'),
					'type' => 'dropdown',
					'name' => 'seo_metabox_permission_level',
					'items' => array(
						'manage_network' => __('Super Admin'),
						'activate_plugins' => __('Site Admin'),
						'moderate_comments' => __('Editor'),
						'edit_published_posts' => __('Author'),
						'edit_posts' => __('Contributor'),
					)
				),
				array(
					'title' => __('Show SEOmoz metabox to role', 'wds'),
					'type' => 'dropdown',
					'name' => 'urlmetrics_metabox_permission_level',
					'items' => array(
						'manage_network' => __('Super Admin'),
						'activate_plugins' => __('Site Admin'),
						'moderate_comments' => __('Editor'),
						'edit_published_posts' => __('Author'),
						'edit_posts' => __('Contributor'),
					)
				),
			)
		),
	);

	$contextual_help = '';

	if ( wds_is_wizard_step( '1' ) )
		$settings = new WDS_Core_Admin_Tab( $name, $title, $description, $fields, 'wds', $contextual_help );
}
add_action( 'init', 'wds_settings' );

/* Default settings */
function wds_defaults() {
	if( is_multisite() && WDS_SITEWIDE == true ) {
		$defaults = get_site_option( 'wds_settings_options' );
	} else {
		$defaults = get_option( 'wds_settings_options' );
	}

	if( ! is_array( $defaults ) ) {
		$defaults = array(
			//'onpage' => 1,
			'onpage' => 'on', // 'on' instead of 1
			'seo_metabox_permission_level' => (is_multisite() ? 'manage_network_options' : 'activate_plugins'), // Default to highest permission level available
			//'autolinks' => 1,
			'autolinks' => 'on', // 'on' instead of 1
			//'seomoz' => 1,
			'seomoz' => 'on', // 'on' instead of 1
			'urlmetrics_metabox_permission_level' => (is_multisite() ? 'manage_network_options' : 'activate_plugins'), // Default to highest permission level available
			//'sitemaps' => 1, // Removed plural
			'sitemap' => 'on', // Added singular. Also, changed to 'on' instead of 1
		);
	}
	apply_filters( 'wds_defaults', $defaults );

	if( is_multisite() && WDS_SITEWIDE == true ) {
		update_site_option( 'wds_settings_options', $defaults );
	} else {
		update_option( 'wds_settings_options', $defaults );
	}
}
add_action( 'init', 'wds_defaults' );

/*if ( ! class_exists( 'WDS_Admin' ) ) {

	class WDS_Admin extends WDS_Core_Admin {

		function permalinks_page() {
			$this->admin_header('Permalinks', true, true, 'wds_permalinks_options', 'wds_permalinks');
			$content = $this->checkbox('trailingslash','Enforce a trailing slash on all category and tag URL\'s');
			$content .= '<p class="desc">'.__('If you choose a permalink for your posts with <code>.html</code>, or anything else but a / on the end, this will force WordPress to add a trailing slash to non-post pages nonetheless.', 'wds-wds').'</p>';

			$content .= $this->checkbox('redirectattachment','Redirect attachment URL\'s to parent post URL.');
			$content .= '<p class="desc">'.__('Attachments to posts are stored in the database as posts, this means they\'re accessible under their own URL\'s if you do not redirect them, enabling this will redirect them to the post they were attached to.', 'wds-wds').'</p>';

			$content .= $this->checkbox('cleanpermalinks','Redirect ugly URL\'s to clean permalinks.');
			$content .= '<p class="desc">'.__('People make mistakes in their links towards you sometimes, or unwanted parameters are added to the end of your URLs, this allows you to redirect them all away.', 'wds-wds').'</p>';

			$this->postbox('permalinks',__('Permalink Settings', 'wds-wds'),$content);

			$content = $this->checkbox('cleanpermalink-googlesitesearch','Prevent cleaning out Google Site Search URL\'s.');
			$content .= '<p class="desc">'.__('Google Site Search URL\'s look weird, and ugly, but if you\'re using Google Site Search, you probably do not want them cleaned out.', 'wds-wds').'</p>';

			$content .= $this->checkbox('cleanpermalink-googlecampaign','Prevent cleaning out Google Analytics Campaign Parameters.');
			$content .= '<p class="desc">'.__('If you use Google Analytics campaign parameters starting with <code>?utm_</code>, check this box. You shouldn\'t use these btw, you should instead use the hash tagged version instead.', 'wds-wds').'</p>';

			$this->postbox('cleanpermalinksdiv',__('Clean Permalink Settings', 'wds-wds'),$content);

			$this->admin_footer('Permalinks');
		}

		function indexation_page() {
			$this->admin_header('Indexation', true, true, 'wds_indexation_options', 'wds_indexation');

			$content = '<p>'.__("Below you'll find checkboxes for each of the sections of your site that you might want to disallow the search engines from indexing. Be aware that this is a powerful tool, blocking category archives, for instance, really blocks all category archives from showing up in the index.").'</p>';
			$content .= $this->checkbox('search',__('This site\'s search result pages', 'wds-wds'));
			$content .= '<p class="desc">'.__('Prevents the search engines from indexing your search result pages, by a <code>noindex,follow</code> robots tag to them. The <code>follow</code> part means that search engine crawlers <em>will</em> spider the pages listed in the search results.', 'wds-wds').'</p>';
			$content .= $this->checkbox('logininput',__('The login and register pages', 'wds-wds') );
			$content .= '<p class="desc">'.__('(warning: don\'t enable this if you have the <a href="http://wordpress.org/extend/plugins/minimeta-widget/">minimeta widget</a> installed!)', 'wds-wds').'</p>';
			$content .= $this->checkbox('admin',__('All admin pages', 'wds-wds') );
			$content .= '<p class="desc">'.__('The above two options prevent the search engines from indexing your login, register and admin pages.', 'wds-wds').'</p>';
			$content .= $this->checkbox('pagedhome',__('Subpages of the homepage', 'wds-wds') );
			$content .= '<p class="desc">'.__('Prevent the search engines from indexing your subpages, if you want them to only index your category and / or tag archives.', 'wds-wds').'</p>';
			$content .= $this->checkbox('noindexauthor',__('Author archives', 'wds-wds') );
			$content .= '<p class="desc">'.__('By default, WordPress creates author archives for each user, usually available under <code>/author/username</code>. If you have sufficient other archives, or yours is a one person blog, there\'s no need and you can best disable them or prevent search engines from indexing them.', 'wds-wds').'</p>';

			$content .= $this->checkbox('noindexdate',__('Date-based archives', 'wds-wds') );
			$content .= '<p class="desc">'.__('If you want to offer your users the option of crawling your site by date, but have ample other ways for the search engines to find the content on your site, I highly encourage you to prevent your date-based archives from being indexed.', 'wds-wds').'</p>';
			$content .= $this->checkbox('noindexcat',__('Category archives', 'wds-wds') );
			$content .= '<p class="desc">'.__('If you\'re using tags as your only way of structure on your site, you would probably be better off when you prevent your categories from being indexed.', 'wds-wds').'</p>';

			$content .= $this->checkbox('noindextag',__('Tag archives', 'wds-wds') );
			$content .= '<p class="desc">'.__('Read the categories explanation above for categories and switch the words category and tag around ;)', 'wds-wds').'</p>';

			$this->postbox('preventindexing',__('Indexation Rules', 'wds-wds'),$content);

			$content = $this->checkbox('nofollowmeta',__('Nofollow login and registration links', 'wds-wds') );
			$content .= '<p class="desc">'.__('This might have happened to you: logging in to your admin panel to notice that it has become PR6... Nofollow those admin and login links, there\'s no use flowing PageRank to those pages!', 'wds-wds').'</p>';
			$content .= $this->checkbox('nofollowcommentlinks',__('Nofollow comments links', 'wds-wds') );
			$content .= '<p class="desc">'.__('Simple way to decrease the number of links on your pages: nofollow all the links pointing to comment sections.', 'wds-wds').'</p>';
			$content .= $this->checkbox('replacemetawidget',__('Replace the Meta Widget with a nofollowed one', 'wds-wds') );
			$content .= '<p class="desc">'.__('By default the Meta widget links to your RSS feeds and to WordPress.org with a follow link, this will replace that widget by a custom one in which all these links are nofollowed.', 'wds-wds').'</p>';

			$this->postbox('internalnofollow',__('Internal nofollow settings', 'wds-wds'),$content);

			$content = $this->checkbox('disableauthor',__('Disable the author archives', 'wds-wds') );
			$content .= '<p class="desc">'.__('If you\'re running a one author blog, the author archive will always look exactly the same as your homepage. And even though you may not link to it, others might, to do you harm. Disabling them here will make sure any link to those archives will be 301 redirected to the blog homepage.', 'wds-wds').'</p>';
			$content .= $this->checkbox('disabledate',__('Disable the date-based archives', 'wds-wds') );
			$content .= '<p class="desc">'.__('For the date based archives, the same applies: they probably look a lot like your homepage, and could thus be seen as duplicate content.', 'wds-wds').'</p>';

			$this->postbox('archivesettings',__('Archive Settings', 'wds-wds'),$content);

			$content = '<p>'.__("You can add all these on a per post / page basis from the edit screen, by clicking on advanced. Should you wish to use any of these sitewide, you can do so here. (This is <em>not</em> recommended.)").'</p>';
			$content .= $this->checkbox('noodp',__('Add <code>noodp</code> meta robots tag sitewide', 'wds-wds') );
			$content .= '<p class="desc">'.__('Prevents search engines from using the DMOZ description for pages from this site in the search results.', 'wds-wds').'</p>';
			$content .= $this->checkbox('noydir',__('Add <code>noydir</code> meta robots tag sitewide', 'wds-wds') );
			$content .= '<p class="desc">'.__('Prevents search engines from using the Yahoo! directory description for pages from this site in the search results.', 'wds-wds').'</p>';
			$content .= $this->checkbox('nosnippet',__('Add <code>nosnippet</code> meta robots tag sitewide', 'wds-wds') );
			$content .= '<p class="desc">'.__('Prevents search engines from displaying snippets for your pages.', 'wds-wds').'</p>';
			$content .= $this->checkbox('noarchive',__('Add <code>noarchive</code> meta robots tag sitewide', 'wds-wds') );
			$content .= '<p class="desc">'.__('Prevents search engines from caching pages from this site.', 'wds-wds').'</p>';

			$this->postbox('directories',__('Robots Meta Settings', 'wds-wds'),$content);

			$content = '<p>'.__('Some of us like to keep our &lt;heads&gt; clean. The settings below allow you to make it happen.', 'wds-wds').'</p>';
			$content .= $this->checkbox('hidersdlink','Hide RSD Links');
			$content .= '<p class="desc">'.__('Might be necessary if you or other people on this site use remote editors.', 'wds-wds').'</p>';
			$content .= $this->checkbox('hidewlwmanifest','Hide WLW Manifest Links');
			$content .= '<p class="desc">'.__('Might be necessary if you or other people on this site use Windows Live Writer.', 'wds-wds').'</p>';
			$content .= $this->checkbox('hidewpgenerator','Hide WordPress Generator');
			$content .= '<p class="desc">'.__('If you want to show off that you\'re on the latest version, don\'t check this box.', 'wds-wds').'</p>';
			$content .= $this->checkbox('hideindexrel','Hide Index Relation Links');
			$content .= '<p class="desc">'.__('Check this box, or please tell the plugin author why you shouldn\'t.', 'wds-wds').'</p>';
			$content .= $this->checkbox('hideprevnextpostlink','Hide Previous &amp; Next Post Links');
			$content .= $this->checkbox('hideshortlink','Hide Shortlink for posts');
			$content .= '<p class="desc">'.__('Hides the shortlink for the current post.', 'wds-wds').'</p>';
			$content .= $this->checkbox('hidefeedlinks','Hide RSS Links');
			$content .= '<p class="desc">'.__('Check this box only if you\'re absolutely positive your site doesn\'t need and use RSS feeds.', 'wds-wds').'</p>';

			$this->postbox('headsection','Clean up &lt;head&gt; section',$content);

			$this->admin_footer('Indexation');
		}

		function rss_page() {
			global $wds_options;
			$this->admin_header('RSS', true, true, 'wds_rss_options', 'wds_rss');
			$content = $this->checkbox('commentfeeds',__('<code>noindex</code> the comment RSS feeds', 'wds-wds') );
			$content .= '<p class="desc">'.__('This will prevent the search engines from indexing your comment feeds.', 'wds-wds').'</p>';

			$content .= $this->checkbox('allfeeds',__('<code>noindex</code> <strong>all</strong> RSS feeds', 'wds-wds') );
			$content .= '<p class="desc">'.__('This will prevent the search engines from indexing <strong>all your</strong> feeds. Highly discouraged.', 'wds-wds').'</p>';

			$content .= $this->checkbox('pingfeed',__('Ping the Search Engines with feed on new post', 'wds-wds') );
			$content .= '<p class="desc">'.__('This will ping search engines that your RSS feed has been updated.', 'wds-wds').'</p>';

			$this->postbox('rssfeeds',__('RSS Feeds', 'wds-wds'),$content);

			$content = '<p>'."The feature below is used to automatically add content to your RSS, more specifically, it's meant to add links back to your blog and your blog posts, so dumb scrapers will automatically add these links too, helping search engines identify you as the original source of the content.".'</p>';
			$rows = array();
			$rssbefore = '';
			if ( isset($wds_options['rssbefore']) )
				$rssbefore = stripslashes(htmlentities($wds_options['rssbefore']));

			$rssafter = '';
			if ( isset($wds_options['rssafter']) )
				$rssafter = stripslashes(htmlentities($wds_options['rssafter']));

			$rows[] = array(
				"id" => "rssbefore",
				"label" => __("Content to put before each post in the feed", 'wds-wds'),
				"desc" => __("(HTML allowed)", 'wds-wds'),
				"content" => '<textarea cols="50" rows="5" id="rssbefore" name="wds_rss[rssbefore]">'.$rssbefore.'</textarea>',
			);
			$rows[] = array(
				"id" => "rssafter",
				"label" => __("Content to put after each post", 'wds-wds'),
				"desc" => __("(HTML allowed)", 'wds-wds'),
				"content" => '<textarea cols="50" rows="5" id="rssafter" name="wds_rss[rssafter]">'.$rssafter.'</textarea>',
			);
			$rows[] = array(
				"label" => __('Explanation', 'wds-wds'),
				"content" => '<p>'.__('You can use the following variables within the content, they will be replaced by the value on the right.', 'wds-wds').'</p>'.
				'<ul>'.
				'<li><strong>%%POSTLINK%%</strong> : '.__('A link to the post, with the title as anchor text.', 'wds-wds').'</li>'.
				'<li><strong>%%BLOGLINK%%</strong> : '.__("A link to your site, with your site's name as anchor text.", 'wds-wds').'</li>'.
				'<li><strong>%%BLOGDESCLINK%%</strong> : '.__("A link to your site, with your site's name and description as anchor text.", 'wds-wds').'</li>'.
				'</ul>'
			);
			$this->postbox('rssfootercontent',__('Content of your RSS Feed', 'wds-wds'),$content.$this->form_table($rows));

			$this->admin_footer('RSS');
		}

		function config_page() {
			$this->admin_header('Settings', false);

			$content = '';

			if ( strpos( get_option('permalink_structure'), '%postname%' ) === false )
				$content .= '<p class="wrong"><a href="'.admin_url('options-permalink.php').'" class="button fixit">'.__('Go fix it.').'</a>'.__('You do not have your postname in the URL of your posts and pages, it is highly recommended that you do. Consider setting your permalink structure to <strong>/%postname%/</strong>.').'</p>';

			if ( get_option('page_comments') )
				$content .= '<p class="wrong"><a href="'.admin_url('options-discussion.php').'" class="button fixit">'.__('Go fix it.').'</a>'.__('Paging comments is enabled, this is not needed in 999 out of 1000 cases, so the suggestion is to disable it, to do that, simply uncheck the box before "Break comments into pages..."').'</p>';

			if ( strpos( get_option('ping_sites'), 'http://blogsearch.google.com/ping/RPC2' ) === false )
				$content .= '<p class="wrong"><a href="'.admin_url('options-writing.php').'" class="button fixit">'.__('Go fix it.').'</a>'.__('You\'re not pinging Google Blogsearch when you publish new blogposts, you should add <strong>http://blogsearch.google.com/ping/RPC2</strong> to the textarea under the "Update Services" header.').'</p>';

			// $content .= '<pre>'.print_r(get_option('ping_sites'),1).'</pre>';
			if ($content != '')
				$this->postbox('advice',__('Settings Advice', 'wds-wds'),$content);

			$content = '<p>'.__('You can use the boxes below to verify with the different Webmaster Tools, if your site is already verified, you can just forget about these. Enter the verify meta values for:').'</p>';
			$content .= $this->textinput('googleverify', '<a target="_blank" href="https://www.google.com/webmasters/tools/dashboard?hl=en&amp;siteUrl='.urlencode(get_bloginfo('url')).'%2F">'.__('Google Webmaster Tools', 'wds-wds').'</a>');
			$content .= $this->textinput('yahooverify','<a target="_blank" href="https://siteexplorer.search.yahoo.com/mysites">'.__('Yahoo! Site Explorer', 'wds-wds').'</a>');
			$content .= $this->textinput('msverify','<a target="_blank" href="http://www.bing.com/webmaster/?rfp=1#/Dashboard/?url='.str_replace('http://','',get_bloginfo('url')).'">'.__('Bing Webmaster Tools', 'wds-wds').'</a>');

			$content .= '<br class="clear"/><br/>';

			$this->postbox('webmastertools',__('Webmaster Tools', 'wds-wds'),$content);

			$content .= '<br class="clear"/>';
			$content .= '<p>'.__('<strong>Note:</strong> make sure to save the settings if you\'ve changed anything above before regenerating the XML sitemap.').'</p>';
			$content .= '<a class="button" href="javascript:testSitemap(\''.WDS_PLUGIN_URL.'\',\'\');">Test XML sitemap values</a> ';
			$content .= '<a class="button" href="javascript:rebuildSitemap(\''.WDS_PLUGIN_URL.'\',\'\');">(Re)build XML sitemap</a><br/><br/>';
			$content .= '<div id="sitemaptestresult">'.wds_test_sitemap_callback(true).'</div>';
			$content .= '<br/>';
			$content .= '<div id="sitemapgeneration"></div>';
			$content .= '</div>';
			$this->postbox('xmlsitemaps',__('XML Sitemap', 'wds-wds'),$content);

			do_action('wds_settings', $this);

			$this->admin_footer('');
		}

		function wds_user_profile($user) {
			if (!current_user_can('edit_users'))
				return;
			?>
				<h3 id="wordpress-seo">WordPress SEO settings</h3>
				<table class="form-table">
					<tr>
						<th>Title to use for Author page</th>
						<td><input class="regular-text" type="text" name="wds_author_title" value="<?php echo esc_attr(get_the_author_meta('wds_title', $user->ID) ); ?>"/></td>
					</tr>
					<tr>
						<th>Meta description to use for Author page</th>
						<td><textarea rows="3" cols="30" name="wds_author_metadesc"><?php echo esc_html(get_the_author_meta('wds_metadesc', $user->ID) ); ?></textarea></td>
					</tr>
				</table>
				<br/><br/>
			<?php
		}

		function wds_process_user_option_update($user_id) {
			update_usermeta($user_id, 'wds_title', ( isset($_POST['wds_author_title']) ? $_POST['wds_author_title'] : '' ) );
			update_usermeta($user_id, 'wds_metadesc', ( isset($_POST['wds_author_metadesc']) ? $_POST['wds_author_metadesc'] : '' ) );
		}

	} // end class WDS_Admin
	$wds_admin = new WDS_Admin();
}
*/
