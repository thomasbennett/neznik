        <?php $odd_or_even = 'odd'; ?> 
        <?php while(have_posts()) : the_post(); ?>
			<div class="post <?php if(!is_singular()) { echo $odd_or_even; } ?>">
            	<h1>
					 <a href="<?php the_permalink() ?>" 
						rel="bookmark" 
						title="Permanent Link to <?php the_title_attribute(); ?>">
						<?php the_title(); ?>
					</a>
				</h1>
				<div class="date">Published <?php echo the_date('F d, Y g:ia'); ?></div>

            	<div class="divider"></div>

            	<div class="post-thumbnail">    
                	<?php the_post_thumbnail(); ?>  
            	</div>         
		        
            	<div class="entry">  
                    <?php $odd_or_even = ('odd' == $odd_or_even) ? 'even' : 'odd'; ?>
                    <?php the_content('Read More &raquo;'); ?>
            	</div>
  			</div>
        <?php $counter++ ?>
        <?php endwhile; ?>     
