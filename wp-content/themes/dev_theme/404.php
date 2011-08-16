<?php

/* 404 */
ob_start();

?>

<h1>Whoops...</h1>
<p>I've searched and searched but I can't find the page you're looking for. Try again?</p>

<?php

get_search_form();
include('loop.php');

$content = ob_get_clean(); 
require('template.php');

?>
