<?php
/**
 * @package WordPress
 * @subpackage TGB_Development_Theme
 */

if(!empty($_SERVER['SCRIPT_FILENAME']) && 'comments.php' == basename($_SERVER['SCRIPT_FILENAME'])):
  die('You can not access this page directly!');
endif;

function alternate_rows($i){    
  if($i % 2) {        
    echo ' class="alt-comment"';    
  } else {        
    echo '';    
  }  
} 
?>

<?php if(!empty($post->post_password)) : ?>
  <?php if($_COOKIE['wp-postpass_' . COOKIEHASH] != $post->post_password) : ?>
    <p>This post is password protected. Enter the password to view comments.</p>
  <?php endif; ?>
<?php endif; ?>

<?php if($comments) : ?>
  <ul id="comments">
    <p class="comment-total"><?php comments_number('No comments', 'One comment', '% comments'); ?> </p> 
    <?php foreach($comments as $comment) : ?>
    <?php $i++; ?>
    <li<?php alternate_rows($i); ?> id="comment-<?php comment_ID(); ?>">
      <?php if ($comment->comment_approved == '0') : ?>
        <p>Your comment is awaiting approval.</p>
      <?php endif; ?>
      <p class="meta">
        <span class="comment-avatar left"><?php echo get_avatar(get_comment_author_email(), $size, $default_avatar); ?></span>
        <span class="comment-text"><?php comment_text(); ?></span>
        <span class="comment-meta"><?php comment_type(); ?> by <?php comment_author_link(); ?> on <?php comment_date(); ?> at <?php comment_time(); ?></span>
      </p>
    </li>
  <?php endforeach; ?>
</ul>
<?php else : ?>
	<p>No comments yet.</p>
<?php endif; ?>

<?php if(comments_open()) : ?>
	<?php if(get_option('comment_registration') && !$user_ID) : ?>
		<p>You must be <a href="<?php echo get_option('siteurl'); ?>/wp-login.php?redirect_to=<?php echo urlencode(get_permalink()); ?>">logged in</a> to post a comment.</p><?php else : ?>
		<form action="<?php echo get_option('siteurl'); ?>/wp-comments-post.php" method="post" id="commentform">
			<?php if($user_ID) : ?>
				<p>Logged in as <a href="<?php echo get_option('siteurl'); ?>/wp-admin/profile.php"><?php echo $user_identity; ?></a>. <a href="<?php echo get_option('siteurl'); ?>/wp-login.php?action=logout" title="Log out of this account">Log out &raquo;</a></p>
			<?php else : ?>
				<p>
          <label for="author"><small>Name: <?php if($req) echo "*"; ?></small></label>
          <input type="text" name="author" id="author" value="<?php echo $comment_author; ?>" size="22" tabindex="1" />
				</p>
				<p>
          <label for="email"><small>Email: <?php if($req) echo "*"; ?></small></label>
          <input type="text" name="email" id="email" value="<?php echo $comment_author_email; ?>" size="22" tabindex="2" />
        </p>
				<p>
				<label for="url"><small>Website:</small></label>
        <input type="text" name="url" id="url" value="<?php echo $comment_author_url; ?>" size="22" tabindex="3" />
        </p>
			<?php endif; ?>
			<p>
        <label class="left" for="comment"><small>Comment:</small></label>
        <textarea name="comment" id="comment" cols="50" rows="5" tabindex="4"></textarea>
      </p>
			<p><input name="submit" type="submit" id="submit" class="comment-submit" tabindex="5" value="Submit Comment" />
			<input type="hidden" name="comment_post_ID" value="<?php echo $id; ?>" /></p>
			<?php do_action('comment_form', $post->ID); ?>
		</form>
	<?php endif; ?>
<?php else : ?>
	<p>The comments are closed.</p>
<?php endif; ?>
