<?php
	/**
	 * Coupon CPT — registers the `wbtm_coupon` post type (nested under the Bus
	 * menu) and renders the admin list-table columns (code, discount, targeting,
	 * usage, status). Not publicly queryable; managed from wp-admin only.
	 *
	 * @Author MagePeople Team
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}

	if ( ! class_exists( 'WBTM_Coupon_CPT' ) ) {
		class WBTM_Coupon_CPT {

			public function __construct() {
				add_action( 'init', array( $this, 'register' ) );
				add_filter( 'manage_' . WBTM_Coupon_Module::CPT . '_posts_columns', array( $this, 'columns' ) );
				add_action( 'manage_' . WBTM_Coupon_Module::CPT . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
				add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
				// The coupon code is what identifies a coupon, so surface it as the title placeholder.
				add_filter( 'enter_title_here', array( $this, 'title_placeholder' ), 10, 2 );
				// Modern stats cards above the coupon list table.
				add_action( 'admin_notices', array( $this, 'render_stats' ) );
			}

			public function register() {
				$bus_name = WBTM_Functions::get_name();
				$labels   = array(
					'name'               => esc_html__( 'Coupons', 'bus-ticket-booking-with-seat-reservation' ),
					'singular_name'      => esc_html__( 'Coupon', 'bus-ticket-booking-with-seat-reservation' ),
					'menu_name'          => esc_html__( 'Coupons', 'bus-ticket-booking-with-seat-reservation' ),
					'add_new'            => esc_html__( 'Add Coupon', 'bus-ticket-booking-with-seat-reservation' ),
					'add_new_item'       => esc_html__( 'Add New Coupon', 'bus-ticket-booking-with-seat-reservation' ),
					'edit_item'          => esc_html__( 'Edit Coupon', 'bus-ticket-booking-with-seat-reservation' ),
					'new_item'           => esc_html__( 'New Coupon', 'bus-ticket-booking-with-seat-reservation' ),
					'view_item'          => esc_html__( 'View Coupon', 'bus-ticket-booking-with-seat-reservation' ),
					'search_items'       => esc_html__( 'Search Coupons', 'bus-ticket-booking-with-seat-reservation' ),
					'not_found'          => esc_html__( 'No coupons found', 'bus-ticket-booking-with-seat-reservation' ),
					'not_found_in_trash' => esc_html__( 'No coupons found in Trash', 'bus-ticket-booking-with-seat-reservation' ),
					/* translators: %s: bus post type name */
					'all_items'          => esc_html__( 'Coupons', 'bus-ticket-booking-with-seat-reservation' ),
				);
				$args = array(
					'labels'              => $labels,
					'public'              => false,
					'publicly_queryable'  => false,
					'show_ui'             => true,
					'show_in_menu'        => 'edit.php?post_type=' . WBTM_Functions::get_cpt(),
					'show_in_nav_menus'   => false,
					'show_in_rest'        => false,
					'exclude_from_search' => true,
					'hierarchical'        => false,
					'has_archive'         => false,
					'rewrite'             => false,
					'query_var'           => false,
					'supports'            => array( 'title' ),
					'capability_type'     => 'post',
					'map_meta_cap'        => true,
					'menu_icon'           => 'dashicons-tickets-alt',
				);
				register_post_type( WBTM_Coupon_Module::CPT, $args );
			}

			public function title_placeholder( $text, $post ) {
				if ( $post && $post->post_type === WBTM_Coupon_Module::CPT ) {
					return esc_html__( 'Coupon name (internal label)', 'bus-ticket-booking-with-seat-reservation' );
				}
				return $text;
			}

			public function columns( $columns ) {
				$new = array();
				$new['cb']    = isset( $columns['cb'] ) ? $columns['cb'] : '';
				$new['title'] = esc_html__( 'Name', 'bus-ticket-booking-with-seat-reservation' );
				$new['wbtm_code']     = esc_html__( 'Code', 'bus-ticket-booking-with-seat-reservation' );
				$new['wbtm_discount'] = esc_html__( 'Discount', 'bus-ticket-booking-with-seat-reservation' );
				$new['wbtm_target']   = esc_html__( 'Applies To', 'bus-ticket-booking-with-seat-reservation' );
				$new['wbtm_usage']    = esc_html__( 'Usage', 'bus-ticket-booking-with-seat-reservation' );
				$new['wbtm_validity'] = esc_html__( 'Validity', 'bus-ticket-booking-with-seat-reservation' );
				$new['wbtm_status']   = esc_html__( 'Status', 'bus-ticket-booking-with-seat-reservation' );
				$new['date']  = isset( $columns['date'] ) ? $columns['date'] : esc_html__( 'Date', 'bus-ticket-booking-with-seat-reservation' );
				return $new;
			}

			public function column_content( $column, $post_id ) {
				switch ( $column ) {
					case 'wbtm_code':
						$code = WBTM_Coupon_Module::get( $post_id, 'code', '' );
						echo $code ? '<code class="wbtm-coupon-code-pill">' . esc_html( $code ) . '</code>' : '&mdash;';
						break;

					case 'wbtm_discount':
						echo esc_html( $this->discount_label( $post_id ) );
						break;

					case 'wbtm_target':
						echo esc_html( $this->target_label( $post_id ) );
						break;

					case 'wbtm_usage':
						$used  = WBTM_Coupon_Module::get_int( $post_id, 'used_count', 0 );
						$limit = WBTM_Coupon_Module::get_int( $post_id, 'usage_limit_total', 0 );
						$limit_txt = $limit > 0 ? (string) $limit : '&infin;';
						echo esc_html( $used ) . ' / ' . wp_kses_post( $limit_txt );
						break;

					case 'wbtm_validity':
						echo esc_html( $this->validity_label( $post_id ) );
						break;

					case 'wbtm_status':
						$enabled = ! WBTM_Coupon_Module::is_on( $post_id, 'disabled' );
						$active  = $enabled && $this->is_within_schedule( $post_id );
						if ( ! $enabled ) {
							echo '<span class="wbtm-badge wbtm-badge--off">' . esc_html__( 'Disabled', 'bus-ticket-booking-with-seat-reservation' ) . '</span>';
						} elseif ( $active ) {
							echo '<span class="wbtm-badge wbtm-badge--on">' . esc_html__( 'Active', 'bus-ticket-booking-with-seat-reservation' ) . '</span>';
						} else {
							echo '<span class="wbtm-badge wbtm-badge--warn">' . esc_html__( 'Scheduled / Expired', 'bus-ticket-booking-with-seat-reservation' ) . '</span>';
						}
						break;
				}
			}

			private function discount_label( $post_id ) {
				$type   = WBTM_Coupon_Module::get( $post_id, 'discount_type', 'percent' );
				$amount = WBTM_Coupon_Module::get_float( $post_id, 'amount', 0 );
				switch ( $type ) {
					case 'percent':
						$cap = WBTM_Coupon_Module::get_float( $post_id, 'max_discount', 0 );
						// Show "20%" not "20.00%", but never strip a whole-number's own zero.
						$num = ( floor( $amount ) == $amount ) ? (string) (int) $amount : rtrim( rtrim( number_format( $amount, 2, '.', '' ), '0' ), '.' );
						$txt = $num . '%';
						if ( $cap > 0 ) {
							$txt .= ' ' . sprintf(
								/* translators: %s: formatted maximum discount amount */
								esc_html__( '(max %s)', 'bus-ticket-booking-with-seat-reservation' ),
								wp_strip_all_tags( wc_price( $cap ) )
							);
						}
						return $txt;
					case 'fixed_seat':
						return wp_strip_all_tags( wc_price( $amount ) ) . ' ' . esc_html__( '/ seat', 'bus-ticket-booking-with-seat-reservation' );
					case 'fixed_booking':
					default:
						return wp_strip_all_tags( wc_price( $amount ) ) . ' ' . esc_html__( '/ booking', 'bus-ticket-booking-with-seat-reservation' );
				}
			}

			private function target_label( $post_id ) {
				if ( WBTM_Coupon_Module::get( $post_id, 'apply_to', 'all' ) !== 'specific' ) {
					return esc_html__( 'All buses', 'bus-ticket-booking-with-seat-reservation' );
				}
				$bus_ids = WBTM_Coupon_Module::get_array( $post_id, 'bus_ids' );
				$cats    = WBTM_Coupon_Module::get_array( $post_id, 'bus_cats' );
				$parts   = array();
				if ( $bus_ids ) {
					/* translators: %d: number of buses */
					$parts[] = sprintf( _n( '%d bus', '%d buses', count( $bus_ids ), 'bus-ticket-booking-with-seat-reservation' ), count( $bus_ids ) );
				}
				if ( $cats ) {
					/* translators: %d: number of bus types */
					$parts[] = sprintf( _n( '%d type', '%d types', count( $cats ), 'bus-ticket-booking-with-seat-reservation' ), count( $cats ) );
				}
				return $parts ? implode( ', ', $parts ) : esc_html__( 'Specific (none set)', 'bus-ticket-booking-with-seat-reservation' );
			}

			private function validity_label( $post_id ) {
				$start = WBTM_Coupon_Module::get( $post_id, 'date_start', '' );
				$end   = WBTM_Coupon_Module::get( $post_id, 'date_end', '' );
				if ( ! $start && ! $end ) {
					return esc_html__( 'Always', 'bus-ticket-booking-with-seat-reservation' );
				}
				$fmt = get_option( 'date_format' );
				$s   = $start ? date_i18n( $fmt, strtotime( $start ) ) : '…';
				$e   = $end ? date_i18n( $fmt, strtotime( $end ) ) : '…';
				return $s . ' → ' . $e;
			}

			private function is_within_schedule( $post_id ) {
				$now   = current_time( 'timestamp' );
				$start = WBTM_Coupon_Module::get( $post_id, 'date_start', '' );
				$end   = WBTM_Coupon_Module::get( $post_id, 'date_end', '' );
				if ( $start && $now < strtotime( $start . ' 00:00:00' ) ) {
					return false;
				}
				if ( $end && $now > strtotime( $end . ' 23:59:59' ) ) {
					return false;
				}
				return true;
			}

			/**
			 * Render the modern KPI cards shown above the coupon list table.
			 * Hooked to admin_notices but only paints on the coupon list screen.
			 */
			public function render_stats() {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				if ( ! $screen || $screen->base !== 'edit' || $screen->post_type !== WBTM_Coupon_Module::CPT ) {
					return;
				}

				$ids = get_posts( array(
					'post_type'      => WBTM_Coupon_Module::CPT,
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				) );

				$total       = count( $ids );
				$active      = 0;
				$redemptions = 0;
				$saved       = 0.0;
				foreach ( $ids as $id ) {
					if ( ! WBTM_Coupon_Module::is_on( $id, 'disabled' ) && $this->is_within_schedule( $id ) ) {
						$active++;
					}
					$redemptions += WBTM_Coupon_Module::get_int( $id, 'used_count', 0 );
					$saved       += WBTM_Coupon_Module::get_float( $id, 'discount_total', 0 );
				}

				$cards = array(
					array(
						'label' => esc_html__( 'Total Coupons', 'bus-ticket-booking-with-seat-reservation' ),
						'value' => number_format_i18n( $total ),
						'mod'   => 'brand',
						'icon'  => 'tickets-alt',
					),
					array(
						'label' => esc_html__( 'Active Now', 'bus-ticket-booking-with-seat-reservation' ),
						'value' => number_format_i18n( $active ),
						'mod'   => 'ok',
						'icon'  => 'yes-alt',
					),
					array(
						'label' => esc_html__( 'Total Redemptions', 'bus-ticket-booking-with-seat-reservation' ),
						'value' => number_format_i18n( $redemptions ),
						'mod'   => 'blue',
						'icon'  => 'chart-bar',
					),
					array(
						'label' => esc_html__( 'Total Customer Savings', 'bus-ticket-booking-with-seat-reservation' ),
						'value' => wp_strip_all_tags( wc_price( $saved ) ),
						'mod'   => 'amber',
						'icon'  => 'money-alt',
					),
				);
				?>
				<div class="wbtm-cpn-stats">
					<div class="wbtm-cpn-stats__head">
						<h2 class="wbtm-cpn-stats__title"><?php esc_html_e( 'Coupon Overview', 'bus-ticket-booking-with-seat-reservation' ); ?></h2>
						<span class="wbtm-cpn-stats__sub"><?php esc_html_e( 'Per-bus discount rules, restrictions & usage', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
					</div>
					<div class="wbtm-cpn-stats__grid">
						<?php foreach ( $cards as $card ) : ?>
							<div class="wbtm-cpn-card wbtm-cpn-card--<?php echo esc_attr( $card['mod'] ); ?>">
								<div class="wbtm-cpn-card__icon"><span class="dashicons dashicons-<?php echo esc_attr( $card['icon'] ); ?>"></span></div>
								<div class="wbtm-cpn-card__meta">
									<span class="wbtm-cpn-card__value"><?php echo esc_html( $card['value'] ); ?></span>
									<span class="wbtm-cpn-card__label"><?php echo esc_html( $card['label'] ); ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
				<?php
			}

			public function row_actions( $actions, $post ) {
				if ( $post->post_type === WBTM_Coupon_Module::CPT ) {
					unset( $actions['inline hide-if-no-js'] ); // Quick Edit exposes raw meta noise; hide it.
				}
				return $actions;
			}
		}
	}
