<?php if(have_posts()): ?>
  <article>
  <?php while(have_posts()): ?>
    <?php the_post(); ?>
    <div class="published"><?php the_date(); ?></div>
    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
    <?php the_excerpt(); ?>
  <?php endwhile; ?>
  </article>
<?php endif; ?>
