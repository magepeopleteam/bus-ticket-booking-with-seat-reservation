<?php
/*
* @Author 		engr.sumonazma@gmail.com
* Copyright: 	mage-people.com
*/
if (!defined('ABSPATH')) {
    die;
} // Cannot access pages directly.
if (!class_exists('WBTM_Layout')) {
    class WBTM_Layout {
        public function __construct() {
            add_action('wbtm_search_result', [$this, 'search_result'], 10, 9);
            /*********************/
            add_action('wp_ajax_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
            add_action('wp_ajax_nopriv_get_wbtm_dropping_point', [$this, 'get_wbtm_dropping_point']);
            /**************************/
            add_action('wp_ajax_get_wbtm_journey_date', [$this, 'get_wbtm_journey_date']);
            add_action('wp_ajax_nopriv_get_wbtm_journey_date', [$this, 'get_wbtm_journey_date']);
            /**************************/
            add_action('wp_ajax_get_wbtm_return_date', [$this, 'get_wbtm_return_date']);
            add_action('wp_ajax_nopriv_get_wbtm_return_date', [$this, 'get_wbtm_return_date']);
            /**************************/
            add_action('wp_ajax_get_wbtm_bus_list', [$this, 'get_wbtm_bus_list']);
            add_action('wp_ajax_nopriv_get_wbtm_bus_list', [$this, 'get_wbtm_bus_list']);
            /**************************/
            add_action('wp_ajax_get_wbtm_bus_details', [$this, 'get_wbtm_bus_details']);
            add_action('wp_ajax_nopriv_get_wbtm_bus_details', [$this, 'get_wbtm_bus_details']);
            /**************************/
        }
        public function search_result($start_route, $end_route, $date, $post_id = '',$style='',$btn_show='',$search_info=[], $journey_type='', $left_filter_show='') {
            if($style=='flix'){
                require WBTM_Functions::template_path('layout/search_result_flix.php');
            }
            else{
                
                require WBTM_Functions::template_path('layout/search_result.php');
            }
        }
        public function get_wbtm_dropping_point() {
            $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
            $start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
            self::route_list($post_id, $start_route);
            die();
        }
        public function get_wbtm_journey_date() {
            $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
            $start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
            $end_route = MP_Global_Function::data_sanitize($_POST['end_route']);
            self::journey_date_picker($post_id, $start_route,$end_route);
            die();
        }
        public function get_wbtm_return_date() {
            $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
            $start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
            $end_route = MP_Global_Function::data_sanitize($_POST['end_route']);
            $j_date = $_POST['j_date'] ?? '';
            self::return_date_picker($post_id, $end_route,$start_route, $j_date);
            die();
        }
        public function get_wbtm_bus_list() {
            $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
            $start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
            $end_route = MP_Global_Function::data_sanitize($_POST['end_route']);
            $j_date = $_POST['j_date'] ?? '';
            $r_date = $_POST['r_date'] ?? '';
            $style = $_POST['style'] ?? '';
            $btn_show = $_POST['btn_show'] ?? '';
            $left_filter_show = $_POST['left_filter_show'] ?? '';
            $search_info['bus_start_route']=$start_route;
            $search_info['bus_end_route']=$end_route;
            $search_info['j_date']=$j_date;
            $search_info['r_date']=$r_date;
            self::wbtm_bus_list($post_id,$start_route,$end_route,$j_date,$r_date,$style,$btn_show,$search_info, $left_filter_show);
            die();
        }
        public function get_wbtm_bus_details() {
            $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
            $start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
            $end_route = MP_Global_Function::data_sanitize($_POST['end_route']);
            $date = $_POST['date'] ?? '';
            $seat_type = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_type_conf');
            if ($post_id > 0 && $start_route && $end_route && $date) {
                $all_info = WBTM_Functions::get_bus_all_info($post_id, $date, $start_route, $end_route);
                if ($all_info['available_seat'] > 0) {
                    $seat_infos = MP_Global_Function::get_post_info($post_id, 'wbtm_bus_seats_info', []);
                    $seat_row = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_rows', 0);
                    $seat_column = MP_Global_Function::get_post_info($post_id, 'wbtm_seat_cols', 0);
					$bus_start_time=$all_info['start_time'];
                    ?>
                    <div class="wbtm_registration_area _bgWhite mT">
                        <form action="" method="post" class="">
                            <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                            <input type="hidden" name='wbtm_start_point' value='<?php echo esc_attr($all_info['start_point']); ?>'/>
                            <input type="hidden" name='wbtm_start_time' value='<?php echo esc_attr($bus_start_time); ?>'/>
                            <input type="hidden" name='wbtm_bp_place' value='<?php echo esc_attr($all_info['bp']); ?>'/>
                            <input type="hidden" name='wbtm_bp_time' value='<?php echo esc_attr($all_info['bp_time']); ?>'/>
                            <input type="hidden" name='wbtm_dp_place' value='<?php echo esc_attr($all_info['dp']); ?>'/>
                            <input type="hidden" name='wbtm_dp_time' value='<?php echo esc_attr($all_info['dp_time']); ?>'/>
                            <input type="hidden" name='bus_start_route' value='<?php echo esc_attr(MP_Global_Function::data_sanitize($_POST['bus_start_route'])); ?>'/>
                            <input type="hidden" name='bus_end_route' value='<?php echo esc_attr(MP_Global_Function::data_sanitize($_POST['bus_end_route'])); ?>'/>
                            <input type="hidden" name='j_date' value='<?php echo esc_attr(MP_Global_Function::data_sanitize($_POST['j_date'])); ?>'/>
                            <input type="hidden" name='r_date' value='<?php echo esc_attr(MP_Global_Function::data_sanitize($_POST['r_date'])); ?>'/>

                            <?php
wp_nonce_field('wbtm_form_nonce', 'wbtm_form_nonce');
                            if ($seat_type == 'wbtm_seat_plan' && sizeof($seat_infos) > 0 && $seat_row > 0 && $seat_column > 0) {
                                require WBTM_Functions::template_path('layout/registration_seat_plan.php');
                            } else {
                                require WBTM_Functions::template_path('layout/registration_without_seat_plan.php');
                            }
                            ?>
                        </form>
                        <?php do_action('wbtm_attendee_form_hidden', $post_id); ?>
                    </div>
                    <?php
                } else {
                    WBTM_Layout::msg(WBTM_Translations::text_no_seat());
                }
            }
            die();
        }
        public static function wbtm_bus_list($post_id,$start_route,$end_route,$j_date,$r_date,$style='',$btn_show='',$search_info=[], $left_filter_show='') {
            
            if ($start_route && $end_route && $j_date) { ?>
                <div class="_dLayout_dShadow_1_mT">
                    <?php self::next_date_suggestion($post_id,$start_route,$end_route,$j_date,$r_date); ?>
                    <?php self::route_title($start_route,$end_route,$j_date,$r_date); ?>
                    <?php do_action('wbtm_search_result', $start_route, $end_route, $j_date,$post_id,$style,$btn_show,$search_info,'start_journey', $left_filter_show); ?>
                </div>
            <?php }
              
            if ($post_id==0 && $start_route && $end_route && $r_date) { ?>
            
                <div class="_dLayout_dShadow_1" id="wbtm_return_container">
                    <h4 class="textCenter"><?php echo WBTM_Translations::text_return_trip(); ?></h4>
                    <div class="divider"></div>
                    <?php self::next_date_suggestion($post_id,$start_route,$end_route,$j_date,$r_date,true); ?>
                    <?php self::route_title($start_route,$end_route,$j_date,$r_date,true); ?>
                    <?php do_action('wbtm_search_result', $end_route, $start_route, $r_date,'',$style,$btn_show,$search_info,'return_journey', $left_filter_show); ?>
                </div>
            <?php }
        }
        public static function route_list($post_id = 0, $start_route = '') {
            $all_routes = WBTM_Functions::get_bus_route($post_id, $start_route);
            if (sizeof($all_routes) > 0) {
                ?>
                <ul class="mp_input_select_list">
                    <?php foreach ($all_routes as $route) { ?>
                        <li data-value="<?php echo esc_attr($route); ?>">
                            <span class="fas fa-map-marker"></span><?php echo esc_html($route); ?>
                        </li>
                    <?php } ?>
                </ul>
                <?php
            }
        }
        public static function next_date_suggestion($post_id,$start_route,$end_route,$j_date,$r_date,$return = false) {
            $route = $return ? $end_route : $start_route;
            $all_dates = WBTM_Functions::get_all_dates($post_id, $route);
            $total_date = sizeof($all_dates);
            if ($total_date > 0) {
                $active_date = $return ? $r_date : $j_date;
                if ($start_route && $end_route && $j_date) {
                    $key = array_search($active_date, $all_dates);
                    $start_key = $key > 2 ? $key - 2 : 0;
                    $start_key = $total_date - 3 <= $key ? max(0, $total_date - 5) : $start_key;
                    $all_dates = array_slice($all_dates, $start_key, 5);
                    ?>
                    <div class="buttonGroup _hidden_xs_equalChild_fullWidth">
                        <?php foreach ($all_dates as $date) { ?>
                            <?php $btn_class = strtotime($date) == strtotime($active_date) ? '_dButton_textWhite' : '_mpBtn_bgLight_textTheme'; ?>
                            <button type="button" class="wbtm_next_date <?php echo esc_attr($btn_class); ?>" data-date="<?php echo esc_attr($date); ?>">
                                <?php echo MP_Global_Function::date_format($date); ?>
                            </button>
                        <?php } ?>
                    </div>
                    <?php
                }
            } else {
                WBTM_Layout::msg(WBTM_Translations::text_bus_close_msg());
            }
        }
        public static function route_title($start_route,$end_route,$j_date,$r_date,$return = false) {
            $start = $return ? $end_route : $start_route;
            $end = $return ? $start_route : $end_route;
            $date = $return ? $r_date : $j_date;
            if ($date) {
                ?>
                <div class="buttonGroup _mT_xs_equalChild_fullWidth">
                    <button type="button" class="_mpBtn_h4">
                        <?php echo esc_html($start); ?>
                        <span class="fas fa-long-arrow-alt-right _mLR_xs"></span>
                        <?php echo esc_html($end); ?>
                    </button>
                    <button type="button" class="_mpBtn_h4">
                        <?php echo MP_Global_Function::date_format($date); ?>
                    </button>
                </div>
                <?php
            }
        }
        public static function journey_date_picker($post_id = '', $start_route = '',$end_route='',$date='') {
            $date_format = MP_Global_Function::date_picker_format();
            $now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
            $hidden_date = $date ? date('Y-m-d', strtotime($date)) : '';
            $visible_date = $date ? date_i18n($date_format, strtotime($date)) : '';
            ?>
            <label class="fdColumn">
                <span><i class="fas fa-calendar-alt"></i> <?php echo WBTM_Translations::text_journey_date(); ?></span>
                <input type="hidden" name="j_date" value="<?php echo esc_attr($hidden_date); ?>" required/>
                <input id="wbtm_journey_date" type="text" value="<?php echo esc_attr($visible_date); ?>" class="formControl " placeholder="<?php echo esc_attr($now); ?>" data-alert="<?php echo WBTM_Translations::text_select_route(); ?>" readonly required/>
            </label>
            <?php
            if ($start_route) {
                $all_dates = WBTM_Functions::get_all_dates($post_id, $start_route,$end_route);
                do_action('mp_load_date_picker_js', '#wbtm_journey_date', $all_dates);
            }
        }
        public static function return_date_picker($post_id = '', $end_route = '',$start_route='', $j_date = '',$date='') {
            $date_format = MP_Global_Function::date_picker_format();
            $now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
            $hidden_date = $date ? date('Y-m-d', strtotime($date)) : '';
            $visible_date = $date ? date_i18n($date_format, strtotime($date)) : '';
            ?>
            <label class="fdColumn">
                <span><i class="fas fa-calendar-alt"></i> <?php echo WBTM_Translations::text_return_date(); ?></span>
                <input type="hidden" name="r_date" value="<?php echo esc_attr($hidden_date); ?>"/>
                <input id="wbtm_return_date" type="text" value="<?php echo esc_attr($visible_date); ?>" class="formControl" placeholder="<?php echo esc_attr($now); ?>" readonly/>
            </label>
            <?php
            if ($end_route && $j_date) {
                $all_dates = WBTM_Functions::get_all_dates($post_id, $end_route,$start_route);
                if (sizeof($all_dates) > 0) {
                    $j_date = strtotime($j_date);
                    $date_list = [];
                    foreach ($all_dates as $date) {
                        if (strtotime($date) >= $j_date) {
                            $date_list[] = $date;
                        }
                    }
                    do_action('mp_load_date_picker_js', '#wbtm_return_date', $date_list);
                }
            }
        }
        public static function msg($msg, $class = '') {
            ?>
            <div class="_mZero_textCenter <?php echo esc_attr($class); ?>">
                <label class="_textTheme"><?php echo esc_html($msg); ?></label>
            </div>
            <?php
        }
        
        public static function trigger_view_seat_details(){
            ?>
            <script type="text/javascript">
                var get_wbtm_bus_details = document.getElementById("get_wbtm_bus_details");
                get_wbtm_bus_details.click();
            </script>
            <?php 
        }

    }
    new WBTM_Layout();
}