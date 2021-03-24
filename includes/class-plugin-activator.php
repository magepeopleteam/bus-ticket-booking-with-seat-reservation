<?php 
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

class WBTM_Plugin_Activator{
    

    // Function to get page slug
    function wbtm_get_page_by_slug($slug) {
      if ($pages = get_pages())
          foreach ($pages as $page)
              if ($slug === $page->post_name) return $page;
      return false;
    }

    public function activate(){
        if (! $this->wbtm_get_page_by_slug('bus-search-list')) {
          $bus_search_page = array(
          'post_type' => 'page',
          'post_name' => 'bus-search-list',
          'post_title' => 'Bus Search',
          'post_content' => '',
          // 'post_content' => '[wbtm-bus-search]',
          'post_status' => 'publish',
          );

          wp_insert_post($bus_search_page);
      }

      if (! $this->wbtm_get_page_by_slug('view-ticket')) {
          $view_ticket_page = array(
          'post_type' => 'page',
          'post_name' => 'view-ticket',
          'post_title' => 'View Ticket',
          'post_content' => '[view-ticket]',
          'post_status' => 'publish',
          );

          wp_insert_post($view_ticket_page);
      }
      
    }




}