<?php
	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.
	/**
	 * @package WBTM_Plugin
	 */
	if (!class_exists('WBTM_Welcome')) {
		class WBTM_Welcome {
			public function __construct() {
				add_action("admin_menu", array($this, "WBTM_welcome_init"));
			}
			public function WBTM_welcome_init() {
				add_submenu_page('edit.php?post_type=wbtm_bus', __('Welcome to WBTM', 'bus-ticket-booking-with-seat-reservation'), '<span style="color:#13df13">' . __('Welcome', 'bus-ticket-booking-with-seat-reservation') . '</span>', 'manage_options', 'admin/WBTM_Welcome', array($this, "WBTM_welcome_page_callback"));
			}
			public function WBTM_welcome_page_callback() {
				$pro_badge = '<span class="wbtm-badge pro-badge">' . __("PRO", "bus-ticket-booking-with-seat-reservation") . '</span>';
				?>
                <style>
                    :root {
                        --wbtm-primary-color: var(--wbtm_color_theme, #ff4500);
                        --wbtm-secondary-color: #2c3e50;
                        --wbtm-bg-light: #f8f9fa;
                        --wbtm-white: #ffffff;
                        --wbtm-text-muted: #6c757d;
                        --wbtm-border: #e9ecef;
                        --wbtm-shadow: 0 4px 6px rgba(0,0,0,0.1);
                        --wbtm-radius: 8px;
                    }
                    .wbtm_welcome_wrap {
                        margin: 20px 20px 0 0;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    }
                    .wbtm-welcome-header {
                        background: var(--wbtm-white);
                        padding: 30px;
                        border-radius: var(--wbtm-radius);
                        box-shadow: var(--wbtm-shadow);
                        margin-bottom: 20px;
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        position: relative;
                        overflow: hidden;
                    }
                    .wbtm-welcome-header::before {
                        content: '';
                        position: absolute;
                        top: 0; left: 0; width: 5px; height: 100%;
                        background: var(--wbtm-primary-color);
                    }
                    .wbtm-welcome-header h1 {
                        margin: 0;
                        font-size: 28px;
                        color: var(--wbtm-secondary-color);
                    }
                    .wbtm-welcome-header p {
                        margin: 10px 0 0;
                        font-size: 16px;
                        color: var(--wbtm-text-muted);
                    }
                    .wbtm-tabs-container {
                        display: flex;
                        gap: 20px;
                    }
                    .wbtm-tabs-nav {
                        width: 250px;
                        background: var(--wbtm-white);
                        border-radius: var(--wbtm-radius);
                        box-shadow: var(--wbtm-shadow);
                        padding: 10px;
                        height: fit-content;
                    }
                    .wbtm-tab-link {
                        display: flex;
                        align-items: center;
                        padding: 12px 15px;
                        margin-bottom: 5px;
                        border-radius: 6px;
                        cursor: pointer;
                        color: var(--wbtm-secondary-color);
                        font-weight: 500;
                        transition: all 0.3s ease;
                        text-decoration: none;
                    }
                    .wbtm-tab-link i {
                        margin-right: 12px;
                        width: 20px;
                        text-align: center;
                        font-size: 18px;
                    }
                    .wbtm-tab-link:hover {
                        background: #f0f4f8;
                        color: var(--wbtm-primary-color);
                    }
                    .wbtm-tab-link.active {
                        background: var(--wbtm-primary-color);
                        color: var(--wbtm-white);
                    }
                    .wbtm-tabs-content {
                        flex: 1;
                        background: var(--wbtm-white);
                        border-radius: var(--wbtm-radius);
                        box-shadow: var(--wbtm-shadow);
                        padding: 30px;
                        min-height: 500px;
                    }
                    .wbtm-tab-pane {
                        display: none;
                        animation: fadeIn 0.4s ease;
                    }
                    .wbtm-tab-pane.active {
                        display: block;
                    }
                    @keyframes fadeIn {
                        from { opacity: 0; transform: translateY(10px); }
                        to { opacity: 1; transform: translateY(0); }
                    }

                    /* Content Styles */
                    .wbtm-section-title {
                        margin: 0 0 20px;
                        font-size: 22px;
                        padding-bottom: 10px;
                        border-bottom: 2px solid var(--wbtm-bg-light);
                        color: var(--wbtm-secondary-color);
                    }
                    .wbtm-grid {
                        display: grid;
                        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                        gap: 20px;
                        margin-top: 20px;
                    }
                    .wbtm-card {
                        background: var(--wbtm-bg-light);
                        border-radius: var(--wbtm-radius);
                        padding: 20px;
                        border: 1px solid var(--wbtm-border);
                        transition: transform 0.3s ease;
                    }
                    .wbtm-card:hover {
                        transform: translateY(-5px);
                    }
                    .wbtm-card h3 {
                        margin: 0 0 15px;
                        font-size: 18px;
                        color: var(--wbtm-secondary-color);
                    }
                    .wbtm-video-wrapper {
                        position: relative;
                        padding-bottom: 56.25%;
                        height: 0;
                        overflow: hidden;
                        border-radius: 6px;
                        margin-bottom: 15px;
                    }
                    .wbtm-video-wrapper iframe {
                        position: absolute;
                        top: 0; left: 0; width: 100%; height: 100%;
                    }

                    /* Import Step Table */
                    .wbtm-steps-table {
                        width: 100%;
                        border-collapse: collapse;
                    }
                    .wbtm-steps-table td {
                        padding: 20px;
                        vertical-align: top;
                        border-bottom: 1px solid var(--wbtm-border);
                    }
                    .wbtm-step-num {
                        background: var(--wbtm-primary-color);
                        color: #fff;
                        width: 30px;
                        height: 30px;
                        border-radius: 50%;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        font-weight: bold;
                        flex-shrink: 0;
                    }

                    /* Table Styles */
                    .wbtm-data-table {
                        width: 100%;
                        border-collapse: collapse;
                        margin-top: 20px;
                    }
                    .wbtm-data-table th {
                        background: var(--wbtm-bg-light);
                        text-align: left;
                        padding: 12px 15px;
                        border: 1px solid var(--wbtm-border);
                        font-weight: 600;
                    }
                    .wbtm-data-table td {
                        padding: 12px 15px;
                        border: 1px solid var(--wbtm-border);
                    }
                    .wbtm-data-table code {
                        background: #fff3ed;
                        color: #d35400;
                        padding: 3px 6px;
                        border-radius: 4px;
                        font-size: 13px;
                    }

                    /* Badges & Buttons */
                    .wbtm-badge {
                        padding: 3px 8px;
                        border-radius: 4px;
                        font-size: 10px;
                        font-weight: bold;
                        margin-left: 5px;
                        vertical-align: middle;
                    }
                    .pro-badge { background: #ffd700; color: #000; }
                    .wbtm-btn {
                        display: inline-block;
                        padding: 10px 20px;
                        background: var(--wbtm-primary-color);
                        color: #fff !important;
                        text-decoration: none;
                        border-radius: 5px;
                        font-weight: 500;
                        transition: opacity 0.3s;
                    }
                    .wbtm-btn:hover { opacity: 0.9; }
                    .pro-btn { background: #27ae60; }

                    /* Responsive */
                    @media (max-width: 900px) {
                        .wbtm-tabs-container { flex-direction: column; }
                        .wbtm-tabs-nav { width: 100%; }
                    }
                </style>

                <div class="wrap wbtm_welcome_wrap">
                    <div class="wbtm-welcome-header">
                        <div>
                            <h1><?php esc_html_e('Bus Ticket Booking & Reservation', 'bus-ticket-booking-with-seat-reservation'); ?></h1>
                            <p><?php esc_html_e('Thank you for choosing the most comprehensive bus ticketing system for WordPress.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                        </div>
                        <a href="https://mage-people.com/product/addon-bus-ticket-booking-with-seat-reservation-pro/" class="wbtm-btn pro-btn" target="_blank">
                            <i class="fas fa-crown"></i> <?php esc_html_e('Unlock PRO Features', 'bus-ticket-booking-with-seat-reservation'); ?>
                        </a>
                    </div>

                    <div class="wbtm-tabs-container">
                        <div class="wbtm-tabs-nav">
                            <div class="wbtm-tab-link active" data-tab="tab-get-started">
                                <i class="fas fa-rocket"></i> <?php esc_html_e('Get Started', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </div>
                            <div class="wbtm-tab-link" data-tab="tab-tutorials">
                                <i class="fas fa-play-circle"></i> <?php esc_html_e('Video Tutorials', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </div>
                            <div class="wbtm-tab-link" data-tab="tab-shortcodes">
                                <i class="fas fa-code"></i> <?php esc_html_e('Shortcodes', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </div>
                            <div class="wbtm-tab-link" data-tab="tab-support">
                                <i class="fas fa-headset"></i> <?php esc_html_e('Help & Support', 'bus-ticket-booking-with-seat-reservation'); ?>
                            </div>
                        </div>

                        <div class="wbtm-tabs-content">
                            <!-- Get Started Tab -->
                            <div id="tab-get-started" class="wbtm-tab-pane active">
                                <h2 class="wbtm-section-title"><?php esc_html_e('How to Import Dummy Content', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                                <p><?php esc_html_e('Quickly set up your site by importing our pre-configured dummy data.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                
                                <table class="wbtm-steps-table">
                                    <tr>
                                        <td width="50"><div class="wbtm-step-num">1</div></td>
                                        <td>
                                            <h3><?php esc_html_e('Download Source Files', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                            <p><?php esc_html_e('Download the XML data file to your computer.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                            <div style="margin-top: 15px;">
                                                <a href="https://bus.mage-people.com/bus-dummy-content.zip" class="wbtm-btn" style="background: #34495e;">
                                                    <i class="fas fa-download"></i> <?php esc_html_e('Download Dummy XML (ZIP)', 'bus-ticket-booking-with-seat-reservation'); ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><div class="wbtm-step-num">2</div></td>
                                        <td>
                                            <h3><?php esc_html_e('Import Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                            <p><?php printf(__('Navigate to %s and select "WordPress" importer.', 'bus-ticket-booking-with-seat-reservation'), '<strong>Tools &raquo; Import</strong>'); ?></p>
                                            <p style="margin-top:10px; font-size: 13px; font-style: italic;">
                                                <?php esc_html_e('Note: If the WordPress importer is not installed, click "Install Now" first.', 'bus-ticket-booking-with-seat-reservation'); ?>
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><div class="wbtm-step-num">3</div></td>
                                        <td>
                                            <h3><?php esc_html_e('Run & Assign', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                            <p><?php esc_html_e('Upload the XML file, assign a user to the posts, and check "Download and import file attachments". Click Submit to finish.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Tutorials Tab -->
                            <div id="tab-tutorials" class="wbtm-tab-pane">
                                <h2 class="wbtm-section-title"><?php esc_html_e('Video Learning Center', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                                <div class="wbtm-grid">
                                    <?php
                                    $videos = array(
                                        array('title' => 'Initial Plugin Setup', 'id' => '1kY9vFIJdE4'),
                                        array('title' => 'Adding Your First Bus', 'id' => 'N_6MbfzZw84'),
                                        array('title' => 'Booking Process Overview', 'id' => 'vAMln7298eg'),
                                        array('title' => 'PDF Ticket Configuration', 'id' => '8F_Jw2_alGw', 'pro' => true),
                                        array('title' => 'Email Notifications', 'id' => 'hbc0kYd8zA8'),
                                        array('title' => 'Setting Ticket Prices', 'id' => '5XNiRwl9VAM'),
                                        array('title' => 'Bus Booking on Specific Day', 'id' => 'z18HXrPf0-Q'),
                                        array('title' => 'Booking Buffer Time', 'id' => '7McbXsaPHEg'),
                                        array('title' => 'Get Ticket from Admin', 'id' => 'TmB_FEbQagk'),
                                        array('title' => 'Export Passenger List (CSV)', 'id' => '9ODsKeFwMpY'),
                                        array('title' => '2-Door & 4-Column Plan', 'id' => 'Mh_2UUKo8Nk'),
                                        array('title' => '3-Column Seat Plan', 'id' => '2yEfMio10-I'),
                                        array('title' => 'General Booking Guide', 'id' => 'fK1-JCuI9rY'),
                                    );
                                    foreach ($videos as $video) :
                                    ?>
                                    <div class="wbtm-card">
                                        <div class="wbtm-video-wrapper">
                                            <iframe src="https://www.youtube.com/embed/<?php echo esc_attr($video['id']); ?>" frameborder="0" allowfullscreen></iframe>
                                        </div>
                                        <h3 style="font-size: 15px; margin-bottom: 0;">
                                            <?php echo esc_html($video['title']); ?>
                                            <?php if (isset($video['pro'])) echo $pro_badge; ?>
                                        </h3>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Shortcodes Tab -->
                            <div id="tab-shortcodes" class="wbtm-tab-pane">
                                <h2 class="wbtm-section-title"><?php esc_html_e('Available Shortcodes', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                                <table class="wbtm-data-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Shortcode', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                            <th><?php esc_html_e('Description', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><code>[wbtm-bus-list cat="" show=""]</code></td>
                                            <td><?php esc_html_e('Displays a list of available buses. Filter by "cat" (Category ID) and "show" (Items per page).', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><code>[wbtm-bus-search-form]</code></td>
                                            <td><?php esc_html_e('Displays only the search form to place anywhere on your site.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><code>[wbtm-bus-search]</code></td>
                                            <td><?php esc_html_e('Displays both the search form and results on the same page.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><code>[view-ticket]</code></td>
                                            <td><?php esc_html_e('Allows passengers to view and print their tickets using a PIN.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><code>[wbtm-bus-details id="" name=""]</code></td>
                                            <td><?php esc_html_e('Display detailed information for a single bus by ID or Name.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Support Tab -->
                            <div id="tab-support" class="wbtm-tab-pane">
                                <h2 class="wbtm-section-title"><?php esc_html_e('Help & Support', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                                <div class="wbtm-grid" style="grid-template-columns: 1fr 1fr;">
                                    <div class="wbtm-card">
                                        <h3><i class="fas fa-book-open"></i> <?php esc_html_e('Documentation', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                        <p><?php esc_html_e('Explore our detailed guides and knowledge base for advanced configurations.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                        <div style="margin-top: 20px;">
                                            <a href="https://mage-people.com/docs/bus-ticket-booking-with-seat-reservation/" class="wbtm-btn" target="_blank"><?php esc_html_e('Read Documentation', 'bus-ticket-booking-with-seat-reservation'); ?></a>
                                        </div>
                                    </div>
                                    <div class="wbtm-card">
                                        <h3><i class="fas fa-ticket-alt"></i> <?php esc_html_e('Technical Support', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                        <p><?php esc_html_e('Stuck? Our expert developers are here to help you solve any technical issues.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                        <div style="margin-top: 20px;">
                                            <a href="https://mage-people.com/contact-us/" class="wbtm-btn" style="background: #2980b9;" target="_blank"><?php esc_html_e('Open Support Ticket', 'bus-ticket-booking-with-seat-reservation'); ?></a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <script type="text/javascript">
                    jQuery(document).ready(function($) {
                        $('.wbtm-tab-link').click(function() {
                            var tab_id = $(this).attr('data-tab');

                            $('.wbtm-tab-link').removeClass('active');
                            $('.wbtm-tab-pane').removeClass('active');

                            $(this).addClass('active');
                            $("#" + tab_id).addClass('active');
                        });
                    });
                </script>
				<?php
			}
		}
		new WBTM_Welcome();
	}
