<?php 

if (!function_exists('dynamic_sidebar') || !dynamic_sidebar('Sidebar')) :
    // If the Widget sidebar isn't hooked up stuff in here will show up
endif; 

if(isset($blog_loop)): 
    if(is_singular()) : 
        // adds related posts to single blog post page
        echo "<h2>Related Posts</h2>";
        the_related();
    else :
        // shows most popular posts (on homepage);
        echo "<h2>Popular Posts</h2>";
        popular_posts();

        if (function_exists('WPPP_show_popular_posts')) WPPP_show_popular_posts(); 
    endif;
endif;

?>
