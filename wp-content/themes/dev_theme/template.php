<?php include_once('wp-config.php'); ?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
  <meta charset="utf-8">
  <title><?php bloginfo('name'); wp_title(); ?></title>

  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <meta name="author" content="Thomas Bennett" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" type="text/css" href="<?php bloginfo('template_directory') ?>/style.css" />

  <?php if(is_singular()) wp_enqueue_script('comment-reply'); ?>
  <?php wp_enqueue_script('jquery'); ?>

  <?php get_header() ?>
  <script src="<?php bloginfo('template_directory') ?>/js/libs/modernizr-1.7.min.js"></script>
</head>

<body>
  <div id="container">
    <header>
      <a href="/"><h1 id="logo"><?php bloginfo('name'); ?></h1></a>
      <nav>
          
      </nav>
    </header>

    <div id="content">
      <?php echo $content ?>
    </div>

    <aside>
      <?php get_sidebar(); ?>
    </aside>
    <div class="clear"></div>
  </div>

  <footer>
    <?php get_footer() ?>
  </footer>

  <script src="<?php bloginfo('template_directory') ?>/js/plugins.js"></script>
  <script src="<?php bloginfo('template_directory') ?>/js/script.js"></script>

  <!--[if lt IE 7 ]>
    <script src="<?php bloginfo('template_directory') ?>/js/libs/dd_belatedpng.js"></script>
    <script>DD_belatedPNG.fix("img, .png_bg"); // Fix any <img> or .png_bg bg-images. Also, please read goo.gl/mZiyb </script>
  <![endif]-->
</body>
</html>
