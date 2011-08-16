<?php ob_start(); ?>

<?php include('custom-loop.php') ?>

<?php $content = ob_get_clean(); ?>
<?php require('template.php'); ?>
