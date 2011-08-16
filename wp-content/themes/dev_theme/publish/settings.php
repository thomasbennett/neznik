<?php 

/* Custom Loop Options */

// if is a blog & not using loop.php
$blog_loop = null;

// use featured image in admin
$ft_image = null;

//include a slideshow
$slideshow = true;

if(isset($pageTitle)):
    query_posts('pagename=' . $pageTitle);
endif;

?>
