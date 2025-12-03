<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	if (!class_exists('WBTM_Bus_Import')) {
		class WBTM_Bus_Import {
			public function __construct() {
				add_action('admin_menu', array($this, 'add_import_menu'));
				add_action('admin_init', array($this, 'process_import'));
				add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
			}
			/**
			 * Add import menu item to admin menu
			 */
			public function add_import_menu() {
				add_submenu_page(
					'edit.php?post_type=wbtm_bus',
					__('Import Buses', 'bus-ticket-booking-with-seat-reservation'),
					__('Import Buses', 'bus-ticket-booking-with-seat-reservation'),
					'manage_options',
					'wbtm-bus-import',
					array($this, 'import_page')
				);
			}
			/**
			 * Enqueue necessary scripts and styles
			 */
			public function enqueue_scripts($hook) {
				if ('wbtm_bus_page_wbtm-bus-import' !== $hook) {
					return;
				}
				wp_enqueue_style('wbtm-import-style', WBTM_PLUGIN_URL . '/assets/admin/css/wbtm-import.css', array(), time());
				wp_enqueue_script('wbtm-import-script', WBTM_PLUGIN_URL . '/assets/admin/js/wbtm-import.js', array('jquery'), time(), true);
			}
			/**
			 * Display the import page
			 */
			public function import_page() {
				?>
                <div class="wrap wbtm-import-wrap">
                    <h1><?php esc_html_e('Import Buses', 'bus-ticket-booking-with-seat-reservation'); ?></h1>
					<?php
				if (isset($_POST['wbtm_import_nonce']) && (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wbtm_import_nonce'])), 'wbtm_import_buses_nonce'))) {
					// Show success/error messages
					if (isset($_GET['imported']) && sanitize_text_field(wp_unslash($_GET['imported'])) > 0) {
						$count = intval($_GET['imported']);
						echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($count) . ' ' . esc_html__('bus imported successfully.', 'bus-ticket-booking-with-seat-reservation') . '</p></div>';
					}
					if (isset($_GET['failed']) && sanitize_text_field(wp_unslash($_GET['failed'])) > 0) {
						$count = intval($_GET['failed']);
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($count) . ' ' . esc_html__('bus failed to import.', 'bus-ticket-booking-with-seat-reservation') . '</p></div>';
					}
					if (isset($_GET['error']) && !empty(sanitize_text_field(wp_unslash($_GET['error'])))) {
						echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($_GET['error']) . '</p></div>';
					}
				}
					?>
                    <div class="wbtm-import-container">
                        <div class="wbtm-import-section">
                            <h2><?php esc_html_e('Import Buses from CSV', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                            <p><?php esc_html_e('Upload a CSV file to import multiple buses at once. This will allow you to create all your buses in a single operation instead of adding them one by one.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                            <form method="post" enctype="multipart/form-data">
								<?php wp_nonce_field('wbtm_import_buses_nonce', 'wbtm_import_nonce'); ?>
                                <table class="form-table">
                                    <tr>
                                        <th scope="row">
                                            <label for="wbtm_import_file"><?php esc_html_e('Choose CSV File', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                        </th>
                                        <td>
                                            <input type="file" name="wbtm_import_file" id="wbtm_import_file" accept=".csv" required/>
                                            <p class="description"><?php esc_html_e('Select a CSV file with your bus data.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row">
											<?php esc_html_e('CSV Format', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </th>
                                        <td>
                                            <p><?php esc_html_e('Your CSV file should include the following columns:', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                            <ul class="wbtm-csv-format">
                                                <li><strong>name</strong> - <?php esc_html_e('Bus name (required)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>bus_no</strong> - <?php esc_html_e('Bus number (required)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>category</strong> - <?php esc_html_e('Bus category (AC/Non AC)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>seat_type</strong> - <?php esc_html_e('Seat type configuration (wbtm_seat_plan)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>driver_seat</strong> - <?php esc_html_e('Driver seat position (driver_left/driver_right)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>seat_rows</strong> - <?php esc_html_e('Number of seat rows', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>seat_cols</strong> - <?php esc_html_e('Number of seat columns', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>total_seats</strong> - <?php esc_html_e('Total number of seats', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>has_upper_deck</strong> - <?php esc_html_e('Has upper deck (yes/no)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>route_from</strong> - <?php esc_html_e('Starting point of route (comma separated for multiple routes)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>route_to</strong> - <?php esc_html_e('End point of route (comma separated for multiple routes)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>boarding_points</strong> - <?php esc_html_e('Boarding points (comma separated)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>dropping_points</strong> - <?php esc_html_e('Dropping points (comma separated)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>start_date</strong> - <?php esc_html_e('Start date (YYYY-MM-DD)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>end_date</strong> - <?php esc_html_e('End date (YYYY-MM-DD)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                                <li><strong>price_data</strong> - <?php esc_html_e('Price data in format: from|to|price,from|to|price', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                            </ul>
                                        </td>
                                    </tr>
                                </table>
                                <p class="submit">
                                    <input type="submit" name="wbtm_import_submit" class="button button-primary" value="<?php esc_attr_e('Import Buses', 'bus-ticket-booking-with-seat-reservation'); ?>"/>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=wbtm-bus-import&action=download_sample')); ?>" class="button"><?php esc_html_e('Download Sample CSV', 'bus-ticket-booking-with-seat-reservation'); ?></a>
                                </p>
                            </form>
                        </div>
                        <div class="wbtm-import-section">
                            <h2><?php esc_html_e('Import Instructions', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                            <ol>
                                <li><?php esc_html_e('Download the sample CSV file to see the required format.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                <li><?php esc_html_e('Fill in your bus data following the same format.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                <li><?php esc_html_e('Upload your CSV file using the form above.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                <li><?php esc_html_e('Review the import results and check your buses.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                            </ol>
                            <div class="wbtm-import-notes">
                                <h3><?php esc_html_e('Important Notes:', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                <ul>
                                    <li><?php esc_html_e('Make sure your CSV file is properly formatted with the correct columns.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    <li><?php esc_html_e('Bus names and numbers must be unique.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    <li><?php esc_html_e('For complex seat arrangements, you may need to edit the buses after import.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    <li><?php esc_html_e('The import process may take some time depending on the number of buses.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
			}
			/**
			 * Process the import form submission
			 */
			public function process_import() {
				if (isset($_POST['wbtm_import_nonce']) && (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wbtm_import_nonce'])), 'wbtm_import_buses_nonce'))) {
					wp_die(esc_html__('Security check failed. Please try again.', 'bus-ticket-booking-with-seat-reservation'));
				}
				// Check if we need to download the sample CSV
				if (isset($_GET['page']) && sanitize_text_field(wp_unslash($_GET['page'])) === 'wbtm-bus-import' && isset($_GET['action']) && sanitize_text_field(wp_unslash($_GET['action'])) === 'download_sample') {
					$this->download_sample_csv();
					exit;
				}
				// Check if form is submitted
				if (!isset($_POST['wbtm_import_submit'])) {
					return;
				}
				// Verify nonce

				// Check user capabilities
				if (!current_user_can('manage_options')) {
					wp_die(esc_html__('You do not have sufficient permissions to import data.', 'bus-ticket-booking-with-seat-reservation'));
				}
				// Check if file is uploaded
				if (!isset($_FILES['wbtm_import_file']) || $_FILES['wbtm_import_file']['error'] !== UPLOAD_ERR_OK) {
					wp_safe_redirect(
						add_query_arg(
							array(
								'error' => esc_html__('No file uploaded or upload error.', 'bus-ticket-booking-with-seat-reservation'),
							),
							admin_url('admin.php?page=wbtm-bus-import')
						)
					);
					exit;
				}
				// Check file type
				$file_info = pathinfo($_FILES['wbtm_import_file']['name']);
				if (!isset($file_info['extension']) || strtolower($file_info['extension']) !== 'csv') {
					wp_safe_redirect(
						add_query_arg(
							array(
								'error' => esc_html__('Invalid file type. Please upload a CSV file.', 'bus-ticket-booking-with-seat-reservation'),
							),
							admin_url('admin.php?page=wbtm-bus-import')
						)
					);
					exit;
				}
				// Process the CSV file
				$result = $this->import_csv($_FILES['wbtm_import_file']['tmp_name']);
				wp_safe_redirect(
					add_query_arg(
						array(
							'imported' => esc_html($result['imported']),
							'failed' => esc_html($result['failed']),
							'error' => esc_html($result['error']),
						),
						admin_url('admin.php?page=wbtm-bus-import')
					)
				);
				exit;
			}
			/**
			 * Import buses from CSV file
			 */
			private function import_csv($file) {
				$result = array(
					'imported' => 0,
					'failed' => 0,
					'error' => ''
				);
				// Open the file
				$handle = fopen($file, 'r');
				if (!$handle) {
					$result['error'] = __('Could not open the file.', 'bus-ticket-booking-with-seat-reservation');
					return $result;
				}
				// Get headers
				$headers = fgetcsv($handle);
				if (!$headers) {
					fclose($handle);
					$result['error'] = __('Could not read CSV headers.', 'bus-ticket-booking-with-seat-reservation');
					return $result;
				}
				// Normalize headers
				$headers = array_map('trim', $headers);
				$headers = array_map('strtolower', $headers);
				// Check required headers
				$required_headers = array('name', 'bus_no');
				foreach ($required_headers as $required) {
					if (!in_array($required, $headers)) {
						fclose($handle);
						$result['error'] = sprintf('Required column "%s" is missing.', $required);
						return $result;
					}
				}
				// Process rows
				while (($data = fgetcsv($handle)) !== false) {
					// Skip empty rows
					if (count($data) <= 1 && empty($data[0])) {
						continue;
					}
					// Combine headers with data
					$bus_data = array_combine($headers, $data);
					// Import the bus
					$import_result = $this->import_single_bus($bus_data);
					if ($import_result) {
						$result['imported']++;
					} else {
						$result['failed']++;
					}
				}
				fclose($handle);
				return $result;
			}
			/**
			 * Import a single bus from CSV data
			 */
			private function import_single_bus($data) {
				// Check required fields
				if (empty($data['name']) || empty(sanitize_text_field(wp_unslash($data['bus_no'])))) {
					return false;
				}
				// Create post array
				$post_args = array(
					'post_title' => sanitize_text_field(wp_unslash($data['name'])),
					'post_status' => 'publish',
					'post_type' => 'wbtm_bus'
				);
				// Insert the post
				$post_id = wp_insert_post($post_args);
				if (is_wp_error($post_id)) {
					return false;
				}
				// Prepare meta data
				$meta_data = array(
					'wbtm_bus_no' => isset($data['bus_no']) ? sanitize_text_field(wp_unslash($data['bus_no'])) : '',
					'wbtm_bus_category' => isset($data['category']) ? sanitize_text_field(wp_unslash($data['category'])) : 'Non AC',
					'wbtm_seat_type_conf' => isset($data['seat_type']) ? sanitize_text_field(wp_unslash($data['seat_type'])) : 'wbtm_seat_plan',
					'driver_seat_position' => isset($data['driver_seat']) ? sanitize_text_field(wp_unslash($data['driver_seat'])) : 'driver_left',
					'wbtm_seat_rows' => isset($data['seat_rows']) ? intval(sanitize_text_field(wp_unslash($data['seat_rows']))) : 8,
					'wbtm_seat_cols' => isset($data['seat_cols']) ? intval(sanitize_text_field(wp_unslash($data['seat_cols']))) : 5,
					'wbtm_get_total_seat' => isset($data['total_seats']) ? intval(sanitize_text_field(wp_unslash($data['total_seats']))) : 32,
					'show_upper_desk' => isset($data['has_upper_deck']) && strtolower(sanitize_text_field(wp_unslash($data['has_upper_deck']))) === 'yes' ? 'yes' : 'no',
				);
				// Add upper deck data if needed
				if ($meta_data['show_upper_desk'] === 'yes') {
					$meta_data['wbtm_seat_rows_dd'] = isset($data['upper_rows']) ? intval(sanitize_text_field(wp_unslash($data['upper_rows']))) : 8;
					$meta_data['wbtm_seat_cols_dd'] = isset($data['upper_cols']) ? intval(sanitize_text_field(wp_unslash($data['upper_cols']))) : 5;
					$meta_data['wbtm_seat_dd_price_parcent'] = isset($data['upper_price_percent']) ? intval(sanitize_text_field(wp_unslash($data['upper_price_percent']))) : 10;
				}
				// Process route data
				if (!empty($data['route_from']) && !empty($data['route_to'])) {
					$route_from = array_map('trim', explode(',', $data['route_from']));
					$route_to = array_map('trim', explode(',', $data['route_to']));
					// Create route direction
					$meta_data['wbtm_route_direction'] = array_merge($route_from, $route_to);
					// Boarding and dropping points
					if (!empty($data['boarding_points'])) {
						$meta_data['wbtm_bus_bp_stops'] = array_map('trim', explode(',', $data['boarding_points']));
					}
					if (!empty($data['dropping_points'])) {
						$meta_data['wbtm_bus_next_stops'] = array_map('trim', explode(',', $data['dropping_points']));
					}
					// Create route info
					$route_info = array();
					$index = 0;
					foreach ($route_from as $place) {
						$route_info[$index] = array(
							'place' => $place,
							'type' => 'bp',
							'time' => isset($data['departure_time']) ? $data['departure_time'] : '08:00'
						);
						$index++;
					}
					foreach ($route_to as $place) {
						$route_info[$index] = array(
							'place' => $place,
							'type' => 'dp',
							'time' => isset($data['arrival_time']) ? $data['arrival_time'] : '12:00'
						);
						$index++;
					}
					$meta_data['wbtm_route_info'] = $route_info;
				}
				// Process price data
				if (!empty($data['price_data'])) {
					$price_entries = explode(',', $data['price_data']);
					$price_data = array();
					foreach ($price_entries as $index => $entry) {
						$price_parts = explode('|', $entry);
						if (count($price_parts) >= 3) {
							$price_data[$index] = array(
								'wbtm_bus_bp_price_stop' => $price_parts[0],
								'wbtm_bus_dp_price_stop' => $price_parts[1],
								'wbtm_bus_price' => $price_parts[2],
								'wbtm_bus_child_price' => isset($price_parts[3]) ? $price_parts[3] : '',
								'wbtm_bus_infant_price' => isset($price_parts[4]) ? $price_parts[4] : ''
							);
						}
					}
					if (!empty($price_data)) {
						$meta_data['wbtm_bus_prices'] = $price_data;
					}
				}
				// Process date settings
				if (!empty($data['start_date'])) {
					$meta_data['wbtm_repeated_start_date'] = sanitize_text_field($data['start_date']);
				} else {
					$meta_data['wbtm_repeated_start_date'] = gmdate('Y-m-d', strtotime('+1 day'));
				}
				if (!empty($data['end_date'])) {
					$meta_data['wbtm_repeated_end_date'] = sanitize_text_field($data['end_date']);
				} else {
					$meta_data['wbtm_repeated_end_date'] = gmdate('Y-m-d', strtotime('+90 days'));
				}
				$meta_data['wbtm_repeated_after'] = isset($data['repeat_days']) ? intval($data['repeat_days']) : 1;
				$meta_data['wbtm_active_days'] = isset($data['active_days']) ? intval($data['active_days']) : 90;
				if (!empty($data['off_days'])) {
					$meta_data['wbtm_off_days'] = sanitize_text_field($data['off_days']);
				}
				// Save all meta data
				foreach ($meta_data as $meta_key => $meta_value) {
					update_post_meta($post_id, $meta_key, $meta_value);
				}
				// Set bus category if provided
				if (!empty($data['category'])) {
					$term = term_exists($data['category'], 'wbtm_bus_cat');
					if (!$term) {
						$term = wp_insert_term($data['category'], 'wbtm_bus_cat');
					}
					if (!is_wp_error($term)) {
						wp_set_object_terms($post_id, intval($term['term_id']), 'wbtm_bus_cat');
					}
				}
				// Add bus stops to taxonomy
				$all_stops = array_merge(
					isset($meta_data['wbtm_bus_bp_stops']) ? $meta_data['wbtm_bus_bp_stops'] : array(),
					isset($meta_data['wbtm_bus_next_stops']) ? $meta_data['wbtm_bus_next_stops'] : array()
				);
				foreach ($all_stops as $stop) {
					$term = term_exists($stop, 'wbtm_bus_stops');
					if (!$term) {
						$term = wp_insert_term($stop, 'wbtm_bus_stops');
					}
					if (!is_wp_error($term)) {
						wp_set_object_terms($post_id, intval($term['term_id']), 'wbtm_bus_stops', true);
					}
				}
				// Generate basic seat info based on rows and columns
				$this->generate_seat_info($post_id, $meta_data);
				return true;
			}
			/**
			 * Generate basic seat information
			 */
			private function generate_seat_info($post_id, $meta_data) {
				$rows = isset($meta_data['wbtm_seat_rows']) ? intval($meta_data['wbtm_seat_rows']) : 8;
				$cols = isset($meta_data['wbtm_seat_cols']) ? intval($meta_data['wbtm_seat_cols']) : 5;
				$seat_info = array();
				$row_letters = range('A', 'Z');
				for ($i = 0; $i < $rows; $i++) {
					$row_letter = $row_letters[$i];
					$row_seats = array();
					for ($j = 1; $j <= $cols; $j++) {
						// Skip middle aisle (position 3 in a 5-column layout)
						if ($cols == 5 && $j == 3) {
							$row_seats["seat{$j}"] = '';
						} else {
							$row_seats["seat{$j}"] = $row_letter . $j;
						}
					}
					$seat_info[$i] = $row_seats;
				}
				update_post_meta($post_id, 'wbtm_bus_seats_info', $seat_info);
				// Generate upper deck seats if needed
				if (isset($meta_data['show_upper_desk']) && $meta_data['show_upper_desk'] === 'yes') {
					$upper_rows = isset($meta_data['wbtm_seat_rows_dd']) ? intval($meta_data['wbtm_seat_rows_dd']) : 8;
					$upper_cols = isset($meta_data['wbtm_seat_cols_dd']) ? intval($meta_data['wbtm_seat_cols_dd']) : 5;
					$upper_seat_info = array();
					$upper_row_letters = range('S', 'Z');
					for ($i = 0; $i < $upper_rows; $i++) {
						$row_letter = $upper_row_letters[$i];
						$row_seats = array();
						for ($j = 1; $j <= $upper_cols; $j++) {
							// Skip middle aisle (position 3 in a 5-column layout)
							if ($upper_cols == 5 && $j == 3) {
								$row_seats["dd_seat{$j}"] = '';
							} else {
								$row_seats["dd_seat{$j}"] = $row_letter . $j;
							}
						}
						$upper_seat_info[$i] = $row_seats;
					}
					update_post_meta($post_id, 'wbtm_bus_seats_info_dd', $upper_seat_info);
				}
			}
			/**
			 * Download sample CSV file
			 */
			private function download_sample_csv() {
				// Set headers for download
				header('Content-Type: text/csv');
				header('Content-Disposition: attachment; filename="wbtm-bus-import-sample.csv"');
				header('Pragma: no-cache');
				header('Expires: 0');
				// Create output stream
				$output = fopen('php://output', 'w');
				// Add headers
				fputcsv($output, array(
					'name',
					'bus_no',
					'category',
					'seat_type',
					'driver_seat',
					'seat_rows',
					'seat_cols',
					'total_seats',
					'has_upper_deck',
					'upper_rows',
					'upper_cols',
					'upper_price_percent',
					'route_from',
					'route_to',
					'boarding_points',
					'dropping_points',
					'departure_time',
					'arrival_time',
					'start_date',
					'end_date',
					'repeat_days',
					'active_days',
					'off_days',
					'price_data'
				));
				// Add sample data
				fputcsv($output, array(
					'Express Bus Service',
					'EXP-001',
					'AC',
					'wbtm_seat_plan',
					'driver_left',
					'8',
					'5',
					'32',
					'no',
					'',
					'',
					'',
					'New York',
					'Washington DC',
					'New York,Philadelphia',
					'Baltimore,Washington DC',
					'08:00',
					'12:30',
					gmdate('Y-m-d', strtotime('+1 day')),
					gmdate('Y-m-d', strtotime('+90 days')),
					'1',
					'90',
					'saturday,sunday',
					'New York|Washington DC|50,New York|Baltimore|40,Philadelphia|Washington DC|30'
				));
				fputcsv($output, array(
					'Luxury Double Decker',
					'LUX-002',
					'AC',
					'wbtm_seat_plan',
					'driver_left',
					'8',
					'5',
					'64',
					'yes',
					'8',
					'5',
					'10',
					'Boston,New York',
					'Chicago,Detroit',
					'Boston,New York,Cleveland',
					'Detroit,Chicago',
					'09:00',
					'18:00',
					gmdate('Y-m-d', strtotime('+2 days')),
					gmdate('Y-m-d', strtotime('+120 days')),
					'2',
					'90',
					'sunday',
					'Boston|Chicago|80,Boston|Detroit|70,New York|Chicago|60,New York|Detroit|50'
				));
				fclose($output);
				exit;
			}
		}
	}
// Initialize the class
	new WBTM_Bus_Import();
