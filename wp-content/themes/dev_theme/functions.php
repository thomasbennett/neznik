<?php
/**
 * @package WordPress
 * @subpackage TGB_Development_Theme
**/

// Add featured image to posts
add_theme_support('post-thumbnails');

// Defines the Excerpt
function new_excerpt_length($length) {
	return 100;
}
add_filter('excerpt_length', 'new_excerpt_length');

// Gives the excerpt a Read More link
function new_excerpt_more($more) {
    global $post;
	return '... <a href="'. get_permalink($post->ID) . '">Read More</a>';
}
add_filter('excerpt_more', 'new_excerpt_more');

//  Adds Custom menu ability
add_action('init', 'register_custom_menu');

// Allow users to add their own Menu
function register_custom_menu() {
    register_nav_menu('custom_menu', __('Custom Menu'));
}

// Dynamic Widgetized Sidebars
if (function_exists('register_sidebar')) {
   widgets_array(); 
}

function widgets_array()
{
	$widgetized_areas = array(
		'Sidebar' => array(
      'admin_menu_order'  => 100,
			'args' => array (
				'name'          => 'Sidebar',
				'id'            => 'sidebar-widget',
        'description'   => 'What ya put here will show up in your sidebar.',
        'before_title'  => '<h2>',
        'after_title'   => '</h2>',
				'before_widget' => '',
				'after_widget'  => '',
      ),
    ),
    'Custom' => array(
      'admin_menu_order'  => 200,
      'args' => array(
        'name'          => 'Custom',
        'id'            => 'custom-sidebar',
        'description'   => 'Sidebar for a custom page.',
        'before_title'  => '<h2>',
        'after_title'   => '</h2>',
        'before_widget' => '',
        'after_widget'  => '',
      )
    )
  );

	return apply_filters('widgetized_areas', $widgetized_areas);
}

$widgetized_areas = widgets_array();

if ( !function_exists('register_sidebars') )
    return;

foreach ($widgetized_areas as $key => $value) {
    register_sidebar($widgetized_areas[$key]['args']);
}

// Default comments template (use DISQUS)
if ( ! function_exists( 'twentyten_comment' ) ) :
/**
 * Template for comments and pingbacks.
 *
 * To override this walker in a child theme without modifying the comments template
 * simply create your own twentyten_comment(), and that function will be used instead.
 *
 * Used as a callback by wp_list_comments() for displaying the comments.
 *
 * @since Twenty Ten 1.0
 */
    function twentyten_comment( $comment, $args, $depth ) {
        $GLOBALS['comment'] = $comment;
        switch ( $comment->comment_type ) :
        case '' :
            ?>
                <li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
                <div id="comment-<?php comment_ID(); ?>">
                <div class="comment-author vcard">
                <?php echo get_avatar( $comment, 40 ); ?>
                <?php printf( __( '%s <span class="says">says:</span>', 'twentyten' ), sprintf( '<cite class="fn">%s</cite>', get_comment_author_link() ) ); ?>
                </div><!-- .comment-author .vcard -->
                <?php if ( $comment->comment_approved == '0' ) : ?>
                <em><?php _e( 'Your comment is awaiting moderation.', 'twentyten' ); ?></em>
                <br />
                <?php endif; ?>

                <div class="comment-meta commentmetadata"><a href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
                <?php
                /* translators: 1: date, 2: time */
                printf( __( '%1$s at %2$s', 'twentyten' ), get_comment_date(),  get_comment_time() ); ?></a><?php edit_comment_link( __( '(Edit)', 'twentyten' ), ' ' );
            ?>
                </div><!-- .comment-meta .commentmetadata -->

                <div class="comment-body"><?php comment_text(); ?></div>

                <div class="reply">
                <?php comment_reply_link( array_merge( $args, array( 'depth' => $depth, 'max_depth' => $args['max_depth'] ) ) ); ?>
                </div><!-- .reply -->
                </div><!-- #comment-##  -->

                <?php
                break;
        case 'pingback'  :
        case 'trackback' :
            ?>
                <li class="post pingback">
                <p><?php _e( 'Pingback:', 'twentyten' ); ?> <?php comment_author_link(); ?><?php edit_comment_link( __('(Edit)', 'twentyten'), ' ' ); ?></p>
                <?php
                break;
            endswitch;
    }
endif;

function clear() {
    $clear = "<div class='clear'></div>";
    echo $clear;
}

/* clean up wp-head */
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_generator');

remove_filter('the_content', 'wptexturize');
remove_filter('comment_text', 'wptexturize');

// add tags to meta keywords 
function tags_to_keywords(){
  global $post;
  if(is_single() || is_page()){ 
    $tags = wp_get_post_tags($post->ID); 
    foreach($tags as $tag){ 
      $tag_array[] = $tag->name; 
    }
    $tag_string = implode(', ',$tag_array); 
    if($tag_string !== ''){
        echo "<meta name='keywords' content='".$tag_string."' />\r\n";
    }
  }
}

// Add tags_to_keywords to wp_head function
add_action('wp_head','tags_to_keywords'); 

// add except as description
function excerpt_to_description(){
  global $post;
  if(is_single() || is_page()){ 
    $all_post_content = wp_get_single_post($post->ID); 
    $excerpt = substr($all_post_content->post_content, 0, 100).' [...]'; 
    echo "<meta name='description' content='".$excerpt."' />\r\n"; 
  }
  else{ 
    echo "<meta name='description' content='".get_bloginfo('description')."' />\r\n"; 
  }
}
add_action('wp_head','excerpt_to_description');

// show admin bar only for admins
if (!current_user_can('manage_options')) {
  add_filter('show_admin_bar', '__return_false');
}

/*/////////////////////////////////////////////////////////////////////*/
/* ///////////////       CUSTOM SLIDESHOW      ////////////////////////*/
/*/////////////////////////////////////////////////////////////////////*/

//  create the custom post type
add_action('init', 'post_type_before_after');
function post_type_before_after()
{
  $labels = array(
    'name' => _x('Slideshow', 'post type general name'),
    'singular_name' => _x('Slideshow', 'post type singular name'),
    'add_new' => _x('Add New', 'custom_slideshow'),
    'add_new_item' => __('Add New Slide')
 
  );

  $args = array(
    'labels' => $labels,
    'public' => true,
    'publicly_queryable' => true,
    'show_ui' => true,
    'query_var' => true,
    'rewrite' => true,
    'capability_type' => 'post',
    'hierarchical' => true,
    'menu_position' => null,
    'supports' => array('title'));

  register_post_type('custom_slideshow',$args);

}
$prefix = 'sic_';
 
$meta_box = array(
	'id' => 'my-meta-box',
	'title' => 'Slideshow',
	'page' => 'custom_slideshow',
	'context' => 'normal',
	'priority' => 'high',
	'fields' => array(
		array(
			'name' => 'Image',
			'desc' => 'Select an Image',
			'id'   => 'upload_image',
			'type' => 'text',
			'std'  => ''
		),
    array(
			'name' => '',
			'desc' => 'Select an Image',
			'id'   => 'upload_image_button',
			'type' => 'button',
			'std'  => 'Browse'
		),
	)
);
 
add_action('admin_menu', 'tgb_add_box');
 
// Add meta box
function tgb_add_box() {
	global $meta_box;
 
	add_meta_box($meta_box['id'], $meta_box['title'], 'tgb_show_box', $meta_box['page'], $meta_box['context'], $meta_box['priority']);
}
 
// Callback function to show fields in meta box
function tgb_show_box() {
	global $meta_box, $post;
 
	// Use nonce for verification
	echo '<input type="hidden" name="tgb_meta_box_nonce" value="', wp_create_nonce(basename(__FILE__)), '" />';
 
	echo '<table class="form-table">';
 
	foreach ($meta_box['fields'] as $field) {
		// get current post meta data
		$meta = get_post_meta($post->ID, $field['id'], true);
 
		echo '<tr>',
				'<th style="width:20%"><label for="', $field['id'], '">', $field['name'], '</label></th>',
				'<td>';
		switch ($field['type']) {
 
      //If Text		
			case 'text':
				echo '<input type="text" name="', $field['id'], '" id="', $field['id'], '" value="', $meta ? $meta : $field['std'], '" size="30" style="width:97%" />',
					'<br />', $field['desc'];
				break;
 
      //If Text Area			
			case 'textarea':
				echo '<textarea name="', $field['id'], '" id="', $field['id'], '" cols="60" rows="4" style="width:97%">', $meta ? $meta : $field['std'], '</textarea>',
					'<br />', $field['desc'];
				break;
 
        //If Button	
				case 'button':
				echo '<input type="button" name="', $field['id'], '" id="', $field['id'], '"value="', $meta ? $meta : $field['std'], '" />';
				break;
		}
		echo 	'<td>',
			'</tr>';
	}
	echo '</table>';
}
 
add_action('save_post', 'tgb_save_data');
 
// Save data from meta box
function tgb_save_data($post_id) {
	global $meta_box;
 
	// verify nonce
	if (!wp_verify_nonce($_POST['tgb_meta_box_nonce'], basename(__FILE__))) {
		return $post_id;
	}
 
	// check autosave
	if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
		return $post_id;
	}
 
	// check permissions
	if ('page' == $_POST['post_type']) {
		if (!current_user_can('edit_page', $post_id)) {
			return $post_id;
		}
	} elseif (!current_user_can('edit_post', $post_id)) {
		return $post_id;
	}
 
	foreach ($meta_box['fields'] as $field) {
		$old = get_post_meta($post_id, $field['id'], true);
		$new = $_POST[$field['id']];
 
		if ($new && $new != $old) {
			update_post_meta($post_id, $field['id'], $new);
		} elseif ('' == $new && $old) {
			delete_post_meta($post_id, $field['id'], $old);
		}
	}
}
 
function my_admin_scripts() {
  wp_enqueue_script('media-upload');
  wp_enqueue_script('thickbox');
  wp_register_script('my-upload', get_bloginfo('template_url') . '/functions/my-script.js', array('jquery','media-upload','thickbox'));
  wp_enqueue_script('my-upload');
}

function my_admin_styles() {
  wp_enqueue_style('thickbox');
}

add_action('admin_print_scripts', 'my_admin_scripts');
add_action('admin_print_styles', 'my_admin_styles');

?>
