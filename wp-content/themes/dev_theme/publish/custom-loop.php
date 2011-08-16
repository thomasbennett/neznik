            <?php include('settings.php'); ?>
            <?php $odd_or_even = 'odd'; ?> 
            <?php while(have_posts()) : the_post(); ?>
                <?php if(isset($blog_loop)): ?>
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
                <?php endif; ?>
                <?php if(isset($ft_image)): ?>
                    <div class="post-thumbnail">    
                        <?php the_post_thumbnail(); ?>  
                    </div>         
                <?php endif; ?>

                    <div class="entry">  
                        <?php if(isset($blog_loop)): ?>
                            <?php $odd_or_even = ('odd' == $odd_or_even) ? 'even' : 'odd'; ?>
                            <?php the_excerpt(); ?>
                        <?php endif; ?>

                        <?php if(isset($slideshow)): ?>
                            <div id="slideshow">
                                <?php $page = get_page_by_title('Main slideshow'); ?>
                                <div>
                                    <h1><?php echo get('heading',1,1,3,$page->ID) ?></h1>
                                    <span><?php echo get('ft_img_content',1,1,3,$page->ID) ?></span>
                                    <?php echo get_image('slideshow_image_featured_image',1,1,3,$page->ID); ?>
                                </div>
                                <div>
                                    <h1><?php echo get('heading2',1,1,3,$page->ID) ?></h1>
                                    <span><?php echo get('content2',1,1,3,$page->ID) ?></span> 
                                    <?php echo get_image('slideshow_image_image',1,1,3,$page->ID); ?>
                                </div>
                                <div>
                                    <h1><?php echo get('heading3',1,1,3,$page->ID) ?></h1>
                                    <span><?php echo get('content3',1,1,3,$page->ID) ?></span>
                                    <?php echo get_image('slideshow_image_image2',1,1,3,$page->ID); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if(isset($blog_loop)): ?>
                    <?php $counter++ ?>
                <?php endif; ?>
            <?php endwhile; ?>     

            <?php rewind_posts(); ?>
