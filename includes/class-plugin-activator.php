<?php 
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

class WBTM_Plugin_Activator{
    

    // Function to get page slug
    public function wbtm_get_page_by_slug($slug) {
      if ($pages = get_pages()){
        foreach ($pages as $page){
          if ($slug === $page->post_name){
            return $page;
          } else {
            return false;
          }
        }       
      }    
    }

    public static function activate(){
        if (! (new self())->wbtm_get_page_by_slug('bus-search-list')) {
          $bus_search_page = array(
          'post_type' => 'page',
          'post_name' => 'bus-search-list',
          'post_title' => 'Bus Search result',
          'post_content' => '[wbtm-bus-search]',
          'post_status' => 'publish',
          );

          wp_insert_post($bus_search_page);
      }

        if (! (new self())->wbtm_get_page_by_slug('bus-global-search')) {
            $bus_global_search_page = array(
                'post_type' => 'page',
                'post_name' => 'bus-global-search',
                'post_title' => 'Global search form',
                'post_content' => '[wbtm-bus-search-form]',
                'post_status' => 'publish',
            );
            wp_insert_post($bus_global_search_page);
        }
    }
}