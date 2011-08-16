<?php

/* Archives */
ob_start();

include('loop.php');

$content = ob_get_clean();
require('template.php');

?>
