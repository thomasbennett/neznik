<?php

/* Category */
ob_start(); 

?>

<div class="category-headline">
    <p>Posts found in categories:<br />
    <?php the_category(' &amp; '); ?></p>
</div>

<?php

include('loop.php');

$content = ob_get_clean();
require('template.php');

?>
