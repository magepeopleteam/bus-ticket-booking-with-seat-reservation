<?php
if (!defined('ABSPATH')) exit;  // if direct access

class ActiveDataShowClass extends CommonClass
{
    public function __construct()
    {

    }

    //next 6  date suggestion
   public function active_date_picker($singleBus, $post_id)
    {
        $settings = get_option('wbtm_bus_settings');
        $global_offdates = isset($settings['wbtm_bus_global_offdates']) ? $settings['wbtm_bus_global_offdates'] : [];
        $global_offdates_arr = array();
        if($global_offdates) {
            $global_offdates = str_replace(' ', '', $global_offdates); // all white space
            $global_offdates_arr = explode(',', $global_offdates);
        }

        $global_search_page_offdates = $global_offdates_arr ? implode(',', $global_offdates_arr) : ''; // Global search page

        $global_offdays = isset($settings['wbtm_bus_global_offdays']) ? $settings['wbtm_bus_global_offdays'] : [];

        if($singleBus){
            $wbtm_bus_on_dates = get_post_meta($post_id, 'wbtm_bus_on_dates', true) ? maybe_unserialize(get_post_meta($post_id, 'wbtm_bus_on_dates', true)) : '';
            $wbtm_offday_schedules = get_post_meta($post_id, 'wbtm_offday_schedule', true) ? get_post_meta($post_id, 'wbtm_offday_schedule', true) : [];
            $show_operational_on_day = get_post_meta($post_id, 'show_operational_on_day', true) ? get_post_meta($post_id, 'show_operational_on_day', true) : '';
            $show_off_day = get_post_meta($post_id, 'show_off_day', true) ? get_post_meta($post_id, 'show_off_day', true) : '';

            $alloffdays = array();
            foreach ($wbtm_offday_schedules as $wbtm_offday_schedule) {
                $alloffdays =  array_unique(array_merge($alloffdays, wbtm_displayDates($wbtm_offday_schedule['from_date'], $wbtm_offday_schedule['to_date'])));;
            }

            $all_offdates = array_merge($global_offdates_arr, $alloffdays);
            $off_particular_date = implode(',', $all_offdates);
            
            $all_off_days = '';
            $weekly_offday = get_post_meta($post_id, 'weekly_offday', true) ? get_post_meta($post_id, 'weekly_offday', true) : [];
            $merged_global_local_off_days = array_merge($global_offdays, $weekly_offday);
            if ($merged_global_local_off_days) {
                $all_off_days = implode(',', $merged_global_local_off_days);
            }

            echo '<input id="all_date_picker_info" data-single_bus="' . $singleBus . '"  data-enableDates="' . $wbtm_bus_on_dates . '" data-off_particular_date="' . $off_particular_date . '" data-weekly_offday="' . $all_off_days . '" data-enable_onday="' . $show_operational_on_day . '" data-enable_offday="' . $show_off_day . '" data-date_format="' . $this->convert_datepicker_dateformat() . '" type="hidden">';

        }else{
            

            if ($global_offdays) {
                $particular_offdays = implode(',', $global_offdays);
            } else {
                $particular_offdays = '';
            }

            echo '<input id="all_date_picker_info" data-single_bus="0" data-disableDates="' . $global_search_page_offdates . '" data-disableDays="' . $particular_offdays . '" data-date_format="' . $this->convert_datepicker_dateformat() . '" type="hidden" />';

        }

    }

    //next 6  date suggestion
    public function return_active_date_picker($singleBus, $post_id)
    {

        if($singleBus){
            $wbtm_bus_on_dates = get_post_meta($post_id, 'wbtm_bus_on_dates_return', true) ? maybe_unserialize(get_post_meta($post_id, 'wbtm_bus_on_dates_return', true)) : [];
            $wbtm_offday_schedules = get_post_meta($post_id, 'wbtm_offday_schedule_return', true) ? get_post_meta($post_id, 'wbtm_offday_schedule_return', true) : [];
            $show_operational_on_day = get_post_meta($post_id, 'return_show_operational_on_day', true) ? get_post_meta($post_id, 'return_show_operational_on_day', true) : '';
            $show_off_day = get_post_meta($post_id, 'return_show_off_day', true) ? get_post_meta($post_id, 'return_show_off_day', true) : '';

            if($wbtm_bus_on_dates){
                $wbtm_bus_on_dates_arr = explode(',', $wbtm_bus_on_dates);
                $onday = array();
                foreach ($wbtm_bus_on_dates_arr as $ondate) {
                    $onday[] = '"' . date('d-m-Y', strtotime($ondate)) . '"';
                }
                $on_particular_date = implode(',', $onday);
                $enableDates = '[' . $on_particular_date . ']';
            }else{
                $enableDates = '0';
            }



            $alloffdays = array();
            foreach ($wbtm_offday_schedules as $wbtm_offday_schedule) {
                $alloffdays =  array_unique(array_merge($alloffdays, wbtm_displayDates($wbtm_offday_schedule['from_date'], $wbtm_offday_schedule['to_date'])));;
            }
            $offday = array();
            foreach ($alloffdays as $alloffday) {
                $offday[] = '"' . date('d-m-Y', strtotime($alloffday)) . '"';
            }
            $off_particular_date = implode(',', $offday);
            $off_particular_date = '[' . $off_particular_date . ']';
            $weekly_offday = get_post_meta($post_id, 'weekly_offday_return', true) ? get_post_meta($post_id, 'weekly_offday_return', true) : [];
            $weekly_offday = implode(',', $weekly_offday);
            $weekly_offday = '[' . $weekly_offday . ']';


            echo "<input id=".'return_all_date_picker_info'." data-return_single_bus=".$singleBus."  data-enableDates=".$enableDates." data-off_particular_date=".$off_particular_date." data-weekly_offday=".$weekly_offday." data-enable_onday=".$show_operational_on_day." data-enable_offday=".$show_off_day." data-date_format=".$this->convert_datepicker_dateformat()." type=".'hidden'.">";

        }
    }
}

