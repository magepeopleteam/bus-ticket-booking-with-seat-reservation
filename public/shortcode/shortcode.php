<?php
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
class WBTM_Shortcode {
    public function __construct() {
        add_shortcode('wbtm-bus-list', array($this, 'wbtm_bus_list'));
        add_shortcode('wbtm-bus-search-form', array($this, 'wbtm_bus_search_form'));
        add_shortcode('wbtm-bus-search', array($this, 'wbtm_bus_search'));
    }

    // Shortcode for Showing Bus List....
    public function wbtm_bus_list($atts, $content = null) {
        global $wbtmmain, $wbtmpublic;
        $defaults = array(
            "cat" => "0",
            "show" => "20",
        );
        $params = shortcode_atts($defaults, $atts);
        $cat = $params['cat'];
        $show = $params['show'];
        ob_start();
        $paged = get_query_var("paged") ? get_query_var("paged") : 1;
        if ($cat > 0) {
            $args_search_qqq = array(
                'post_type' => array('wbtm_bus'),
                'paged' => $paged,
                'posts_per_page' => $show,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'wbtm_bus_cat',
                        'field' => 'term_id',
                        'terms' => $cat
                    )
                )

            );
        } else {
            $args_search_qqq = array(
                'post_type' => array('wbtm_bus'),
                'paged' => $paged,
                'posts_per_page' => $show

            );
        }
        $loop = new WP_Query($args_search_qqq);
        ?>
        <div class="wbtm-bus-list-sec">
            <?php
            while ($loop->have_posts()) {
                $loop->the_post();
                $wbtmpublic->wbtm_template_part('bus-list');
            }
            wp_reset_postdata();
            ?>
        </div>
        <div class="row">
            <div class="col-md-12"><?php
                $pargs = array(
                    "current" => $paged,
                    "total" => $loop->max_num_pages
                );
                echo "<div class='pagination-sec'>" . paginate_links($pargs) . "</div>";
                ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        return $content;
    }



    
// Shortcode for Showing Bus Search Form...........
    function wbtm_bus_search_form($atts, $content = null) {
        global $wbtmmain;

        $defaults = array(
            "style"      => '',
            "search-page" => ''
        );
        $params          = shortcode_atts($defaults, $atts);
        $style           = $params['style'];
        $global_target   = $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec') ? get_post_field( 'post_name', $wbtmmain->bus_get_option('search_target_page', 'label_setting_sec')) : 'bus-search-list' ;
        $target          = $params['search-page'] ? $params['search-page'] : $global_target;


        // echo get_post_field( 'post_name', 'bus-search-list');


        ob_start();
        if ($style == 'horizontal') {
            mage_bus_search_form_horizontal($target);

        } else {
            mage_bus_search_form($target);
        }
        $content = ob_get_clean();
        return $content;
    }


// Shortcode to Show Bus Search Result....... 
    function wbtm_bus_search($atts, $content = null) {
        $defaults = array(
            "cat" => "0",
            "style" => ''
        );
        $params = shortcode_atts($defaults, $atts);
        $cat = $params['cat'];
        $style = $params['style'];
        ob_start();

        do_action('woocommerce_before_single_product');
            mage_bus_search_page(); 
        do_action('wbtm_after_search_result_section',$params);
        return ob_get_clean();
    }
}

new WBTM_Shortcode();
