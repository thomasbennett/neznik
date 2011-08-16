<?php
/**
 * WDS_OnPage::wds_title(), WDS_OnPage::wds_head(), WDS_OnPage::wds_metadesc()
 * inspired by WordPress SEO by Joost de Valk (http://yoast.com/wordpress/seo/).
 */


class WDS_OnPage {

	function WDS_OnPage() {
		global $wds_options;

		if (defined('SF_PREFIX') && function_exists('sf_get_option')) {
			add_action('template_redirect', array($this, 'postpone_for_simplepress'), 1);
			return;
		}
		$this->_init();
	}

	function _init () {
		global $wds_options;

		remove_action('wp_head', 'rel_canonical');

		add_action('wp_head', array(&$this, 'wds_head'), 10, 1);
		add_filter('wp_title', array(&$this, 'wds_title'), 10, 3); // wp_title isn't enough. We'll do it anyway: suspenders and belt approach.
		add_action('template_redirect', array($this, 'wds_start_title_buffer')); // Buffer the header output and process it instead.

		add_filter('bp_page_title', array(&$this, 'wds_title'), 10, 3); // This should now work with BuddyPress as well.

		add_action('wp',array(&$this,'wds_page_redirect'),99,1);
	}

	/**
	 * Can't fully handle SimplePress installs properly.
	 * For non-forum pages, do our thing all the way.
	 * For forum pages, do nothing.
	 */
	function postpone_for_simplepress () {
		global $wp_query;
		if ((int)sf_get_option('sfpage') != $wp_query->post->ID) {
			$this->_init();
		}
	}

	/**
	 * Starts buffering the header.
	 * The buffer output will be used to replace the title.
	 */
	function wds_start_title_buffer () {
		ob_start(array($this, 'wds_process_title_buffer'));
	}

	/**
	 * Stops buffering the output - the title should now be in the buffer.
	 */
	function wds_stop_title_buffer () {
		if (function_exists('ob_list_handlers')) {
			$active_handlers = ob_list_handlers();
		} else {
			$active_handlers = array();
		}
		if (count($active_handlers) > 0 && preg_match('/::wds_process_title_buffer$/', trim($active_handlers[count($active_handlers) - 1]))) {
			ob_end_flush();
		}
	}

	/**
	 * Handles the title buffer.
	 * Replaces the title with what we get from the old wds_title method.
	 * If we get nothing from it, do nothing.
	 */
	function wds_process_title_buffer ($head) {
		$title_rx = '<title[^>]*?>.*?' . preg_quote('</title>');
		$head_rx = '<head [^>]*? >';
		$head = preg_replace('/\n/', '__WDS_NL__', $head);
		$title = $this->wds_title('');
		$head = ($title && preg_match("~$head_rx~ix", $head)) ? // Make sure we're replacing TITLE that's actually in the HEAD
			preg_replace("~{$title_rx}~i", "<title>{$title}</title>", $head)
			:
			$head
		;
		return preg_replace('/__WDS_NL__/', "\n", $head);
	}

	function wds_title( $title, $sep = '', $seplocation = '', $postid = '' ) {
		global $post, $wp_query;
		if ( empty($post) && is_singular() ) {
			$post = get_post($postid);
		}

		global $wds_options;

		if ( is_home() && 'posts' == get_option('show_on_front') ) {
			$title = wds_replace_vars($wds_options['title-home'], (array) $post );
		} else if ( is_home() && 'posts' != get_option('show_on_front') ) {
			$post = get_post(get_option('page_for_posts'));
			$fixed_title = wds_get_value('title');
			if ( $fixed_title ) {
				$title = $fixed_title;
			} else if (isset($wds_options['title-'.$post->post_type]) && !empty($wds_options['title-'.$post->post_type]) ) {
				$title = wds_replace_vars($wds_options['title-'.$post->post_type], (array) $post );
			}
		} else if ( is_singular() ) {
			$fixed_title = wds_get_value('title');
			if ( $fixed_title ) {
				$title = $fixed_title;
			} else if (isset($wds_options['title-'.$post->post_type]) && !empty($wds_options['title-'.$post->post_type]) ) {
				$title = wds_replace_vars($wds_options['title-'.$post->post_type], (array) $post );
			}
		} else if ( is_category() || is_tag() || is_tax() ) {
			$term = $wp_query->get_queried_object();
			$title = wds_get_term_meta( $term, $term->taxonomy, 'wds_title' );
			if ( !$title && isset($wds_options['title-'.$term->taxonomy]) && !empty($wds_options['title-'.$term->taxonomy]) )
				$title = wds_replace_vars($wds_options['title-'.$term->taxonomy], (array) $term );
		} else if ( is_search() && isset($wds_options['title-search']) && !empty($wds_options['title-search']) ) {
			$title = wds_replace_vars($wds_options['title-search'], (array) $wp_query->get_queried_object() );
		} else if ( is_author() ) {
			$author_id = get_query_var('author');
			$title = get_the_author_meta('wds_title', $author_id);
			if ( empty($title) && isset($wds_options['title-author']) && !empty($wds_options['title-author']) ) {
				$title = wds_replace_vars($wds_options['title-author'], array() );
			}
		} else if ( is_archive() && isset($wds_options['title-archive']) && !empty($wds_options['title-archive']) ) {
			$title = wds_replace_vars($wds_options['title-archive'], array('post_title' => $title) );
		} else if ( is_404() && isset($wds_options['title-404']) && !empty($wds_options['title-404']) ) {
			$title = wds_replace_vars($wds_options['title-404'], array('post_title' => $title) );
		}

		return esc_html( strip_tags( stripslashes( $title ) ) );
	}

	function wds_head() {
		global $wds_options;
		global $wp_query, $paged;

		$this->wds_stop_title_buffer(); // STOP processing the buffer.

		$robots = '';

		$this->wds_metadesc();
		$this->wds_meta_keywords();

		// Set decent canonicals for homepage, singulars and taxonomy pages
		if ( wds_get_value('canonical') && wds_get_value('canonical') != '' ) {
			echo "\t".'<link rel="canonical" href="'.wds_get_value('canonical').'" />'."\n";
		} else {
			if (is_singular()) {
				echo "\t";
				rel_canonical();
			} else {
				$canonical = '';
				if ( is_front_page() ) {
					$canonical = get_bloginfo('url').'/';
				} else if ( is_tax() || is_tag() || is_category() ) {
					$term = $wp_query->get_queried_object();
					$canonical = wds_get_term_meta( $term, $term->taxonomy, 'wds_canonical' );
					$canonical = $canonical	? $canonical : get_term_link( $term, $term->taxonomy );
				}

				//only show id not error object
				if ($canonical && !is_wp_error($canonical)) {
					if ($paged && !is_wp_error($paged))
						$canonical .= 'page/'.$paged.'/';

					echo "\t".'<link rel="canonical" href="'.$canonical.'" />'."\n";
				}
			}
		}

		if (is_singular()) {
			$robots .= wds_get_value('meta-robots-noindex') ? 'noindex,' : 'index,';
			$robots .= wds_get_value('meta-robots-nofollow') ? 'nofollow' : 'follow';
			if ( wds_get_value('meta-robots-adv') && wds_get_value('meta-robots-adv') != 'none' ) {
				$robots .= ','.wds_get_value('meta-robots-adv');
			}
		} else {
			if ( isset($term) && is_object($term) ) {
				if ( wds_get_term_meta( $term, $term->taxonomy, 'wds_noindex' ) )
					$robots .= 'noindex,';
				else
					$robots .= 'index,';
				if ( wds_get_term_meta( $term, $term->taxonomy, 'wds_nofollow' ) )
					$robots .= 'nofollow';
				else
					$robots .= 'follow';
			}
		}

		// Clean up, index, follow is the default and doesn't need to be in output. All other combinations should be.
		if ($robots == 'index,follow')
			$robots = '';
		if (strpos($robots, 'index,follow,') === 0)
			$robots = str_replace('index,follow,','',$robots);

		foreach (array('noodp','noydir','noarchive','nosnippet') as $robot) {
			if (isset($wds_options[$robot]) && $wds_options[$robot]) {
				if (!empty($robots) && substr($robots, -1) != ',')
					$robots .= ',';
				$robots .= $robot;
			}
		}

		if ($robots != '') {
			$robots = rtrim($robots,',');
			echo "\t".'<meta name="robots" content="'.$robots.'"/>'."\n";
		}
	}

	function wds_metadesc() {
		if ( !is_admin() ) {
			global $post, $wp_query;
			global $wds_options;

			if (is_singular()) {
				$metadesc = wds_get_value('metadesc');
				if ($metadesc == '' || !$metadesc) {
					$metadesc = wds_replace_vars($wds_options['metadesc-'.$post->post_type], (array) $post );
				}
			} else {
				if ( is_home() && 'posts' == get_option('show_on_front') && isset($wds_options['metadesc-home']) ) {
					$metadesc = wds_replace_vars($wds_options['metadesc-home'], array() );
				} else if ( is_home() && 'posts' != get_option('show_on_front') ) {
					$post = get_post( get_option('page_for_posts') );
					$metadesc = wds_get_value('metadesc');
					if ( ($metadesc == '' || !$metadesc) && isset($wds_options['metadesc-'.$post->post_type]) ) {
						$metadesc = wds_replace_vars($wds_options['metadesc-'.$post->post_type], (array) $post );
					}
				} else if ( is_category() || is_tag() || is_tax() ) {
					$term = $wp_query->get_queried_object();

					$metadesc = wds_get_term_meta( $term, $term->taxonomy, 'wds_desc' );
					if ( !$metadesc && isset($wds_options['metadesc-'.$term->taxonomy])) {
						$metadesc = wds_replace_vars($wds_options['metadesc-'.$term->taxonomy], (array) $term );
					}
				} else if ( is_author() ) {
					$author_id = get_query_var('author');
					$metadesc = get_the_author_meta('wds_metadesc', $author_id);
				}
			}

			if (!empty($metadesc))
				echo "\t".'<meta name="description" content="'. esc_attr( strip_tags( stripslashes( $metadesc ) ) ).'" />'."\n";
		}
	}

	/**
	* Output meta keywords, if any.
	*/
	function wds_meta_keywords () {
		if (is_admin()) return;
		global $post;
		global $wds_options;
		$metakey = is_singular() ? wds_get_value('keywords') : false;
		$use_tags = is_singular() ? wds_get_value('tags_to_keywords') : false;
		$metakey = $use_tags ? $this->_tags_to_keywords($metakey) : $metakey;
		if ($metakey) echo "\t".'<meta name="keywords" content="'. esc_attr(stripslashes($metakey)).'" />'."\n";
	}

	/**
	 * Merges keywords (if any) and tags (if any) into one keyword string.
	 * Returned string is checked for duplicates.
	 *
	 * @access private
	 * @return mixed Keyword string if we found anything, false otherwise.
	 */
	function _tags_to_keywords ($kws) {
		$kw_array = $kws ? explode(',', trim($kws)) : array();
		$kw_array = is_array($kw_array) ? $kw_array : array();
		$kw_array = array_map('trim', $kw_array);

		$tags = array();
		$raw_tags = get_the_tags();
		if ($raw_tags) foreach($raw_tags as $tag) {
			$tags[] = $tag->name;
		}
		$result = array_filter(array_unique(array_merge($kw_array, $tags)));
		return count($result) ? join(',', $result) : false;
	}

	function wds_page_redirect( $input ) {
		global $post;
		if ($post && $redir = wds_get_value('redirect', $post->ID)) {
			wp_redirect( $redir, 301 );
			exit;
		}
	}
}

$wds_onpage = new WDS_OnPage;