<?php
get_header();
the_post();
?>
<div class='wbtm_default_search_listing_page'>
<h2><?php the_title(); ?></h2>
<?php echo str_replace('[wbtm-bus-search]','',get_the_content()); ?>
<?php echo do_shortcode('[wbtm-bus-search]') ?>
</div>
<?php
get_footer();