<?php
get_header();
the_post();
/**
 * Hook: wbtm_before_search_listing_page.
 */
do_action('wbtm_before_search_listing_page');
?>
<div class='wbtm_default_search_listing_page hhhh'>
<h2><?php the_title(); ?></h2>
<?php echo str_replace('[wbtm-bus-search]','',get_the_content()); ?>
<?php echo do_shortcode('[wbtm-bus-search]') ?>
</div>
<?php
/**
 * Hook: wbtm_after_search_listing_page.
 */
do_action('wbtm_after_search_listing_page');
get_footer();