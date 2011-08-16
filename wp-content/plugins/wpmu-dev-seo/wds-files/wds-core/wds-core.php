<?php
/**
 * wds_get_value(), wds_replace_vars(), wds_get_term_meta()
 * inspired by WordPress SEO by Joost de Valk (http://yoast.com/wordpress/seo/).
 */

function wds_get_value ($val, $post_id=false) {
	if (!$post_id) {
		global $post;
		$post_id = isset($post) ? $post->ID : false;
	}
	if (!$post_id) return false;

	$custom = get_post_custom($post_id);
	return ( !empty($custom['_wds_'.$val][0]) ) ?
		maybe_unserialize($custom['_wds_'.$val][0])
		:
		false
	;
}

function wds_set_value ($meta, $val, $post_id) {
	update_post_meta($post_id, "_wds_{$meta}", $val);
}

function wds_value ($val, $filter=false) {
	$val = wds_get_value($val);
	$val = $filter ? apply_filters('the_content', $val) : $val;
	echo $val;
}

function get_wds_options () {
	if( is_multisite() && WDS_SITEWIDE == true ) {
		return array_merge(
			(array) get_site_option( 'wds_settings_options' ),
			(array) get_site_option( 'wds_autolinks_options' ),
			(array) get_site_option( 'wds_onpage_options' ),
			//(array) get_site_option( 'wds_sitemaps_options' ), // Removed plural
			(array) get_site_option( 'wds_sitemap_options' ), // Added singular
			(array) get_site_option( 'wds_seomoz_options' )
		);
	} else if (is_multisite() && !WDS_SITEWIDE) {
		$settings = (array) (wds_is_allowed_tab('wds_settings') ? get_option('wds_settings_options') : get_site_option('wds_settings_options'));
		$autolinks = (array) (wds_is_allowed_tab('wds_autolinks') ? get_option('wds_autolinks_options') : get_site_option('wds_autolinks_options'));
		$onpage = (array) (wds_is_allowed_tab('wds_onpage') ? get_option('wds_onpage_options') : get_site_option('wds_onpage_options'));
		$sitemap = (array) (wds_is_allowed_tab('wds_sitemap') ? get_option('wds_sitemap_options') : get_site_option('wds_sitemap_options'));
		$seomoz = (array) (wds_is_allowed_tab('wds_seomoz') ? get_option('wds_seomoz_options') : get_site_option('wds_seomoz_options'));
		return array_merge(
			$settings,
			$autolinks,
			$onpage,
			$sitemap,
			$seomoz
		);
	} else {
		return array_merge(
			(array) get_option( 'wds_settings_options' ),
			(array) get_option( 'wds_autolinks_options' ),
			(array) get_option( 'wds_onpage_options' ),
			//(array) get_option( 'wds_sitemaps_options' ), // Removed plural
			(array) get_option( 'wds_sitemap_options' ), // Added singular
			(array) get_option( 'wds_seomoz_options' )
		);
	}
}

function wds_replace_vars ($string, $args) {
	global $wp_query;

	$defaults = array(
		'ID' => '',
		'name' => '',
		'post_author' => '',
		'post_content' => '',
		'post_date' => '',
		'post_excerpt' => '',
		'post_modified' => '',
		'post_title' => '',
		'taxonomy' => '',
	);

	$pagenum = get_query_var('paged');
	if ($pagenum === 0) {
		$pagenum = ($wp_query->max_num_pages > 1) ? 1 : '';
	}

	$r = wp_parse_args($args, $defaults);

	$replacements = array(
		'%%date%%' 					=> $r['post_date'],
		'%%title%%'					=> stripslashes($r['post_title']),
		'%%sitename%%'				=> get_bloginfo('name'),
		'%%sitedesc%%'				=> get_bloginfo('description'),
		'%%excerpt%%'				=> !empty($r['post_excerpt']) ? apply_filters('get_the_excerpt', $r['post_excerpt']) : substr(wp_trim_excerpt($r['post_content']), 0, 155),
		'%%excerpt_only%%'			=> $r['post_excerpt'],
		'%%category%%'				=> ( get_the_category_list('','',$r['ID']) != '' ) ? get_the_category_list('','',$r['ID']) : $r['name'],
		'%%category_description%%'	=> !empty($r['taxonomy']) ? trim(strip_tags(get_term_field( 'description', $r['term_id'], $r['taxonomy'] ))) : '',
		'%%tag_description%%'		=> !empty($r['taxonomy']) ? trim(strip_tags(get_term_field( 'description', $r['term_id'], $r['taxonomy'] ))) : '',
		'%%term_description%%'		=> !empty($r['taxonomy']) ? trim(strip_tags(get_term_field( 'description', $r['term_id'], $r['taxonomy'] ))) : '',
		'%%term_title%%'			=> $r['name'],
		'%%tag%%'					=> $r['name'],
		'%%modified%%'				=> $r['post_modified'],
		'%%id%%'					=> $r['ID'],
		'%%name%%'					=> get_the_author_meta('display_name', !empty($r['post_author']) ? $r['post_author'] : get_query_var('author')),
		'%%userid%%'				=> !empty($r['post_author']) ? $r['post_author'] : get_query_var('author'),
		'%%searchphrase%%'			=> esc_html(get_query_var('s')),
		'%%currenttime%%'			=> date('H:i'),
		'%%currentdate%%'			=> date('M jS Y'),
		'%%currentmonth%%'			=> date('F'),
		'%%currentyear%%'			=> date('Y'),
		'%%page%%'		 			=> ( get_query_var('paged') != 0 ) ? 'Page '.get_query_var('paged').' of '.$wp_query->max_num_pages : '',
		'%%pagetotal%%'	 			=> ( $wp_query->max_num_pages > 1 ) ? $wp_query->max_num_pages : '',
		'%%pagenumber%%' 			=> $pagenum,
		'%%caption%%'				=> $r['post_excerpt'],
	);

	foreach ($replacements as $var => $repl) {
		$string = str_replace($var, $repl, $string);
	}

	return $string;
}

function wds_get_term_meta ($term, $taxonomy, $meta) {
	$term = (is_object($term)) ? $term->term_id : get_term_by('slug', $term, $taxonomy);
	$tax_meta = get_option('wds_taxonomy_meta');

	return (isset($tax_meta[$taxonomy][$term][$meta])) ? $tax_meta[$taxonomy][$term][$meta] : false;
}

function wds_hide_blog_public_warning () {
	$wds_options = get_option('wds');
	$wds_options['blog_public_warning'] = 'nolonger';
	update_option('wds', $wds_options);
	echo 'nolonger';
}

function wds_set_option () {
	$option = $_POST['option'];
	$newval = $_POST['newval'];

	return update_option($option, $newval);
}

function wds_autogen_title_callback () {
	global $wds_options;
	$p = get_post($_POST['postid'], ARRAY_A);
	$p['post_title'] = stripslashes($_POST['curtitle']);
	if ($wds_options['title-'.$p['post_type']] != '')
		echo wds_replace_vars($wds_options['title-'.$p['post_type']], $p );
	else
		echo $p['post_title'] . ' - ' .get_bloginfo('name');
}

function wds_test_sitemap_callback ($return=false, $type='') {
	if (empty($type) && isset($_POST['type']))
		$type = $_POST['type'];

	global $wds_options;
	if (isset($_POST['sitemappath']) && !empty($_POST['sitemappath'])) {
		$fpath 	= $_POST['sitemappath'];
		$url	= $_POST['sitemapurl'];
	} else {
		$fpath 	= $wds_options[$type.'sitemappath'];
		$url 	= $wds_options[$type.'sitemapurl'];
	}

	$type = ucfirst($type).' ';

	$output = '';
	if (file_exists($fpath)) {
		if (is_writable($fpath)) {
			$output .= '<div class="correct">' . sprintf( __( 'XML %s Sitemap file found and writable.' , 'wds'), $type ) . '</div>';
			if (file_exists($fpath.'.gz') && !is_writable($fpath)) {
				$output .= '<div class="wrong">' . sprintf( __( 'XML %s Sitemap GZ file found but not writable, please make it writable!' , 'wds'), $type ) . '</div>';
			}
		} else {
			$output .= '<div class="wrong">' . sprintf( __( 'XML %s Sitemap file found but not writable, please make it writable!' , 'wds'), $type ) . '</div>';
		}
	} else {
		if ( @touch($fpath) ) {
			touch($fpath.'.gz');
			$output .= '<div class="correct">' . sprintf( __( 'XML %s Sitemap file created (but still empty).' , 'wds'), $type ) . '</div>';
		} else {
			$output .= '<div class="wrong">' . sprintf( __( 'XML %s Sitemap file not found and it could not be created, is the directory correct? And is it writable?' , 'wds'), $type ) . '</div>';
		}
	}
	$output .= '<br/>';

	$resp = wp_remote_get($url);

	if ( is_array($resp) && $resp['response']['code'] == 200 )
		$output .= sprintf( __( '<div class="correct">XML %s Sitemap URL correct.' , 'wds'), $type ) . '</div>';
	else
		$output .= sprintf( __( '<div class="wrong">XML %s Sitemap URL could not be verified, please make sure it\'s correct.' , 'wds'), $type ) . '</div>';
	if ($return)
		return $output;
	else
		echo $output;
}

function save_options_sitewide ($whitelist_options) {
	global $action;

	//if ( is_multisite() && WDS_SITEWIDE == true && 'update' == $action && isset( $_POST['option_page'] ) && in_array( $_POST['option_page'], array( 'wds_settings_options', 'wds_autolinks_options', 'wds_onpage_options', 'wds_sitemaps_options', 'wds_seomoz_options' ) ) ) { // Removed plural
	if ( is_multisite() && WDS_SITEWIDE == true && 'update' == $action && isset( $_POST['option_page'] ) && in_array( $_POST['option_page'], array( 'wds_settings_options', 'wds_autolinks_options', 'wds_onpage_options', 'wds_sitemap_options', 'wds_seomoz_options' ) ) ) { // Added singular
		global $option_page;

		$unregistered = false;
		check_admin_referer( $option_page . '-options' );

		if ( !isset( $whitelist_options[ $option_page ] ) )
			wp_die( __( 'Error: options page not found.' , 'wds') );

		$options = $whitelist_options[ $option_page ];

		if ( $options ) {
			foreach ( $options as $option ) {
				$option = trim($option);
				$value = null;
				if ( isset($_POST[$option]) )
					$value = $_POST[$option];
				if ( !is_array($value) )
					$value = trim($value);
				$value = stripslashes_deep($value);
				update_site_option($option, $value);
			}
		}

		if ( !count( get_settings_errors() ) )
			add_settings_error('general', 'settings_updated', __( 'Settings saved.' , 'wds'), 'updated');
		set_transient( 'settings_errors' , get_settings_errors(), 30 );

		$goback = add_query_arg( 'updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		die;
	}

	return $whitelist_options;
}
add_filter( 'whitelist_options', 'save_options_sitewide', 20 );

function wds_blog_template_settings ($and) {
	//$and .= " AND `option_name` != 'wds_sitemaps_options'"; // Removed plural
	$and .= " AND `option_name` != 'wds_sitemap_options'"; // Added singular
	return $and;
}
add_filter( 'blog_template_exclude_settings', 'wds_blog_template_settings' );

function wds_is_wizard_step ($step) {
	$current_page = isset( $_GET['page'] ) ? $_GET['page'] : '';
	$current_step = isset( $_GET['step'] ) ? $_GET['step'] : '1';

	if ( ( 'wds_wizard' == $current_page && $step == $current_step )
		|| ( 'wds_wizard' !== $current_page && '1' == $step )
		|| ( ! empty( $_REQUEST['_wp_http_referer'] ) && strpos( admin_url( "admin.php?page=wds_wizard&step=$step" ), remove_query_arg( 'updated', $_REQUEST['_wp_http_referer'] ) ) )
		|| ( ! empty( $_REQUEST['_wp_http_referer'] ) && strpos( network_admin_url( "admin.php?page=wds_wizard&step=$step" ), remove_query_arg( 'updated', $_REQUEST['_wp_http_referer'] ) ) ) ) {
		return true;
	} else {
		return false;
	}
}

/**
 * Checks user persmission level against minumum requirement
 * for displaying SEO metabox.
 *
 * @return bool
 */
function user_can_see_seo_metabox () {
	global $wds_options;
	return current_user_can($wds_options['seo_metabox_permission_level']);
}

/**
 * Checks user persmission level against minumum requirement
 * for displaying SEOmoz urlmetrics metabox.
 *
 * @return bool
 */
function user_can_see_urlmetrics_metabox () {
	global $wds_options;
	return current_user_can($wds_options['urlmetrics_metabox_permission_level']);
}

/**
 * Attempt to hide metaboxes by default by adding them to "hidden" array.
 * Metaboxes are still added to "Screen Options".
 * If user chooses to show/hide them, respect her decision.
 *
 * DEPRECATED as of version 1.0.9
 */
function wds_process_default_hidden_meta_boxes ($arg) {
	global $wds_options;
	$arg[] = 'wds-wds-meta-box';
	$arg[] = 'wds_seomoz_urlmetrics';
	return $arg;
}
//add_filter('default_hidden_meta_boxes', 'wds_process_default_hidden_meta_boxes');


/**
 * Hide ALL wds metaboxes.
 * Respect wishes for other metaboxes.
 * Still accessble from "Screen Options".
 */
function wds_hide_metaboxes ($arg) {
	// Hide WP defaults, if nothing else:
	if (empty($arg)) $arg = array('slugdiv', 'trackbacksdiv', 'postcustom', 'postexcerpt', 'commentstatusdiv', 'commentsdiv', 'authordiv', 'revisionsdiv');
	$arg[] = 'wds-wds-meta-box';
	$arg[] = 'wds_seomoz_urlmetrics';
	return $arg;
}
/**
 * Register metabox hiding for other boxes.
 */
function wds_register_metabox_hiding () {
	$post_types = get_post_types();
	foreach ($post_types as $type) add_filter('get_user_option_metaboxhidden_' . $type, 'wds_hide_metaboxes');

}
//add_action('admin_init', 'wds_register_metabox_hiding');

/**
 * Forces metaboxes to start collapsed.
 * It properly merges the WDS boxes with the rest of the users collapsed boxes.
 * For info on registering, see `register_metabox_collapsed_state`.
 */
function force_metabox_collapsed_state ($closed) {
	$closed = is_array($closed) ? $closed : array();
	return array_merge($closed, array(
		'wds-wds-meta-box', 'wds_seomoz_urlmetrics'
	));
}

/**
 * Registers WDS boxes state.
 * Collapsed state is tracked per post type.
 * This is why we have this separate hook to register state change processing.
 */
function register_metabox_collapsed_state () {
	global $post;
	if ($post && $post->post_type) {
		add_filter('get_user_option_closedpostboxes_' . $post->post_type, 'force_metabox_collapsed_state');
	}
}
add_filter('post_edit_form_tag', 'register_metabox_collapsed_state');


/**
 * Checks the page tab slug against permitted ones.
 * This applies only for multisite, non-sitewide setups.
 */
function wds_is_allowed_tab ($slug) {
	$blog_tabs = get_site_option('wds_blog_tabs');
	$blog_tabs = is_array($blog_tabs) ? $blog_tabs : array();
	$allowed = true;
	if (is_multisite() && !WDS_SITEWIDE) {
		$allowed = in_array($slug, $blog_tabs) ? true : false;
	}
	return $allowed;
}

/**
 * Checks if transient is stuck (has no expiry time) and
 * if so, removes it.
 */
function wds_kill_stuck_transient ($key) {
	global $_wp_using_ext_object_cache;
	if ($_wp_using_ext_object_cache) return true; // In object cache, nothing to do

	$key = "_transient_{$key}";
	$alloptions = wp_load_alloptions();
	// If option is in alloptions, it is autoloaded and thus has no timeout - kill it
	if (isset($alloptions[$key])) return delete_option($key);

	return true;
}