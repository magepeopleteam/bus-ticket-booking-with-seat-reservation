<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (wp_is_block_theme()) { ?>
	<!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo('charset'); ?>">
		<?php
			$block_content = do_blocks('
		<!-- wp:group {"layout":{"type":"constrained"}} -->
		<div class="wp-block-group">
		<!-- wp:post-content /-->
		</div>
		<!-- /wp:group -->');
			wp_head(); ?>
	</head>
	<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<div class="wp-site-blocks">
		<header class="wp-block-template-part site-header">
			<?php block_header_area(); ?>
		</header>
	</div>
	<?php
}
else {
	get_header();
	the_post();
}
	do_action('wbtm_before_search_listing_page');
?>
	<div class='wbtm_default_search_listing_page'>
		<h2><?php the_title(); ?></h2>
		<?php echo str_replace('[wbtm-bus-search]', '', get_the_content()); ?>
		<?php echo do_shortcode('[wbtm-bus-search]') ?>
	</div>
<?php
	/**
	 * Hook: wbtm_after_search_listing_page.
	 */
	do_action('wbtm_after_search_listing_page');
	if (wp_is_block_theme()) {
// Code for block themes goes here.
		?>
		<footer class="wp-block-template-part">
			<?php block_footer_area(); ?>
		</footer>
		<?php wp_footer(); ?>
		</body>
		<?php
	}
	else {
		get_footer();
	}