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
	return '<a href="'. get_permalink($post->ID) . '">Read the Rest...</a>';
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

?>
