<?php
if ( ! defined('ABSPATH')) exit;  // if direct access 



$page_1_optionsssss = array(
            array(
                'id'		=> 'wbtm_bus_routes_name_list',
                'title'		=> __('Route Point','bus-ticket-booking-with-seat-reservation'),
                'details'	=> __('Please Select Route Point ','bus-ticket-booking-with-seat-reservation'),
                'collapsible'=>true,
                'type'		=> 'repeatable',
                'btn_text'	=> 'Add New Route Point',
                'title_field' => 'wbtm_bus_routes_name',
                'fields'    => array(
                     array(
                         'type'         =>'select',
                         'default'      =>'option_1',
                         'item_id'      =>'wbtm_bus_routes_name',
                         'name'         =>'Stops Name',
                         'args'         => 'TAXN_%wbtm_bus_stops%'
                        )
                ),
            ),
    
        );
      
        $args = array(
            'taxonomy'       => 'wbtm_bus_stops',
            'options' 	        => $page_1_optionsssss,
        );

new TaxonomyEdit( $args );