<?php
/* Single */
ob_start();

include('loop.php');

get_sidebar();
clear();

if(isset($blog)):
    comments_template();
endif;

$content = ob_get_clean();
require('template.php');
?>
