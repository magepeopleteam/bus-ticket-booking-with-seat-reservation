<?php

if ( ! defined( 'ABSPATH' ) ) { die; }

	/*
   * @Author 		engr.sumonazma@gmail.com
   * Copyright: 	mage-people.com
   */
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}  // if direct access
	if ( ! class_exists( 'WBTM_Hidden_Product' ) ) {
		class WBTM_Hidden_Product {
			public function __construct() {
				add_action( 'wp_insert_post', array( $this, 'create_hidden_wc_product_on_publish' ), 10, 3 );
				add_action( 'save_post', array( $this, 'run_link_product_on_save' ), 99, 1 );
				add_action( 'parse_query', array( $this, 'hide_wc_hidden_product_from_product_list' ) );
				add_action( 'wp', array( $this, 'hide_hidden_wc_product_from_frontend' ) );
				//******************//
				add_action('wp_head', [$this, 'url_exclude_search_engine']);
				add_action('init', [$this, 'get_all_hidden_product_id']);
				add_filter('wpseo_exclude_from_sitemap_by_post_ids', [$this, 'get_all_hidden_product_id']);
			}
			public function create_hidden_wc_product_on_publish( $post_id, $post ) {
				if ( $post->post_type == WBTM_Functions::get_cpt() && $post->post_status == 'publish' && empty( WBTM_Global_Function::get_post_info( $post_id, 'check_if_run_once' ) ) ) {
					$this->create_hidden_wc_product( $post_id);
				}
			}
			public function run_link_product_on_save( $post_id ) {
				// Fixed by Shahnur — 2026-05-04 02:35 PM (Asia/Dhaka)
				// Guard against WooCommerce being listed as active but not actually loaded
				// (e.g. plugin folder deleted). Avoids fatal "Call to undefined function is_product()".
				if ( ! function_exists( 'is_product' ) ) {
					return;
				}
				if ( get_post_type( $post_id ) == WBTM_Functions::get_cpt() ) {
					if ( ! isset( $_POST['wbtm_type_nonce'] ) || ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['wbtm_type_nonce'])), 'wbtm_type_nonce' ) ) {
						return;
					}
					if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
						return;
					}
					if ( ! current_user_can( 'edit_post', $post_id ) ) {
						return;
					}
					if ( !is_product() || ($this->count_hidden_wc_product( $post_id ) == 0 || empty( WBTM_Global_Function::get_post_info( $post_id, 'link_wc_product' ) ) )) {
						$this->create_hidden_wc_product( $post_id);
					}
					$product_id = WBTM_Global_Function::get_post_info( $post_id, 'link_wc_product', $post_id );
					set_post_thumbnail( $product_id, get_post_thumbnail_id( $post_id ) );
					wp_publish_post( $product_id );
					$product_type = 'yes';
					$_tax_status = isset($_POST['_tax_status']) ? sanitize_text_field(wp_unslash($_POST['_tax_status'])) : 'none';
					$_tax_class = isset($_POST['_tax_class']) ? sanitize_text_field(wp_unslash($_POST['_tax_class'])) : '';
					update_post_meta( $product_id, '_tax_status', $_tax_status );
					update_post_meta( $product_id, '_tax_class', $_tax_class );
					update_post_meta( $product_id, '_stock_status', 'instock' );
					update_post_meta( $product_id, '_manage_stock', 'no' );
					update_post_meta( $product_id, '_virtual', $product_type );
					update_post_meta( $product_id, '_sold_individually', 'yes' );
					$my_post = array(
						'ID'         => $product_id,
						'post_title' => get_the_title( $post_id ),
						'post_name'  => uniqid()
					);
					//remove_action( 'save_post', 'run_link_product_on_save' );
					wp_update_post( $my_post );
					//add_action( 'save_post', 'run_link_product_on_save' );
				}
			}
			public function hide_wc_hidden_product_from_product_list( $query ) {
				global $pagenow;
				$q_vars = &$query->query_vars;
				if ( $pagenow == 'edit.php' && isset( $q_vars['post_type'] ) && $q_vars['post_type'] == 'product' ) {
					$tax_query = array(
						[
							'taxonomy' => 'product_visibility',
							'field'    => 'slug',
							'terms'    => 'exclude-from-catalog',
							'operator' => 'NOT IN',
						]
					);
					$query->set( 'tax_query', $tax_query );
				}
			}
			public function hide_hidden_wc_product_from_frontend() {
				// Fixed by Shahnur — 2026-05-04 02:35 PM (Asia/Dhaka)
				// is_product() is a WooCommerce conditional; bail if WC isn't actually loaded.
				if ( ! function_exists( 'is_product' ) ) {
					return;
				}
				global $post, $wp_query;
				if ( is_product() ) {
					$post_id    = $post->ID;
					$visibility = get_the_terms( $post_id, 'product_visibility' );
					if ( is_object( $visibility ) ) {
						if ( $visibility[0]->name == 'exclude-from-catalog' ) {
							$check_event_hidden = WBTM_Global_Function::get_post_info( $post_id, 'link_wbtm_bus', 0 );
							if ( $check_event_hidden > 0 ) {
								$wp_query->set_404();
								status_header( 404 );
								get_template_part( 404 );
								exit();
							}
						}
					}
				}
			}
			/**********************/
			public function create_hidden_wc_product( $post_id) {
				return self::create_hidden_wc_product_for( $post_id );
			}
			/**
			 * Create a fresh hidden WC product for a bus and return its ID.
			 *
			 * Static twin of create_hidden_wc_product() so the self-healing helpers
			 * below (and the AJAX add-to-cart controller) can (re)build the mirror
			 * product and get its ID back without needing a class instance.
			 *
			 * @param int $bus_id wbtm_bus post ID.
			 * @return int New product ID, or 0 on failure.
			 */
			public static function create_hidden_wc_product_for( $bus_id ) {
				$new_post = array(
					'post_title'    => get_the_title( $bus_id ),
					'post_content'  => '',
					'post_name'     => uniqid(),
					'post_category' => array(),
					'tags_input'    => array(),
					'post_status'   => 'publish',
					'post_type'     => 'product'
				);
				$pid = wp_insert_post( $new_post );
				if ( is_wp_error( $pid ) || ! $pid ) {
					return 0;
				}
				update_post_meta( $bus_id, 'link_wc_product', $pid );
				update_post_meta( $pid, 'link_wbtm_bus', $bus_id );
				self::ensure_product_is_purchasable( $pid, $bus_id );
				update_post_meta( $bus_id, 'check_if_run_once', true );
				return (int) $pid;
			}
			/**
			 * Force a hidden product into a state WooCommerce will accept in the cart:
			 * published, priced, in stock, virtual, sold individually and excluded from
			 * catalog/search. Only writes what is needed so it is cheap to call.
			 *
			 * @param int $product_id WC product ID.
			 * @param int $bus_id     Owning wbtm_bus ID (to restore the back-link).
			 * @return void
			 */
			public static function ensure_product_is_purchasable( $product_id, $bus_id = 0 ) {
				$product_id = (int) $product_id;
				if ( ! $product_id ) {
					return;
				}
				if ( get_post_status( $product_id ) !== 'publish' ) {
					wp_update_post( array( 'ID' => $product_id, 'post_status' => 'publish' ) );
				}
				$price = get_post_meta( $product_id, '_price', true );
				if ( $price === '' || $price === null ) {
					update_post_meta( $product_id, '_price', 0.01 );
					update_post_meta( $product_id, '_regular_price', 0.01 );
				}
				update_post_meta( $product_id, '_stock_status', 'instock' );
				update_post_meta( $product_id, '_manage_stock', 'no' );
				update_post_meta( $product_id, '_virtual', 'yes' );
				update_post_meta( $product_id, '_sold_individually', 'yes' );
				wp_set_object_terms( $product_id, array( 'exclude-from-catalog', 'exclude-from-search' ), 'product_visibility' );
				if ( $bus_id && ! get_post_meta( $product_id, 'link_wbtm_bus', true ) ) {
					update_post_meta( $product_id, 'link_wbtm_bus', (int) $bus_id );
				}
				if ( function_exists( 'wc_delete_product_transients' ) ) {
					wc_delete_product_transients( $product_id );
				}
			}
			/**
			 * Ensure a bus has a valid, purchasable hidden WC product and return its ID.
			 *
			 * Self-healing entry point used by the "Book Now" AJAX handler. Sites where
			 * buses were imported/migrated (or where the mirror product was deleted) end
			 * up with a missing/broken link_wc_product, which otherwise fails add-to-cart
			 * with a generic "Cart error" (400). This rebuilds the link on demand:
			 *   1. link resolves to a purchasable product -> return it (no writes),
			 *   2. product exists but isn't buyable -> repair its meta,
			 *   3. link lost but a back-linked orphan product exists -> re-adopt it,
			 *   4. nothing exists -> create a fresh hidden product.
			 *
			 * @param int $bus_id wbtm_bus post ID.
			 * @return int Linked WC product ID, or 0 if it could not be ensured.
			 */
			public static function ensure_hidden_wc_product( $bus_id ) {
				$bus_id = (int) $bus_id;
				if ( ! $bus_id || ! function_exists( 'wc_get_product' ) || get_post_type( $bus_id ) !== WBTM_Functions::get_cpt() ) {
					return 0;
				}
				$product_id = (int) WBTM_Global_Function::get_post_info( $bus_id, 'link_wc_product' );
				// Happy path: link already resolves to a buyable product — do nothing.
				if ( $product_id && get_post_type( $product_id ) === 'product' && get_post_status( $product_id ) !== 'trash' ) {
					$product = wc_get_product( $product_id );
					if ( $product && $product->is_purchasable() && $product->is_in_stock() ) {
						return $product_id;
					}
					// Product exists but isn't buyable (lost price/stock/visibility) — repair.
					self::ensure_product_is_purchasable( $product_id, $bus_id );
					return $product_id;
				}
				// Link missing/broken: re-adopt an orphan product that still back-links here.
				$existing = get_posts( array(
					'post_type'      => 'product',
					'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'meta_query'     => array(
						array(
							'key'   => 'link_wbtm_bus',
							'value' => $bus_id,
						),
					),
				) );
				if ( ! empty( $existing ) ) {
					$product_id = (int) $existing[0];
					update_post_meta( $bus_id, 'link_wc_product', $product_id );
					self::ensure_product_is_purchasable( $product_id, $bus_id );
					return $product_id;
				}
				// Nothing to adopt — build a new mirror product.
				return (int) self::create_hidden_wc_product_for( $bus_id );
			}
			/**
			 * Bulk-repair every published bus's hidden product link in one pass.
			 *
			 * Run once on a site whose buses lost their mirror products (the cause of
			 * "Cart error" on Book Now), e.g. via WP-CLI:
			 *   wp eval 'print_r( WBTM_Hidden_Product::repair_all_hidden_products() );'
			 *
			 * @return array{checked:int,repaired:int,created:int,links:array<int,int>}
			 */
			public static function repair_all_hidden_products() {
				$result = array( 'checked' => 0, 'repaired' => 0, 'created' => 0, 'links' => array() );
				if ( ! function_exists( 'wc_get_product' ) ) {
					return $result;
				}
				$buses = get_posts( array(
					'post_type'      => WBTM_Functions::get_cpt(),
					'post_status'    => 'publish',
					'numberposts'    => -1,
					'fields'         => 'ids',
				) );
				foreach ( $buses as $bus_id ) {
					$result['checked']++;
					$before       = (int) WBTM_Global_Function::get_post_info( $bus_id, 'link_wc_product' );
					$valid_before = $before && get_post_type( $before ) === 'product' && get_post_status( $before ) !== 'trash';
					$pid          = self::ensure_hidden_wc_product( $bus_id );
					if ( $pid && ! $valid_before ) {
						$result['repaired']++;
						if ( $pid !== $before ) {
							$result['created']++;
						}
						$result['links'][ $bus_id ] = $pid;
					}
				}
				return $result;
			}
			public function count_hidden_wc_product( $post_id ): int {
				$args = array(
					'post_type'      => 'product',
					'posts_per_page' => - 1,
					'meta_query'     => array(
						array(
							'key'     => 'link_wbtm_bus',
							'value'   => $post_id,
							'compare' => '='
						)
					)
				);
				$loop = new WP_Query( $args );
				return $loop->post_count;
			}
			//**************Google search url hidden*********************//
			public function url_exclude_search_engine() {
				// Fixed by Shahnur — 2026-05-04 02:35 PM (Asia/Dhaka)
				// is_product() is a WooCommerce conditional; bail if WC isn't actually loaded.
				if ( ! function_exists( 'is_product' ) ) {
					return;
				}
				global $post;
				if (is_single() && is_product()) {
					$post_id = $post->ID;
					$visibility = get_the_terms($post_id, 'product_visibility') ? get_the_terms($post_id, 'product_visibility') : [0];
					if (is_object($visibility[0]) && $visibility[0]->name == 'exclude-from-catalog') {
						$check_hidden = WBTM_Global_Function::get_post_info($post_id, 'link_wbtm_bus', 0);
						if ($check_hidden > 0) {
							?>
							<meta name="robots" content="noindex, nofollow">
							<?php
						}
					}
				}
			}
			public function get_all_hidden_product_id() {
				$product_id = [];
				$query = WBTM_Global_Function::query_post_type(WBTM_Functions::get_cpt());
				foreach ($query->posts as $result) {
					$post_id = $result->ID;
					$product_id[] = WBTM_Global_Function::get_post_info($post_id, 'link_wc_product');
				}
				return array_filter($product_id);
			}
		}
		new WBTM_Hidden_Product();
	}