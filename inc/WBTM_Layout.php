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
            add_action('wbtm_search_result', [$this, 'search_result'], 10, 4);
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
        public function search_result($start_route, $end_route, $date, $post_id = '') {
            require WBTM_Functions::template_path('layout/search_result.php');
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
            self::journey_date_picker($post_id, $start_route);
            die();
        }
        public function get_wbtm_return_date() {
            $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
            $end_route = MP_Global_Function::data_sanitize($_POST['end_route']);
            $j_date = $_POST['j_date'] ?? '';
            self::return_date_picker($post_id, $end_route, $j_date);
            die();
        }
        public function get_wbtm_bus_list() {
            $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
            $start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
            $end_route = MP_Global_Function::data_sanitize($_POST['end_route']);
            $j_date = $_POST['j_date'] ?? '';
            $r_date = $_POST['r_date'] ?? '';
            if ($start_route && $end_route && $j_date) { ?>
                <div class="_dLayout_dShadow_1_mT">
                    <?php WBTM_Layout::next_date_suggestion(); ?>
                    <?php WBTM_Layout::route_title(); ?>
                    <?php do_action('wbtm_search_result', $start_route, $end_route, $j_date,$post_id); ?>
                    <div class="wbtm_search_part _mT_xs">
                        <?php //mage_bus_search_list(false); ?>
                    </div>
                </div>
            <?php }
            if ($start_route && $end_route && $r_date) { ?>
                <div class="_dLayout_dShadow_1" id="wbtm_return_container">
                    <h4 class="textCenter"><?php echo WBTM_Translations::text_return_trip(); ?></h4>
                    <div class="divider"></div>
                    <?php WBTM_Layout::next_date_suggestion(true); ?>
                    <?php WBTM_Layout::route_title(true); ?>
                    <?php do_action('wbtm_search_result', $end_route, $start_route, $r_date); ?>
                    <div class="wbtm_search_part _mT_xs">
                        <?php //mage_bus_search_list(true); ?>
                    </div>
                </div>
            <?php }
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
                    ?>
                    <div class=" wbtm_registration_area ">
                        <form action="" method="post" class="">
                            <input type="hidden" name="wbtm_post_id" value="<?php echo esc_attr($post_id); ?>"/>
                            <input type="hidden" name='wbtm_start_point' value='<?php echo esc_attr($all_info['start_point']); ?>'/>
                            <input type="hidden" name='wbtm_start_time' value='<?php echo esc_attr($all_info['start_time']); ?>'/>
                            <input type="hidden" name='wbtm_bp_place' value='<?php echo esc_attr($all_info['bp']); ?>'/>
                            <input type="hidden" name='wbtm_bp_time' value='<?php echo esc_attr($all_info['bp_time']); ?>'/>
                            <input type="hidden" name='wbtm_dp_place' value='<?php echo esc_attr($all_info['dp']); ?>'/>
                            <input type="hidden" name='wbtm_dp_time' value='<?php echo esc_attr($all_info['dp_time']); ?>'/>
                            <?php do_action('wbtm_registration_form_inside', $post_id); ?>
                            <?php
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
        public static function next_date_suggestion($return = false) {
            $post_id = MP_Global_Function::data_sanitize($_POST['post_id']);
            $start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
            $end_route = MP_Global_Function::data_sanitize($_POST['end_route']);
            $route = $return ? $end_route : $start_route;
            $all_dates = WBTM_Functions::get_all_dates($post_id, $route);
            $total_date = sizeof($all_dates);
            if ($total_date > 0) {
                $j_date = $_POST['j_date'] ?? '';
                $r_date = $_POST['r_date'] ?? '';
                $active_date = $return ? $r_date : $j_date;
                if ($start_route && $end_route && $j_date) {
                    $key = array_search($active_date, $all_dates);
                    $start_key = $key > 2 ? $key - 2 : 0;
                    $start_key = $total_date - 3 <= $key ? max(0, $total_date - 5) : $start_key;
                    $all_dates = array_slice($all_dates, $start_key, 5);
                    ?>
                    <div class="buttonGroup bus-next-date _equalChild_fullWidth">
                        <?php foreach ($all_dates as $date) { ?>
                            <?php $btn_class = strtotime($date) == strtotime($active_date) ? '_themeButton_textWhite' : '_mpBtn_bgLight_textTheme'; ?>
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
        public static function route_title($return = false) {
            $start_route = MP_Global_Function::data_sanitize($_POST['start_route']);
            $end_route = MP_Global_Function::data_sanitize($_POST['end_route']);
            $start = $return ? $end_route : $start_route;
            $end = $return ? $start_route : $end_route;
            $j_date = $_POST['j_date'] ?? '';
            $r_date = $_POST['r_date'] ?? '';
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
        public static function journey_date_picker($post_id = '', $start_route = '') {
            $date_format = MP_Global_Function::date_picker_format();
            $now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
            ?>
            <label class="fdColumn">
                <span><i class="fas fa-calendar-alt"></i> <?php echo WBTM_Translations::text_journey_date(); ?></span>
                <input type="hidden" name="j_date" value="" required/>
                <input id="wbtm_journey_date" type="text" value="" class="formControl " placeholder="<?php echo esc_attr($now); ?>" data-alert="<?php echo WBTM_Translations::text_select_route(); ?>" readonly required/>
            </label>
            <?php
            if ($start_route) {
                $all_dates = WBTM_Functions::get_all_dates($post_id, $start_route);
                do_action('mp_load_date_picker_js', '#wbtm_journey_date', $all_dates);
            }
        }
        public static function return_date_picker($post_id = '', $end_route = '', $j_date = '') {
            $date_format = MP_Global_Function::date_picker_format();
            $now = date_i18n($date_format, strtotime(current_time('Y-m-d')));
            ?>
            <label class="fdColumn">
                <span><i class="fas fa-calendar-alt"></i> <?php echo WBTM_Translations::text_return_date(); ?></span>
                <input type="hidden" name="r_date" value=""/>
                <input id="wbtm_return_date" type="text" value="" class="formControl" placeholder="<?php echo esc_attr($now); ?>" readonly/>
            </label>
            <?php
            if ($end_route && $j_date) {
                $all_dates = WBTM_Functions::get_all_dates($post_id, $end_route);
                if (sizeof($all_dates) > 0) {
                    $j_date = strtotime($j_date);
                    $date_list = [];
                    foreach ($all_dates as $date) {
                        if (strtotime($date) > $j_date) {
                            $date_list[] = $date;
                        }
                    }
                    do_action('mp_load_date_picker_js', '#wbtm_return_date', $date_list);
                }
            }
        }
        public static function msg($msg, $class = '_bgWarning') {
            ?>
            <div class="_dLayout_mZero <?php echo esc_attr($class); ?>">
                <h4 class="_textCenter_textBlack"><?php echo esc_html($msg); ?></h4>
            </div>
            <?php
        }
    }
    new WBTM_Layout();
}