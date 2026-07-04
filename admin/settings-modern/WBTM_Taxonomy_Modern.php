<?php
	/*
	 * Modern reskin of the bus-related taxonomy screens (edit-tags.php /
	 * term.php for Bus Type, Bus Stops, Pickup Point, Drop-Off Point, and
	 * Bus Features) — pure CSS/JS on top of WordPress's own term list +
	 * add-term form, same approach as the modern bus editor: the underlying
	 * markup, field names, and save handling are all core WordPress and stay
	 * untouched, only the presentation changes. Also drops the Screen Options
	 * tab on these screens, since "items per page" doesn't apply to these
	 * short, fixed lists.
	 *
	 * One instance serves all five taxonomies (rather than one instance per
	 * taxonomy) so there's a single set of AJAX actions — every request
	 * names its taxonomy explicitly and it's checked against the whitelist
	 * below before anything touches the database.
	 *
	 * @Author MagePeople Team
	 */
	if (!defined('ABSPATH')) {
		die;
	}
	if (!class_exists('WBTM_Taxonomy_Modern')) {
		class WBTM_Taxonomy_Modern {
			const TAXONOMIES = [
				'wbtm_bus_cat',
				'wbtm_bus_stops',
				'wbtm_bus_pickpoint',
				'wbtm_bus_drop_off',
				'wbtm_bus_feature',
			];

			/** Only Bus Features carries an icon — same term meta key WTBM_Features_Seating already uses elsewhere in the plugin, so both stay in sync. */
			const FEATURE_TAXONOMY  = 'wbtm_bus_feature';
			const FEATURE_ICON_META = 'wbtm_bus_feature_icon';

			/**
			 * Pickup Point and Drop-Off Point terms both belong "under" a Bus
			 * Stop — stored as term meta on the pickup/drop-off term, pointing
			 * at a wbtm_bus_stops term ID. Only these two taxonomies get the
			 * renamed Name field, hidden Slug field, and this dropdown in the
			 * popup, plus the extra list-table column showing the chosen stop.
			 */
			const STOP_LINKED_TAXONOMIES = ['wbtm_bus_pickpoint', 'wbtm_bus_drop_off'];
			const STOPS_TAXONOMY         = 'wbtm_bus_stops';
			const UNDER_STOP_META        = 'wbtm_under_bus_stop';
			const UNDER_STOP_COLUMN      = 'wbtm_under_stop';

			/**
			 * Host taxonomy (its own admin screen) => secondary taxonomy whose
			 * whole term-management UI (list + Add/Edit/Delete) is rendered as
			 * an extra section on the host's screen instead of getting its own
			 * submenu. Drop-Off Point no longer has a menu entry of its own
			 * (see WBTM_Taxonomy::taxonomy() / show_in_menu => false) — its
			 * content lives at the bottom of the Pickup Point screen.
			 */
			const MERGED_TAXONOMIES = [
				'wbtm_bus_pickpoint' => 'wbtm_bus_drop_off',
			];

			public function __construct() {
				add_filter('screen_options_show_screen', [$this, 'maybe_hide_screen_options'], 10, 2);
				add_action('admin_enqueue_scripts', [$this, 'enqueue']);
				add_action('wp_ajax_wbtm_add_bus_type', [$this, 'ajax_add_bus_type']);
				add_action('wp_ajax_wbtm_get_bus_type', [$this, 'ajax_get_bus_type']);
				add_action('wp_ajax_wbtm_edit_bus_type', [$this, 'ajax_edit_bus_type']);
				add_action('wp_ajax_wbtm_delete_bus_type', [$this, 'ajax_delete_bus_type']);
				foreach (self::MERGED_TAXONOMIES as $host_taxonomy => $secondary_taxonomy) {
					add_action("after-{$host_taxonomy}-table", [$this, 'render_merged_taxonomy_section']);
				}
				foreach (self::STOP_LINKED_TAXONOMIES as $stop_linked_taxonomy) {
					add_filter("manage_edit-{$stop_linked_taxonomy}_columns", [$this, 'add_under_stop_column']);
					add_filter("manage_{$stop_linked_taxonomy}_custom_column", [$this, 'render_under_stop_column'], 10, 3);
				}
			}

			/** Inserts the "Under Bus Stop" column right after Description in the core term list table. */
			public function add_under_stop_column($columns) {
				$with_under_stop = [];
				foreach ($columns as $key => $label) {
					$with_under_stop[$key] = $label;
					if ('description' === $key) {
						$with_under_stop[self::UNDER_STOP_COLUMN] = esc_html__('Under Bus Stop', 'bus-ticket-booking-with-seat-reservation');
					}
				}
				return $with_under_stop;
			}

			/** Cell content for the "Under Bus Stop" column in the core term list table. */
			public function render_under_stop_column($value, $column_name, $term_id) {
				if (self::UNDER_STOP_COLUMN !== $column_name) {
					return $value;
				}
				$stop_name = $this->under_stop_name((int) $term_id);
				return $stop_name !== '' ? esc_html($stop_name) : '&#8212;';
			}

			/** Current screen's taxonomy, but only if it's one we reskin — null otherwise. */
			private function current_target_taxonomy() {
				if (!function_exists('get_current_screen')) {
					return null;
				}
				$screen = get_current_screen();
				if ($screen && isset($screen->taxonomy) && in_array($screen->taxonomy, self::TAXONOMIES, true)) {
					return $screen->taxonomy;
				}
				return null;
			}

			/**
			 * Taxonomy named in the AJAX request, validated against the
			 * whitelist — every handler below calls this first so a request
			 * can never operate on a taxonomy outside this reskin's scope.
			 */
			private function taxonomy_from_request() {
				$taxonomy = isset($_POST['taxonomy']) ? sanitize_key(wp_unslash($_POST['taxonomy'])) : '';
				return in_array($taxonomy, self::TAXONOMIES, true) ? $taxonomy : '';
			}

			/** Hides the Screen Options tab on both the term list/add page and the single edit-term page. */
			public function maybe_hide_screen_options($show_screen, $screen) {
				if (isset($screen->taxonomy) && in_array($screen->taxonomy, self::TAXONOMIES, true)) {
					return false;
				}
				return $show_screen;
			}

			/** Cache-bust on file change so edits show without a manual hard-refresh. */
			private function asset_ver($rel_path) {
				$file = WBTM_PLUGIN_DIR . $rel_path;
				return file_exists($file) ? (string) filemtime($file) : WBTM_VERSION;
			}

			/**
			 * Hand-illustrated Name/Slug examples and a one-line subheading per
			 * taxonomy — the two things WordPress's own taxonomy labels can't
			 * supply. Everything else (headings, button/modal copy) is built
			 * from $tax->labels in enqueue() instead of hardcoded here, so it
			 * can't drift out of sync with how the taxonomy is registered.
			 */
			private function copy_for($taxonomy) {
				$map = [
					'wbtm_bus_cat'        => [
						'subheading'      => __('Configure and organize different classifications for your fleet. These categories help in scheduling and maintenance tracking.', 'bus-ticket-booking-with-seat-reservation'),
						'namePlaceholder' => __('e.g. Luxury Coach', 'bus-ticket-booking-with-seat-reservation'),
						'slugPlaceholder' => 'luxury-coach',
						'nameFieldLabel'  => __('Name', 'bus-ticket-booking-with-seat-reservation'),
					],
					'wbtm_bus_stops'      => [
						'subheading'      => __('Manage the stops your routes pass through. A stop can be reused as a boarding or dropping point on any route.', 'bus-ticket-booking-with-seat-reservation'),
						'namePlaceholder' => __('e.g. Central Station', 'bus-ticket-booking-with-seat-reservation'),
						'slugPlaceholder' => 'central-station',
						'nameFieldLabel'  => __('Name', 'bus-ticket-booking-with-seat-reservation'),
					],
					'wbtm_bus_pickpoint'  => [
						'subheading'      => __('Define the pickup points passengers can choose when boarding a bus on its route.', 'bus-ticket-booking-with-seat-reservation'),
						'namePlaceholder' => __('e.g. Downtown Terminal', 'bus-ticket-booking-with-seat-reservation'),
						'slugPlaceholder' => 'downtown-terminal',
						'nameFieldLabel'  => __('Pickup Point Name', 'bus-ticket-booking-with-seat-reservation'),
					],
					'wbtm_bus_drop_off'   => [
						'subheading'      => __('Define the drop-off points passengers can choose when a bus reaches the end of its route.', 'bus-ticket-booking-with-seat-reservation'),
						'namePlaceholder' => __('e.g. Airport Terminal 2', 'bus-ticket-booking-with-seat-reservation'),
						'slugPlaceholder' => 'airport-terminal-2',
						'nameFieldLabel'  => __('Drop-off Point Name', 'bus-ticket-booking-with-seat-reservation'),
					],
					'wbtm_bus_feature'    => [
						'subheading'      => __('Highlight onboard amenities — WiFi, charging ports, reclining seats — so passengers know what to expect before they book.', 'bus-ticket-booking-with-seat-reservation'),
						'namePlaceholder' => __('e.g. Onboard WiFi', 'bus-ticket-booking-with-seat-reservation'),
						'slugPlaceholder' => 'onboard-wifi',
						'nameFieldLabel'  => __('Name', 'bus-ticket-booking-with-seat-reservation'),
					],
				];
				return isset($map[$taxonomy]) ? $map[$taxonomy] : [
					'subheading'      => '',
					'namePlaceholder' => __('e.g. New Item', 'bus-ticket-booking-with-seat-reservation'),
					'slugPlaceholder' => 'new-item',
					'nameFieldLabel'  => __('Name', 'bus-ticket-booking-with-seat-reservation'),
				];
			}

			/** term_id => bus stop name, for every Bus Stop term — used both for the popup's dropdown options and the "Under Bus Stop" column. */
			private function get_bus_stops() {
				$terms = get_terms(
					[
						'taxonomy'   => self::STOPS_TAXONOMY,
						'hide_empty' => false,
						'orderby'    => 'name',
						'order'      => 'ASC',
					]
				);
				if (is_wp_error($terms)) {
					return [];
				}
				$stops = [];
				foreach ($terms as $term) {
					$stops[] = ['id' => (int) $term->term_id, 'name' => $term->name];
				}
				return $stops;
			}

			/** The Bus Stop name a Pickup/Drop-Off Point term is assigned to, or '' if none/not applicable. */
			private function under_stop_name($term_id) {
				$stop_id = (int) get_term_meta($term_id, self::UNDER_STOP_META, true);
				if (!$stop_id) {
					return '';
				}
				$stop = get_term($stop_id, self::STOPS_TAXONOMY);
				return ($stop && !is_wp_error($stop)) ? $stop->name : '';
			}

			/**
			 * Everything the popup and heading need for one taxonomy: built once
			 * here so both the host screen (enqueue()) and a merged section
			 * (render_merged_taxonomy_section()) can ask for the copy of whichever
			 * taxonomy they're currently rendering/acting on, instead of always
			 * assuming "the current screen's taxonomy".
			 */
			private function per_taxonomy_bundle($taxonomy) {
				$tax_object = get_taxonomy($taxonomy);
				if (!$tax_object) {
					return null;
				}
				$labels      = $tax_object->labels;
				$singular    = $labels->singular_name;
				$singular_lc = function_exists('mb_strtolower') ? mb_strtolower($singular) : strtolower($singular);
				$copy        = $this->copy_for($taxonomy);

				return [
					/* translators: %s: taxonomy name, e.g. "Bus Type" */
					'heading'         => sprintf(esc_html__('%s Management', 'bus-ticket-booking-with-seat-reservation'), $labels->name),
					'subheading'      => esc_html($copy['subheading']),
					/* translators: %s: singular taxonomy label, e.g. "Bus Type" */
					'addButtonLabel'  => sprintf(esc_html__('+ Add New %s', 'bus-ticket-booking-with-seat-reservation'), $singular),
					/* translators: %s: singular taxonomy label, e.g. "Bus Type" */
					'modalTitle'      => sprintf(esc_html__('Add New %s', 'bus-ticket-booking-with-seat-reservation'), $singular),
					/* translators: %s: singular taxonomy label, e.g. "Bus Type" */
					'editModalTitle'  => sprintf(esc_html__('Edit %s', 'bus-ticket-booking-with-seat-reservation'), $singular),
					'namePlaceholder' => esc_attr($copy['namePlaceholder']),
					'nameFieldLabel'  => esc_html($copy['nameFieldLabel']),
					'slugPlaceholder' => esc_attr($copy['slugPlaceholder']),
					/* translators: %s: singular taxonomy label, lowercased, e.g. "bus type" */
					'descPlaceholder' => sprintf(esc_attr__('Enter a detailed description of this %s…', 'bus-ticket-booking-with-seat-reservation'), $singular_lc),
					/* translators: %s: singular taxonomy label, e.g. "Bus Type" */
					'submit'          => sprintf(esc_html__('Add New %s', 'bus-ticket-booking-with-seat-reservation'), $singular),
					/* translators: %s: singular taxonomy label, lowercased, e.g. "bus type" */
					'confirmDelete'   => sprintf(esc_html__('Delete this %s? This cannot be undone.', 'bus-ticket-booking-with-seat-reservation'), $singular_lc),
					// Pickup/Drop-Off Point only: the popup swaps its Slug field
					// for the "Under Bus Stop" dropdown when this is true.
					'linkedToStops'   => in_array($taxonomy, self::STOP_LINKED_TAXONOMIES, true),
				];
			}

			/**
			 * Renders the merged secondary taxonomy's whole management UI (heading,
			 * "Add New" button, term table with Edit/Delete) as an extra section
			 * appended to the host taxonomy's screen, right after its own term
			 * table — hooked to core's `after-{$taxonomy}-table` action, which
			 * fires while still inside #col-right .col-wrap, so this section lands
			 * in the same card as the host's own list.
			 *
			 * The rows are plain server-rendered markup in the same "already
			 * modernized" shape buildActionsColumn() in JS produces for the host
			 * table (icon Edit/Delete column, slug badge, no name link) — there's
			 * no separate raw-WordPress-table-then-JS-transform pass for this one,
			 * since it never has to work without our own JS/CSS enqueued (both are
			 * only ever loaded together, from this same class, for this exact
			 * taxonomy pairing). Edit/Delete still carry real, nonced core hrefs
			 * as a no-JS fallback.
			 *
			 * @param string $host_taxonomy The taxonomy slug of the screen this fired on.
			 */
			public function render_merged_taxonomy_section($host_taxonomy) {
				if (!isset(self::MERGED_TAXONOMIES[$host_taxonomy])) {
					return;
				}
				$taxonomy = self::MERGED_TAXONOMIES[$host_taxonomy];

				$tax_object = get_taxonomy($taxonomy);
				if (!$tax_object || !current_user_can($tax_object->cap->manage_terms)) {
					return;
				}

				$bundle = $this->per_taxonomy_bundle($taxonomy);
				if (!$bundle) {
					return;
				}

				$terms = get_terms(
					[
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'orderby'    => 'name',
						'order'      => 'ASC',
					]
				);
				if (is_wp_error($terms)) {
					$terms = [];
				}

				$can_edit   = current_user_can($tax_object->cap->edit_terms);
				$can_delete = current_user_can($tax_object->cap->delete_terms);
				?>
				<div class="wbtm-merged-section" data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
					<div class="wbtm-merged-section-header">
						<div>
							<h2><?php echo esc_html($bundle['heading']); ?></h2>
							<?php if ($bundle['subheading']) : ?>
								<p class="wbtm-taxonomy-subtitle"><?php echo esc_html($bundle['subheading']); ?></p>
							<?php endif; ?>
						</div>
						<?php if ($can_edit) : ?>
							<button type="button" class="button button-primary wbtm-add-bus-type-btn"><?php echo esc_html($bundle['addButtonLabel']); ?></button>
						<?php endif; ?>
					</div>
					<table class="wp-list-table widefat fixed striped">
						<thead>
						<tr>
							<th class="column-name"><?php esc_html_e('Name', 'bus-ticket-booking-with-seat-reservation'); ?></th>
							<th class="column-description"><?php esc_html_e('Description', 'bus-ticket-booking-with-seat-reservation'); ?></th>
							<th class="column-wbtm-understop"><?php esc_html_e('Under Bus Stop', 'bus-ticket-booking-with-seat-reservation'); ?></th>
							<th class="column-slug"><?php esc_html_e('Slug', 'bus-ticket-booking-with-seat-reservation'); ?></th>
							<th class="column-posts"><?php echo esc_html(_x('Count', 'Number/count of items', 'bus-ticket-booking-with-seat-reservation')); ?></th>
							<th class="column-wbtm-actions"><?php esc_html_e('Actions', 'bus-ticket-booking-with-seat-reservation'); ?></th>
						</tr>
						</thead>
						<tbody>
						<?php if (empty($terms)) : ?>
							<tr class="no-items">
								<td colspan="6"><?php echo esc_html($tax_object->labels->not_found); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ($terms as $term) : ?>
								<tr id="tag-<?php echo (int) $term->term_id; ?>">
									<td class="column-name"><span class="row-title"><?php echo esc_html($term->name); ?></span></td>
									<td class="column-description"><?php echo esc_html($term->description); ?></td>
									<td class="column-wbtm-understop">
										<?php $stop_name = $this->under_stop_name((int) $term->term_id); ?>
										<?php echo $stop_name !== '' ? esc_html($stop_name) : '&#8212;'; ?>
									</td>
									<td class="column-slug"><span class="wbtm-slug-badge"><?php echo esc_html($term->slug); ?></span></td>
									<td class="column-posts">
										<?php if ((int) $term->count > 0) : ?>
											<a href="<?php echo esc_url(admin_url('edit.php?post_type=wbtm_bus&' . $taxonomy . '=' . $term->slug)); ?>"><?php echo (int) $term->count; ?></a>
										<?php else : ?>
											<?php echo (int) $term->count; ?>
										<?php endif; ?>
									</td>
									<td class="column-wbtm-actions">
										<?php if ($can_edit) : ?>
											<a href="<?php echo esc_url(get_edit_term_link($term, $taxonomy, 'wbtm_bus')); ?>" class="wbtm-row-action-icon wbtm-row-action-edit" data-term-id="<?php echo (int) $term->term_id; ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: term name */ __('Edit &#8220;%s&#8221;', 'bus-ticket-booking-with-seat-reservation'), $term->name)); ?>"><span class="dashicons dashicons-edit" aria-hidden="true"></span></a>
										<?php endif; ?>
										<?php if ($can_delete) : ?>
											<a href="<?php echo esc_url(wp_nonce_url("edit-tags.php?action=delete&taxonomy={$taxonomy}&tag_ID={$term->term_id}", 'delete-tag_' . $term->term_id)); ?>" class="wbtm-row-action-icon wbtm-row-action-delete" data-term-id="<?php echo (int) $term->term_id; ?>" aria-label="<?php echo esc_attr(sprintf(/* translators: %s: term name */ __('Delete &#8220;%s&#8221;', 'bus-ticket-booking-with-seat-reservation'), $term->name)); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
						</tbody>
					</table>
				</div>
				<?php
			}

			/** term_id => icon class, for every Bus Feature term that has one — used to prepend the icon before the name in the list table. */
			private function get_feature_icons() {
				$terms = get_terms(
					[
						'taxonomy'   => self::FEATURE_TAXONOMY,
						'hide_empty' => false,
					]
				);
				if (is_wp_error($terms) || empty($terms)) {
					return [];
				}
				$icons = [];
				foreach ($terms as $term) {
					$icon = get_term_meta($term->term_id, self::FEATURE_ICON_META, true);
					if ($icon) {
						$icons[$term->term_id] = $icon;
					}
				}
				return $icons;
			}

			/**
			 * Popup's AJAX submit handler. Deliberately a plain wp_send_json_*
			 * endpoint (not WordPress's legacy WP_Ajax_Response-based 'add-tag'
			 * action) — same wp_insert_term() call underneath, but a response
			 * shape that's trivial for the popup JS to consume; the term list
			 * is simply reloaded from the server on success rather than
			 * patched in place.
			 */
			public function ajax_add_bus_type() {
				check_ajax_referer('wbtm_add_bus_type', 'nonce');

				$taxonomy = $this->taxonomy_from_request();
				$taxonomy_object = $taxonomy ? get_taxonomy($taxonomy) : false;
				if (!$taxonomy_object || !current_user_can($taxonomy_object->cap->edit_terms)) {
					wp_send_json_error(['message' => esc_html__('You are not allowed to add items here.', 'bus-ticket-booking-with-seat-reservation')], 403);
				}

				$name = isset($_POST['tag-name']) ? sanitize_text_field(wp_unslash($_POST['tag-name'])) : '';
				if ($name === '') {
					wp_send_json_error(['message' => esc_html__('Please enter a name.', 'bus-ticket-booking-with-seat-reservation')], 422);
				}

				$args = [
					'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
				];
				$slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
				if ($slug !== '') {
					$args['slug'] = $slug;
				}

				$result = wp_insert_term($name, $taxonomy, $args);

				if (is_wp_error($result)) {
					wp_send_json_error(['message' => $result->get_error_message()], 400);
				}

				if ($taxonomy === self::FEATURE_TAXONOMY && isset($_POST[self::FEATURE_ICON_META])) {
					update_term_meta((int) $result['term_id'], self::FEATURE_ICON_META, sanitize_text_field(wp_unslash($_POST[self::FEATURE_ICON_META])));
				}

				if (in_array($taxonomy, self::STOP_LINKED_TAXONOMIES, true)) {
					$under_stop = isset($_POST['under_bus_stop']) ? absint($_POST['under_bus_stop']) : 0;
					update_term_meta((int) $result['term_id'], self::UNDER_STOP_META, $under_stop);
				}

				wp_send_json_success(['message' => esc_html__('Added.', 'bus-ticket-booking-with-seat-reservation')]);
			}

			/**
			 * Feeds the Edit popup: current field values for one term, plus a
			 * parent-option list with that term's own subtree excluded. Kept
			 * as a small round trip (rather than scraping the rendered row)
			 * so the popup always edits fresh, authoritative data.
			 */
			public function ajax_get_bus_type() {
				check_ajax_referer('wbtm_get_bus_type', 'nonce');

				$taxonomy = $this->taxonomy_from_request();
				$taxonomy_object = $taxonomy ? get_taxonomy($taxonomy) : false;
				if (!$taxonomy_object || !current_user_can($taxonomy_object->cap->edit_terms)) {
					wp_send_json_error(['message' => esc_html__('You are not allowed to edit this item.', 'bus-ticket-booking-with-seat-reservation')], 403);
				}

				$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
				$term    = $term_id ? get_term($term_id, $taxonomy) : null;

				if (!$term || is_wp_error($term)) {
					wp_send_json_error(['message' => esc_html__('That item could not be found.', 'bus-ticket-booking-with-seat-reservation')], 404);
				}

				wp_send_json_success(
					[
						'id'          => (int) $term->term_id,
						'name'        => $term->name,
						'slug'        => $term->slug,
						'description' => $term->description,
						'icon'        => $taxonomy === self::FEATURE_TAXONOMY ? get_term_meta($term->term_id, self::FEATURE_ICON_META, true) : '',
						'underStop'   => in_array($taxonomy, self::STOP_LINKED_TAXONOMIES, true) ? (int) get_term_meta($term->term_id, self::UNDER_STOP_META, true) : 0,
					]
				);
			}

			/** Edit popup's submit handler — same validation as Add, via wp_update_term(). */
			public function ajax_edit_bus_type() {
				check_ajax_referer('wbtm_edit_bus_type', 'nonce');

				$taxonomy = $this->taxonomy_from_request();
				$taxonomy_object = $taxonomy ? get_taxonomy($taxonomy) : false;
				if (!$taxonomy_object || !current_user_can($taxonomy_object->cap->edit_terms)) {
					wp_send_json_error(['message' => esc_html__('You are not allowed to edit this item.', 'bus-ticket-booking-with-seat-reservation')], 403);
				}

				$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
				$name    = isset($_POST['tag-name']) ? sanitize_text_field(wp_unslash($_POST['tag-name'])) : '';

				if (!$term_id) {
					wp_send_json_error(['message' => esc_html__('That item could not be found.', 'bus-ticket-booking-with-seat-reservation')], 404);
				}
				if ($name === '') {
					wp_send_json_error(['message' => esc_html__('Please enter a name.', 'bus-ticket-booking-with-seat-reservation')], 422);
				}

				// No 'parent' key here on purpose: wp_update_term() merges this
				// array over the term's own current data, so omitting it keeps
				// whatever parent the term already has rather than resetting it
				// to top-level (there's no parent field in this popup to submit
				// a new value from).
				$args = [
					'name'        => $name,
					'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
				];
				$slug = isset($_POST['slug']) ? sanitize_title(wp_unslash($_POST['slug'])) : '';
				if ($slug !== '') {
					$args['slug'] = $slug;
				}

				$result = wp_update_term($term_id, $taxonomy, $args);

				if (is_wp_error($result)) {
					wp_send_json_error(['message' => $result->get_error_message()], 400);
				}

				if ($taxonomy === self::FEATURE_TAXONOMY && isset($_POST[self::FEATURE_ICON_META])) {
					update_term_meta($term_id, self::FEATURE_ICON_META, sanitize_text_field(wp_unslash($_POST[self::FEATURE_ICON_META])));
				}

				if (in_array($taxonomy, self::STOP_LINKED_TAXONOMIES, true)) {
					$under_stop = isset($_POST['under_bus_stop']) ? absint($_POST['under_bus_stop']) : 0;
					update_term_meta($term_id, self::UNDER_STOP_META, $under_stop);
				}

				wp_send_json_success(['message' => esc_html__('Updated.', 'bus-ticket-booking-with-seat-reservation')]);
			}

			/**
			 * Delete icon's handler — plain wp_send_json_* over wp_delete_term(),
			 * so the confirm-then-delete flow never leaves the taxonomy screen
			 * (unlike a bare GET link to edit-tags.php?action=delete...).
			 */
			public function ajax_delete_bus_type() {
				check_ajax_referer('wbtm_delete_bus_type', 'nonce');

				$taxonomy = $this->taxonomy_from_request();
				$taxonomy_object = $taxonomy ? get_taxonomy($taxonomy) : false;
				if (!$taxonomy_object || !current_user_can($taxonomy_object->cap->delete_terms)) {
					wp_send_json_error(['message' => esc_html__('You are not allowed to delete this item.', 'bus-ticket-booking-with-seat-reservation')], 403);
				}

				$term_id = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;
				if (!$term_id) {
					wp_send_json_error(['message' => esc_html__('That item could not be found.', 'bus-ticket-booking-with-seat-reservation')], 404);
				}

				$result = wp_delete_term($term_id, $taxonomy);

				if (is_wp_error($result)) {
					wp_send_json_error(['message' => $result->get_error_message()], 400);
				}
				if (!$result) {
					wp_send_json_error(['message' => esc_html__('That item could not be found.', 'bus-ticket-booking-with-seat-reservation')], 404);
				}

				wp_send_json_success(['message' => esc_html__('Deleted.', 'bus-ticket-booking-with-seat-reservation')]);
			}

			public function enqueue() {
				$taxonomy = $this->current_target_taxonomy();
				if (!$taxonomy) {
					return;
				}

				$bundle = $this->per_taxonomy_bundle($taxonomy);

				// Per-taxonomy popup copy, keyed by taxonomy slug — always has an
				// entry for the current screen's own taxonomy, plus one more if a
				// secondary taxonomy's management UI is merged onto this screen
				// (see render_merged_taxonomy_section()), so the popup can show the
				// right title/placeholders no matter which "Add"/"Edit"/"Delete"
				// on the page triggered it.
				$per_taxonomy = [$taxonomy => $bundle];
				if (isset(self::MERGED_TAXONOMIES[$taxonomy])) {
					$merged_bundle = $this->per_taxonomy_bundle(self::MERGED_TAXONOMIES[$taxonomy]);
					if ($merged_bundle) {
						$per_taxonomy[self::MERGED_TAXONOMIES[$taxonomy]] = $merged_bundle;
					}
				}

				// Bus Features gets an icon field, reusing the plugin's existing
				// icon-library picker (WBTM_Select_Icon_image) verbatim rather
				// than building a second one — do_action() both renders the
				// hidden-input/preview/button markup we inject into the popup
				// AND (as a side effect) registers the shared picker popup to
				// print once in admin_footer, exactly like WTBM_Features_Seating
				// does for the classic term-edit screen and the bus-edit modal.
				$icon_field_html = '';
				$feature_icons   = [];
				if ($taxonomy === self::FEATURE_TAXONOMY) {
					if (has_action('wbtm_input_add_icon')) {
						ob_start();
						do_action('wbtm_input_add_icon', self::FEATURE_ICON_META);
						$icon_field_html = ob_get_clean();
					}
					$feature_icons = $this->get_feature_icons();
				}

				// Pickup/Drop-Off Point popup dropdown — only fetched when one of
				// them is actually in play on this screen (host or merged section).
				$bus_stops = [];
				$needs_bus_stops = in_array($taxonomy, self::STOP_LINKED_TAXONOMIES, true)
					|| (isset(self::MERGED_TAXONOMIES[$taxonomy]) && in_array(self::MERGED_TAXONOMIES[$taxonomy], self::STOP_LINKED_TAXONOMIES, true));
				if ($needs_bus_stops) {
					$bus_stops = $this->get_bus_stops();
				}

				wp_enqueue_style('wbtm-taxonomy-modern', WBTM_PLUGIN_URL . '/assets/admin/css/wbtm-taxonomy-modern.css', [], $this->asset_ver('/assets/admin/css/wbtm-taxonomy-modern.css'));
				wp_enqueue_script('wbtm-taxonomy-modern', WBTM_PLUGIN_URL . '/assets/admin/js/wbtm-taxonomy-modern.js', [], $this->asset_ver('/assets/admin/js/wbtm-taxonomy-modern.js'), true);

				wp_localize_script(
					'wbtm-taxonomy-modern',
					'wbtmTaxonomyModern',
					[
						'taxonomy'       => $taxonomy,
						'heading'        => $bundle['heading'],
						'subheading'     => $bundle['subheading'],
						'addButtonLabel' => $bundle['addButtonLabel'],
						'ajaxUrl'        => admin_url('admin-ajax.php'),
						'nonce'          => wp_create_nonce('wbtm_add_bus_type'),
						'getNonce'       => wp_create_nonce('wbtm_get_bus_type'),
						'editNonce'      => wp_create_nonce('wbtm_edit_bus_type'),
						'deleteNonce'    => wp_create_nonce('wbtm_delete_bus_type'),
						'iconFieldHtml'  => $icon_field_html,
						'iconMetaKey'    => self::FEATURE_ICON_META,
						'featureIcons'   => $feature_icons,
						'busStops'       => $bus_stops,
						'proUrl'         => admin_url('edit.php?post_type=wbtm_bus&page=admin/WBTM_Welcome'),
						// Keyed by taxonomy slug — the popup looks up whichever
						// taxonomy the clicked Add/Edit/Delete actually belongs to
						// here, rather than always assuming the host screen's own
						// taxonomy (needed once a merged section's own controls,
						// for a different taxonomy, can open the same popup).
						'perTaxonomy'    => $per_taxonomy,
						'strings'        => [
							'modalTitle'       => $bundle['modalTitle'],
							'name'             => esc_html__('Name', 'bus-ticket-booking-with-seat-reservation'),
							'namePlaceholder'  => $bundle['namePlaceholder'],
							'nameHelp'         => esc_html__('The name is how it appears on your site.', 'bus-ticket-booking-with-seat-reservation'),
							'slug'             => esc_html__('Slug', 'bus-ticket-booking-with-seat-reservation'),
							'slugPlaceholder'  => $bundle['slugPlaceholder'],
							'slugHelp'         => esc_html__('The "slug" is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.', 'bus-ticket-booking-with-seat-reservation'),
							'description'      => esc_html__('Description', 'bus-ticket-booking-with-seat-reservation'),
							'descPlaceholder'  => $bundle['descPlaceholder'],
							'descHelp'         => esc_html__('The description is not prominent by default; however, some themes may show it.', 'bus-ticket-booking-with-seat-reservation'),
							'underStop'        => esc_html__('Under Bus Stop', 'bus-ticket-booking-with-seat-reservation'),
							'underStopEmpty'   => esc_html__('— Select a bus stop —', 'bus-ticket-booking-with-seat-reservation'),
							'icon'             => esc_html__('Icon', 'bus-ticket-booking-with-seat-reservation'),
							'iconHelp'         => esc_html__('Shown before the name wherever this feature is listed.', 'bus-ticket-booking-with-seat-reservation'),
							'actionsColumn'    => esc_html__('Actions', 'bus-ticket-booking-with-seat-reservation'),
							'cancel'           => esc_html__('Cancel', 'bus-ticket-booking-with-seat-reservation'),
							'submit'           => $bundle['submit'],
							'submitting'       => esc_html__('Adding…', 'bus-ticket-booking-with-seat-reservation'),
							'genericError'     => esc_html__('Something went wrong. Please try again.', 'bus-ticket-booking-with-seat-reservation'),
							'nameRequired'     => esc_html__('Please enter a name.', 'bus-ticket-booking-with-seat-reservation'),
							'editModalTitle'   => $bundle['editModalTitle'],
							'saveChanges'      => esc_html__('Save Changes', 'bus-ticket-booking-with-seat-reservation'),
							'saving'           => esc_html__('Saving…', 'bus-ticket-booking-with-seat-reservation'),
							'loadingTerm'      => esc_html__('Loading…', 'bus-ticket-booking-with-seat-reservation'),
							'confirmDelete'    => $bundle['confirmDelete'],
							'deleteFailed'     => esc_html__('Could not delete this item. Please try again.', 'bus-ticket-booking-with-seat-reservation'),
							'promoEyebrow'     => esc_html__('WBTM PRO', 'bus-ticket-booking-with-seat-reservation'),
							'promoTitle'       => esc_html__('Get more out of your fleet', 'bus-ticket-booking-with-seat-reservation'),
							'promoBody'        => esc_html__('Unlock advanced passenger tools and route flexibility with WBTM Pro and addons.', 'bus-ticket-booking-with-seat-reservation'),
							'promoCta'         => esc_html__('Explore Pro & Addons', 'bus-ticket-booking-with-seat-reservation'),
						],
						'proFeatures'    => [
							esc_html__('Filterable, exportable passenger lists (PDF & CSV)', 'bus-ticket-booking-with-seat-reservation'),
							esc_html__('Alphanumeric seat-number sorting', 'bus-ticket-booking-with-seat-reservation'),
							esc_html__('Bidirectional stop search on same-bus returns', 'bus-ticket-booking-with-seat-reservation'),
							esc_html__('Editable return routes', 'bus-ticket-booking-with-seat-reservation'),
						],
					]
				);
			}
		}
		new WBTM_Taxonomy_Modern();
	}
