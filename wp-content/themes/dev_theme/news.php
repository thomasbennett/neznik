<?php
/* 
* Template name: News
*/

ob_start();
$paged = (get_query_var('paged')) ? get_query_var('paged') : 1;
query_posts(array(
  'orderby' => 'date',
  'order'   => 'DESC',
  'cat'     => '3',
  'paged'   => $paged
)); ?>

<?php if(have_posts()): ?>
  <article>
  <?php while(have_posts()): ?>
    <?php the_post(); ?>
    <div class="published"><?php the_date(); ?></div>
    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
    <?php the_content(); ?>
  <?php endwhile; ?>
  </article>
<?php endif; ?>

<?php $content = ob_get_clean();
require('template.php');
?>
