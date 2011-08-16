<?php 

ob_start();

// call in just 5 posts 
// all of which will serve as news
query_posts('posts_per_page=5');
include('loop.php');
wp_reset_query();

$content = ob_get_clean();
require('template.php'); 

?>
