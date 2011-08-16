<?php

class WDS_Metabox {

	function WDS_Metabox() {

		// WPSC integration
		add_action('wpsc_edit_product', array(&$this, 'rebuild_sitemap'));
		add_action('wpsc_rate_product', array(&$this, 'rebuild_sitemap'));

		add_action('admin_menu', array(&$this, 'wds_create_meta_box'));
		add_action('save_post', array(&$this, 'wds_save_postdata'));

		add_action('save_post', array(&$this, 'update_video_meta'));

		add_filter('manage_page_posts_columns', array(&$this, 'wds_page_title_column_heading'), 10, 1);
		add_filter('manage_post_posts_columns', array(&$this, 'wds_page_title_column_heading'), 10, 1);
		add_action('manage_pages_custom_column', array(&$this, 'wds_page_title_column_content'), 10, 2);
		add_action('manage_posts_custom_column', array(&$this, 'wds_page_title_column_content'), 10, 2);

		add_action('admin_print_scripts-post.php', array($this, 'js_load_scripts'));
		add_action('admin_print_scripts-post-new.php', array($this, 'js_load_scripts'));
	}

	function js_load_scripts () {
		wp_enqueue_script('wds_metabox_counter', WDS_PLUGIN_URL . '/js/wds-metabox-counter.js');
	}


	function wds_meta_boxes() {
		global $post;

		echo '<script type="text/javascript">var lang = "'.substr(get_locale(),0,2).'";</script>';

		$date = '';
		if ($post->post_type == 'post') {
			if ( isset($post->post_date) )
				$date = date('M j, Y', strtotime($post->post_date));
			else
				$date = date('M j, Y');
		}

		echo '<table class="widefat">';

		$title = wds_get_value('title');
		if (empty($title))
			$title = $post->post_title;
		if (empty($title))
			$title = "temp title";

		$desc = wds_get_value('metadesc');
		if (empty($desc))
			$desc = substr(strip_tags($post->post_content), 0, 130).' ...';
		if (empty($desc))
			$desc = 'temp description';

		$slug = $post->post_name;
		if (empty($slug))
			$slug = sanitize_title($title);

?>
	<tr>
		<th><label>Preview:</label></th>
		<td>
<?php
		$video = wds_get_value('video_meta',$post->ID);
		if ( $video && $video != 'none' ) {
?>
			<div id="snippet" class="video">
				<h4 style="margin:0;font-weight:normal;"><a class="title" href="#"><?php echo $title; ?></a></h4>
				<div style="margin:5px 10px 10px 0;width:82px;height:62px;float:left;">
					<img style="border: 1px solid blue;padding: 1px;width:80px;height:60px;" src="<?php echo $video['thumbnail_loc']; ?>"/>
					<div style="margin-top:-23px;margin-right:4px;text-align:right"><img src="http://www.google.com/images/icons/sectionized_ui/play_c.gif" alt="" border="0" height="20" style="-moz-opacity:.88;filter:alpha(opacity=88);opacity:.88" width="20"></div>
				</div>
				<div style="float:left;width:440px;">
					<p style="color:#767676;font-size:13px;line-height:15px;"><?php echo number_format($video['duration']/60); ?> mins - <?php echo $date; ?></p>
					<p style="color:#000;font-size:13px;line-height:15px;" class="desc"><span><?php echo $desc; ?></span></p>
					<a href="#" class="url"><?php echo str_replace('http://','',get_bloginfo('url')).'/'.$slug.'/'; ?></a> - <a href="#" class="util">More videos &raquo;</a>
				</div>
			</div>

<?php
		} else {
			if (!empty($date))
				$date .= ' ... ';
?>
			<div id="snippet">
				<p><a style="color:#2200C1;font-weight:medium;font-size:16px;text-decoration:underline;" href="#"><?php echo $title; ?></a></p>
				<p style="font-size: 12px; color: #000; line-height: 15px;"><?php echo $date; ?><span><?php echo $desc ?></span></p>
				<p><a href="#" style="font-size: 13px; color: #282; line-height: 15px;" class="url"><?php echo str_replace('http://','',get_bloginfo('url')).'/'.$slug.'/'; ?></a> - <a href="#" class="util">Cached</a> - <a href="#" class="util">Similar</a></p>
			</div>
<?php } ?>
		</td>
	</tr>
<?php
		echo $this->show_title_row();
		echo $this->show_metadesc_row();
		echo $this->show_keywords_row();
		echo $this->show_robots_row();
		echo $this->show_canonical_row();
		echo $this->show_redirect_row();
		echo $this->show_sitemap_row();
		echo '</table>';
	}

	function wds_create_meta_box() {
		$show = user_can_see_seo_metabox();
		if ( function_exists('add_meta_box') ) {
			$metabox_title = is_multisite() ? __( 'WPMU DEV SEO' , 'wds') : 'WPMU DEV SEO'; // Show branding for singular installs.
			foreach (get_post_types() as $posttype) {
				if ($show) add_meta_box( 'wds-wds-meta-box', $metabox_title, array(&$this, 'wds_meta_boxes'), $posttype, 'normal', 'high' );
			}
		}
	}

	function wds_save_postdata( $post_id ) {
		if ($post_id == null || empty($_POST)) return;

		global $post;
		if (empty($post)) $post = get_post($post_id);

		if ('page' == $_POST['post_type'] && !current_user_can('edit_page', $post_id)) return $post_id;
		else if (!current_user_can( 'edit_post', $post_id )) return $post_id;

		foreach ($_POST as $key=>$value) {
			if (!preg_match('/^wds_/', $key)) continue;

			$id = "_{$key}";
			$data = $value;
			if (is_array($value)) $data = join(',', $value);

			if ($data) update_post_meta($post_id, $id, $data);
			else delete_post_meta($post_id, $id);
		}

		do_action('wds_saved_postdata');
		$this->rebuild_sitemap();
	}

	function update_video_meta($post_id, $post = null) {
		global $wds_options;
		if ( !$wds_options['enablexmlvideositemap'])
			return;

		if (!is_object($post))
			$post = get_post($post_id);

		if ( !wp_is_post_revision($post) ) {
			require_once WDS_PLUGIN_DIR.'/wds-sitemaps/wds-sitemaps.php';
			$wds_xml_base = new WDS_XML_Sitemap_Base();
			$wds_xml_base->update_video_meta($post);
		}
	}

	function rebuild_sitemap() {
		require_once WDS_PLUGIN_DIR.'/wds-sitemaps/wds-sitemaps.php';
	}

	function wds_page_title_column_heading( $columns ) {
		return array_merge(
			array_slice( $columns, 0, 2 ),
			array( 'page-title' => __( 'Title Tag' , 'wds') ),
			array_slice($columns, 2, 6),
			array( 'page-meta-robots' => __( 'Robots Meta' , 'wds') )
		);
	}

	function wds_page_title_column_content( $column_name, $id ) {
		if ( $column_name == 'page-title' ) {
			echo $this->wds_page_title($id);
		}
		if ( $column_name == 'page-meta-robots' ) {
			$meta_robots_arr = array(
				(wds_get_value( 'meta-robots-noindex', $id ) ? 'noindex' : 'index'),
				(wds_get_value( 'meta-robots-nofollow', $id ) ? 'nofollow' : 'follow')
			);
			$meta_robots = join(',', $meta_robots_arr);
			//$meta_robots = wds_get_value( 'meta-robots', $id );
			if ( empty($meta_robots) )
				$meta_robots = 'index,follow';
			echo ucwords( str_replace( ',', ', ', $meta_robots ) );
		}
	}

	function wds_page_title( $postid ) {
		$post = get_post($postid);
		$fixed_title = wds_get_value('title', $post->ID);
		if ($fixed_title) {
			return $fixed_title;
		} else {
			global $wds_options;
			if (!empty($wds_options['title-'.$post->post_type]))
				return wds_replace_vars($wds_options['title-'.$post->post_type], (array) $post );
			else
				return '';
		}
	}

/* ========== Display helpers ========== */

	function field_title ($str, $for) {
		return "<th valign='top'><label for='{$for}'>{$str}</label></th>";
	}
	function field_content ($str, $desc=false) {
		$desc = $desc ? "<p>$desc</p>" : '';
		return "<td valign='top'>{$str}\n{$desc}</td>";
	}

	function show_title_row () {
		$title = __('Title Tag' , 'wds');
		$desc = __('70 characters maximum' , 'wds');
		$value = esc_html(wds_get_value('title'));
		$field = "<input type='text' class='widefat' id='wds_title' name='wds_title' value='{$value}' class='wds' />";

		return '<tr>' .
			$this->field_title($title, 'wds_title') .
			$this->field_content($field, $desc) .
		'</tr>';
	}

	function show_metadesc_row () {
		$title = __('Meta Description' , 'wds');
		$desc = __('160 characters maximum' , 'wds');
		$value = esc_html(wds_get_value('metadesc'));
		$field = "<textarea rows='2' class='widefat' name='wds_metadesc' id='wds_metadesc' class='wds'>{$value}</textarea>";

		return '<tr>' .
			$this->field_title($title, 'wds_metadesc') .
			$this->field_content($field, $desc) .
		'</tr>';
	}

	function show_keywords_row () {
		$title = __('Meta keywords' , 'wds');
		$desc = __('Separate keywords with commas' , 'wds');
		$desc .= '<br />' . __('If you enable using tags, post tags will be merged in with any other keywords you enter in the text box.', 'wds');
		$value = esc_html(wds_get_value('keywords'));
		$checked = wds_get_value('tags_to_keywords') ? 'checked="checked"' : '';
		$field = "<input type='text' class='widefat' id='wds_keywords' name='wds_keywords' value='{$value}' class='wds' />";
		$field .= '<br /><label for="wds_tags_to_keywords">' . __('I want to use post tags in addition to my keywords', 'wds') . '</label> ' .
			"<input type='checkbox' name='wds_tags_to_keywords' id='wds_tags_to_keywords' value='1' {$checked} />";

		return '<tr>' .
			$this->field_title($title, 'wds_keywords') .
			$this->field_content($field, $desc) .
		'</tr>';
	}

	function show_robots_row () {
		// Index
		$ri_value = (int)wds_get_value('meta-robots-noindex');
		$robots_index = '<input type="radio" name="wds_meta-robots-noindex" id="wds_meta-robots-noindex-index" ' . (!$ri_value ? 'checked="checked"' : '') . ' value="0" /> ' .
			'<label for="wds_meta-robots-noindex-index">' . __( 'Index' , 'wds') . '</label>' .
			'<br />' .
			'<input type="radio" name="wds_meta-robots-noindex" id="wds_meta-robots-noindex-noindex" ' . ($ri_value ? 'checked="checked"' : '') . ' value="1" /> ' .
			'<label for="wds_meta-robots-noindex-noindex">' . __( 'Noindex' , 'wds') . '</label>'
		;
		$row_index = '<tr>' .
			$this->field_title( __('Index', 'wds'), 'wds_robots_follow' ) .
			$this->field_content($robots_index) .
		'</tr>';

		// Follow
		$rf_value = (int)wds_get_value('meta-robots-nofollow');
		$robots_follow = '<input type="radio" name="wds_meta-robots-nofollow" id="wds_meta-robots-nofollow-follow" ' . (!$rf_value ? 'checked="checked"' : '') . ' value="0" /> ' .
			'<label for="wds_meta-robots-nofollow-follow">' . __( 'Follow' , 'wds') . '</label>' .
			'<br />' .
			'<input type="radio" name="wds_meta-robots-nofollow" id="wds_meta-robots-nofollow-nofollow" ' . ($rf_value ? 'checked="checked"' : '') . ' value="1" /> ' .
			'<label for="wds_meta-robots-nofollow-nofollow">' . __( 'Nofollow' , 'wds') . '</label>'
		;
		$row_follow = '<tr>' .
			$this->field_title( __('Follow', 'wds'), 'wds_robots_follow' ) .
			$this->field_content($robots_follow) .
		'</tr>';

		// Advanced
		$adv_value = explode(',', wds_get_value('meta-robots-adv'));
		$advanced = array(
			"noodp" => __( 'NO ODP (Block Open Directory Project description of the page)' , 'wds'),
			"noydir" => __( 'NO YDIR (Don\'t display the Yahoo! Directory titles and abstracts)' , 'wds'),
			"noarchive" => __( 'No Archive' , 'wds'),
			"nosnippet" => __( 'No Snippet' , 'wds'),
		);
		$robots_advanced = '<select name="wds_meta-robots-adv[]" id="wds_meta-robots-adv" multiple="multiple" size="' . count($advanced) . '" style="height:' . count($advanced) * 1.9 . 'em;">';
		foreach ($advanced as $key => $label) {
			$robots_advanced .= "<option value='{$key}' " . (in_array($key, $adv_value) ? 'selected="selected"' : '') . ">{$label}</option>";
		}
		$robots_advanced .= '</select>';
		$row_advanced = '<tr>' .
			$this->field_title( __('Advanced', 'wds'), 'wds_meta-robots-adv' ) .
			$this->field_content($robots_advanced) .
		'</tr>';

		// Overall
		$title = __('Meta Robots' , 'wds');
		$content = "<table class='wds_subtable' broder='0'>{$row_index}\n{$row_follow}\n{$row_advanced}</table>";
		$desc = __('<code>meta</code> robots settings for this page.', 'wds');
		return '<tr>' .
			$this->field_title($title, 'wds-metadesc') .
			$this->field_content($content, $desc) .
		'</tr>';
	}

	function show_canonical_row () {
		$title = __('Canonical URL' , 'wds');
		$value = wds_get_value('canonical');
		$field = "<input type='text' id='wds_canonical' name='wds_canonical' value='{$value}' class='wds' />";
		return '<tr>' .
			$this->field_title($title, 'wds_canonical') .
			$this->field_content($field) .
		'</tr>';
	}

	function show_redirect_row () {
		$title = __('301 Redirect' , 'wds');
		$value = wds_get_value('redirect');
		$field = "<input type='text' id='wds_redirect' name='wds_redirect' value='{$value}' class='wds' />";
		return '<tr>' .
			$this->field_title($title, 'wds_redirect') .
			$this->field_content($field) .
		'</tr>';
	}

	function show_sitemap_row () {
		global $wds_options;
		if (empty($wds_options['enablexmlsitemap']) || !@$wds_options['enablexmlsitemap']) return '';

		$options = array(
			"-" => __( 'Automatic prioritization' , 'wds'),
			"1" => __( '1 - Highest priority' , 'wds'),
			"0.9" => "0.9",
			"0.8" => "0.8 - " . __( 'Default for first tier pages' , 'wds'),
			"0.7" => "0.7",
			"0.6" => "0.6 - " . __( 'Default for second tier pages and posts' , 'wds'),
			"0.5" => "0.5 - " . __( 'Medium priority' , 'wds'),
			"0.4" => "0.4",
			"0.3" => "0.3",
			"0.2" => "0.2",
			"0.1" => "0.1 - " . __( 'Lowest priority' , 'wds'),
		);
		$title = __('Sitemap Priority' , 'wds');
		$desc = __('The priority given to this page in the XML sitemap.' , 'wds');
		$value = wds_get_value('sitemap-prio');

		$field = "<select name='wds_sitemap-prio' id='wds_sitemap-prio'>";
		foreach ($options as $key=>$label) {
			$field .= "<option value='{$key}' " . (($key==$value) ? 'selected="selected"' : '') . ">{$label}</option>";
		}
		$field .= '</select>';

		return '<tr>' .
			$this->field_title($title, 'wds_redirect') .
			$this->field_content($field, $desc . var_export($value,1)) .
		'</tr>';
	}


}
$wds_metabox = new WDS_Metabox();
