<?php
/**
 * GTFS Export Class for Bus Ticket Booking Plugin
 * 
 * This class handles the export of bus data to GTFS (General Transit Feed Specification) format
 * as per Google Transit requirements: https://developers.google.com/transit/gtfs/
 * 
 * @author MagePeople Team
 * @copyright mage-people.com
 */

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('WBTM_GTFS_Export')) {
    class WBTM_GTFS_Export {
        
        private $gtfs_data = [];
        private $export_path = '';
        private $agency_info = [];
        private $unrealistic_speed_warnings = [];
        
        public function __construct() {
            $this->init_hooks();
            $this->set_export_path();
            $this->set_agency_info();
        }
        
        private function init_hooks() {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('wp_ajax_wbtm_export_gtfs', [$this, 'handle_gtfs_export']);
            add_action('wp_ajax_wbtm_download_gtfs', [$this, 'handle_gtfs_download']);
        }
        
        private function set_export_path() {
            $upload_dir = wp_upload_dir();
            $this->export_path = $upload_dir['basedir'] . '/wbtm-gtfs-exports/';
            
            // Create directory if it doesn't exist
            if (!file_exists($this->export_path)) {
                wp_mkdir_p($this->export_path);
            }
        }
        
        private function set_agency_info() {
            // Fix: Use only the language part for agency_lang (e.g., 'en' from 'en_US')
            $locale = WBTM_Global_Function::get_settings('wbtm_gtfs_settings', 'agency_lang', get_locale());
            $lang = strtolower(explode('_', $locale)[0]);
            // Fix: Ensure agency_url is not localhost
            $default_url = home_url();
            if (strpos($default_url, 'localhost') !== false) {
                $default_url = 'https://your-agency-url.com'; // <-- CHANGE THIS TO YOUR REAL URL
            }
            $this->agency_info = [
                'agency_id' => WBTM_Global_Function::get_settings('wbtm_gtfs_settings', 'agency_id', 'WBTM_AGENCY'),
                'agency_name' => WBTM_Global_Function::get_settings('wbtm_gtfs_settings', 'agency_name', get_bloginfo('name')),
                'agency_url' => WBTM_Global_Function::get_settings('wbtm_gtfs_settings', 'agency_url', $default_url),
                'agency_timezone' => WBTM_Global_Function::get_settings('wbtm_gtfs_settings', 'agency_timezone', wp_timezone_string()),
                'agency_lang' => $lang,
                'agency_phone' => WBTM_Global_Function::get_settings('wbtm_gtfs_settings', 'agency_phone', ''),
                'agency_fare_url' => WBTM_Global_Function::get_settings('wbtm_gtfs_settings', 'agency_fare_url', '')
            ];
        }
        
        public function add_admin_menu() {
            add_submenu_page(
                'edit.php?post_type=wbtm_bus',
                __('GTFS Export', 'bus-ticket-booking-with-seat-reservation'),
                __('GTFS Export', 'bus-ticket-booking-with-seat-reservation'),
                'manage_options',
                'wbtm-gtfs-export',
                [$this, 'gtfs_export_page']
            );
        }
        
        public function gtfs_export_page() {
            ?>
            <div class="wrap">
                <h1><?php _e('GTFS Export', 'bus-ticket-booking-with-seat-reservation'); ?></h1>
                <p><?php _e('Export your bus data to GTFS format for Google Transit and other transit applications.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                
                <div class="wbtm-gtfs-export-container">
                    <form id="wbtm-gtfs-export-form">
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="agency_name"><?php _e('Agency Name', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="agency_name" name="agency_name" value="<?php echo esc_attr($this->agency_info['agency_name']); ?>" class="regular-text" required />
                                    <p class="description"><?php _e('The name of your transit agency', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="agency_url"><?php _e('Agency URL', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                </th>
                                <td>
                                    <input type="url" id="agency_url" name="agency_url" value="<?php echo esc_attr($this->agency_info['agency_url']); ?>" class="regular-text" required />
                                    <p class="description"><?php _e('Your agency website URL', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="agency_timezone"><?php _e('Agency Timezone', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                </th>
                                <td>
                                    <select id="agency_timezone" name="agency_timezone" class="regular-text" required>
                                        <?php
                                        $timezones = timezone_identifiers_list();
                                        foreach ($timezones as $timezone) {
                                            $selected = ($timezone === $this->agency_info['agency_timezone']) ? 'selected' : '';
                                            echo "<option value='{$timezone}' {$selected}>{$timezone}</option>";
                                        }
                                        ?>
                                    </select>
                                    <p class="description"><?php _e('Timezone for your bus operations', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="date_range"><?php _e('Export Date Range', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                </th>
                                <td>
                                    <input type="date" id="start_date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required />
                                    <span> <?php _e('to', 'bus-ticket-booking-with-seat-reservation'); ?> </span>
                                    <input type="date" id="end_date" name="end_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required />
                                    <p class="description"><?php _e('Date range for schedule export (recommended: 30-90 days)', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="selected_buses"><?php _e('Select Buses', 'bus-ticket-booking-with-seat-reservation'); ?></label>
                                </th>
                                <td>
                                    <?php
                                    $buses = get_posts([
                                        'post_type' => 'wbtm_bus',
                                        'posts_per_page' => -1,
                                        'post_status' => 'publish'
                                    ]);
                                    ?>
                                    <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ccc; padding: 10px;">
                                        <label>
                                            <input type="checkbox" id="select_all_buses" /> 
                                            <strong><?php _e('Select All', 'bus-ticket-booking-with-seat-reservation'); ?></strong>
                                        </label><br><br>
                                        <?php foreach ($buses as $bus): ?>
                                            <label>
                                                <input type="checkbox" name="selected_buses[]" value="<?php echo $bus->ID; ?>" class="bus-checkbox" /> 
                                                <?php echo esc_html($bus->post_title); ?>
                                            </label><br>
                                        <?php endforeach; ?>
                                    </div>
                                    <p class="description"><?php _e('Select which buses to include in the GTFS export', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                </td>
                            </tr>
                        </table>
                        <p>
                            <label>
                                <input type="checkbox" id="allow_localhost" name="allow_localhost" value="1" />
                                <?php _e('Allow localhost URLs for testing (not valid for public GTFS use)', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </label>
                        </p>
                        <p class="submit">
                            <input type="submit" class="button-primary" value="<?php _e('Export GTFS', 'bus-ticket-booking-with-seat-reservation'); ?>" />
                        </p>
                    </form>
                    
                    <div id="wbtm-gtfs-export-progress" style="display: none;">
                        <h3><?php _e('Export Progress', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                        <div id="progress-bar" style="width: 100%; background-color: #f0f0f0; height: 20px; border-radius: 10px;">
                            <div id="progress-fill" style="width: 0%; background-color: #0073aa; height: 100%; border-radius: 10px; transition: width 0.3s;"></div>
                        </div>
                        <p id="progress-text"><?php _e('Preparing export...', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                    </div>
                    
                    <div id="wbtm-gtfs-export-result" style="display: none;">
                        <h3><?php _e('Export Complete', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                        <p id="export-message"></p>
                        <a id="download-link" class="button-primary" style="display: none;"><?php _e('Download GTFS Feed', 'bus-ticket-booking-with-seat-reservation'); ?></a>
                        <div id="localhost-warning" style="color: orange; display: none;"></div>
                        <div id="invalid-coords-warning" style="color: orange; display: none;"></div>
                    </div>
                </div>
            </div>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Select all buses functionality
                $('#select_all_buses').change(function() {
                    $('.bus-checkbox').prop('checked', this.checked);
                });
                
                // Handle form submission
                $('#wbtm-gtfs-export-form').on('submit', function(e) {
                    e.preventDefault();
                    
                    var selectedBuses = $('input[name="selected_buses[]"]:checked').map(function() {
                        return this.value;
                    }).get();
                    
                    if (selectedBuses.length === 0) {
                        alert('<?php _e('Please select at least one bus to export.', 'bus-ticket-booking-with-seat-reservation'); ?>');
                        return;
                    }
                    
                    $('#wbtm-gtfs-export-progress').show();
                    $('#wbtm-gtfs-export-result').hide();
                    
                    var formData = {
                        action: 'wbtm_export_gtfs',
                        agency_name: $('#agency_name').val(),
                        agency_url: $('#agency_url').val(),
                        agency_timezone: $('#agency_timezone').val(),
                        start_date: $('#start_date').val(),
                        end_date: $('#end_date').val(),
                        selected_buses: selectedBuses,
                        allow_localhost: $('#allow_localhost').is(':checked') ? 1 : 0,
                        nonce: '<?php echo wp_create_nonce('wbtm_gtfs_export'); ?>'
                    };
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: formData,
                        success: function(response) {
                            $('#wbtm-gtfs-export-progress').hide();
                            $('#wbtm-gtfs-export-result').show();
                            
                            if (response.success) {
                                $('#export-message').html('<span style="color: green;">' + response.data.message + '</span>');
                                $('#download-link').attr('href', response.data.download_url).show();
                                if (response.data.localhost_warning) {
                                    $('#localhost-warning').text(response.data.localhost_warning).show();
                                } else {
                                    $('#localhost-warning').hide();
                                }
                                if (response.data.invalid_coords_warning) {
                                    $('#invalid-coords-warning').text(response.data.invalid_coords_warning).show();
                                } else {
                                    $('#invalid-coords-warning').hide();
                                }
                                if (response.data.unrealistic_speed_warning) {
                                    $('#invalid-coords-warning').text(response.data.unrealistic_speed_warning).show();
                                } else {
                                    $('#invalid-coords-warning').hide();
                                }
                            } else {
                                $('#export-message').html('<span style="color: red;">' + response.data.message + '</span>');
                                $('#localhost-warning').hide();
                                $('#invalid-coords-warning').hide();
                            }
                        },
                        error: function() {
                            $('#wbtm-gtfs-export-progress').hide();
                            $('#export-message').html('<span style="color: red;"><?php _e('Export failed. Please try again.', 'bus-ticket-booking-with-seat-reservation'); ?></span>');
                            $('#wbtm-gtfs-export-result').show();
                            $('#localhost-warning').hide();
                            $('#invalid-coords-warning').hide();
                        }
                    });
                });
            });
            </script>
            <?php
        }
        
        public function handle_gtfs_export() {
            // Verify nonce
            if (!wp_verify_nonce($_POST['nonce'], 'wbtm_gtfs_export')) {
                wp_die(__('Security check failed', 'bus-ticket-booking-with-seat-reservation'));
            }
            // Check permissions
            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions', 'bus-ticket-booking-with-seat-reservation'));
            }
            try {
                $agency_name = sanitize_text_field($_POST['agency_name']);
                $agency_url = esc_url_raw($_POST['agency_url']);
                $agency_timezone = sanitize_text_field($_POST['agency_timezone']);
                $start_date = sanitize_text_field($_POST['start_date']);
                $end_date = sanitize_text_field($_POST['end_date']);
                $selected_buses = array_map('intval', $_POST['selected_buses']);
                $allow_localhost = isset($_POST['allow_localhost']) && $_POST['allow_localhost'] == 1;
                // Validate URLs
                $is_localhost = (empty($agency_url) || strpos($agency_url, 'localhost') !== false || !filter_var($agency_url, FILTER_VALIDATE_URL));
                if ($is_localhost && !$allow_localhost) {
                    wp_send_json_error([
                        'message' => __('Export failed: Please provide a valid, public Agency URL (not localhost or empty), or check the box to allow localhost for testing.', 'bus-ticket-booking-with-seat-reservation')
                    ]);
                    return;
                }
                $feed_publisher_url = $agency_url; // For now, use agency_url for feed_publisher_url
                $is_feed_localhost = (empty($feed_publisher_url) || strpos($feed_publisher_url, 'localhost') !== false || !filter_var($feed_publisher_url, FILTER_VALIDATE_URL));
                if ($is_feed_localhost && !$allow_localhost) {
                    wp_send_json_error([
                        'message' => __('Export failed: Please provide a valid, public Feed Publisher URL (not localhost or empty), or check the box to allow localhost for testing.', 'bus-ticket-booking-with-seat-reservation')
                    ]);
                    return;
                }
                // Update agency settings
                $this->agency_info['agency_name'] = $agency_name;
                $this->agency_info['agency_url'] = $agency_url;
                $this->agency_info['agency_timezone'] = $agency_timezone;
                // Save settings
                update_option('wbtm_gtfs_settings', $this->agency_info);
                // Generate GTFS feed
                $zip_file = $this->generate_gtfs_feed($selected_buses, $start_date, $end_date);
                if ($zip_file) {
                    $upload_dir = wp_upload_dir();
                    $download_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $zip_file);
                    $response = [
                        'message' => __('GTFS feed exported successfully!', 'bus-ticket-booking-with-seat-reservation'),
                        'download_url' => $download_url
                    ];
                    if ($is_localhost || $is_feed_localhost) {
                        $response['localhost_warning'] = __('Warning: You exported with a localhost URL. This feed is NOT valid for public GTFS use or Google/MobilityData validator. Use a public URL for production.', 'bus-ticket-booking-with-seat-reservation');
                    }
                    // Add admin warning for invalid coordinates
                    $has_invalid_stop_coords = get_option('wbtm_gtfs_invalid_stop_coords', false);
                    $has_invalid_shape_coords = get_option('wbtm_gtfs_invalid_shape_coords', false);
                    if ($has_invalid_stop_coords || $has_invalid_shape_coords) {
                        $response['invalid_coords_warning'] = __('Warning: Some stops or shape points have coordinates 0.0,0.0. Please update your data for full GTFS validity.', 'bus-ticket-booking-with-seat-reservation');
                    }
                    // Add unrealistic speed warnings
                    if (!empty($this->unrealistic_speed_warnings)) {
                        $response['unrealistic_speed_warning'] = __('Warning: Some trips have unrealistic speeds (>130 km/h) between stops. Please check your stop times and distances.', 'bus-ticket-booking-with-seat-reservation') . "\n" . implode("\n", $this->unrealistic_speed_warnings);
                    }
                    wp_send_json_success($response);
                } else {
                    wp_send_json_error([
                        'message' => __('Failed to generate GTFS feed', 'bus-ticket-booking-with-seat-reservation')
                    ]);
                }
            } catch (Exception $e) {
                wp_send_json_error([
                    'message' => sprintf(__('Export error: %s', 'bus-ticket-booking-with-seat-reservation'), $e->getMessage())
                ]);
            }
        }
        
        private function generate_gtfs_feed($bus_ids, $start_date, $end_date) {
            $timestamp = current_time('YmdHis');
            $export_dir = $this->export_path . "gtfs_export_{$timestamp}/";
            // Create export directory
            if (!wp_mkdir_p($export_dir)) {
                throw new Exception('Could not create export directory');
            }
            // Performance optimization: Process buses in chunks to avoid memory issues
            $chunk_size = apply_filters('wbtm_gtfs_export_chunk_size', 50);
            $bus_chunks = array_chunk($bus_ids, $chunk_size);
            // Pre-load all stops to avoid duplicate queries
            $all_stops = $this->preload_all_stops($bus_ids);
            // Generate GTFS files with memory management
            $this->generate_agency_file($export_dir);
            // Process routes and stops
            $this->generate_routes_file_chunked($export_dir, $bus_chunks);
            $this->generate_stops_file_optimized($export_dir, $all_stops);
            // Process trips and stop times with batching
            $this->generate_trips_and_stop_times_batched($export_dir, $bus_chunks, $start_date, $end_date);
            // Generate calendar files
            $this->generate_calendar_file_chunked($export_dir, $bus_chunks, $start_date, $end_date);
            $this->generate_calendar_dates_file_chunked($export_dir, $bus_chunks, $start_date, $end_date);
            // Add fare files
            $this->generate_fare_attributes_file($export_dir, $bus_ids);
            $this->generate_fare_rules_file($export_dir, $bus_ids);
            // Add shapes.txt if possible
            $this->generate_shapes_file($export_dir, $bus_ids);
            // Allow pro version to add additional files
            do_action('wbtm_gtfs_export_additional_files', $export_dir, $bus_ids);
            // Add feed_info.txt (recommended by GTFS)
            $this->generate_feed_info_file($export_dir, $start_date, $end_date);
            // Create ZIP file
            $zip_file = $this->export_path . "gtfs_feed_{$timestamp}.zip";
            return $this->create_zip_file($export_dir, $zip_file);
        }
        
        private function generate_agency_file($export_dir) {
            $content = "agency_id,agency_name,agency_url,agency_timezone,agency_lang,agency_phone,agency_fare_url\n";
            $content .= sprintf(
                "%s,\"%s\",%s,%s,%s,%s,%s\n",
                $this->agency_info['agency_id'],
                $this->agency_info['agency_name'],
                $this->agency_info['agency_url'],
                $this->agency_info['agency_timezone'],
                $this->agency_info['agency_lang'],
                $this->agency_info['agency_phone'],
                $this->agency_info['agency_fare_url']
            );
            
            file_put_contents($export_dir . 'agency.txt', $content);
        }
        
        private function generate_routes_file($export_dir, $bus_ids) {
            $content = "route_id,agency_id,route_short_name,route_long_name,route_desc,route_type,route_url,route_color,route_text_color\n";
            
            foreach ($bus_ids as $bus_id) {
                $bus_post = get_post($bus_id);
                if (!$bus_post) continue;
                
                $route_info = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_info', []);
                $bus_category = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_category', '');
                
                // Get start and end points
                $start_point = '';
                $end_point = '';
                if (!empty($route_info)) {
                    foreach ($route_info as $info) {
                        if (($info['type'] == 'bp' || $info['type'] == 'both') && empty($start_point)) {
                            $start_point = $info['place'];
                        }
                        if ($info['type'] == 'dp' || $info['type'] == 'both') {
                            $end_point = $info['place'];
                        }
                    }
                }
                
                $route_long_name = !empty($start_point) && !empty($end_point) 
                    ? "{$start_point} - {$end_point}" 
                    : $bus_post->post_title;
                
                $content .= sprintf(
                    "%s,%s,\"%s\",\"%s\",\"%s\",%d,%s,%s,%s\n",
                    "ROUTE_{$bus_id}",
                    $this->agency_info['agency_id'],
                    $bus_post->post_title,
                    $route_long_name,
                    $bus_category,
                    3, // Route type 3 = Bus
                    '',
                    '',
                    ''
                );
            }
            
            file_put_contents($export_dir . 'routes.txt', $content);
        }
        
        private function generate_stops_file($export_dir, $bus_ids) {
            $content = "stop_id,stop_code,stop_name,stop_desc,stop_lat,stop_lon,zone_id,stop_url,location_type,parent_station,stop_timezone,wheelchair_boarding\n";
            $all_stops = [];
            
            foreach ($bus_ids as $bus_id) {
                $route_info = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_info', []);
                
                foreach ($route_info as $info) {
                    if (!isset($all_stops[$info['place']])) {
                        // Get coordinates if available (you may need to add custom fields for lat/lon)
                        $lat = WBTM_Global_Function::get_post_info($bus_id, "stop_lat_{$info['place']}", '0.0');
                        $lon = WBTM_Global_Function::get_post_info($bus_id, "stop_lon_{$info['place']}", '0.0');
                        
                        $all_stops[$info['place']] = [
                            'stop_id' => 'STOP_' . sanitize_title($info['place']),
                            'stop_name' => $info['place'],
                            'stop_lat' => $lat,
                            'stop_lon' => $lon
                        ];
                    }
                }
            }
            
            foreach ($all_stops as $stop) {
                $content .= sprintf(
                    "%s,,%s,,%s,%s,,,0,,,\n",
                    $stop['stop_id'],
                    $stop['stop_name'],
                    $stop['stop_lat'],
                    $stop['stop_lon']
                );
            }
            
            file_put_contents($export_dir . 'stops.txt', $content);
        }
        
        private function generate_trips_file($export_dir, $bus_ids, $start_date, $end_date) {
            $content = "route_id,service_id,trip_id,trip_headsign,trip_short_name,direction_id,block_id,shape_id,wheelchair_accessible,bikes_allowed\n";
            
            foreach ($bus_ids as $bus_id) {
                $bus_post = get_post($bus_id);
                if (!$bus_post) continue;
                
                $route_info = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_info', []);
                $dates = WBTM_Functions::get_post_date($bus_id);
                
                // Filter dates within range
                $filtered_dates = array_filter($dates, function($date) use ($start_date, $end_date) {
                    return $date >= $start_date && $date <= $end_date;
                });
                
                foreach ($filtered_dates as $date) {
                    $trip_id = "TRIP_{$bus_id}_{$date}";
                    $service_id = "SERVICE_{$bus_id}";
                    
                    // Get headsign (destination)
                    $headsign = '';
                    if (!empty($route_info)) {
                        foreach (array_reverse($route_info) as $info) {
                            if ($info['type'] == 'dp' || $info['type'] == 'both') {
                                $headsign = $info['place'];
                                break;
                            }
                        }
                    }
                    
                    $content .= sprintf(
                        "%s,%s,%s,\"%s\",,0,,,1,\n",
                        "ROUTE_{$bus_id}",
                        $service_id,
                        $trip_id,
                        $headsign
                    );
                }
            }
            
            file_put_contents($export_dir . 'trips.txt', $content);
        }
        
        private function generate_stop_times_file($export_dir, $bus_ids, $start_date, $end_date) {
            $content = "trip_id,arrival_time,departure_time,stop_id,stop_sequence,stop_headsign,pickup_type,drop_off_type,shape_dist_traveled\n";
            
            foreach ($bus_ids as $bus_id) {
                $dates = WBTM_Functions::get_post_date($bus_id);
                $route_info = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_info', []);
                
                // Filter dates within range
                $filtered_dates = array_filter($dates, function($date) use ($start_date, $end_date) {
                    return $date >= $start_date && $date <= $end_date;
                });
                
                foreach ($filtered_dates as $date) {
                    $trip_id = "TRIP_{$bus_id}_{$date}";
                    $stop_sequence = 1;
                    
                    foreach ($route_info as $info) {
                        $stop_id = 'STOP_' . sanitize_title($info['place']);
                        $time = $info['time'];
                        
                        // Determine pickup and drop-off types
                        $pickup_type = ($info['type'] == 'bp' || $info['type'] == 'both') ? 0 : 1;
                        $drop_off_type = ($info['type'] == 'dp' || $info['type'] == 'both') ? 0 : 1;
                        
                        $content .= sprintf(
                            "%s,%s,%s,%s,%d,,%d,%d,\n",
                            $trip_id,
                            $time,
                            $time,
                            $stop_id,
                            $stop_sequence,
                            $pickup_type,
                            $drop_off_type
                        );
                        
                        $stop_sequence++;
                    }
                }
            }
            
            file_put_contents($export_dir . 'stop_times.txt', $content);
        }
        
        private function generate_calendar_file($export_dir, $bus_ids, $start_date, $end_date) {
            $content = "service_id,monday,tuesday,wednesday,thursday,friday,saturday,sunday,start_date,end_date\n";
            
            foreach ($bus_ids as $bus_id) {
                $service_id = "SERVICE_{$bus_id}";
                
                // Get active days
                $off_days = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_off_days', '');
                $off_day_array = $off_days ? explode(',', $off_days) : [];
                
                $days = ['monday' => 1, 'tuesday' => 1, 'wednesday' => 1, 'thursday' => 1, 'friday' => 1, 'saturday' => 1, 'sunday' => 1];
                
                foreach ($off_day_array as $off_day) {
                    $day_name = strtolower(trim($off_day));
                    if (isset($days[$day_name])) {
                        $days[$day_name] = 0;
                    }
                }
                
                $content .= sprintf(
                    "%s,%d,%d,%d,%d,%d,%d,%d,%s,%s\n",
                    $service_id,
                    $days['monday'],
                    $days['tuesday'],
                    $days['wednesday'],
                    $days['thursday'],
                    $days['friday'],
                    $days['saturday'],
                    $days['sunday'],
                    str_replace('-', '', $start_date),
                    str_replace('-', '', $end_date)
                );
            }
            
            file_put_contents($export_dir . 'calendar.txt', $content);
        }
        
        private function generate_calendar_dates_file($export_dir, $bus_ids, $start_date, $end_date) {
            $content = "service_id,date,exception_type\n";
            
            foreach ($bus_ids as $bus_id) {
                $service_id = "SERVICE_{$bus_id}";
                
                // Get off-day schedules
                $off_schedules = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_offday_schedule', []);
                
                foreach ($off_schedules as $off_schedule) {
                    if (isset($off_schedule['from_date']) && isset($off_schedule['to_date'])) {
                        $from_date = date('Y-m-d', strtotime(current_time('Y') . '-' . $off_schedule['from_date']));
                        $to_date = date('Y-m-d', strtotime(current_time('Y') . '-' . $off_schedule['to_date']));
                        
                        $current_date = $from_date;
                        while ($current_date <= $to_date && $current_date >= $start_date && $current_date <= $end_date) {
                            $content .= sprintf(
                                "%s,%s,2\n", // 2 = Service removed
                                $service_id,
                                str_replace('-', '', $current_date)
                            );
                            $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                        }
                    }
                }
            }
            
            file_put_contents($export_dir . 'calendar_dates.txt', $content);
        }
        
        private function create_zip_file($source_dir, $zip_file) {
            if (!class_exists('ZipArchive')) {
                throw new Exception('ZipArchive class not available');
            }
            
            $zip = new ZipArchive();
            if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
                throw new Exception('Cannot create ZIP file');
            }
            
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source_dir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    $relative_path = substr($file_path, strlen($source_dir));
                    $zip->addFile($file_path, $relative_path);
                }
            }
            
            $zip->close();
            
            // Clean up temporary directory
            $this->recursive_rmdir($source_dir);
            
            return $zip_file;
        }
        
        private function recursive_rmdir($dir) {
            if (is_dir($dir)) {
                $objects = scandir($dir);
                foreach ($objects as $object) {
                    if ($object != "." && $object != "..") {
                        if (is_dir($dir . "/" . $object)) {
                            $this->recursive_rmdir($dir . "/" . $object);
                        } else {
                            unlink($dir . "/" . $object);
                        }
                    }
                }
                rmdir($dir);
            }
        }
        
        public function handle_gtfs_download() {
            // Handle file downloads if needed
        }
        
        /**
         * Performance optimization methods
         */
        private function preload_all_stops($bus_ids) {
            $all_stops = [];
            
            // Use a single query to get all route info for all buses
            $route_infos = $this->get_bulk_route_info($bus_ids);
            
            foreach ($route_infos as $bus_id => $route_info) {
                foreach ($route_info as $info) {
                    $stop_key = strtolower($info['place']);
                    if (!isset($all_stops[$info['place']])) {
                        $lat = WBTM_Global_Function::get_post_info($bus_id, "stop_lat_{$stop_key}", '0.0');
                        $lon = WBTM_Global_Function::get_post_info($bus_id, "stop_lon_{$stop_key}", '0.0');
                        
                        $all_stops[$info['place']] = [
                            'stop_id' => 'STOP_' . sanitize_title($info['place']),
                            'stop_name' => $info['place'],
                            'stop_lat' => $lat,
                            'stop_lon' => $lon,
                            'bus_id' => $bus_id // Add bus_id for logging/debugging
                        ];
                    }
                }
            }
            
            return $all_stops;
        }
        
        private function get_bulk_route_info($bus_ids) {
            global $wpdb;
            
            if (empty($bus_ids)) {
                return [];
            }
            
            $placeholders = implode(',', array_fill(0, count($bus_ids), '%d'));
            $sql = $wpdb->prepare("
                SELECT post_id, meta_value 
                FROM {$wpdb->postmeta} 
                WHERE post_id IN ($placeholders) 
                AND meta_key = 'wbtm_route_info'
            ", $bus_ids);
            
            $results = $wpdb->get_results($sql);
            $route_infos = [];
            
            foreach ($results as $result) {
                $route_infos[$result->post_id] = maybe_unserialize($result->meta_value) ?: [];
            }
            
            return $route_infos;
        }
        
        private function generate_routes_file_chunked($export_dir, $bus_chunks) {
            $file_handle = fopen($export_dir . 'routes.txt', 'w');
            if (!$file_handle) {
                throw new Exception('Could not create routes.txt file');
            }
            
            // Write header
            fwrite($file_handle, "route_id,agency_id,route_short_name,route_long_name,route_desc,route_type,route_url,route_color,route_text_color\n");
            
            foreach ($bus_chunks as $chunk) {
                foreach ($chunk as $bus_id) {
                    $bus_post = get_post($bus_id);
                    if (!$bus_post) continue;
                    
                    $route_info = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_info', []);
                    $bus_category = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_bus_category', '');
                    
                    // Get start and end points
                    $start_point = '';
                    $end_point = '';
                    if (!empty($route_info)) {
                        foreach ($route_info as $info) {
                            if (($info['type'] == 'bp' || $info['type'] == 'both') && empty($start_point)) {
                                $start_point = $info['place'];
                            }
                            if ($info['type'] == 'dp' || $info['type'] == 'both') {
                                $end_point = $info['place'];
                            }
                        }
                    }
                    
                    $route_long_name = !empty($start_point) && !empty($end_point) 
                        ? "{$start_point} - {$end_point}" 
                        : $bus_post->post_title;
                    
                    $line = sprintf(
                        "%s,%s,\"%s\",\"%s\",\"%s\",%d,%s,%s,%s\n",
                        "ROUTE_{$bus_id}",
                        $this->agency_info['agency_id'],
                        $bus_post->post_title,
                        $route_long_name,
                        $bus_category,
                        3, // Route type 3 = Bus
                        '',
                        '',
                        ''
                    );
                    
                    fwrite($file_handle, $line);
                }
                
                // Clear memory between chunks
                wp_cache_flush();
            }
            
            fclose($file_handle);
        }
        
        private function generate_stops_file_optimized($export_dir, $all_stops) {
            $file_handle = fopen($export_dir . 'stops.txt', 'w');
            if (!$file_handle) {
                throw new Exception('Could not create stops.txt file');
            }
            // Write header
            fwrite($file_handle, "stop_id,stop_code,stop_name,stop_desc,stop_lat,stop_lon,zone_id,stop_url,location_type,parent_station,stop_timezone,wheelchair_boarding\n");
            $has_invalid_coords = false;
            foreach ($all_stops as $stop) {
                // No more error_log debugging
                if ((float)$stop['stop_lat'] == 0.0 && (float)$stop['stop_lon'] == 0.0) {
                    $has_invalid_coords = true;
                }
                $line = sprintf(
                    "%s,,%s,,%s,%s,,,0,,,\n",
                    $stop['stop_id'],
                    $stop['stop_name'],
                    $stop['stop_lat'],
                    $stop['stop_lon']
                );
                fwrite($file_handle, $line);
            }
            fclose($file_handle);
            // Store warning for admin UI if needed
            if ($has_invalid_coords) {
                update_option('wbtm_gtfs_invalid_stop_coords', true);
            } else {
                delete_option('wbtm_gtfs_invalid_stop_coords');
            }
        }
        
        private function generate_trips_and_stop_times_batched($export_dir, $bus_chunks, $start_date, $end_date) {
            // Open both files for writing
            $trips_handle = fopen($export_dir . 'trips.txt', 'w');
            $stop_times_handle = fopen($export_dir . 'stop_times.txt', 'w');
            if (!$trips_handle || !$stop_times_handle) {
                throw new Exception('Could not create trips or stop_times files');
            }
            // Write headers (10 columns)
            fwrite($trips_handle, "route_id,service_id,trip_id,trip_headsign,trip_short_name,direction_id,block_id,shape_id,wheelchair_accessible,bikes_allowed\n");
            fwrite($stop_times_handle, "trip_id,arrival_time,departure_time,stop_id,stop_sequence,stop_headsign,pickup_type,drop_off_type,shape_dist_traveled\n");
            $this->unrealistic_speed_warnings = [];
            foreach ($bus_chunks as $chunk) {
                foreach ($chunk as $bus_id) {
                    $bus_post = get_post($bus_id);
                    if (!$bus_post) continue;
                    $route_info = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_info', []);
                    $dates = WBTM_Functions::get_post_date($bus_id);
                    // Filter dates within range
                    $filtered_dates = array_filter($dates, function($date) use ($start_date, $end_date) {
                        return $date >= $start_date && $date <= $end_date;
                    });
                    // Process dates in smaller batches to manage memory
                    $date_chunks = array_chunk($filtered_dates, 10);
                    foreach ($date_chunks as $date_chunk) {
                        foreach ($date_chunk as $date) {
                            $trip_id = "TRIP_{$bus_id}_{$date}";
                            $service_id = "SERVICE_{$bus_id}";
                            // Get headsign (destination: last stop in route_info)
                            $headsign = '';
                            if (!empty($route_info)) {
                                $last_stop = end($route_info);
                                $headsign = $last_stop['place'] ?? '';
                            }
                            $shape_id = "SHAPE_{$bus_id}";
                            $trip_line = sprintf(
                                "%s,%s,%s,\"%s\",,,,%s,1,\n",
                                "ROUTE_{$bus_id}",
                                $service_id,
                                $trip_id,
                                $headsign,
                                $shape_id
                            );
                            fwrite($trips_handle, $trip_line);
                            // Write stop times for this trip: include all stops in order
                            $stop_sequence = 1;
                            $prev_stop = null;
                            $prev_time = null;
                            $prev_lat = null;
                            $prev_lon = null;
                            foreach ($route_info as $info) {
                                $stop_id = 'STOP_' . sanitize_title($info['place']);
                                $time = $info['time'];
                                // Ensure time is in HH:MM:SS format
                                if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
                                    $time .= ':00';
                                }
                                if (!preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $time)) {
                                    continue;
                                }
                                $pickup_type = ($info['type'] == 'bp' || $info['type'] == 'both') ? 0 : 1;
                                $drop_off_type = ($info['type'] == 'dp' || $info['type'] == 'both') ? 0 : 1;
                                $stop_time_line = sprintf(
                                    "%s,%s,%s,%s,%d,,%d,%d,\n",
                                    $trip_id,
                                    $time,
                                    $time,
                                    $stop_id,
                                    $stop_sequence,
                                    $pickup_type,
                                    $drop_off_type
                                );
                                fwrite($stop_times_handle, $stop_time_line);
                                // --- Unrealistic speed check ---
                                $stop_key = strtolower($info['place']);
                                $lat = WBTM_Global_Function::get_post_info($bus_id, "stop_lat_{$stop_key}", '0.0');
                                $lon = WBTM_Global_Function::get_post_info($bus_id, "stop_lon_{$stop_key}", '0.0');
                                if ($prev_stop !== null && $prev_time !== null && $prev_lat !== null && $prev_lon !== null) {
                                    $distance_km = $this->haversine_distance((float)$prev_lat, (float)$prev_lon, (float)$lat, (float)$lon);
                                    $t1 = strtotime($prev_time);
                                    $t2 = strtotime($time);
                                    if ($t2 > $t1) {
                                        $hours = ($t2 - $t1) / 3600.0;
                                        if ($hours > 0) {
                                            $speed = $distance_km / $hours;
                                            if ($speed > 130) { // GTFS best practice: 130 km/h for bus
                                                $this->unrealistic_speed_warnings[] = sprintf(
                                                    'Bus %s (%s), Trip %s, %s → %s: %.1f km in %.2f h = %.1f km/h',
                                                    $bus_id,
                                                    $bus_post->post_title,
                                                    $date,
                                                    $prev_stop,
                                                    $info['place'],
                                                    $distance_km,
                                                    $hours,
                                                    $speed
                                                );
                                            }
                                        }
                                    }
                                }
                                $prev_stop = $info['place'];
                                $prev_time = $time;
                                $prev_lat = $lat;
                                $prev_lon = $lon;
                                $stop_sequence++;
                            }
                        }
                    }
                }
                // Clear memory between chunks
                wp_cache_flush();
            }
            fclose($trips_handle);
            fclose($stop_times_handle);
        }
        
        private function generate_calendar_file_chunked($export_dir, $bus_chunks, $start_date, $end_date) {
            $file_handle = fopen($export_dir . 'calendar.txt', 'w');
            if (!$file_handle) {
                throw new Exception('Could not create calendar.txt file');
            }
            
            // Write header
            fwrite($file_handle, "service_id,monday,tuesday,wednesday,thursday,friday,saturday,sunday,start_date,end_date\n");
            
            foreach ($bus_chunks as $chunk) {
                foreach ($chunk as $bus_id) {
                    $service_id = "SERVICE_{$bus_id}";
                    
                    // Get active days
                    $off_days = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_off_days', '');
                    $off_day_array = $off_days ? explode(',', $off_days) : [];
                    
                    $days = ['monday' => 1, 'tuesday' => 1, 'wednesday' => 1, 'thursday' => 1, 'friday' => 1, 'saturday' => 1, 'sunday' => 1];
                    
                    foreach ($off_day_array as $off_day) {
                        $day_name = strtolower(trim($off_day));
                        if (isset($days[$day_name])) {
                            $days[$day_name] = 0;
                        }
                    }
                    
                    $line = sprintf(
                        "%s,%d,%d,%d,%d,%d,%d,%d,%s,%s\n",
                        $service_id,
                        $days['monday'],
                        $days['tuesday'],
                        $days['wednesday'],
                        $days['thursday'],
                        $days['friday'],
                        $days['saturday'],
                        $days['sunday'],
                        str_replace('-', '', $start_date),
                        str_replace('-', '', $end_date)
                    );
                    fwrite($file_handle, $line);
                }
            }
            
            fclose($file_handle);
        }
        
        private function generate_calendar_dates_file_chunked($export_dir, $bus_chunks, $start_date, $end_date) {
            $file_handle = fopen($export_dir . 'calendar_dates.txt', 'w');
            if (!$file_handle) {
                throw new Exception('Could not create calendar_dates.txt file');
            }
            
            // Write header
            fwrite($file_handle, "service_id,date,exception_type\n");
            
            foreach ($bus_chunks as $chunk) {
                foreach ($chunk as $bus_id) {
                    $service_id = "SERVICE_{$bus_id}";
                    
                    // Get off-day schedules
                    $off_schedules = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_offday_schedule', []);
                    
                    foreach ($off_schedules as $off_schedule) {
                        if (isset($off_schedule['from_date']) && isset($off_schedule['to_date'])) {
                            $from_date = date('Y-m-d', strtotime(current_time('Y') . '-' . $off_schedule['from_date']));
                            $to_date = date('Y-m-d', strtotime(current_time('Y') . '-' . $off_schedule['to_date']));
                            
                            $current_date = $from_date;
                            while ($current_date <= $to_date && $current_date >= $start_date && $current_date <= $end_date) {
                                $line = sprintf(
                                    "%s,%s,2\n", // 2 = Service removed
                                    $service_id,
                                    str_replace('-', '', $current_date)
                                );
                                fwrite($file_handle, $line);
                                $current_date = date('Y-m-d', strtotime($current_date . ' +1 day'));
                            }
                        }
                    }
                }
            }
            
            fclose($file_handle);
        }

        // Add this new function to generate feed_info.txt
        private function generate_feed_info_file($export_dir, $start_date, $end_date) {
            $content = "feed_publisher_name,feed_publisher_url,feed_lang,feed_start_date,feed_end_date,feed_version,feed_contact_email\n";
            $feed_version = date('Ymd'); // Use current date as version
            $feed_contact_email = trim(preg_replace('/[\r\n\t]+/', '', get_bloginfo('admin_email')));
            $content .= sprintf(
                '"%s",%s,%s,%s,%s,%s,%s\n',
                $this->agency_info['agency_name'],
                $this->agency_info['agency_url'],
                $this->agency_info['agency_lang'],
                str_replace('-', '', $start_date),
                str_replace('-', '', $end_date),
                $feed_version,
                $feed_contact_email
            );
            file_put_contents($export_dir . 'feed_info.txt', $content);
        }

        // Add GTFS fare_attributes.txt export
        private function generate_fare_attributes_file($export_dir, $bus_ids) {
            $content = "fare_id,price,currency_type,payment_method,transfers,transfer_duration
";
            $currency = get_woocommerce_currency() ?: 'USD';
            foreach ($bus_ids as $bus_id) {
                $fare_id = "FARE_{$bus_id}";
                // Get base price for the route (bus)
                $base_price = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_base_price', 0);
                if (!$base_price || $base_price < 0) $base_price = 0;
                $content .= sprintf("%s,%.2f,%s,0,0,\n",
                    $fare_id,
                    $base_price,
                    $currency
                );
            }
            file_put_contents($export_dir . 'fare_attributes.txt', $content);
        }
        // Add GTFS fare_rules.txt export
        private function generate_fare_rules_file($export_dir, $bus_ids) {
            $content = "fare_id,route_id
";
            foreach ($bus_ids as $bus_id) {
                $fare_id = "FARE_{$bus_id}";
                $route_id = "ROUTE_{$bus_id}";
                $content .= sprintf("%s,%s\n",
                    $fare_id,
                    $route_id
                );
            }
            file_put_contents($export_dir . 'fare_rules.txt', $content);
        }

        // Add GTFS shapes.txt export (if route_info has lat/lon for each stop in order)
        private function generate_shapes_file($export_dir, $bus_ids) {
            $content = "shape_id,shape_pt_lat,shape_pt_lon,shape_pt_sequence,shape_dist_traveled\n";
            foreach ($bus_ids as $bus_id) {
                $route_info = WBTM_Global_Function::get_post_info($bus_id, 'wbtm_route_info', []);
                $seq = 1;
                $has_real_shape = false;
                $shape_points = [];
                // Try to get real shape points (if your plugin supports it)
                // If not, use stop coordinates as shape points
                foreach ($route_info as $info) {
                    $stop_key = strtolower($info['place']);
                    $lat = WBTM_Global_Function::get_post_info($bus_id, "stop_lat_{$stop_key}", '0.0');
                    $lon = WBTM_Global_Function::get_post_info($bus_id, "stop_lon_{$stop_key}", '0.0');
                    if ((float)$lat != 0.0 || (float)$lon != 0.0) {
                        $has_real_shape = true;
                    }
                    $shape_points[] = [
                        'lat' => $lat,
                        'lon' => $lon
                    ];
                }
                // If no real shape points, use stop coordinates as shape points
                if (!$has_real_shape && !empty($shape_points)) {
                    foreach ($shape_points as $pt) {
                        $content .= sprintf(
                            "SHAPE_%s,%s,%s,%d,\n",
                            $bus_id,
                            $pt['lat'],
                            $pt['lon'],
                            $seq
                        );
                        $seq++;
                    }
                } else {
                    foreach ($shape_points as $pt) {
                        $content .= sprintf(
                            "SHAPE_%s,%s,%s,%d,\n",
                            $bus_id,
                            $pt['lat'],
                            $pt['lon'],
                            $seq
                        );
                        $seq++;
                    }
                }
            }
            file_put_contents($export_dir . 'shapes.txt', $content);
        }

        // Haversine formula for distance in km
        private function haversine_distance($lat1, $lon1, $lat2, $lon2) {
            $earth_radius = 6371; // km
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat/2) * sin($dLat/2) +
                cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
                sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            return $earth_radius * $c;
        }
    }
    
    // Initialize the GTFS Export class
    new WBTM_GTFS_Export();
}
