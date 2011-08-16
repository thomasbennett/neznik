<?php require_once('wp-config.php'); ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
    <title>Development Theme<?php if(isset($pageTitle)){ wp_title(); } ?></title>
    <meta name="description" content="" />
    <meta name="keywords" content="" />
    <meta http-equiv="content-type" content="text/html;charset=UTF-8" />
    <meta name="author" content="Thomas Bennett" />

    <link rel="stylesheet" type="text/css" href="<?php bloginfo('template_directory') ?>/style.css" />
    <link rel="stylesheet" type="text/css" href="<?php bloginfo('template_directory') ?>/css/resets.css" />
    <link rel="stylesheet" type="text/css" href="<?php bloginfo('template_directory') ?>/css/global.css" />
    <link rel="stylesheet" type="text/css" href="<?php bloginfo('template_directory') ?>/css/fancybox.css" />
    <link rel="stylesheet" type="text/css" href="<?php bloginfo('template_directory') ?>/css/main.css" />

    <?php if(is_singular()) wp_enqueue_script('comment-reply'); ?>
    <?php wp_enqueue_script('jquery'); ?>

    <?php wp_head(); ?>

    <?php include('head-files.php'); ?>
	<script type="text/javascript" src="<?php bloginfo('template_directory') ?>/js/jquery.fancybox-1.3.4.pack.js"></script>
	<script type="text/javascript" src="<?php bloginfo('template_directory'); ?>/js/init.js"></script>

    <style>html, * html { padding: 0 !important } </style>
</head>

<body>
    <div id="container">
        <a href="/"><h1 id="logo"><?php bloginfo('name'); ?></h1></a>
		<div id="search-bar" class="right">
			<?php get_search_form(); ?>
		</div>

        <div id="content">
            <?php echo $content ?>
        </div>

        <div id="sidebar">
            <?php if(isset($pageTitle)): ?>
                <?php get_sidebar($pageTitle); ?>
            <?php else: ?>
                <?php get_sidebar(); ?>
            <?php endif; ?>
        </div>
        <div class="clear"></div>
    </div>

    <div id="footer">
        <?php include('footer.php') ?>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
