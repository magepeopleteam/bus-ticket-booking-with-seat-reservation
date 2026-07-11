<?php
	/**
	 * Coupon editor — a modern, tabbed metabox for the `wbtm_coupon` CPT plus the
	 * sanitising save handler. The UI is scoped under `.wbtm-cpn` so it never
	 * bleeds into the rest of wp-admin, and reuses the plugin's Select2 build for
	 * bus / category / role pickers.
	 *
	 * @Author MagePeople Team
	 */
	if ( ! defined( 'ABSPATH' ) ) {
		die;
	}

	if ( ! class_exists( 'WBTM_Coupon_Meta' ) ) {
		class WBTM_Coupon_Meta {

			public function __construct() {
				add_action( 'add_meta_boxes', array( $this, 'register' ) );
				add_action( 'save_post_' . WBTM_Coupon_Module::CPT, array( $this, 'save' ), 10, 2 );
				add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
			}

			public function register() {
				add_meta_box(
					'wbtm_coupon_editor',
					esc_html__( 'Coupon Rules', 'bus-ticket-booking-with-seat-reservation' ),
					array( $this, 'render' ),
					WBTM_Coupon_Module::CPT,
					'normal',
					'high'
				);
			}

			/** Load the coupon editor styles/scripts only on the coupon screens. */
			public function enqueue( $hook ) {
				$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
				if ( ! $screen || $screen->post_type !== WBTM_Coupon_Module::CPT ) {
					return;
				}
				// Reuse the plugin's bundled Select2 (registered by the global loader).
				wp_enqueue_style( 'mp_select_2' );
				wp_enqueue_script( 'mp_select_2' );
				wp_enqueue_style(
					'wbtm-coupon-admin',
					WBTM_PLUGIN_URL . '/assets/coupon/wbtm-coupon-admin.css',
					array(),
					WBTM_VERSION
				);
				wp_enqueue_script(
					'wbtm-coupon-admin',
					WBTM_PLUGIN_URL . '/assets/coupon/wbtm-coupon-admin.js',
					array( 'jquery' ),
					WBTM_VERSION,
					true
				);
			}

			/* ============================================================
			 *  Rendering
			 * ============================================================ */

			public function render( $post ) {
				$id = $post->ID;
				wp_nonce_field( 'wbtm_coupon_save', 'wbtm_coupon_nonce' );

				$code          = WBTM_Coupon_Module::get( $id, 'code', '' );
				$disabled      = WBTM_Coupon_Module::is_on( $id, 'disabled' );
				$discount_type = WBTM_Coupon_Module::get( $id, 'discount_type', 'percent' );
				$amount        = WBTM_Coupon_Module::get( $id, 'amount', '' );
				$max_discount  = WBTM_Coupon_Module::get( $id, 'max_discount', '' );

				$apply_to  = WBTM_Coupon_Module::get( $id, 'apply_to', 'all' );
				$bus_ids   = array_map( 'intval', WBTM_Coupon_Module::get_array( $id, 'bus_ids' ) );
				$bus_cats  = array_map( 'intval', WBTM_Coupon_Module::get_array( $id, 'bus_cats' ) );
				$min_spend = WBTM_Coupon_Module::get( $id, 'min_spend', '' );
				$min_seats = WBTM_Coupon_Module::get( $id, 'min_seats', '' );
				$max_seats = WBTM_Coupon_Module::get( $id, 'max_seats', '' );

				$date_start   = WBTM_Coupon_Module::get( $id, 'date_start', '' );
				$date_end     = WBTM_Coupon_Module::get( $id, 'date_end', '' );
				$days_of_week = array_map( 'strval', WBTM_Coupon_Module::get_array( $id, 'days_of_week' ) );
				$travel_start = WBTM_Coupon_Module::get( $id, 'travel_start', '' );
				$travel_end   = WBTM_Coupon_Module::get( $id, 'travel_end', '' );

				$limit_total    = WBTM_Coupon_Module::get( $id, 'usage_limit_total', '' );
				$limit_per_user = WBTM_Coupon_Module::get( $id, 'usage_limit_per_user', '' );
				$limit_per_day  = WBTM_Coupon_Module::get( $id, 'usage_limit_per_day', '' );
				$login_required = WBTM_Coupon_Module::is_on( $id, 'login_required' );
				$first_only     = WBTM_Coupon_Module::is_on( $id, 'first_booking_only' );
				$stackable      = WBTM_Coupon_Module::is_on( $id, 'stackable' );
				$allowed_roles  = WBTM_Coupon_Module::get_array( $id, 'allowed_roles' );
				$allowed_emails = WBTM_Coupon_Module::get_array( $id, 'allowed_emails' );

				$used_count = WBTM_Coupon_Module::get_int( $id, 'used_count', 0 );
				?>
				<div class="wbtm-cpn">
					<div class="wbtm-cpn__tabs" role="tablist">
						<button type="button" class="wbtm-cpn__tab is-active" data-tab="general"><span class="dashicons dashicons-tickets-alt"></span><?php esc_html_e( 'General & Discount', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
						<button type="button" class="wbtm-cpn__tab" data-tab="targeting"><span class="dashicons dashicons-filter"></span><?php esc_html_e( 'Targeting & Restrictions', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
						<button type="button" class="wbtm-cpn__tab" data-tab="schedule"><span class="dashicons dashicons-calendar-alt"></span><?php esc_html_e( 'Validity & Schedule', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
						<button type="button" class="wbtm-cpn__tab" data-tab="usage"><span class="dashicons dashicons-groups"></span><?php esc_html_e( 'Usage & Eligibility', 'bus-ticket-booking-with-seat-reservation' ); ?></button>
					</div>

					<div class="wbtm-cpn__body">

						<!-- GENERAL & DISCOUNT -->
						<section class="wbtm-cpn__panel is-active" data-panel="general">
							<div class="wbtm-cpn__grid">
								<div class="wbtm-cpn__field wbtm-cpn__field--wide">
									<label for="wbtm_cpn_code"><?php esc_html_e( 'Coupon Code', 'bus-ticket-booking-with-seat-reservation' ); ?> <span class="req">*</span></label>
									<input type="text" id="wbtm_cpn_code" name="wbtm_coupon[code]" value="<?php echo esc_attr( $code ); ?>" class="wbtm-cpn__code" placeholder="<?php esc_attr_e( 'e.g. SUMMER20', 'bus-ticket-booking-with-seat-reservation' ); ?>" autocomplete="off">
									<p class="wbtm-cpn__hint"><?php esc_html_e( 'The code customers type at checkout. Letters/numbers only; saved uppercase.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
								</div>
								<div class="wbtm-cpn__field">
									<label><?php esc_html_e( 'Status', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<label class="wbtm-cpn__switch">
										<input type="checkbox" name="wbtm_coupon[disabled]" value="yes" <?php checked( $disabled ); ?>>
										<span class="wbtm-cpn__slider"></span>
										<span class="wbtm-cpn__switch-txt"><?php esc_html_e( 'Disable this coupon', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
									</label>
								</div>
							</div>

							<div class="wbtm-cpn__field">
								<label><?php esc_html_e( 'Discount Type', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<div class="wbtm-cpn__segmented" data-controls="discount-type">
									<?php foreach ( WBTM_Coupon_Module::discount_types() as $key => $label ) : ?>
										<label class="wbtm-cpn__seg <?php echo $discount_type === $key ? 'is-active' : ''; ?>">
											<input type="radio" name="wbtm_coupon[discount_type]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $discount_type, $key ); ?>>
											<?php echo esc_html( $label ); ?>
										</label>
									<?php endforeach; ?>
								</div>
							</div>

							<div class="wbtm-cpn__grid">
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_amount"><?php esc_html_e( 'Amount', 'bus-ticket-booking-with-seat-reservation' ); ?> <span class="req">*</span></label>
									<div class="wbtm-cpn__inputgroup">
										<span class="wbtm-cpn__addon" data-amount-symbol data-currency="<?php echo esc_attr( get_woocommerce_currency_symbol() ); ?>"><?php echo $discount_type === 'percent' ? '%' : esc_html( get_woocommerce_currency_symbol() ); ?></span>
										<input type="number" step="0.01" min="0" id="wbtm_cpn_amount" name="wbtm_coupon[amount]" value="<?php echo esc_attr( $amount ); ?>" placeholder="0.00">
									</div>
								</div>
								<div class="wbtm-cpn__field" data-show-for="percent" <?php echo $discount_type === 'percent' ? '' : 'style="display:none"'; ?>>
									<label for="wbtm_cpn_max"><?php esc_html_e( 'Maximum Discount Cap', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<div class="wbtm-cpn__inputgroup">
										<span class="wbtm-cpn__addon"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
										<input type="number" step="0.01" min="0" id="wbtm_cpn_max" name="wbtm_coupon[max_discount]" value="<?php echo esc_attr( $max_discount ); ?>" placeholder="<?php esc_attr_e( '0 = no cap', 'bus-ticket-booking-with-seat-reservation' ); ?>">
									</div>
									<p class="wbtm-cpn__hint"><?php esc_html_e( 'Caps a percentage discount at this amount. Leave 0 for no cap.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
								</div>
							</div>
						</section>

						<!-- TARGETING & RESTRICTIONS -->
						<section class="wbtm-cpn__panel" data-panel="targeting">
							<div class="wbtm-cpn__field">
								<label><?php esc_html_e( 'Which buses does this coupon apply to?', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<div class="wbtm-cpn__segmented" data-controls="apply-to">
									<label class="wbtm-cpn__seg <?php echo $apply_to !== 'specific' ? 'is-active' : ''; ?>">
										<input type="radio" name="wbtm_coupon[apply_to]" value="all" <?php checked( $apply_to !== 'specific' ); ?>>
										<?php esc_html_e( 'All buses', 'bus-ticket-booking-with-seat-reservation' ); ?>
									</label>
									<label class="wbtm-cpn__seg <?php echo $apply_to === 'specific' ? 'is-active' : ''; ?>">
										<input type="radio" name="wbtm_coupon[apply_to]" value="specific" <?php checked( $apply_to === 'specific' ); ?>>
										<?php esc_html_e( 'Specific buses / types', 'bus-ticket-booking-with-seat-reservation' ); ?>
									</label>
								</div>
							</div>

							<div data-show-for-apply="specific" <?php echo $apply_to === 'specific' ? '' : 'style="display:none"'; ?>>
								<div class="wbtm-cpn__grid">
									<div class="wbtm-cpn__field">
										<label for="wbtm_cpn_bus_ids"><?php esc_html_e( 'Buses', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
										<select id="wbtm_cpn_bus_ids" name="wbtm_coupon[bus_ids][]" multiple class="wbtm-cpn__select2" data-placeholder="<?php esc_attr_e( 'Choose buses…', 'bus-ticket-booking-with-seat-reservation' ); ?>">
											<?php foreach ( $this->get_buses() as $bid => $btitle ) : ?>
												<option value="<?php echo esc_attr( $bid ); ?>" <?php selected( in_array( (int) $bid, $bus_ids, true ) ); ?>><?php echo esc_html( $btitle ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
									<div class="wbtm-cpn__field">
										<label for="wbtm_cpn_bus_cats"><?php esc_html_e( 'Bus Types', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
										<select id="wbtm_cpn_bus_cats" name="wbtm_coupon[bus_cats][]" multiple class="wbtm-cpn__select2" data-placeholder="<?php esc_attr_e( 'Choose bus types…', 'bus-ticket-booking-with-seat-reservation' ); ?>">
											<?php foreach ( $this->get_bus_cats() as $tid => $tname ) : ?>
												<option value="<?php echo esc_attr( $tid ); ?>" <?php selected( in_array( (int) $tid, $bus_cats, true ) ); ?>><?php echo esc_html( $tname ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
								<p class="wbtm-cpn__hint"><?php esc_html_e( 'A bus qualifies if it is in the Buses list OR belongs to a selected type.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							</div>

							<hr class="wbtm-cpn__rule">

							<div class="wbtm-cpn__grid wbtm-cpn__grid--3">
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_min_spend"><?php esc_html_e( 'Minimum Fare', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<div class="wbtm-cpn__inputgroup">
										<span class="wbtm-cpn__addon"><?php echo esc_html( get_woocommerce_currency_symbol() ); ?></span>
										<input type="number" step="0.01" min="0" id="wbtm_cpn_min_spend" name="wbtm_coupon[min_spend]" value="<?php echo esc_attr( $min_spend ); ?>" placeholder="0">
									</div>
								</div>
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_min_seats"><?php esc_html_e( 'Minimum Seats', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<input type="number" step="1" min="0" id="wbtm_cpn_min_seats" name="wbtm_coupon[min_seats]" value="<?php echo esc_attr( $min_seats ); ?>" placeholder="0">
								</div>
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_max_seats"><?php esc_html_e( 'Maximum Seats', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<input type="number" step="1" min="0" id="wbtm_cpn_max_seats" name="wbtm_coupon[max_seats]" value="<?php echo esc_attr( $max_seats ); ?>" placeholder="<?php esc_attr_e( '0 = no limit', 'bus-ticket-booking-with-seat-reservation' ); ?>">
								</div>
							</div>
						</section>

						<!-- VALIDITY & SCHEDULE -->
						<section class="wbtm-cpn__panel" data-panel="schedule">
							<div class="wbtm-cpn__grid">
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_date_start"><?php esc_html_e( 'Active From', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<input type="date" id="wbtm_cpn_date_start" name="wbtm_coupon[date_start]" value="<?php echo esc_attr( $date_start ); ?>">
								</div>
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_date_end"><?php esc_html_e( 'Active Until', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<input type="date" id="wbtm_cpn_date_end" name="wbtm_coupon[date_end]" value="<?php echo esc_attr( $date_end ); ?>">
								</div>
							</div>
							<p class="wbtm-cpn__hint"><?php esc_html_e( 'Leave blank for no start / end limit.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>

							<div class="wbtm-cpn__field">
								<label><?php esc_html_e( 'Valid Days of Week', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
								<div class="wbtm-cpn__days">
									<?php foreach ( WBTM_Coupon_Module::week_days() as $dnum => $dlabel ) : ?>
										<label class="wbtm-cpn__day <?php echo in_array( (string) $dnum, $days_of_week, true ) ? 'is-active' : ''; ?>">
											<input type="checkbox" name="wbtm_coupon[days_of_week][]" value="<?php echo esc_attr( $dnum ); ?>" <?php checked( in_array( (string) $dnum, $days_of_week, true ) ); ?>>
											<?php echo esc_html( $dlabel ); ?>
										</label>
									<?php endforeach; ?>
								</div>
								<p class="wbtm-cpn__hint"><?php esc_html_e( 'None selected = valid every day.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
							</div>

							<hr class="wbtm-cpn__rule">

							<div class="wbtm-cpn__grid">
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_travel_start"><?php esc_html_e( 'Travel Date From', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<input type="date" id="wbtm_cpn_travel_start" name="wbtm_coupon[travel_start]" value="<?php echo esc_attr( $travel_start ); ?>">
								</div>
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_travel_end"><?php esc_html_e( 'Travel Date Until', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<input type="date" id="wbtm_cpn_travel_end" name="wbtm_coupon[travel_end]" value="<?php echo esc_attr( $travel_end ); ?>">
								</div>
							</div>
							<p class="wbtm-cpn__hint"><?php esc_html_e( 'Restrict the coupon to journeys departing within this date range. Leave blank for any travel date.', 'bus-ticket-booking-with-seat-reservation' ); ?></p>
						</section>

						<!-- USAGE & ELIGIBILITY -->
						<section class="wbtm-cpn__panel" data-panel="usage">
							<div class="wbtm-cpn__grid wbtm-cpn__grid--3">
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_limit_total"><?php esc_html_e( 'Total Usage Limit', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<input type="number" step="1" min="0" id="wbtm_cpn_limit_total" name="wbtm_coupon[usage_limit_total]" value="<?php echo esc_attr( $limit_total ); ?>" placeholder="<?php esc_attr_e( '0 = unlimited', 'bus-ticket-booking-with-seat-reservation' ); ?>">
								</div>
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_limit_user"><?php esc_html_e( 'Limit Per Customer', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<input type="number" step="1" min="0" id="wbtm_cpn_limit_user" name="wbtm_coupon[usage_limit_per_user]" value="<?php echo esc_attr( $limit_per_user ); ?>" placeholder="<?php esc_attr_e( '0 = unlimited', 'bus-ticket-booking-with-seat-reservation' ); ?>">
								</div>
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_limit_day"><?php esc_html_e( 'Limit Per Day', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<input type="number" step="1" min="0" id="wbtm_cpn_limit_day" name="wbtm_coupon[usage_limit_per_day]" value="<?php echo esc_attr( $limit_per_day ); ?>" placeholder="<?php esc_attr_e( '0 = unlimited', 'bus-ticket-booking-with-seat-reservation' ); ?>">
								</div>
							</div>

							<div class="wbtm-cpn__toggles">
								<label class="wbtm-cpn__switch">
									<input type="checkbox" name="wbtm_coupon[login_required]" value="yes" <?php checked( $login_required ); ?>>
									<span class="wbtm-cpn__slider"></span>
									<span class="wbtm-cpn__switch-txt"><?php esc_html_e( 'Require customer to be logged in', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								</label>
								<label class="wbtm-cpn__switch">
									<input type="checkbox" name="wbtm_coupon[first_booking_only]" value="yes" <?php checked( $first_only ); ?>>
									<span class="wbtm-cpn__slider"></span>
									<span class="wbtm-cpn__switch-txt"><?php esc_html_e( 'First booking only (new customers)', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								</label>
								<label class="wbtm-cpn__switch">
									<input type="checkbox" name="wbtm_coupon[stackable]" value="yes" <?php checked( $stackable ); ?>>
									<span class="wbtm-cpn__slider"></span>
									<span class="wbtm-cpn__switch-txt"><?php esc_html_e( 'Allow combining with other coupons', 'bus-ticket-booking-with-seat-reservation' ); ?></span>
								</label>
							</div>

							<div class="wbtm-cpn__grid">
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_roles"><?php esc_html_e( 'Restrict to User Roles', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<select id="wbtm_cpn_roles" name="wbtm_coupon[allowed_roles][]" multiple class="wbtm-cpn__select2" data-placeholder="<?php esc_attr_e( 'Any role', 'bus-ticket-booking-with-seat-reservation' ); ?>">
										<?php foreach ( $this->get_roles() as $rkey => $rname ) : ?>
											<option value="<?php echo esc_attr( $rkey ); ?>" <?php selected( in_array( $rkey, (array) $allowed_roles, true ) ); ?>><?php echo esc_html( $rname ); ?></option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="wbtm-cpn__field">
									<label for="wbtm_cpn_emails"><?php esc_html_e( 'Restrict to Specific Emails', 'bus-ticket-booking-with-seat-reservation' ); ?></label>
									<textarea id="wbtm_cpn_emails" name="wbtm_coupon[allowed_emails]" rows="3" placeholder="<?php esc_attr_e( 'one email per line', 'bus-ticket-booking-with-seat-reservation' ); ?>"><?php echo esc_textarea( implode( "\n", (array) $allowed_emails ) ); ?></textarea>
								</div>
							</div>

							<div class="wbtm-cpn__stat">
								<span class="dashicons dashicons-chart-bar"></span>
								<?php
									printf(
										/* translators: %s: number of times the coupon has been used */
										esc_html__( 'Used %s time(s) so far.', 'bus-ticket-booking-with-seat-reservation' ),
										'<strong>' . esc_html( $used_count ) . '</strong>'
									);
								?>
							</div>
						</section>

					</div>
				</div>
				<?php
			}

			/* ============================================================
			 *  Data helpers for the pickers
			 * ============================================================ */

			private function get_buses() {
				$list  = array();
				$query = new WP_Query( array(
					'post_type'              => WBTM_Functions::get_cpt(),
					'post_status'            => array( 'publish', 'draft', 'pending' ),
					'posts_per_page'         => 500,
					'orderby'                => 'title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'fields'                 => 'ids',
				) );
				foreach ( $query->posts as $bid ) {
					$list[ (int) $bid ] = get_the_title( $bid ) . ' (#' . (int) $bid . ')';
				}
				return $list;
			}

			private function get_bus_cats() {
				$list  = array();
				$terms = get_terms( array(
					'taxonomy'   => 'wbtm_bus_cat',
					'hide_empty' => false,
				) );
				if ( ! is_wp_error( $terms ) ) {
					foreach ( $terms as $term ) {
						$list[ (int) $term->term_id ] = $term->name;
					}
				}
				return $list;
			}

			private function get_roles() {
				if ( ! function_exists( 'get_editable_roles' ) ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
				}
				$roles = array();
				foreach ( get_editable_roles() as $key => $data ) {
					$roles[ $key ] = translate_user_role( $data['name'] );
				}
				return $roles;
			}

			/* ============================================================
			 *  Save handler
			 * ============================================================ */

			public function save( $post_id, $post ) {
				// Standard guards.
				if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
					return;
				}
				if ( wp_is_post_revision( $post_id ) ) {
					return;
				}
				if ( ! isset( $_POST['wbtm_coupon_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wbtm_coupon_nonce'] ) ), 'wbtm_coupon_save' ) ) {
					return;
				}
				if ( ! current_user_can( 'edit_post', $post_id ) ) {
					return;
				}
				if ( ! isset( $_POST['wbtm_coupon'] ) || ! is_array( $_POST['wbtm_coupon'] ) ) {
					return;
				}
				$raw = wp_unslash( $_POST['wbtm_coupon'] );
				$m   = WBTM_Coupon_Module::META;

				/* -- Code (normalised + de-duplicated) -- */
				$code = WBTM_Coupon_Module::normalize_code( isset( $raw['code'] ) ? $raw['code'] : '' );
				if ( $code === '' ) {
					$code = WBTM_Coupon_Module::normalize_code( $post->post_title );
				}
				if ( $code === '' ) {
					$code = 'CPN' . $post_id;
				}
				$code = $this->unique_code( $code, $post_id );
				update_post_meta( $post_id, $m . 'code', $code );

				/* -- General & discount -- */
				update_post_meta( $post_id, $m . 'disabled', $this->onoff( $raw, 'disabled' ) );
				$type = isset( $raw['discount_type'] ) && array_key_exists( $raw['discount_type'], WBTM_Coupon_Module::discount_types() ) ? $raw['discount_type'] : 'percent';
				update_post_meta( $post_id, $m . 'discount_type', $type );
				update_post_meta( $post_id, $m . 'amount', $this->num( $raw, 'amount' ) );
				update_post_meta( $post_id, $m . 'max_discount', $this->num( $raw, 'max_discount' ) );

				/* -- Targeting & restrictions -- */
				$apply_to = ( isset( $raw['apply_to'] ) && $raw['apply_to'] === 'specific' ) ? 'specific' : 'all';
				update_post_meta( $post_id, $m . 'apply_to', $apply_to );
				update_post_meta( $post_id, $m . 'bus_ids', $this->int_list( $raw, 'bus_ids' ) );
				update_post_meta( $post_id, $m . 'bus_cats', $this->int_list( $raw, 'bus_cats' ) );
				update_post_meta( $post_id, $m . 'min_spend', $this->num( $raw, 'min_spend' ) );
				update_post_meta( $post_id, $m . 'min_seats', $this->int( $raw, 'min_seats' ) );
				update_post_meta( $post_id, $m . 'max_seats', $this->int( $raw, 'max_seats' ) );

				/* -- Validity & schedule -- */
				update_post_meta( $post_id, $m . 'date_start', $this->date( $raw, 'date_start' ) );
				update_post_meta( $post_id, $m . 'date_end', $this->date( $raw, 'date_end' ) );
				update_post_meta( $post_id, $m . 'days_of_week', $this->days( $raw ) );
				update_post_meta( $post_id, $m . 'travel_start', $this->date( $raw, 'travel_start' ) );
				update_post_meta( $post_id, $m . 'travel_end', $this->date( $raw, 'travel_end' ) );

				/* -- Usage & eligibility -- */
				update_post_meta( $post_id, $m . 'usage_limit_total', $this->int( $raw, 'usage_limit_total' ) );
				update_post_meta( $post_id, $m . 'usage_limit_per_user', $this->int( $raw, 'usage_limit_per_user' ) );
				update_post_meta( $post_id, $m . 'usage_limit_per_day', $this->int( $raw, 'usage_limit_per_day' ) );
				update_post_meta( $post_id, $m . 'login_required', $this->onoff( $raw, 'login_required' ) );
				update_post_meta( $post_id, $m . 'first_booking_only', $this->onoff( $raw, 'first_booking_only' ) );
				update_post_meta( $post_id, $m . 'stackable', $this->onoff( $raw, 'stackable' ) );
				update_post_meta( $post_id, $m . 'allowed_roles', $this->role_list( $raw ) );
				update_post_meta( $post_id, $m . 'allowed_emails', $this->email_list( $raw ) );
			}

			/* ---- sanitising primitives ---- */

			private function onoff( $raw, $key ) {
				return ( isset( $raw[ $key ] ) && $raw[ $key ] === 'yes' ) ? 'yes' : 'no';
			}

			private function num( $raw, $key ) {
				if ( ! isset( $raw[ $key ] ) || $raw[ $key ] === '' ) {
					return '';
				}
				$v = (float) $raw[ $key ];
				return $v < 0 ? '0' : (string) $v;
			}

			private function int( $raw, $key ) {
				if ( ! isset( $raw[ $key ] ) || $raw[ $key ] === '' ) {
					return '';
				}
				$v = (int) $raw[ $key ];
				return $v < 0 ? '0' : (string) $v;
			}

			private function int_list( $raw, $key ) {
				if ( empty( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
					return array();
				}
				return array_values( array_unique( array_filter( array_map( 'intval', $raw[ $key ] ) ) ) );
			}

			private function date( $raw, $key ) {
				if ( empty( $raw[ $key ] ) ) {
					return '';
				}
				$v = sanitize_text_field( $raw[ $key ] );
				// Expect YYYY-MM-DD from <input type=date>.
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
			}

			private function days( $raw ) {
				if ( empty( $raw['days_of_week'] ) || ! is_array( $raw['days_of_week'] ) ) {
					return array();
				}
				$valid = array( '0', '1', '2', '3', '4', '5', '6' );
				$out   = array();
				foreach ( $raw['days_of_week'] as $d ) {
					$d = (string) (int) $d;
					if ( in_array( $d, $valid, true ) ) {
						$out[] = $d;
					}
				}
				return array_values( array_unique( $out ) );
			}

			private function role_list( $raw ) {
				if ( empty( $raw['allowed_roles'] ) || ! is_array( $raw['allowed_roles'] ) ) {
					return array();
				}
				$editable = array_keys( $this->get_roles() );
				$out      = array();
				foreach ( $raw['allowed_roles'] as $r ) {
					$r = sanitize_key( $r );
					if ( in_array( $r, $editable, true ) ) {
						$out[] = $r;
					}
				}
				return array_values( array_unique( $out ) );
			}

			private function email_list( $raw ) {
				if ( empty( $raw['allowed_emails'] ) ) {
					return array();
				}
				$parts = preg_split( '/[\s,;]+/', (string) $raw['allowed_emails'] );
				$out   = array();
				foreach ( (array) $parts as $email ) {
					$email = sanitize_email( trim( $email ) );
					if ( $email && is_email( $email ) ) {
						$out[] = strtolower( $email );
					}
				}
				return array_values( array_unique( $out ) );
			}

			/** Guarantee the code is unique among published coupons (append -2, -3 …). */
			private function unique_code( $code, $post_id ) {
				$base   = $code;
				$suffix = 2;
				while ( true ) {
					$existing = WBTM_Coupon_Module::find_by_code( $code );
					if ( ! $existing || $existing === (int) $post_id ) {
						return $code;
					}
					$code = $base . '-' . $suffix;
					$suffix++;
					if ( $suffix > 50 ) {
						return $base . '-' . $post_id; // Pathological fallback.
					}
				}
			}
		}
	}
