<?php
if (!defined('ABSPATH')) exit;  // if direct access

class BusTicketBookingWithSeatReservationClass
{
    public function __construct()
    {
        $this->load_dependencies();
        $this->define_all_hooks();
        $this->define_all_filters();

    }


    private function load_dependencies() {
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/CommonClass.php';
        require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/ActiveDataShowClass.php';
    }

    private function define_all_hooks() {
        $ActiveDataShowClass = new ActiveDataShowClass;
        add_action('active_date', array($ActiveDataShowClass,'active_date_picker'), 99, 3);
        add_action('active_date', array($ActiveDataShowClass,'return_active_date_picker'), 99, 3);
    }

    private function define_all_filters() {
        //add_action('mage_next_date', array($NextDateClass,'mage_next_date_suggestion_single'), 99, 3);
    }
}

new BusTicketBookingWithSeatReservationClass();

