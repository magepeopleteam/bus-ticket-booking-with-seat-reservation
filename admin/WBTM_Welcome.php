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
                            <div class="wbtm-tab-link" data-tab="tab-documents">
                                <i class="fas fa-book-open"></i> <?php esc_html_e('Documents', 'bus-ticket-booking-with-seat-reservation'); ?>
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

                            <!-- Documents Tab -->
                            <div id="tab-documents" class="wbtm-tab-pane">
                                <h2 class="wbtm-section-title"><?php esc_html_e('Documentation', 'bus-ticket-booking-with-seat-reservation'); ?></h2>
                                <p style="margin-bottom:20px;color:var(--wbtm-text-muted);"><?php esc_html_e('Select a topic below to learn about each section of the bus settings.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                <style>
                                    .wbtm-doc-tabs { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:20px; }
                                    .wbtm-doc-tab-btn {
                                        padding:8px 16px; border:1px solid var(--wbtm-border); border-radius:6px;
                                        background:var(--wbtm-bg-light); color:var(--wbtm-secondary-color);
                                        cursor:pointer; font-size:13px; font-weight:500; transition:all .25s;
                                    }
                                    .wbtm-doc-tab-btn:hover { background:#e8eaed; }
                                    .wbtm-doc-tab-btn.active { background:var(--wbtm-primary-color); color:#fff; border-color:var(--wbtm-primary-color); }
                                    .wbtm-doc-panel { display:none; animation:fadeIn .4s ease; }
                                    .wbtm-doc-panel.active { display:block; }
                                    .wbtm-doc-panel h3 { margin:0 0 12px; font-size:18px; color:var(--wbtm-secondary-color); }
                                    .wbtm-doc-panel h4 { margin:18px 0 8px; font-size:15px; color:var(--wbtm-secondary-color); }
                                    .wbtm-doc-panel p { line-height:1.7; color:#444; }
                                    .wbtm-doc-panel ul { padding-left:20px; }
                                    .wbtm-doc-panel li { margin-bottom:8px; line-height:1.6; color:#444; }
                                    .wbtm-doc-panel .doc-note {
                                        background:#fff8e1; border-left:4px solid #ffc107; padding:12px 16px;
                                        border-radius:4px; margin:12px 0; font-size:13px;
                                    }
                                    .wbtm-doc-panel .doc-tip {
                                        background:#e8f5e9; border-left:4px solid #4caf50; padding:12px 16px;
                                        border-radius:4px; margin:12px 0; font-size:13px;
                                    }
                                    .wbtm-doc-panel .doc-pro-section {
                                        background:#f3e5f5; border:1px solid #ce93d8; border-radius:8px;
                                        padding:16px 20px; margin:16px 0;
                                    }
                                    .wbtm-doc-panel .doc-pro-section h4 { color:#7b1fa2; margin-top:0; }
                                    .wbtm-doc-tabs .doc-tab-pro {
                                        background:linear-gradient(135deg,#f3e5f5,#e1bee7); border-color:#ce93d8;
                                    }
                                    .wbtm-doc-tabs .doc-tab-pro.active { background:linear-gradient(135deg,#7b1fa2,#9c27b0); border-color:#7b1fa2; }
                                </style>

                                <div class="wbtm-doc-tabs">
                                    <button class="wbtm-doc-tab-btn active" data-doc="doc-general"><?php esc_html_e('General Info', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-seat"><?php esc_html_e('Seat Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-pricing"><?php esc_html_e('Pricing & Route', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-extra-service"><?php esc_html_e('Extra Service', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-pickup"><?php esc_html_e('Pickup / Drop-Off', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-date"><?php esc_html_e('Date Settings', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-tax"><?php esc_html_e('Tax Configure', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-gallery"><?php esc_html_e('Gallery Image', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-terms"><?php esc_html_e('Terms & Conditions', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-features"><?php esc_html_e('Bus Features', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-reg-form"><?php esc_html_e('Registration Form', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-deposit"><?php esc_html_e('Deposit / Partial Payment', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-return-discount"><?php esc_html_e('Return Discount', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-ai-chatbot"><?php esc_html_e('AI Chatbot', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-pdf-tickets"><?php esc_html_e('PDF Tickets', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-email-settings"><?php esc_html_e('Email Settings', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-passenger-list"><?php esc_html_e('Passenger List & Export', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-booking-calendar"><?php esc_html_e('Booking Calendar', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-sales-report"><?php esc_html_e('Sales Report', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-purchase-ticket"><?php esc_html_e('Purchase Ticket (Admin)', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-view-ticket"><?php esc_html_e('View Ticket (Frontend)', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn doc-tab-pro" data-doc="doc-staff-role"><?php esc_html_e('Bus Staff Role', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-global"><?php esc_html_e('Global Settings', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                    <button class="wbtm-doc-tab-btn" data-doc="doc-shortcodes-doc"><?php esc_html_e('Shortcodes', 'bus-ticket-booking-with-seat-reservation'); ?></button>
                                </div>

                                <!-- General Info -->
                                <div id="doc-general" class="wbtm-doc-panel active">
                                    <h3><i class="fas fa-cog"></i> <?php esc_html_e('General Info Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('This is the first tab you see when editing a bus. It contains the essential identification and basic configuration for each bus.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Bus Logo', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Upload a logo image for the bus company or operator. Displayed on the bus listing and detail pages.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Bus Number', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('A unique identifier for each bus (e.g., plate number or internal code). Used internally to distinguish buses.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Coach Type / Category', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Select the bus category from pre-defined categories (e.g., AC, Non-AC, Volvo, Scania). Categories are managed under Bus → Categories in the admin menu.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Reservation On/Off', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Toggle seat reservation for this bus. Turn OFF to disable seat selection (useful for general boarding without assigned seats).', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Boarding Time', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Show or hide the boarding time on the frontend.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Dropping Time', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Show or hide the dropping time on the frontend.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-tip"><?php esc_html_e('Tip: Set the Bus Number and Category before configuring other tabs — the pricing and seat layout depend on these basics.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                </div>

                                <!-- Seat Configuration -->
                                <div id="doc-seat" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-chair"></i> <?php esc_html_e('Seat Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('Configure how seats are displayed and booked on this bus. Choose from multiple seat plan layouts.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Seat Type', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Select the seat layout type: Without Seat Plan (general ticket), 2-Column, 3-Column, or 4-Column layout.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Total Seats', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enter the total number of seats available on the bus.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Seat Price', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Set the base price per seat. This can be overridden per route in the Pricing & Route tab.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Seat Layout Options', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Depending on the selected seat type, you can configure row/column arrangements, aisle position, driver seat location, and door side.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-note"><?php esc_html_e('Note: The "Without Seat Plan" option allows customers to book without choosing a specific seat. All other options show an interactive seat map during booking.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                </div>

                                <!-- Pricing & Route -->
                                <div id="doc-pricing" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-coins"></i> <?php esc_html_e('Pricing & Route Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('This is the core tab where you define where the bus goes, at what times, and how much each route costs.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Boarding & Dropping Stops', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Add stop points in order. Each stop has a name, arrival time, and a price for the route up to that point. Stops must be created first under Bus → Bus Stops.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Same Bus Return Journey', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enable this to allow the same bus to appear in return trip searches. You can configure separate return pricing or let the system reverse the outbound route automatically.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Ticket Types', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Create different ticket categories (Adult, Child, Senior, etc.) with their own pricing for each route.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-pro-section">
                                        <h4><i class="fas fa-crown"></i> <?php esc_html_e('PRO Feature: Return Discount', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h4>
                                        <p><?php esc_html_e('When the PRO addon is active, a "Return Discount" section appears inside this tab. Offer a percentage or fixed discount to customers who book a return journey on the same bus.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                        <ul>
                                            <li><strong><?php esc_html_e('Discount Value', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enter the discount amount (e.g., 10 for 10%).', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                            <li><strong><?php esc_html_e('Discount Type', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Choose Percentage (%) or Fixed amount.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        </ul>
                                        <p><?php esc_html_e('The discount is automatically applied in the WooCommerce cart when the customer books a return trip (B→A) after already booking the outbound (A→B).', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    </div>
                                    <div class="doc-tip"><?php esc_html_e('Tip: Always configure the route stops BEFORE setting prices. The pricing table is generated based on the stops you define.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                </div>

                                <!-- Extra Service -->
                                <div id="doc-extra-service" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-concierge-bell"></i> <?php esc_html_e('Extra Service', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('Offer additional paid services that customers can add during booking (e.g., luggage, meals, Wi-Fi).', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Enable/Disable', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Toggle extra services on or off for this bus.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Service Name', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('The name of the extra service as shown to customers.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Service Price', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('The cost of the extra service. Can be a fixed amount per booking or per passenger.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Service Image/Icon', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Optional visual representation for the service.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                </div>

                                <!-- Pickup / Drop-Off -->
                                <div id="doc-pickup" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-map-marker-alt"></i> <?php esc_html_e('Pickup / Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('Define specific pickup and drop-off locations within each stop for customer convenience.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Enable Pickup Point', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Turn on to allow customers to select a specific pickup point instead of the main boarding stop.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Enable Drop-Off Point', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Turn on to allow customers to select a specific drop-off point instead of the main dropping stop.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Point Name & Time', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('For each route stop, add sub-locations with their own arrival/departure times.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-tip"><?php esc_html_e('Tip: Pickup/Drop-off points are useful when a bus stop covers a large area and you want to offer precise meeting points (e.g., "Bus Stand Gate 3", "Railway Station North Exit").', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                </div>

                                <!-- Date Settings -->
                                <div id="doc-date" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-calendar-alt"></i> <?php esc_html_e('Date Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('Control when this bus operates. Choose between recurring weekly schedules or specific one-time dates.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Operational On Day', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Select whether the bus runs on specific dates or on repeated weekly days (e.g., every Monday, Wednesday, Friday).', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Repeated Days', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('If using weekly schedule, check the days of the week this bus operates.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Particular Dates', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Add specific dates when the bus runs. Useful for special services or seasonal routes.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Off Day Schedule', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Define dates when the bus does NOT run (holidays, maintenance days, etc.).', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-note"><?php esc_html_e('Note: If both particular dates and repeated days are set, particular dates take priority. Off days override both.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                </div>

                                <!-- Tax Configure -->
                                <div id="doc-tax" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-percentage"></i> <?php esc_html_e('Tax Configuration', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('Set up tax rules specific to this bus booking.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Enable Tax', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Turn on tax calculation for this bus. When enabled, tax is added to the ticket price during checkout.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Tax Amount/Percent', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enter the tax value. Can be a fixed amount or a percentage depending on your WooCommerce tax settings.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-tip"><?php esc_html_e('Tip: If you want global tax rules for all buses, configure them in WooCommerce → Settings → Tax instead of per-bus.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                </div>

                                <!-- Gallery Image -->
                                <div id="doc-gallery" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-images"></i> <?php esc_html_e('Gallery Image', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('Upload images to showcase the bus interior, exterior, and amenities on the bus detail page.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Upload Images', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Click to add multiple images. These appear in a gallery slider on the bus detail page.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Drag to Reorder', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Reorder images by dragging. The first image is used as the featured/thumbnail image.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                </div>

                                <!-- Terms & Conditions -->
                                <div id="doc-terms" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-file-contract"></i> <?php esc_html_e('Terms & Conditions', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('Add custom terms and conditions for this specific bus. Customers must agree before booking.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Enable T&C', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Turn on to show a terms & conditions checkbox during the booking process.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Terms Content', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enter your terms and conditions text. You can also set global terms from the Settings → Terms & Conditions page.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-note"><?php esc_html_e('Note: If both global and per-bus terms are set, the per-bus terms take priority for that specific bus.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                </div>

                                <!-- Bus Features -->
                                <div id="doc-features" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-star"></i> <?php esc_html_e('Bus Features', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('Highlight amenities and features of the bus (Wi-Fi, AC, charging ports, etc.) to help customers make informed choices.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Add Features', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Select from pre-defined features created under Bus → Bus Features taxonomy. Each feature can have a custom icon.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Feature Icons', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('When creating features in the taxonomy, you can assign FontAwesome icons to make them visually appealing on the frontend.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                </div>

                                <!-- Registration Form (PRO) -->
                                <div id="doc-reg-form" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-clipboard-list"></i> <?php esc_html_e('Registration Form', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('Customize the passenger information form shown during booking. This is a per-bus settings tab added by the PRO addon.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <h4><?php esc_html_e('Default Fields', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('Field Label', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Rename any default field (e.g., change "Phone" to "Mobile Number").', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Required', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Mark a field as mandatory (customer must fill it) or optional.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Active / Hidden', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Show or hide any field from the booking form entirely.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <h4><?php esc_html_e('Custom Fields', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <p><?php esc_html_e('Add unlimited custom fields to collect extra passenger data (National ID, Passport, Emergency Contact, etc.).', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Field Label', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Display name shown to the customer.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Field Type', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Choose from: Text Input, Select Dropdown, Textarea, Checkbox, or Radio Button.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Options', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('For dropdown/checkbox/radio fields, enter the options separated by commas.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Required', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Make the custom field mandatory.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-note"><?php esc_html_e('Note: Registration Form is a per-bus tab. Each bus can have its own unique form configuration.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                    </div>
                                </div>

                                <!-- Deposit / Partial Payment (PRO) -->
                                <div id="doc-deposit" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-hand-holding-usd"></i> <?php esc_html_e('Deposit / Partial Payment', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('Allow customers to pay a deposit (partial payment) upfront and the remaining balance later. This feature has both global and per-bus settings.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('Global Settings (Bus → Settings → Deposit)', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('Enable Deposit', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Turn on the deposit feature globally.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Let Customer Choose', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('YES = customer sees both "Pay Deposit" and "Pay Full" options. NO = deposit is forced (no choice shown).', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Default Deposit Type', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Percentage (%) or Fixed Amount. Used when a bus does not override.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Default Deposit Value', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('E.g., 30 for 30% or 50 for a fixed amount.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Balance Due Days', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Number of days after booking the balance must be paid. Leave 0 for no deadline.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('Per-Bus Override (Bus Edit → Deposit tab)', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <p><?php esc_html_e('Each bus can override the global deposit settings with its own values.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('How It Works on Frontend', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><?php esc_html_e('At checkout, a "Payment Option" section appears with deposit/full payment choices.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('If deposit is chosen, only the deposit amount is charged. The remaining balance appears as a fee.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Customers can pay the balance later from their My Account → "Pending Balances" page.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Deposit info is shown on PDF tickets and in confirmation emails.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    </div>
                                </div>

                                <!-- Return Discount (PRO) -->
                                <div id="doc-return-discount" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-percentage"></i> <?php esc_html_e('Return Discount', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('Offer a discount to customers who book a return journey. This setting appears inside the Pricing & Route tab when the PRO addon is active.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Discount Value', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enter the discount amount (e.g., 10.4 or 5).', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Discount Type', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Choose Percentage (%) or Fixed amount.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <h4><?php esc_html_e('How It Works', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <p><?php esc_html_e('The discount is automatically applied in the WooCommerce cart when:', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><?php esc_html_e('Customer books Outbound (A → B)', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Then books Return (B → A) on the same bus', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('The return trip price is reduced by the configured discount', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-tip"><?php esc_html_e('Tip: The "Same Bus Return Journey" must be enabled in Pricing & Route for this to work.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                    </div>
                                </div>

                                <!-- AI Chatbot (PRO) -->
                                <div id="doc-ai-chatbot" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-robot"></i> <?php esc_html_e('AI Chatbot', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('An intelligent chatbot widget that helps customers search buses, view seats, book tickets, and manage their cart — all through a conversational interface.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('Global Settings (Bus → Settings → AI Chatbot)', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('Enable/Disable Chatbot', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Turn the chatbot widget on or off globally.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Chatbot Title & Welcome Message', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Customize the widget title and the first message customers see.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Provider Selection', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Choose between Rule-Based (free, no API key needed) or AI-powered providers:', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li style="list-style:none;padding-left:10px;">
                                            ⚡ <?php esc_html_e('Rule-Based — Built-in keyword matching, no external API needed.', 'bus-ticket-booking-with-seat-reservation'); ?><br>
                                            🟢 <?php esc_html_e('OpenAI (GPT-4o Mini, GPT-4o, etc.)', 'bus-ticket-booking-with-seat-reservation'); ?><br>
                                            🟣 <?php esc_html_e('Anthropic Claude (Haiku, Sonnet, Opus)', 'bus-ticket-booking-with-seat-reservation'); ?><br>
                                            🔵 <?php esc_html_e('xAI Grok (Grok 3, Grok 3 Mini)', 'bus-ticket-booking-with-seat-reservation'); ?><br>
                                            🟠 <?php esc_html_e('Alibaba Qwen (Qwen Plus, Turbo, Max)', 'bus-ticket-booking-with-seat-reservation'); ?><br>
                                            🔴 <?php esc_html_e('Google Gemini (Gemini 2.0 Flash, Pro)', 'bus-ticket-booking-with-seat-reservation'); ?><br>
                                            🟡 <?php esc_html_e('DeepSeek (DeepSeek Chat, Reasoner)', 'bus-ticket-booking-with-seat-reservation'); ?>
                                        </li>
                                        <li><strong><?php esc_html_e('API Key', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enter the API key for your chosen provider.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Model Selection', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Pick the specific model for your provider.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Test Connection', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Verify your API key works before going live.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('What the Chatbot Can Do', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('Search Buses', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('"Find buses from Dhaka to Chittagong tomorrow"', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Filter Results', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('"Show me the cheapest option" or "morning buses only"', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('View Seats', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('"Show available seats" — displays an interactive seat map.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Book Seats', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('"Book seat A1 and A2" — adds seats to cart.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Manage Cart', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('"Show my cart", "Remove seat B3", "Clear cart".', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Apply Coupon', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('"Apply coupon SAVE20"', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Checkout', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('"Proceed to payment" — redirects to WooCommerce checkout.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Return Journey', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('"I want to come back on Friday"', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('AI Learning', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <p><?php esc_html_e('The chatbot has a built-in learning engine that:', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><?php esc_html_e('Logs all conversations to identify common questions and failed queries.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Learns popular routes and suggests them proactively.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Improves intent detection over time from real customer inputs.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-note"><?php esc_html_e('Note: The Rule-Based mode works out of the box with no configuration. AI modes require an API key from the respective provider.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                    </div>
                                </div>

                                <!-- PDF Tickets (PRO) -->
                                <div id="doc-pdf-tickets" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-file-pdf"></i> <?php esc_html_e('PDF Tickets', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('Generate professional PDF tickets for customers. Requires the MagePeople PDF Support plugin and the PRO addon.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('Global Settings (Bus → Settings → PDF Settings)', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('Merge PDF Ticket', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('YES = multiple seats in same order generate ONE ticket. NO = one ticket per seat.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Logo', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Upload a custom logo for the PDF header.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Background Image', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Custom background (680px wide recommended).', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Background Color', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Solid background color for the PDF.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Text Color', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Customize text color.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Company Address, Phone, Email', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Displayed on the PDF ticket for contact info.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Terms & Condition', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Title and rich-text content shown in the PDF footer.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('Passenger PDF List (Bus → Settings → PDF Passenger List)', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <p><?php esc_html_e('Configure which columns appear in the admin passenger PDF export:', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><?php esc_html_e('Toggle: Bus Name, Bus No, Price, Total Passenger, Total Order, PIN, Order ID, Seat Name, Passenger Name, Phone, Extra Service, From/To, Pickup/Drop-off, Attendee Info.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-note"><?php esc_html_e('Note: PDF generation requires the MagePeople PDF Support Master plugin to be installed and active.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                    </div>
                                </div>

                                <!-- Email Settings (PRO) -->
                                <div id="doc-email-settings" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-envelope"></i> <?php esc_html_e('Email Settings', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('Configure automatic email notifications with PDF ticket attachments sent to customers after booking.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><strong><?php esc_html_e('Send Ticket?', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enable/disable automatic email sending.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Send Email On', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Select which order statuses trigger the email: On Hold, Pending, Processing, Completed.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Email Subject', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Custom subject line for the ticket email.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Email Content', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Rich-text editor with shortcodes:', 'bus-ticket-booking-with-seat-reservation'); ?> <code>{customer_name}</code>, <code>{bus_name}</code>, <code>{journey_date}</code>, <code>{order_id}</code></li>
                                        <li><strong><?php esc_html_e('From Name / From Email', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Customize the sender. Defaults to WooCommerce email settings.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Admin Notification Email', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Email address to receive admin copies and seat threshold alerts.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Minimum Seat Threshold', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('When available seats drop to this number, an alert email is sent to the admin.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Threshold Email Content', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Custom email template for low-seat alerts. Shortcodes:', 'bus-ticket-booking-with-seat-reservation'); ?> <code>{bus_name}</code>, <code>{journey_date}</code></li>
                                    </ul>
                                    </div>
                                </div>

                                <!-- Passenger List & Export (PRO) -->
                                <div id="doc-passenger-list" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-users"></i> <?php esc_html_e('Passenger List & Export', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('A powerful admin page to view, filter, and export all passenger bookings. Found under Bus → Passenger List.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('Filtering', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('By Bus', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Select a specific bus to see its passengers.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('By Date Range', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Filter by booking date or journey date.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('By Boarding/Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Filter passengers by their pickup or drop-off location.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('By Name / Email / Phone', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Search for specific passengers.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('By Order Status', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Filter by WooCommerce order status.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('Export Options', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('PDF Export', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Generate a PDF passenger list. Columns are configurable in Bus → Settings → PDF Passenger List.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('CSV Export', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Download a CSV file for use in Excel/Google Sheets. Columns configurable in Bus → Settings → CSV Settings.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('CSV Settings (Bus → Settings → CSV Settings)', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <p><?php esc_html_e('Toggle which columns appear in the CSV export:', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <ul>
                                        <li><?php esc_html_e('Bus Name, Bus No, Price, PIN, Journey Date, Order ID, Seat Name, Passenger Name, Phone, Email, Address, Extra Service, From/To, Pickup/Drop-off.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    </div>
                                </div>

                                <!-- Booking Calendar (PRO) -->
                                <div id="doc-booking-calendar" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-calendar-check"></i> <?php esc_html_e('Booking Calendar', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('A professional visual calendar to manage and view all bookings at a glance. Found under Bus → Booking Calendar.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('Calendar Views', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('Month View', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('See all bookings for the entire month.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Week View', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Focus on a specific week.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Day View', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Detailed view of a single day.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('Features', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('Journey Date / Order Date Toggle', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Switch between viewing by when the bus departs or when the order was placed.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Filter by Bus & Status', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Narrow down to specific buses or order statuses.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Stats Bar', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Shows Total Bookings, Confirmed, Pending, and Revenue for the visible period.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Side Panel', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Click a day to see a detailed panel with tabs: ALL, CONFIRMED, PENDING, PARTIAL. Each card shows time, status, passenger, bus, route, seat, and fare.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Order Detail Modal', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Click any booking card to see the full WooCommerce order details.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    </div>
                                </div>

                                <!-- Sales Report (PRO) -->
                                <div id="doc-sales-report" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-chart-bar"></i> <?php esc_html_e('Sales Report', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('A reporting dashboard to analyze your bus booking revenue and sales. Found under Bus → Sales Report.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('Filter Options', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('By Bus', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('See sales for a specific bus.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('By Date', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Filter by specific date or date range.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('By Boarding / Dropping Point', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('See sales by route segments.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('By Order Status', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Filter by completed, processing, etc.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('Report Data', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><?php esc_html_e('Total tickets sold, total revenue, average ticket price.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Breakdown by bus, route, and date.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Visual charts for quick analysis.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    </div>
                                </div>

                                <!-- Purchase Ticket Admin (PRO) -->
                                <div id="doc-purchase-ticket" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-shopping-cart"></i> <?php esc_html_e('Purchase Ticket (Admin)', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('Create bookings on behalf of customers directly from the admin dashboard. Found under Bus → Purchase Ticket.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('How It Works', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('Search', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Use the search form to find buses by route and date, just like the frontend.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Select Seats', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Pick seats from the seat map or enter quantity (for non-seat-plan buses).', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Fill Passenger Info', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enter customer details using the registration form.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Create Order', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('A WooCommerce order is created with "Completed" status automatically. No payment gateway needed.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Full Bus Booking', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Option to book the entire bus (charter) with custom pricing.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <div class="doc-tip"><?php esc_html_e('Tip: This is great for walk-in customers, phone bookings, or when customers need help placing an order.', 'bus-ticket-booking-with-seat-reservation'); ?></div>
                                    </div>
                                </div>

                                <!-- View Ticket Frontend (PRO) -->
                                <div id="doc-view-ticket" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-ticket-alt"></i> <?php esc_html_e('View Ticket (Frontend)', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('A frontend page where customers can look up their tickets using a PIN code. The page is automatically created on PRO plugin activation.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('Shortcode', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <p><code>[view-ticket]</code> — <?php esc_html_e('Place this shortcode on any page to show the ticket lookup form.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('How Customers Use It', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><?php esc_html_e('Customer enters their Ticket PIN (received via email after booking).', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('The system looks up the booking and displays ticket details.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('For multi-seat bookings, all seats are shown.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('PDF download button is available for each ticket.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('Access Control', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><?php esc_html_e('Logged-in customers can view tickets matching their account or email.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Admins and Bus Staff can view any ticket.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    </div>
                                </div>

                                <!-- Bus Staff Role (PRO) -->
                                <div id="doc-staff-role" class="wbtm-doc-panel">
                                    <div class="doc-pro-section">
                                    <h3><i class="fas fa-user-shield"></i> <?php esc_html_e('Bus Staff Role', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h3>
                                    <p><?php esc_html_e('A dedicated WordPress user role for bus staff members with limited admin access.', 'bus-ticket-booking-with-seat-reservation'); ?></p>

                                    <h4><?php esc_html_e('The "Bus Staff" Role', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><?php esc_html_e('Automatically created when the PRO addon is activated.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Has access to: Bus list, Passenger List, Purchase Ticket, Sales Report, Booking Calendar.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Cannot access: Pages, Posts, Plugins, Themes, Settings, WooCommerce settings, or other admin areas.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Can view tickets on the frontend /view-ticket page.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>

                                    <h4><?php esc_html_e('How to Use', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><?php esc_html_e('Go to Users → Add New or edit an existing user.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('Set the role to "Bus Staff".', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><?php esc_html_e('The user will see only the Bus-related menu items when they log in.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    </div>
                                </div>

                                <!-- Global Settings -->
                                <div id="doc-global" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-sliders-h"></i> <?php esc_html_e('Global Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('These settings apply to ALL buses and are found under Bus → Settings in the admin menu.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <h4><?php esc_html_e('Free Plugin Settings', 'bus-ticket-booking-with-seat-reservation'); ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('General Settings', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Seat booking status (when seats get marked as booked: on-hold, processing, completed), bus label, URL slug, menu icon, return date search toggle, ticket sale cutoff date, max advance booking days.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Global Settings', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Search results display style, single bus page layout, pagination, and other display options.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Slider Settings', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Configure the hero banner slider on the search page.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Style Settings', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Customize colors, fonts, and theme styling for the bus booking pages.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Mage-People License', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enter your license key for automatic plugin updates and premium support.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    <?php if (class_exists('WBTM_Settings_Global_PRO') || class_exists('Wbtm_Woocommerce_bus_Pro')) { ?>
                                    <div class="doc-pro-section">
                                    <h4><i class="fas fa-crown"></i> <?php esc_html_e('PRO Global Settings', 'bus-ticket-booking-with-seat-reservation'); ?> <?php echo $pro_badge; ?></h4>
                                    <ul>
                                        <li><strong><?php esc_html_e('Deposit / Partial Payment', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Global deposit defaults (type, value, customer choice, balance due days). See Deposit tab for details.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('PDF Settings', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Ticket PDF layout: logo, background, colors, company info, T&C.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('PDF Passenger List', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Column visibility for admin PDF exports.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('CSV Settings', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Column visibility for CSV exports.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong><?php esc_html_e('Email Settings', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Ticket email config: subject, content, sender, admin notification, seat threshold alert.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                        <li><strong>🤖 <?php esc_html_e('AI Chatbot Settings', 'bus-ticket-booking-with-seat-reservation'); ?></strong> — <?php esc_html_e('Enable chatbot, choose provider, set API key, select model, test connection.', 'bus-ticket-booking-with-seat-reservation'); ?></li>
                                    </ul>
                                    </div>
                                    <?php } ?>
                                </div>

                                <!-- Shortcodes Doc -->
                                <div id="doc-shortcodes-doc" class="wbtm-doc-panel">
                                    <h3><i class="fas fa-code"></i> <?php esc_html_e('Shortcodes Reference', 'bus-ticket-booking-with-seat-reservation'); ?></h3>
                                    <p><?php esc_html_e('Use these shortcodes to display bus booking features on any page or post.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
                                    <table class="wbtm-data-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e('Shortcode', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                                <th><?php esc_html_e('Description', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                                <th><?php esc_html_e('Parameters', 'bus-ticket-booking-with-seat-reservation'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td><code>[wbtm-bus-list]</code></td>
                                                <td><?php esc_html_e('Display a list of all buses with filtering.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                                <td><code>cat</code> <?php esc_html_e('— Category ID', 'bus-ticket-booking-with-seat-reservation'); ?><br><code>show</code> <?php esc_html_e('— Items per page', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><code>[wbtm-bus-search-form]</code></td>
                                                <td><?php esc_html_e('Shows only the search form widget.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                                <td><?php esc_html_e('No parameters required.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><code>[wbtm-bus-search]</code></td>
                                                <td><?php esc_html_e('Shows the search form AND results on the same page.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                                <td><?php esc_html_e('No parameters required.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><code>[view-ticket]</code></td>
                                                <td><?php esc_html_e('Passenger ticket lookup by PIN (PRO). Shows ticket details and PDF download.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                                <td><?php esc_html_e('No parameters required.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><code>[wbtm-bus-details]</code></td>
                                                <td><?php esc_html_e('Display a single bus detail page anywhere.', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                                <td><code>id</code> <?php esc_html_e('— Bus post ID', 'bus-ticket-booking-with-seat-reservation'); ?><br><code>name</code> <?php esc_html_e('— Bus name/slug', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                            </tr>
                                            <tr>
                                                <td><code>[wbtm_download_pdf]</code></td>
                                                <td><?php esc_html_e('PDF ticket download button (PRO).', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                                <td><code>order_id</code> <?php esc_html_e('— WooCommerce Order ID', 'bus-ticket-booking-with-seat-reservation'); ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                            </div> <!-- end doc panels container -->

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
                        // Main tab switching
                        $('.wbtm-tab-link').click(function() {
                            var tab_id = $(this).attr('data-tab');

                            $('.wbtm-tab-link').removeClass('active');
                            $('.wbtm-tab-pane').removeClass('active');

                            $(this).addClass('active');
                            $("#" + tab_id).addClass('active');
                        });

                        // Document sub-tab switching
                        $('.wbtm-doc-tab-btn').click(function() {
                            var doc_id = $(this).attr('data-doc');

                            $('.wbtm-doc-tab-btn').removeClass('active');
                            $('.wbtm-doc-panel').removeClass('active');

                            $(this).addClass('active');
                            $('#' + doc_id).addClass('active');
                        });
                    });
                </script>
				<?php
			}
		}
		new WBTM_Welcome();
	}
