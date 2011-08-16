<?php
/**
 * WDS_XML_Sitemap::generate_sitemap()
 * inspired by WordPress SEO by Joost de Valk (http://yoast.com/wordpress/seo/).
 */

/**
 * WPMU DEV SEO pages optimization classes
 *
 * @package WPMU DEV SEO
 * @since 0.1
 */

class WDS_XML_Sitemap_Base {

	function WDS_XML_Sitemap_Base() {
	}

	function generate_sitemap() {
	}

	function write_sitemap( $sitemappath, $output ) {
		$f = @fopen($sitemappath, 'w+');
		@fwrite($f, $output);
		@fclose($f);
		if ( $this->gzip_sitemap( $sitemappath, $output ) )
			return true;
		return false;
	}

	function gzip_sitemap( $sitemap, $output ) {
		$f = @fopen( $sitemap.'.gz', "w" );
		if ( $f ) {
			@fwrite( $f, gzencode( $output , 9 ) );
			@fclose( $f );
			return true;
		}
		return false;
	}

	function ping_search_engines( $sitemapurl, $echo = false ) {
		$sitemapurl = urlencode($sitemapurl.'.gz');

		$resp = wp_remote_get('http://www.google.com/webmasters/tools/ping?sitemap='.$sitemapurl);

		if ($echo && $resp['response']['code'] == '200')
			__e( 'Successfully notified Google of updated sitemap.' ) . '<br/>';

		//$appid = '';
		//$resp = wp_remote_get('http://search.yahooapis.com/SiteExplorerService/V1/updateNotification?appid='.$appid.'&url='.$sitemapurl);

		if ($echo && $resp['response']['code'] == '200')
			__e( 'Successfully notified Yahoo! of updated sitemap.' ) . '<br/>';

		$resp = wp_remote_get('http://www.bing.com/webmaster/ping.aspx?sitemap='.$sitemapurl);

		if ($echo && $resp['response']['code'] == '200')
			__e( 'Successfully notified Bing of updated sitemap.' ) . '<br/>';

		$resp = wp_remote_get('http://submissions.ask.com/ping?sitemap='.$sitemapurl);

		if ($echo && $resp['response']['code'] == '200')
			__e( 'Successfully notified Ask.com of updated sitemap.' ) . '<br/>';
	}

	function w3c_date($time='') {
	  if (empty($time))
	    $time = time();
		else
			$time = strtotime($time);
	  $offset = date("O",$time);
	  return date("Y-m-d\TH:i:s",$time).substr($offset,0,3).":".substr($offset,-2);
	}

	function xml_clean( $str ) {
		return ent2ncr( esc_html( str_replace ( "’", "&quot;", $str ) ) );
	}

	function make_image_local( $url, $post_id, $title, $type = '' ) {
		$tmp = download_url( $url );

		preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $url, $matches);

		$title = sanitize_title( strtolower($title) );
		$file_array['name'] = $title . '.' . $matches[1];
		$file_array['tmp_name'] = $tmp;

		if ( is_wp_error($tmp) ) {
			@unlink($file_array['tmp_name']);
			$file_array['tmp_name'] = '';
			return false;
		} else {
			return media_handle_sideload( $file_array, $post_id, sprintf( __( 'Poster image for %1$s video in %2$s' , 'wds'), $type, $title ) );
		}
	}

	function update_video_meta( $post, $echo = false ) {
		global $shortcode_tags;

		global $wds_options;

		$shortcode_tags = array(
			'blip.tv' => '',
			// 'dailymotion' => '',
			// 'flickrvideo' => '',
			// 'flash' => '',
			'flv' => '',
			// 'googlevideo' => '',
			// 'metacafe' => '',
			// 'myspace' => '',
			// 'quicktime' => '',
			// 'spike' => '',
			// 'veoh' => '',
			// 'videopress' => '',
			// 'viddler' => '',
			// 'videofile' => '',
			'vimeo' => '',
			// 'wpvideo' => '',
			'youtube' => '',
		);

		$oldvid = wds_get_value('video_meta', $post->ID);

		if (preg_match('/'.get_shortcode_regex().'/', $post->post_content, $matches)) {
			$_GLOBALS['post'] 	= $post;

			if ($post->post_type == 'post') {
				$wp_query->is_single = true;
				$wp_query->is_page = false;
			} else {
				$wp_query->is_single = false;
				$wp_query->is_page = true;
			}
			// Grab the meta data from the post
			$cats = get_the_terms($post->ID, 'category');
			$tags = get_the_terms($post->ID, 'post_tag');
			$tag = array();
			if (is_array($tags)) {
				foreach ($tags as $t) {
					$tag[] = $t->name;
				}
			} else {
				$tag[] = $cats[0]->name;
			}

			$focuskw = wds_get_value('focuskw', $post->ID);
			if (!empty($focuskw))
				$tag[] = $focuskw;

			$title = wds_get_value('title', $post->ID);
			if (empty($title)) {
				$title = wds_replace_vars($wds_options['title-'.$post->post_type], (array) $post );
			}

			$vid 						= array();
			$vid['loc'] 				= get_permalink($post->ID);
			$vid['title']				= $this->xml_clean($title);
			$vid['publication_date'] 	= $this->w3c_date($post->post_date);
			$vid['category']			= $cats[0]->name;
			$vid['tag']					= $tag;

			$vid['description'] 		= wds_get_value('metadesc', $post->ID);
			if ( !$vid['description'] ) {
				$vid['description']	= $this->xml_clean(substr(preg_replace('/\s+/',' ',strip_tags(strip_shortcodes($post->post_content))), 0, 300));
			}

			preg_match('/image=(\'|")?([^\'"\s]+)(\'|")?/', $matches[3], $match);
			if (isset($match[2]) && !empty($match[2]))
				$vid['thumbnail_loc'] 	= $match[2];

			if (!isset($vid['thumbnail_loc']) && isset($oldvid['thumbnail_loc']))
				$vid['thumbnail_loc'] 	= $oldvid['thumbnail_loc'];

			if ($vid['thumbnail_loc'] == 'n')
				unset($vid['thumbnail_loc']);

			switch ($matches[2]) {
				case 'vimeo':
					$videoid 	= preg_replace('|http://(www\.)?vimeo\.com/|','',$matches[5]);
					$url 		= 'http://vimeo.com/api/v2/video/'.$videoid.'.php';
					$vimeo_info = wp_remote_get($url);
					$vimeo_info = unserialize($vimeo_info['body']);

					// echo '<pre>'.print_r($vimeo_info, 1).'</pre>';

					$vid['player_loc'] 		= 'http://www.vimeo.com/moogaloop.swf?clip_id='.$videoid;
					$vid['duration']		= $vimeo_info[0]['duration'];
					$vid['view_count']		= $vimeo_info[0]['stats_number_of_plays'];

					if (!isset($vid['thumbnail_loc']))
						$vid['thumbnail_loc'] 	= $this->make_image_local($vimeo_info[0]['thumbnail_medium'], $post->ID, $title, $matches[2]);
					break;
				case 'blip.tv':
					preg_match('|posts_id=(\d+)|', $matches[3], $match);
					$videoid	= $match[1];

					$blip_info	= wp_remote_get('http://blip.tv/rss/view/'.$videoid);
					$blip_info	= $blip_info['body'];

					preg_match("|<blip:runtime>([\d]+)</blip:runtime>|", $blip_info, $match);
					$vid['duration']		= $match[1];

					preg_match('|<media:player url="([^"]+)">|', $blip_info, $match);
					$vid['player_loc']		= $match[1];

					preg_match('|<enclosure length="[\d]+" type="[^"]+" url="([^"]+)"/>|', $blip_info, $match);
					$vid['content_loc']		= $match[1];

					preg_match('|<media:thumbnail url="([^"]+)"/>|', $blip_info, $match);

					if (!isset($vid['thumbnail_loc'])) {
						// $vid['thumbnail_loc']	= $this->make_image_local($match[1], $post->ID, $title, $matches[2]);
						$vid['thumbnail_loc']	= $match[1];
					}
					break;
				case 'youtube':
					$videoid	= preg_replace('|http://(www\.)?youtube.com/(watch)?\?v=|','',$matches[5]);

					$youtube_info = wp_remote_get('http://gdata.youtube.com/feeds/api/videos/'.$videoid);
					$youtube_info = $youtube_info['body'];

					preg_match("|<yt:duration seconds='([\d]+)'/>|", $youtube_info, $match);
					$vid['duration']		= $match[1];

					$vid['player_loc']		= 'http://www.youtube-nocookie.com/v/'.$videoid;

					if (!isset($vid['thumbnail_loc']))
						$vid['thumbnail_loc']	= $this->make_image_local('http://img.youtube.com/vi/'.$videoid.'/0.jpg', $post->ID, $title, $matches[2]);
					break;
				case 'flv':
					// TODO add fallback poster image for when no poster image present
					$vid['content_loc']		= $matches[5];
					break;
				default:
					echo '<pre>'.print_r($matches,1).'</pre>';
					echo '<pre>'.print_r($vid,1).'</pre>';
					$vid = 'none';
					break;
			}
			if ($echo)
				echo sprintf( __( 'Video Metadata updated for %s' , 'wds'), $post->post_title ) . '<br/>';
		} else {
			$vid = 'none';
		}

		wds_set_value( 'video_meta', $vid, $post->ID );
		return $vid;
	}
}

class WDS_XML_Sitemap extends WDS_XML_Sitemap_Base {

	function WDS_XML_Sitemap() {
		global $wds_echo, $wds_options;

		//if ( !$wds_options['sitemaps'] )
		if ( !$wds_options['sitemap'] ) // Changed plural to singular
			return;

		$sitemap_options = get_option( 'wds_sitemap_options' );
//echo "<pre>" . var_export($sitemap_options,1)  . "</pre>";
		if ( !isset( $sitemap_options['sitemappath'] ) || empty( $sitemap_options['sitemappath'] ) )
			return;

		$this->generate_sitemap( $sitemap_options['sitemapurl'], $sitemap_options['sitemappath'], $wds_echo );
		$this->ping_search_engines( $sitemap_options['sitemapurl'], $wds_echo );
	}

	function generate_sitemap( $sitemapurl, $sitemappath, $echo = false ) {
		global $wpdb, $wp_taxonomies, $wp_rewrite, $wds_options;

		//this can take a whole lot of time on big blogs
    set_time_limit(120);

		$wp_rewrite->flush_rules();

		// The stack of URL's to add to the sitemap
		$stack = array();
		$stackedurls = array();

		// Add the homepage first
		$url = array();
		$url['loc'] = get_bloginfo('url').'/';
		$url['pri'] = 1;
		$url['chf'] = 'daily';

		$stackedurls[] = $url['loc'];
		$stack[] = $url;

		$post_types = array();
		foreach (get_post_types() as $post_type) {
			if ( !empty( $wds_options['post_types-'.$post_type.'-not_in_sitemap'] ) )
				continue;
			if ( in_array( $post_type, array('revision','nav_menu_item','attachment') ) )
				continue;
			$post_types[] = $post_type;
		}

		$pt_query = 'AND post_type IN (';
		foreach ($post_types as $pt) {
			$pt_query .= '"'.$pt.'",';
		}
		$pt_query = rtrim($pt_query,',').')';

		// Grab posts and pages and add to stack
		$posts = $wpdb->get_results("SELECT ID, post_content, post_parent, post_type, post_modified
										FROM $wpdb->posts
										WHERE post_status = 'publish'
										AND	post_password = ''
										$pt_query
										ORDER BY post_parent ASC, post_modified DESC LIMIT " . WDS_SITEMAP_POST_LIMIT );
		if ( $echo )
			echo sprintf( __( '%s posts and pages found.', count( $posts ) , 'wds') ) . '<br/>';

		foreach ($posts as $p) {
			$link 		= get_permalink($p->ID);

			if (isset($wds_options['trailingslash']) && $wds_options['trailingslash'] && $p->post_type != 'single')
				$link = trailingslashit($link);

			$canonical 	= wds_get_value('canonical', $p->ID);
			if ( !empty($canonical) && $canonical != $link )
				$link = $canonical;
			if ( wds_get_value('meta-robots-noindex', $p->ID) )
				continue;
			if ( strlen( wds_get_value('redirect', $p->ID) ) > 0 )
				continue;
			if ($p->ID == get_option('page_on_front'))
				continue;

			$url = array();
			$pri = wds_get_value('sitemap-prio', $p->ID);
			if (is_numeric($pri))
				$url['pri'] = $pri;
			elseif ($p->post_parent == 0 && $p->post_type = 'page')
				$url['pri'] = 0.8;
			else
				$url['pri'] = 0.6;

			$url['images'] = array();

			preg_match_all("|(<img [^>]+?>)|", $p->post_content, $matches, PREG_SET_ORDER);

			if (count($matches) > 0) {
				$tmp_img = array();
				foreach ($matches as $imgarr) {
					unset($imgarr[0]);
					foreach($imgarr as $img) {
						unset($image['title']);
						unset($image['alt']);

						// FIXME: get true caption instead of alt / title
						$res = preg_match( '/src=("|\')([^"\']+)("|\')/', $img, $match );
						if ($res) {
							$image['src'] = $match[2];
							if ( strpos($image['src'], 'http') !== 0 ) {
								$image['src'] = get_bloginfo('url').$image['src'];
							}
						}
						if ( in_array($image['src'], $tmp_img) )
							continue;
						else
							$tmp_img[] = $image['src'];

						$res = preg_match( '/title=("|\')([^"\']+)("|\')/', $img, $match );
						if ($res)
							$image['title'] = str_replace('-',' ',str_replace('_',' ',$match[2]));

						$res = preg_match( '/alt=("|\')([^"\']+)("|\')/', $img, $match );
						if ($res)
							$image['alt'] = str_replace('-',' ',str_replace('_',' ',$match[2]));

						if (empty($image['title']))
							unset($image['title']);
						if (empty($image['alt']))
							unset($image['alt']);
						$url['images'][] = $image;
					}
				}
			}

			// echo '<pre>'.print_r($url,1).'</pre>';

			$url['mod']	= $p->post_modified;
			$url['loc'] = $link;
			$url['chf'] = 'weekly';
			if (!in_array($url['loc'], array_values($stackedurls))) {
				$stack[] = $url;
				$stackedurls[] = $url['loc'];
			}
		}
		unset($posts);

		// Grab all taxonomies and add to stack
		$sitemap_taxonomies = array();
		foreach($wp_taxonomies as $taxonomy) {
			if ( !empty( $wds_options['taxonomies-'.$taxonomy->name.'-not_in_sitemap'] ) )
				continue;

			// Skip link and nav categories
			if ($taxonomy->name == 'link_category' || $taxonomy->name == 'nav_menu')
				continue;

			$sitemap_taxonomies[] = $taxonomy->name;
		}
		$terms = get_terms( $sitemap_taxonomies, array('hide_empty' => true) );

		if ( $echo )
			echo sprintf( __( '%s taxonomy entries found.', count( $terms ) , 'wds') ) . '<br/>';

		foreach( $terms as $c ) {
			$url = array();

			if ( wds_get_term_meta( $c, $c->taxonomy, 'wds_noindex' ) )
				continue;

			$url['loc'] = wds_get_term_meta( $c, $c->taxonomy, 'wds_canonical' );
			if ( !$url['loc'] )
				$url['loc'] = get_term_link( $c, $c->taxonomy );

			if ($c->count > 10) {
				$url['pri'] = 0.6;
			} else if ($c->count > 3) {
				$url['pri'] = 0.4;
			} else {
				$url['pri'] = 0.2;
			}

			// Grab last modified date
			$sql = "SELECT MAX(p.post_date) AS lastmod
					FROM	$wpdb->posts AS p
					INNER JOIN $wpdb->term_relationships AS term_rel
					ON		term_rel.object_id = p.ID
					INNER JOIN $wpdb->term_taxonomy AS term_tax
					ON		term_tax.term_taxonomy_id = term_rel.term_taxonomy_id
					AND		term_tax.taxonomy = '$c->taxonomy'
					AND		term_tax.term_id = $c->term_id
					WHERE	p.post_status = 'publish'
					AND		p.post_password = ''";
			$url['mod'] = $wpdb->get_var( $sql );
			$url['chf'] = 'weekly';
			// echo '<pre>'.print_r($url,1).'</pre>';
			$stack[] = $url;
		}
		unset($terms);

		// Set-up XSL URL to a relative value.
		$plugin_host = parse_url(WDS_PLUGIN_URL, PHP_URL_HOST);
		$xsl_host = preg_replace('~' . preg_quote('http://' . $plugin_host . '/') . '~', '', WDS_PLUGIN_URL);
		if (is_multisite() && defined('SUBDOMAIN_INSTALL') && !SUBDOMAIN_INSTALL) {
			$xsl_host = '../' . $xsl_host;
		}

		/*$output = '<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="' . WDS_PLUGIN_URL . 'wds-sitemaps/xsl/xml-sitemap.xsl"?>'."\n";*/
		$output = '<?xml version="1.0" encoding="UTF-8"?><?xml-stylesheet type="text/xsl" href="' . $xsl_host . 'wds-sitemaps/xsl/xml-sitemap.xsl"?>'."\n";
		$output .= '<!-- ' . sprintf( __( 'XML Sitemap Generated by WPMU DEV SEO, containing %s' , 'wds'), count($stack) ) .' URLs -->'."\n";
		$output .= '<urlset xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd" xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
		if ($echo)
			echo __( 'Starting to generate output.' , 'wds') . '<br/><br/>';
		foreach ($stack as $url) {
			if (!isset($url['mod']))
				$url['mod'] = '';
			$output .= "\t<url>\n";
			$output .= "\t\t<loc>".$url['loc']."</loc>\n";
			$output .= "\t\t<lastmod>".$this->w3c_date($url['mod'])."</lastmod>\n";
			$output .= "\t\t<changefreq>".$url['chf']."</changefreq>\n";
			$output .= "\t\t<priority>".number_format($url['pri'],1)."</priority>\n";
			if (isset($url['images']) && count($url['images']) > 0) {
				foreach($url['images'] as $img) {
					$output .= "\t\t<image:image>\n";
					$output .= "\t\t\t<image:loc>".$this->xml_clean($img['src'])."</image:loc>\n";
					if ( isset($img['title']) )
						$output .= "\t\t\t<image:title>".$this->xml_clean($img['title'])."</image:title>\n";
					if ( isset($img['alt']) )
						$output .= "\t\t\t<image:caption>".$this->xml_clean($img['alt'])."</image:caption>\n";
					$output .= "\t\t</image:image>\n";
				}
			}
			$output .= "\t</url>\n";
		}
		$output .= '</urlset>';

		if ($this->write_sitemap( $sitemappath, $output ) && $echo)
			echo sprintf( __( '%1$s: <a href="%2$s">Sitemap</a> successfully (re-)generated.' , 'wds'), date( 'H:i' ), $sitemapurl ) . '<br/><br/>';
		else if ($echo)
			echo sprintf( __( '%1$s: <a href="%2$s">Something went wrong...</a>.' , 'wds'), date( 'H:i' ), $sitemapurl ) . '<br/><br/>';
	}
}

class WDS_XML_News_Sitemap extends WDS_XML_Sitemap_Base {

	function WDS_XML_News_Sitemap() {
		global $wds_echo, $wds_options;

		add_action( 'wds_settings', array(&$this, 'admin_panel' ), 10, 1 );

		if ( !isset($wds_options['enablexmlnewssitemap']) || !$wds_options['enablexmlnewssitemap'])
			return;

		add_filter('wds_metabox_entries',array(&$this, 'filter_meta_box_entries' ),10,1);

		if ( !isset($wds_options['newssitemappath']) || empty($wds_options['newssitemappath']) )
			return;

		$this->generate_sitemap( $wds_options['newssitemapurl'], $wds_options['newssitemappath'], $wds_echo );
		$this->ping_search_engines( $wds_options['newssitemapurl'], $wds_echo );
	}

	function filter_meta_box_entries( $mbs ) {
		$mbs['newssitemap-genre'] = array(
			'name' => 'newssitemap-genre',
			'type' => 'multiselect',
			'std' => 'blog',
			'title' => __( 'Google News Genre' , 'wds'),
			'description' => __( 'Genre to show in Google News Sitemap.' , 'wds'),
			'options' => array(
				'pressrelease' => __( 'Press Release', 'wds'),
				'satire' => __( 'Satire' , 'wds'),
				'blog' => __( 'Blog' , 'wds'),
				'oped' => __( 'Op-Ed' , 'wds'),
				'opinion' => __( 'Opinion' , 'wds'),
				'usergenerated' => __( 'User Generated' , 'wds'),
			),
		);
		return $mbs;
	}

	function generate_sitemap( $sitemapurl, $sitemappath, $echo = false) {

		global $wpdb, $wp_taxonomies, $wp_rewrite, $wds_options;

		// Set-up XSL URL to a relative value.
		$plugin_host = parse_url(WDS_PLUGIN_URL, PHP_URL_HOST);
		$xsl_host = preg_replace('~' . preg_quote('http://' . $plugin_host . '/') . '~', '', WDS_PLUGIN_URL);
		if (is_multisite() && defined('SUBDOMAIN_INSTALL') && !SUBDOMAIN_INSTALL) {
			$xsl_host = '../' . $xsl_host;
		}

		$output = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
		/*$output .= '<?xml-stylesheet type="text/xsl" href="' . WDS_PLUGIN_URL . 'wds-sitemaps/xsl/xml-news-sitemap.xsl"?>'."\n";*/
		$output .= '<?xml-stylesheet type="text/xsl" href="' . $xsl_host . 'wds-sitemaps/xsl/xml-news-sitemap.xsl"?>'."\n";
		$output .= '<!-- ' . __( 'XML NEWS Sitemap Generated by WPMU DEV SEO ' , 'wds') . ' -->'."\n";
		$output .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:n="http://www.google.com/schemas/sitemap-news/0.9">'."\n";
		if ($echo)
			echo __( 'Starting to generate output.' , 'wds') . '<br/><br/>';

		// Grab posts and pages and add to output
		$posts = $wpdb->get_results("SELECT ID, post_title, post_date FROM $wpdb->posts WHERE post_status = 'publish' AND post_type = 'post' ORDER BY post_date DESC LIMIT 1000");
		if ($echo) {
			echo sprintf( __( '%s posts and pages found.' , 'wds'), count($posts) ) . '<br/>';
		}

		foreach ($posts as $post) {
			if ( strpos(wds_get_value( 'meta-robots', $post->ID ), 'noindex' ) !== false )
				continue;

			$link 	 = get_permalink( $post->ID );
			$keywords = '';
			$tags 	 = get_the_terms( $post->ID, 'post_tag' );
			if ($tags) {
				foreach ($tags as $tag) {
					$keywords .= $tag->name.', ';
				}
			}
			$keywords = preg_replace('/, $/','',$keywords);
			$genre = wds_get_value("newssitemap-genre", $post->ID);
			if (is_array($genre))
				$genre = implode(",", $genre);
			$genre = preg_replace('/^none,?/','',$genre);

			$output .= "\t<url>\n";
			$output .= "\t\t<loc>".$link."</loc>\n";
			$output .= "\t\t<n:news>\n";
			$output .= "\t\t\t<n:publication>\n";
			$output .= "\t\t\t\t<n:name>".$wds_options['newssitemapname']."</n:name>\n";
			$output .= "\t\t\t\t<n:language>".substr(get_locale(),0,2)."</n:language>\n";
			$output .= "\t\t\t</n:publication>\n";
			$output .= "\t\t\t<n:genres>".$genre."</n:genres>\n";
			$output .= "\t\t\t<n:publication_date>".$this->w3c_date($post->post_date)."</n:publication_date>\n";
			$output .= "\t\t\t<n:title>".$this->xml_clean($post->post_title)."</n:title>\n";
			if (strlen($keywords) > 0)
				$output .= "\t\t\t<n:keywords>".$this->xml_clean($keywords)."</n:keywords>\n";
			$output .= "\t\t</n:news>\n";
			$output .= "\t</url>\n";
		}
		unset($posts);

		$output .= '</urlset>';

		if ($this->write_sitemap( $sitemappath, $output ) && $echo)
			echo sprintf( __( '%1$s: <a href="%2$s">News Sitemap</a> successfully (re-)generated.' , 'wds'), date( 'H:i' ), $sitemapurl ) . '<br/><br/>';
	}
}

function wds_xml_sitemap_init() {
	global $plugin_page;

	if( isset( $plugin_page ) && $plugin_page == 'wds_wizard' ) {
		$wds_xml = new WDS_XML_Sitemap();
		$wds_news_xml = new WDS_XML_News_Sitemap();
	}
}
add_action( 'admin_init', 'wds_xml_sitemap_init' );

function wds_sitemaps_read() {
	$sitemap_options = get_option( 'wds_sitemap_options' );

	//$wds_sitemap_request_uri = str_replace( $_SERVER['HTTP_HOST'], '', str_replace( 'http://', '', $sitemap_options['sitemapurl'] ) );
	$path = $sitemap_options['sitemappath'];

	//if( $wds_sitemap_request_uri == $_SERVER['REQUEST_URI'] ) {
	if (preg_match('~' . preg_quote('/sitemap.xml') . '$~', $_SERVER['REQUEST_URI'])) {
		if( file_exists( $path ) ) {
			header( 'Content-Type: text/xml' );
			readfile( $path );
			die;
		} else {
			$sitemap = new WDS_XML_Sitemap;
			$sitemap->generate_sitemap( $sitemap_options['sitemapurl'], $sitemap_options['sitemappath'] );
			if( file_exists( $path ) ) {
				header( 'Content-Type: text/xml' );
				readfile( $path );
				die;
			} else wp_die( __( 'The sitemap file was not found.' , 'wds') );
		}
	}
}
add_action( 'init', 'wds_sitemaps_read' );
