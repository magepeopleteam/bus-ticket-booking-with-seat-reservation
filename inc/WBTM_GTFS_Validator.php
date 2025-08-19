<?php
/**
 * GTFS Validator Class for Bus Ticket Booking Plugin
 * 
 * This class validates GTFS feeds according to the specification
 * and provides detailed error reporting
 * 
 * @author MagePeople Team
 * @copyright mage-people.com
 */

if (!defined('ABSPATH')) {
    die;
}

if (!class_exists('WBTM_GTFS_Validator')) {
    class WBTM_GTFS_Validator {
        
        private $errors = [];
        private $warnings = [];
        private $required_files = [
            'agency.txt',
            'stops.txt',
            'routes.txt',
            'trips.txt',
            'stop_times.txt'
        ];
        
        private $optional_files = [
            'calendar.txt',
            'calendar_dates.txt',
            'fare_attributes.txt',
            'fare_rules.txt',
            'shapes.txt',
            'frequencies.txt',
            'transfers.txt',
            'pathways.txt',
            'levels.txt',
            'feed_info.txt'
        ];
        
        public function __construct() {
            $this->init_hooks();
        }
        
        private function init_hooks() {
            add_action('wp_ajax_wbtm_validate_gtfs', [$this, 'handle_gtfs_validation']);
            add_action('wbtm_after_gtfs_export', [$this, 'validate_exported_feed'], 10, 1);
        }
        
        public function validate_gtfs_feed($zip_file_path) {
            $this->errors = [];
            $this->warnings = [];
            
            if (!file_exists($zip_file_path)) {
                $this->errors[] = __('GTFS feed file not found', 'bus-ticket-booking-with-seat-reservation');
                return false;
            }
            
            // Extract ZIP file
            $extract_dir = $this->extract_zip_file($zip_file_path);
            if (!$extract_dir) {
                $this->errors[] = __('Could not extract GTFS feed', 'bus-ticket-booking-with-seat-reservation');
                return false;
            }
            
            try {
                // Validate file structure
                $this->validate_file_structure($extract_dir);
                
                // Validate individual files
                $this->validate_agency_file($extract_dir);
                $this->validate_stops_file($extract_dir);
                $this->validate_routes_file($extract_dir);
                $this->validate_trips_file($extract_dir);
                $this->validate_stop_times_file($extract_dir);
                $this->validate_calendar_files($extract_dir);
                
                // Cross-reference validation
                $this->validate_references($extract_dir);
                
                // Clean up
                $this->cleanup_directory($extract_dir);
                
                return empty($this->errors);
                
            } catch (Exception $e) {
                /* translators: %s: error message */
                $this->errors[] = sprintf(__('Validation error: %s', 'bus-ticket-booking-with-seat-reservation'), $e->getMessage());
                $this->cleanup_directory($extract_dir);
                return false;
            }
        }
        
        private function extract_zip_file($zip_file_path) {
            $upload_dir = wp_upload_dir();
            $extract_dir = $upload_dir['basedir'] . '/wbtm-gtfs-validation/' . uniqid() . '/';
            
            if (!wp_mkdir_p($extract_dir)) {
                return false;
            }
            
            $zip = new ZipArchive();
            if ($zip->open($zip_file_path) === TRUE) {
                $zip->extractTo($extract_dir);
                $zip->close();
                return $extract_dir;
            }
            
            return false;
        }
        
        private function validate_file_structure($extract_dir) {
            // Check required files
            foreach ($this->required_files as $required_file) {
                if (!file_exists($extract_dir . $required_file)) {
                    /* translators: %s: required file name */
                    $this->errors[] = sprintf(__('Required file missing: %s', 'bus-ticket-booking-with-seat-reservation'), $required_file);
                }
            }
            
            // Check for unknown files
            $files = scandir($extract_dir);
            $known_files = array_merge($this->required_files, $this->optional_files, ['.', '..']);
            
            foreach ($files as $file) {
                if (!in_array($file, $known_files)) {
                    /* translators: %s: unknown file name */
                    $this->warnings[] = sprintf(__('Unknown file found: %s', 'bus-ticket-booking-with-seat-reservation'), $file);
                }
            }
        }
        
        private function validate_agency_file($extract_dir) {
            $file_path = $extract_dir . 'agency.txt';
            if (!file_exists($file_path)) {
                return; // Already reported as missing
            }
            
            $data = $this->parse_csv_file($file_path);
            if (empty($data)) {
                $this->errors[] = __('agency.txt is empty or invalid', 'bus-ticket-booking-with-seat-reservation');
                return;
            }
            
            $required_fields = ['agency_name', 'agency_url', 'agency_timezone'];
            $this->validate_required_fields($data, $required_fields, 'agency.txt');
            
            // Validate timezone
            foreach ($data as $row_num => $row) {
                if (isset($row['agency_timezone']) && !in_array($row['agency_timezone'], timezone_identifiers_list())) {
                    /* translators: %d: row number, %s: timezone */
                    $this->errors[] = sprintf(__('Invalid timezone in agency.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['agency_timezone']);
                }
                
                if (isset($row['agency_url']) && !filter_var($row['agency_url'], FILTER_VALIDATE_URL)) {
                    /* translators: %d: row number, %s: URL */
                    $this->errors[] = sprintf(__('Invalid URL in agency.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['agency_url']);
                }
            }
        }
        
        private function validate_stops_file($extract_dir) {
            $file_path = $extract_dir . 'stops.txt';
            if (!file_exists($file_path)) {
                return;
            }
            
            $data = $this->parse_csv_file($file_path);
            if (empty($data)) {
                $this->errors[] = __('stops.txt is empty or invalid', 'bus-ticket-booking-with-seat-reservation');
                return;
            }
            
            $required_fields = ['stop_id', 'stop_name', 'stop_lat', 'stop_lon'];
            $this->validate_required_fields($data, $required_fields, 'stops.txt');
            
            $stop_ids = [];
            foreach ($data as $row_num => $row) {
                // Check for duplicate stop IDs
                if (isset($row['stop_id'])) {
                    if (in_array($row['stop_id'], $stop_ids)) {
                        /* translators: %s: stop_id */
                        $this->errors[] = sprintf(__('Duplicate stop_id in stops.txt: %s', 'bus-ticket-booking-with-seat-reservation'), $row['stop_id']);
                    }
                    $stop_ids[] = $row['stop_id'];
                }
                
                // Validate coordinates
                if (isset($row['stop_lat'])) {
                    $lat = floatval($row['stop_lat']);
                    if ($lat < -90 || $lat > 90) {
                        /* translators: %d: row number, %s: latitude */
                        $this->errors[] = sprintf(__('Invalid latitude in stops.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['stop_lat']);
                    }
                }
                
                if (isset($row['stop_lon'])) {
                    $lon = floatval($row['stop_lon']);
                    if ($lon < -180 || $lon > 180) {
                        /* translators: %d: row number, %s: longitude */
                        $this->errors[] = sprintf(__('Invalid longitude in stops.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['stop_lon']);
                    }
                }
                
                // Check for missing coordinates (0,0 is likely invalid)
                if (isset($row['stop_lat']) && isset($row['stop_lon'])) {
                    if (floatval($row['stop_lat']) == 0.0 && floatval($row['stop_lon']) == 0.0) {
                        /* translators: %d: row number */
                        $this->warnings[] = sprintf(__('Stop at coordinates (0,0) in stops.txt row %d - please verify location', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1);
                    }
                }
            }
        }
        
        private function validate_routes_file($extract_dir) {
            $file_path = $extract_dir . 'routes.txt';
            if (!file_exists($file_path)) {
                return;
            }
            
            $data = $this->parse_csv_file($file_path);
            if (empty($data)) {
                $this->errors[] = __('routes.txt is empty or invalid', 'bus-ticket-booking-with-seat-reservation');
                return;
            }
            
            $required_fields = ['route_id', 'route_short_name', 'route_long_name', 'route_type'];
            $this->validate_required_fields($data, $required_fields, 'routes.txt');
            
            $route_ids = [];
            foreach ($data as $row_num => $row) {
                // Check for duplicate route IDs
                if (isset($row['route_id'])) {
                    if (in_array($row['route_id'], $route_ids)) {
                        /* translators: %s: route_id */
                        $this->errors[] = sprintf(__('Duplicate route_id in routes.txt: %s', 'bus-ticket-booking-with-seat-reservation'), $row['route_id']);
                    }
                    $route_ids[] = $row['route_id'];
                }
                
                // Validate route type
                if (isset($row['route_type'])) {
                    $valid_types = [0, 1, 2, 3, 4, 5, 6, 7, 11, 12];
                    if (!in_array(intval($row['route_type']), $valid_types)) {
                        /* translators: %d: row number, %s: route_type */
                        $this->errors[] = sprintf(__('Invalid route_type in routes.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['route_type']);
                    }
                }
                
                // Check that either route_short_name or route_long_name is provided
                if (empty($row['route_short_name']) && empty($row['route_long_name'])) {
                    /* translators: %d: row number */
                    $this->errors[] = sprintf(__('Either route_short_name or route_long_name must be provided in routes.txt row %d', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1);
                }
            }
        }
        
        private function validate_trips_file($extract_dir) {
            $file_path = $extract_dir . 'trips.txt';
            if (!file_exists($file_path)) {
                return;
            }
            
            $data = $this->parse_csv_file($file_path);
            if (empty($data)) {
                $this->errors[] = __('trips.txt is empty or invalid', 'bus-ticket-booking-with-seat-reservation');
                return;
            }
            
            $required_fields = ['route_id', 'service_id', 'trip_id'];
            $this->validate_required_fields($data, $required_fields, 'trips.txt');
            
            $trip_ids = [];
            foreach ($data as $row_num => $row) {
                // Check for duplicate trip IDs
                if (isset($row['trip_id'])) {
                    if (in_array($row['trip_id'], $trip_ids)) {
                        /* translators: %s: trip_id */
                        $this->errors[] = sprintf(__('Duplicate trip_id in trips.txt: %s', 'bus-ticket-booking-with-seat-reservation'), $row['trip_id']);
                    }
                    $trip_ids[] = $row['trip_id'];
                }
                
                // Validate direction_id
                if (isset($row['direction_id']) && !in_array($row['direction_id'], ['0', '1', ''])) {
                    /* translators: %d: row number, %s: direction_id */
                    $this->errors[] = sprintf(__('Invalid direction_id in trips.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['direction_id']);
                }
                
                // Validate wheelchair_accessible
                if (isset($row['wheelchair_accessible']) && !in_array($row['wheelchair_accessible'], ['0', '1', '2', ''])) {
                    /* translators: %d: row number, %s: wheelchair_accessible */
                    $this->errors[] = sprintf(__('Invalid wheelchair_accessible in trips.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['wheelchair_accessible']);
                }
                
                // Validate bikes_allowed
                if (isset($row['bikes_allowed']) && !in_array($row['bikes_allowed'], ['0', '1', '2', ''])) {
                    /* translators: %d: row number, %s: bikes_allowed */
                    $this->errors[] = sprintf(__('Invalid bikes_allowed in trips.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['bikes_allowed']);
                }
            }
        }
        
        private function validate_stop_times_file($extract_dir) {
            $file_path = $extract_dir . 'stop_times.txt';
            if (!file_exists($file_path)) {
                return;
            }
            
            $data = $this->parse_csv_file($file_path);
            if (empty($data)) {
                $this->errors[] = __('stop_times.txt is empty or invalid', 'bus-ticket-booking-with-seat-reservation');
                return;
            }
            
            $required_fields = ['trip_id', 'arrival_time', 'departure_time', 'stop_id', 'stop_sequence'];
            $this->validate_required_fields($data, $required_fields, 'stop_times.txt');
            
            $trips = [];
            foreach ($data as $row_num => $row) {
                // Group by trip_id for sequence validation
                if (isset($row['trip_id'])) {
                    if (!isset($trips[$row['trip_id']])) {
                        $trips[$row['trip_id']] = [];
                    }
                    $trips[$row['trip_id']][] = $row;
                }
                
                // Validate time format
                if (isset($row['arrival_time']) && !$this->validate_time_format($row['arrival_time'])) {
                    /* translators: %d: row number, %s: arrival_time */
                    $this->errors[] = sprintf(__('Invalid arrival_time format in stop_times.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['arrival_time']);
                }
                
                if (isset($row['departure_time']) && !$this->validate_time_format($row['departure_time'])) {
                    /* translators: %d: row number, %s: departure_time */
                    $this->errors[] = sprintf(__('Invalid departure_time format in stop_times.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['departure_time']);
                }
                
                // Validate pickup_type and drop_off_type
                if (isset($row['pickup_type']) && !in_array($row['pickup_type'], ['0', '1', '2', '3', ''])) {
                    /* translators: %d: row number, %s: pickup_type */
                    $this->errors[] = sprintf(__('Invalid pickup_type in stop_times.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['pickup_type']);
                }
                
                if (isset($row['drop_off_type']) && !in_array($row['drop_off_type'], ['0', '1', '2', '3', ''])) {
                    /* translators: %d: row number, %s: drop_off_type */
                    $this->errors[] = sprintf(__('Invalid drop_off_type in stop_times.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['drop_off_type']);
                }
            }
            
            // Validate stop sequences for each trip
            foreach ($trips as $trip_id => $trip_stops) {
                $sequences = array_column($trip_stops, 'stop_sequence');
                if (count($sequences) !== count(array_unique($sequences))) {
                    /* translators: %s: trip_id */
                    $this->errors[] = sprintf(__('Duplicate stop_sequence values for trip_id: %s', 'bus-ticket-booking-with-seat-reservation'), $trip_id);
                }
                
                // Check if sequences are in order
                $sorted_sequences = $sequences;
                sort($sorted_sequences, SORT_NUMERIC);
                if ($sequences !== $sorted_sequences) {
                    /* translators: %s: trip_id */
                    $this->warnings[] = sprintf(__('Stop sequences not in order for trip_id: %s', 'bus-ticket-booking-with-seat-reservation'), $trip_id);
                }
            }
        }
        
        private function validate_calendar_files($extract_dir) {
            $calendar_exists = file_exists($extract_dir . 'calendar.txt');
            $calendar_dates_exists = file_exists($extract_dir . 'calendar_dates.txt');
            
            if (!$calendar_exists && !$calendar_dates_exists) {
                $this->errors[] = __('Either calendar.txt or calendar_dates.txt must be provided', 'bus-ticket-booking-with-seat-reservation');
                return;
            }
            
            if ($calendar_exists) {
                $this->validate_calendar_file($extract_dir);
            }
            
            if ($calendar_dates_exists) {
                $this->validate_calendar_dates_file($extract_dir);
            }
        }
        
        private function validate_calendar_file($extract_dir) {
            $file_path = $extract_dir . 'calendar.txt';
            $data = $this->parse_csv_file($file_path);
            
            if (empty($data)) {
                $this->errors[] = __('calendar.txt is empty or invalid', 'bus-ticket-booking-with-seat-reservation');
                return;
            }
            
            $required_fields = ['service_id', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday', 'start_date', 'end_date'];
            $this->validate_required_fields($data, $required_fields, 'calendar.txt');
            
            foreach ($data as $row_num => $row) {
                // Validate day values (0 or 1)
                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                foreach ($days as $day) {
                    if (isset($row[$day]) && !in_array($row[$day], ['0', '1'])) {
                        /* translators: %s: day, %d: row number, %s: value */
                        $this->errors[] = sprintf(__('Invalid %s value in calendar.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $day, $row_num + 1, $row[$day]);
                    }
                }
                
                // Validate date format (YYYYMMDD)
                if (isset($row['start_date']) && !$this->validate_date_format($row['start_date'])) {
                    /* translators: %d: row number, %s: start_date */
                    $this->errors[] = sprintf(__('Invalid start_date format in calendar.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['start_date']);
                }
                
                if (isset($row['end_date']) && !$this->validate_date_format($row['end_date'])) {
                    /* translators: %d: row number, %s: end_date */
                    $this->errors[] = sprintf(__('Invalid end_date format in calendar.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['end_date']);
                }
                
                // Check that end_date is after start_date
                if (isset($row['start_date']) && isset($row['end_date'])) {
                    if ($row['end_date'] < $row['start_date']) {
                        /* translators: %d: row number */
                        $this->errors[] = sprintf(__('end_date must be after start_date in calendar.txt row %d', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1);
                    }
                }
            }
        }
        
        private function validate_calendar_dates_file($extract_dir) {
            $file_path = $extract_dir . 'calendar_dates.txt';
            $data = $this->parse_csv_file($file_path);
            
            if (empty($data)) {
                $this->errors[] = __('calendar_dates.txt is empty or invalid', 'bus-ticket-booking-with-seat-reservation');
                return;
            }
            
            $required_fields = ['service_id', 'date', 'exception_type'];
            $this->validate_required_fields($data, $required_fields, 'calendar_dates.txt');
            
            foreach ($data as $row_num => $row) {
                // Validate date format
                if (isset($row['date']) && !$this->validate_date_format($row['date'])) {
                    /* translators: %d: row number, %s: date */
                    $this->errors[] = sprintf(__('Invalid date format in calendar_dates.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['date']);
                }
                
                // Validate exception_type
                if (isset($row['exception_type']) && !in_array($row['exception_type'], ['1', '2'])) {
                    /* translators: %d: row number, %s: exception_type */
                    $this->errors[] = sprintf(__('Invalid exception_type in calendar_dates.txt row %d: %s', 'bus-ticket-booking-with-seat-reservation'), $row_num + 1, $row['exception_type']);
                }
            }
        }
        
        private function validate_references($extract_dir) {
            // This would implement cross-file reference validation
            // For example, checking that all route_ids in trips.txt exist in routes.txt
            // This is a complex validation that would require loading all files into memory
            // and cross-referencing IDs
        }
        
        private function parse_csv_file($file_path) {
            if (!file_exists($file_path)) {
                return false;
            }
            
            $data = [];
            if (($handle = fopen($file_path, "r")) !== FALSE) {
                $headers = fgetcsv($handle);
                if ($headers === FALSE) {
                    fclose($handle);
                    return false;
                }
                
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) === count($headers)) {
                        $data[] = array_combine($headers, $row);
                    }
                }
                fclose($handle);
            }
            
            return $data;
        }
        
        private function validate_required_fields($data, $required_fields, $filename) {
            if (empty($data)) {
                return;
            }
            
            $headers = array_keys($data[0]);
            foreach ($required_fields as $field) {
                if (!in_array($field, $headers)) {
                    /* translators: %s: filename, %s: field */
                    $this->errors[] = sprintf(__('Required field missing in %s: %s', 'bus-ticket-booking-with-seat-reservation'), $filename, $field);
                }
            }
        }
        
        private function validate_time_format($time) {
            // GTFS allows times like 25:30:00 for next day
            return preg_match('/^([0-9]{1,2}):([0-5][0-9]):([0-5][0-9])$/', $time);
        }
        
        private function validate_date_format($date) {
            return preg_match('/^[0-9]{8}$/', $date) && checkdate(
                substr($date, 4, 2),
                substr($date, 6, 2),
                substr($date, 0, 4)
            );
        }
        
        private function cleanup_directory($dir) {
            if (is_dir($dir)) {
                $files = array_diff(scandir($dir), ['.', '..']);
                foreach ($files as $file) {
                    $file_path = $dir . $file;
                    if (is_dir($file_path)) {
                        $this->cleanup_directory($file_path . '/');
                    } else {
                        unlink($file_path);
                    }
                }
                rmdir($dir);
            }
        }
        
        public function get_errors() {
            return $this->errors;
        }
        
        public function get_warnings() {
            return $this->warnings;
        }
        
        public function has_errors() {
            return !empty($this->errors);
        }
        
        public function has_warnings() {
            return !empty($this->warnings);
        }
        
        public function get_validation_report() {
            $report = [
                'is_valid' => !$this->has_errors(),
                'errors' => $this->get_errors(),
                'warnings' => $this->get_warnings(),
                'error_count' => count($this->errors),
                'warning_count' => count($this->warnings)
            ];
            
            return $report;
        }
        
        public function handle_gtfs_validation() {
            // Handle AJAX validation requests
            if (!wp_verify_nonce($_POST['nonce'], 'wbtm_gtfs_validation')) {
                wp_die(__('Security check failed', 'bus-ticket-booking-with-seat-reservation'));
            }
            
            if (!current_user_can('manage_options')) {
                wp_die(__('Insufficient permissions', 'bus-ticket-booking-with-seat-reservation'));
            }
            
            $file_url = sanitize_url($_POST['file_url']);
            $file_path = str_replace(wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $file_url);
            
            $is_valid = $this->validate_gtfs_feed($file_path);
            $report = $this->get_validation_report();
            
            wp_send_json_success($report);
        }
        
        public function validate_exported_feed($zip_file_path) {
            $is_valid = $this->validate_gtfs_feed($zip_file_path);
            
            if (!$is_valid) {
                // Log validation errors
                error_log('GTFS Validation Errors: ' . print_r($this->get_errors(), true));
            }
            
            return $is_valid;
        }
    }
    
    // Initialize the GTFS Validator class
    new WBTM_GTFS_Validator();
}
