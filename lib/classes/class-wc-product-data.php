<?php
if ( ! defined( 'ABSPATH' ) ) { die; } // Cannot access pages directly.

add_action('plugins_loaded', 'mqtc_load_wc_class');
function mqtc_load_wc_class() {

if ( class_exists('WC_Product_Data_Store_CPT') && !class_exists('MAGE_Product_Data_Store_CPT')) {

class MAGE_Product_Data_Store_CPT extends WC_Product_Data_Store_CPT {

	public function cpt_product(){
		$wc_product = array('product');
		return apply_filters('mage_wc_products', $wc_product);
	}

    public function read( &$product ) {

        $product->set_defaults();

        if ( ! $product->get_id() || ! ( $post_object = get_post( $product->get_id() ) ) || ! in_array( $post_object->post_type, $this->cpt_product() ) ) { // change birds with your post type
            throw new Exception( __( 'Invalid product.', 'woocommerce' ) );
        }

        $id = $product->get_id();

        $product->set_props( array(
            'name'              => $post_object->post_title,
            'slug'              => $post_object->post_name,
            'date_created'      => 0 < $post_object->post_date_gmt ? wc_string_to_timestamp( $post_object->post_date_gmt ) : null,
            'date_modified'     => 0 < $post_object->post_modified_gmt ? wc_string_to_timestamp( $post_object->post_modified_gmt ) : null,
            'status'            => $post_object->post_status,
            'description'       => $post_object->post_content,
            'product_id'        => $post_object->ID,
            'sku'               => $post_object->ID,
            'short_description' => $post_object->post_excerpt,
            'parent_id'         => $post_object->post_parent,
            'menu_order'        => $post_object->menu_order,
            'reviews_allowed'   => 'open' === $post_object->comment_status,
        ) );

        $this->read_attributes( $product );
        $this->read_downloads( $product );
        $this->read_visibility( $product );
        $this->read_product_data( $product );
        $this->read_extra_data( $product );
        $product->set_object_read( true );
    }

    /**
     * Get the product type based on product ID.
     *
     * @since 3.0.0
     * @param int $product_id
     * @return bool|string
     */
    public function get_product_type( $product_id ) {
        $post_type = get_post_type( $product_id );
        if ( 'product_variation' === $post_type ) {
            return 'variation';
        } elseif ( in_array( $post_type, $this->cpt_product() ) ) { // change birds with your post type
            $terms = get_the_terms( $product_id, 'product_type' );
            return ! empty( $terms ) ? sanitize_title( current( $terms )->name ) : 'simple';
        } else {
            return false;
        }
    }
}

	add_filter( 'woocommerce_data_stores', 'wbtm_woocommerce_data_stores' );
	function wbtm_woocommerce_data_stores ( $stores ) {     
	      $stores['product'] = 'MAGE_Product_Data_Store_CPT';
	      return $stores;
	}
}
}