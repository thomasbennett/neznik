<?php
/**
 * Autolinks module contains code from SEO Smart Links plugin
 * (http://wordpress.org/extend/plugins/seo-automatic-links/ and http://www.prelovac.com/products/seo-smart-links/)
 * by Vladimir Prelovac (http://www.prelovac.com/).
 */

/**
 * WPMU DEV SEO Auto Links class
 *
 * @package WPMU DEV SEO
 * @since 0.1
 */

class WPS_AutoLinks {

	/* component settings */
	var $settings = array();

 	/* log file */
 	var $log_file;

 	/* whether there should be logging */
 	var $do_log;

	function WPS_AutoLinks() {
		$this->__construct();
	}

	function __construct() {
		global $wds_options;

		$this->settings = $wds_options;

	  	if ( !empty( $wds_options['post'] ) || !empty( $wds_options['page'] ) )
			add_filter('the_content',  array(&$this, 'the_content_filter'), 10);

		if ( !empty( $wds_options['comment'] ) )
			add_filter('comment_text',  array(&$this, 'comment_text_filter'), 10);

		add_action( 'create_category', array(&$this, 'delete_cache'));
		add_action( 'edit_category',  array(&$this,'delete_cache'));
		add_action( 'edit_post',  array(&$this,'delete_cache'));
		add_action( 'save_post',  array(&$this,'delete_cache'));
	}

	function process_text($text, $mode) {

		global $wpdb, $post;

		$options = $this->settings;

		$links = 0;

		if (is_feed() && !$options['allowfeed'])
			 return $text;
		elseif ( isset( $options['onlysingle'] ) && !( is_single() || is_page() ) )
			return $text;

		$arrignorepost = $this->explode_trim( ",", ( $options['ignorepost'] ) );

		if ( is_page( $arrignorepost ) || is_single( $arrignorepost ) ) {
			return $text;
		}

		if (!$mode) {
			if ($post->post_type == 'post' && !$options['post'])
				return $text;
			else if ($post->post_type == 'page' && !$options['page'])
				return $text;

			if ( ( $post->post_type == 'page' && empty( $options['pageself'] ) ) || ( $post->post_type == 'post' && empty( $options['pageself'] ) ) ) {
				$thistitle = isset( $options['casesens'] ) ? $post->post_title : strtolower( $post->post_title );
				$thisurl = trailingslashit( get_permalink( $post->ID ) );
			} else {
				$thistitle = '';
				$thisurl = '';
			}
		}


		$maxlinks = !empty( $options['maxlinks'] ) ? $options['maxlinks'] : 0;
		$maxsingle = !empty( $options['maxsingle'] ) ? $options['maxsingle'] : -1;
		$maxsingleurl = !empty( $options['maxsingleurl'] ) ? $options['maxsingleurl'] : 0;
		$minusage = !empty( $options['minusage'] ) ? $options['minusage'] : 1;

		$urls = array();

		$arrignore=$this->explode_trim(",", ($options['ignore']));
		if ( !empty( $options['minusage'] ) && $options['excludeheading'] == 'on' ) {
			$text = preg_replace('%(<h.*?>)(.*?)(</h.*?>)%sie', "'\\1'.$this->insertspecialchars('\\2').'\\3'", $text);
		}

		$reg_post		=	!empty( $options['casesens'] ) ? '/(?!(?:[^<\[]+[>\]]|[^>\]]+<\/a>))($name)/msU' : '/(?!(?:[^<\[]+[>\]]|[^>\]]+<\/a>))($name)/imsU';
		$reg			=	!empty( $options['casesens'] ) ? '/(?!(?:[^<\[]+[>\]]|[^>\]]+<\/a>))\b($name)\b/msU' : '/(?!(?:[^<\[]+[>\]]|[^>\]]+<\/a>))\b($name)\b/imsU';
		$strpos_fnc		=	!empty( $options['casesens'] ) ? 'strpos' : 'stripos';

		$text = " $text ";

		// insert custom keywords
		if ( !empty($options['customkey']) ) {
			$kw_array = array();

			foreach (explode("\n", $options['customkey']) as $line) {

				if( !empty( $options['customkey_preventduplicatelink'] ) ) {

					$line = trim($line);
					$lastDelimiterPos = strrpos($line, ',');
					$url = substr($line, $lastDelimiterPos + 1 );
					$keywords = substr($line, 0, $lastDelimiterPos);

					if(!empty($keywords) && !empty($url)){
						$kw_array[$keywords] = $url;
					}

					$keywords = '';
					$url = '';

				} else {

					$chunks = array_map('trim', explode(",", $line));
					$total_chuncks = count($chunks);
					if($total_chuncks > 2) {

						$i = 0;
						$url = $chunks[$total_chuncks-1];

						while($i < $total_chuncks-1) {
							if (!empty($chunks[$i]))
								$kw_array[$chunks[$i]] = $url;

							$i++;
						}

					} else {

						list($keyword, $url) = array_map('trim', explode(",", $line, 2));

						if (!empty($keyword))
							$kw_array[$keyword] = $url;

					}

				}

			}

			// Add htmlemtities and wordpress texturizer alternations for keywords
			$kw_array_tmp = $kw_array;
			foreach ($kw_array_tmp as $kw => $url) {
				$kw_entity = htmlspecialchars($kw, ENT_QUOTES);
				if (!isset($kw_array[$kw_entity])) $kw_array[$kw_entity] = $url;

				$kw_entity = wptexturize($kw);
				if (!isset($kw_array[$kw_entity])) $kw_array[$kw_entity] = $url;
			}

			// prevent duplicate links
			foreach ($kw_array as $name=>$url) {

				if ((!$maxlinks || ($links < $maxlinks)) && (trailingslashit($url)!=$thisurl) && !in_array( !empty( $options['casesens'] ) ? $name : strtolower($name), $arrignore) && (!$maxsingleurl || $urls[$url]<$maxsingleurl) ) {

					if ( !empty( $options['customkey_preventduplicatelink'] ) || $strpos_fnc($text, $name) !== false )
						$name= preg_quote($name, '/');

					if( !empty( $options['customkey_preventduplicatelink'] ) )
						$name = str_replace(',','|',$name);

					$replace="<a title=\"$1\" href=\"$url\">$1</a>";
					//$regexp=str_replace('$name', $name, $reg);
					$regexp=str_replace('$name', $name, $reg_post);
					$newtext = preg_replace($regexp, $replace, $text, $maxsingle);

					if ($newtext!=$text) {
						$links++;
						$text=$newtext;
						if (!isset($urls[$url]))
							$urls[$url]=1; else $urls[$url]++;
					}
				}
			}
		}
		// process posts
		if ( !empty( $options['lposts'] ) || !empty( $options['lpages'] ) ) {
			if ( !$posts = wp_cache_get( 'wds-autolinks-posts', 'wds-autolinks' ) ) {
				$query="SELECT post_title, ID, post_type FROM $wpdb->posts WHERE post_status = 'publish' AND LENGTH(post_title)>3 ORDER BY LENGTH(post_title) DESC LIMIT 2000";
				$posts = $wpdb->get_results($query);

				wp_cache_add( 'wds-autolinks', $posts, 'wds-autolinks', 86400 );
			}


			foreach ($posts as $postitem) {
				if ((($options['lposts'] && $postitem->post_type=='post') || ($options['lpages'] && $postitem->post_type=='page')) &&
				(!$maxlinks || ($links < $maxlinks))  && (($options['casesens'] ? $postitem->post_title : strtolower($postitem->post_title))!=$thistitle) && (!in_array( ($options['casesens'] ? $postitem->post_title : strtolower($postitem->post_title)), $arrignore))
				) {
						if ($strpos_fnc($text, $postitem->post_title) !== false) {
							$name = preg_quote($postitem->post_title, '/');

							$regexp=str_replace('$name', $name, $reg);


							$replace='<a title="$1" href="$$$url$$$">$1</a>';

							$newtext = preg_replace($regexp, $replace, $text, $maxsingle);
							if ($newtext!=$text) {
								$url = get_permalink($postitem->ID);
								if (!$maxsingleurl || $urls[$url]<$maxsingleurl) {
									$links++;
									$text=str_replace('$$$url$$$', $url, $newtext);

									if (!isset($urls[$url]))
										$urls[$url]=1;
									else
										$urls[$url]++;
								}
							}
						}
					}
			}
		}

		// process taxonomies
		if ( !empty( $options['ltaxonomies'] ) ) {

			foreach( $options['ltaxonomies'] as $taxonomy ) {
				if ( !$terms = wp_cache_get( "wds-autolinks-$taxonomy", 'wds-autolinks' ) ) {

					$query="SELECT $wpdb->terms.name, $wpdb->terms.term_id FROM $wpdb->terms LEFT JOIN $wpdb->term_taxonomy ON $wpdb->terms.term_id = $wpdb->term_taxonomy.term_id WHERE $wpdb->term_taxonomy.taxonomy = 'category'  AND LENGTH($wpdb->terms.name)>3 AND $wpdb->term_taxonomy.count >= $minusage ORDER BY LENGTH($wpdb->terms.name) DESC LIMIT 2000";
					$terms = $wpdb->get_results($query);

					wp_cache_add( "wds-autolinks-$term", $terms, 'wds-autolinks',86400 );
				}

				foreach ($terms as $term) {
					if ((!$maxlinks || ($links < $maxlinks)) &&  !in_array( $options['casesens'] ?  $term->name : strtolower($term->name), $arrignore)  )
					{
						if ($strpos_fnc($text, $term->name) !== false) {		// credit to Dominik Deobald
							$name = preg_quote($term->name, '/');
							$regexp = str_replace('$name', $name, $reg);	;
							$replace = '<a title="$1" href="$$$url$$$">$1</a>';

							$newtext = preg_replace($regexp, $replace, $text, $maxsingle);
							if ($newtext!=$text) {
								$url = (get_term_link($term->term_id));
								if (!$maxsingleurl || $urls[$url]<$maxsingleurl)
														{
								  $links++;
								  $text = str_replace('$$$url$$$', $url, $newtext);
								   if (!isset($urls[$url])) $urls[$url]=1; else $urls[$url]++;
								}
							}
						}
					}
				}
			}
		}

		// exclude headers
		if ( !empty( $options['excludeheading'] ) ) {
			//Here insert special characters
			$text = preg_replace('%(<h.*?>)(.*?)(</h.*?>)%sie', "'\\1'.$this->removespecialchars('\\2').'\\3'", $text);
			$text = stripslashes($text);
		}

		return trim( $text );

	}

	function filter_text($text) {
		$result = $this->process_text($text, 1);

		$options = $this->settings();
		$link = parse_url(site_url());
		$host = 'http://'.$link['host'];

		if ($options['blank'])
			$result = preg_replace('%<a(\s+.*?href=\S(?!' . $host . '))%i', '<a target="_blank"\\1', $result);

		if ($options['nofollow'])
			$result = preg_replace('%<a(\s+.*?href=\S(?!' . $host . '))%i', '<a rel="nofollow"\\1', $result);

		return $result;
	}

	function explode_trim($separator, $text) {
		$arr = explode($separator, $text);

		$ret = array();
		foreach($arr as $e)
		{
		  $ret[] = trim($e);
		}
		return $ret;
	}

	function delete_cache($id) {
		$options = $this->settings;

		if (is_array($options['ltaxonomies'])) {
			foreach($options['ltaxonomies'] as $taxonomy) {
				wp_cache_delete( "wds-autolinks-$taxonomy", 'wds-autolinks' );
			}
		}
	}

	function insertspecialchars($str) {
		$strarr = array();
		for($i=0; $i < strlen($str); $i++){
			array_push($strarr, $str{$i});
		}
		$str = implode("<!---->", $strarr);

		return $str;
	}

	function removespecialchars($str) {
		$strarr = explode("<!---->", $str);
		$str = implode("", $strarr);
		$str = stripslashes($str);
		return $str;
	}

	function comment_text_filter($text) {
		return $this->the_content_filter($text);
	}

	function the_content_filter($text) {
		$result = $this->process_text($text, 0);

		$options = $this->settings;
		$link = parse_url(get_bloginfo('wpurl'));
		$host = 'http://'.$link['host'];

		if ( !empty( $options['blanko'] ) )
			$result = preg_replace('%<a(\s+.*?href=\S(?!' . $host . '))%i', '<a target="_blank"\\1', $result);

		if ( !empty( $options['nofolo'] ) )
			$result = preg_replace('%<a(\s+.*?href=\S(?!' . $host . '))%i', '<a rel="nofollow"\\1', $result);
		return $result;
	}

	/* log messages */
	function log($message) {
		if ($this->do_log) {
			error_log(date('Y-m-d H:i:s') . " " . $message . "\n", 3, $this->log_file);
		}
	}

}

$autolinks = new WPS_AutoLinks();
