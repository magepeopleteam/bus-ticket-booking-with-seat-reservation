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
	$post_id = get_the_id();
	do_action('wbtm_before_single_bus_search_page');
	do_action('woocommerce_before_single_product');
	//echo '<pre>';print_r($wp_roles->roles);echo '</pre>';
?>
	<div class="mpStyle wbtm_container">
		<?php require WBTM_Functions::template_path('layout/single_bus_details.php'); ?>
		<?php require WBTM_Functions::template_path('layout/search_form.php'); ?>
	</div>
<?php
	do_action('wbtm_after_single_bus_search_page');
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