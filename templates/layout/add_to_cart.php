<?php
	/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	$post_id = $post_id ?? MP_Global_Function::data_sanitize($_POST['post_id']);
	$backend_order = MP_Global_Function::data_sanitize($_POST['backend_order']);
	$link_wc_product = MP_Global_Function::get_post_info($post_id, 'link_wc_product');;
?>
	<div class="_dLayout_xs col_12 wbtm_form_submit_area">
		<div class="justifyBetween _alignCenter">
			<h4><?php echo WBTM_Translations::text_total(); ?> :</h4>
			<h4>
				<span class="wbtm_total _textTheme"><?php echo wc_price(0); ?></span>
			</h4>
			<?php if ($backend_order>0) { ?>
				<button type="submit" class="_navy_blueButton">
					<?php echo WBTM_Translations::text_book_now(); ?>
				</button>
			<?php } else { ?>
				<button type="submit" class="_navy_blueButton" name="add-to-cart" value="<?php echo esc_attr($link_wc_product); ?>">
					<?php echo WBTM_Translations::text_book_now(); ?>
				</button>
			<?php } ?>
		</div>
	</div>
<?php