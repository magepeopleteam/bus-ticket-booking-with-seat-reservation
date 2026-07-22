<?php
	/*
   * @Author 		MagePeople Team
   * Copyright: 	mage-people.com
   *
   * System Status — rendered as a tab inside Global Settings (Settings → Status),
   * not as its own submenu any more. WBTM_Global_settings calls
   * WBTM_Status::render_status_cards() for the "status" tab panel.
   *
   * The legacy extension points (wbtm_status_notice_sec / wbtm_status_table_item_sec)
   * still fire, so add-ons that inject <tr> rows (e.g. the Pro PDF checks) keep
   * working — their output is wrapped in a proper table inside its own card.
   */

	if (!defined('ABSPATH')) {
		die;
	} // Cannot access pages directly.

	if (!class_exists('WBTM_Status')) {
		class WBTM_Status {

			/* ---------------------------------------------------------------
			 * Small helpers
			 * ------------------------------------------------------------- */

			/** Convert a php.ini shorthand size (128M, 1G, 512K) to bytes. */
			private static function to_bytes($value) {
				$value = trim((string) $value);
				if ($value === '') {
					return 0;
				}
				$unit   = strtolower(substr($value, -1));
				$number = (float) $value;
				switch ($unit) {
					case 'g': return (int) ($number * 1024 * 1024 * 1024);
					case 'm': return (int) ($number * 1024 * 1024);
					case 'k': return (int) ($number * 1024);
				}
				return (int) $number;
			}

			private static function yes_no($bool) {
				return $bool
					? __('Yes', 'bus-ticket-booking-with-seat-reservation')
					: __('No', 'bus-ticket-booking-with-seat-reservation');
			}

			/** One row: [label, value, state] where state = ok | warn | bad | info. */
			private static function row($label, $value, $state = 'info') {
				return ['label' => $label, 'value' => (string) $value, 'state' => $state];
			}

			/* ---------------------------------------------------------------
			 * Data collection
			 * ------------------------------------------------------------- */

			/** Build every status card as plain data, so it can be both rendered and exported. */
			private static function collect_cards() {
				global $wpdb;

				$cards = [];

				// ── WordPress ────────────────────────────────────────────────
				$wp_version = get_bloginfo('version');
				$debug_on   = (defined('WP_DEBUG') && WP_DEBUG);
				$wp_mem     = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '';
				$permalink  = get_option('permalink_structure');

				$cards[] = [
					'title' => __('WordPress', 'bus-ticket-booking-with-seat-reservation'),
					'icon'  => 'fab fa-wordpress',
					'tone'  => 'blue',
					'rows'  => [
						self::row(__('WordPress version', 'bus-ticket-booking-with-seat-reservation'), $wp_version, version_compare($wp_version, '6.0', '>=') ? 'ok' : 'warn'),
						self::row(__('Site URL', 'bus-ticket-booking-with-seat-reservation'), get_site_url(), 'info'),
						self::row(__('Home URL', 'bus-ticket-booking-with-seat-reservation'), get_home_url(), 'info'),
						self::row(__('Multisite', 'bus-ticket-booking-with-seat-reservation'), self::yes_no(is_multisite()), 'info'),
						self::row(__('Language', 'bus-ticket-booking-with-seat-reservation'), get_locale(), 'info'),
						self::row(__('Timezone', 'bus-ticket-booking-with-seat-reservation'), wp_timezone_string(), 'info'),
						self::row(__('WP memory limit', 'bus-ticket-booking-with-seat-reservation'), $wp_mem ? $wp_mem : __('Not set', 'bus-ticket-booking-with-seat-reservation'), self::to_bytes($wp_mem) >= 67108864 ? 'ok' : 'warn'),
						self::row(__('Debug mode', 'bus-ticket-booking-with-seat-reservation'), $debug_on ? __('Enabled', 'bus-ticket-booking-with-seat-reservation') : __('Disabled', 'bus-ticket-booking-with-seat-reservation'), $debug_on ? 'warn' : 'ok'),
						self::row(__('Permalinks', 'bus-ticket-booking-with-seat-reservation'), $permalink ? $permalink : __('Plain', 'bus-ticket-booking-with-seat-reservation'), $permalink ? 'ok' : 'warn'),
					],
				];

				// ── Server ───────────────────────────────────────────────────
				$server_soft = isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : __('Unknown', 'bus-ticket-booking-with-seat-reservation');
				$php_version = PHP_VERSION;
				$mem_limit   = ini_get('memory_limit');
				$max_exec    = (int) ini_get('max_execution_time');
				$upload_max  = ini_get('upload_max_filesize');
				$post_max    = ini_get('post_max_size');
				$input_vars  = (int) ini_get('max_input_vars');

				$cards[] = [
					'title' => __('Server', 'bus-ticket-booking-with-seat-reservation'),
					'icon'  => 'fas fa-server',
					'tone'  => 'violet',
					'rows'  => [
						self::row(__('Server software', 'bus-ticket-booking-with-seat-reservation'), $server_soft, 'info'),
						self::row(__('Operating system', 'bus-ticket-booking-with-seat-reservation'), PHP_OS, 'info'),
						self::row(__('PHP version', 'bus-ticket-booking-with-seat-reservation'), $php_version, version_compare($php_version, '7.4', '>=') ? 'ok' : 'bad'),
						self::row(__('PHP memory limit', 'bus-ticket-booking-with-seat-reservation'), $mem_limit, self::to_bytes($mem_limit) >= 134217728 ? 'ok' : 'warn'),
						self::row(__('Max execution time', 'bus-ticket-booking-with-seat-reservation'), $max_exec . 's', ($max_exec === 0 || $max_exec >= 30) ? 'ok' : 'warn'),
						self::row(__('Upload max filesize', 'bus-ticket-booking-with-seat-reservation'), $upload_max, 'info'),
						self::row(__('Post max size', 'bus-ticket-booking-with-seat-reservation'), $post_max, 'info'),
						self::row(__('Max input vars', 'bus-ticket-booking-with-seat-reservation'), $input_vars, $input_vars >= 1000 ? 'ok' : 'warn'),
						self::row(__('SSL (HTTPS)', 'bus-ticket-booking-with-seat-reservation'), self::yes_no(is_ssl()), is_ssl() ? 'ok' : 'warn'),
					],
				];

				// ── Database ─────────────────────────────────────────────────
				$db_version = '';
				if (isset($wpdb)) {
					if (method_exists($wpdb, 'db_server_info')) {
						$db_version = $wpdb->db_server_info();
					} elseif (method_exists($wpdb, 'db_version')) {
						$db_version = $wpdb->db_version();
					}
				}
				$cards[] = [
					'title' => __('Database', 'bus-ticket-booking-with-seat-reservation'),
					'icon'  => 'fas fa-database',
					'tone'  => 'teal',
					'rows'  => [
						self::row(__('Database version', 'bus-ticket-booking-with-seat-reservation'), $db_version ? $db_version : __('Unknown', 'bus-ticket-booking-with-seat-reservation'), $db_version ? 'ok' : 'warn'),
						self::row(__('Charset', 'bus-ticket-booking-with-seat-reservation'), isset($wpdb->charset) && $wpdb->charset ? $wpdb->charset : DB_CHARSET, 'info'),
						self::row(__('Collation', 'bus-ticket-booking-with-seat-reservation'), isset($wpdb->collate) && $wpdb->collate ? $wpdb->collate : __('Default', 'bus-ticket-booking-with-seat-reservation'), 'info'),
						self::row(__('Table prefix', 'bus-ticket-booking-with-seat-reservation'), isset($wpdb->prefix) ? $wpdb->prefix : '', 'info'),
					],
				];

				// ── PHP extensions ───────────────────────────────────────────
				$required = ['gd', 'mbstring', 'curl', 'json', 'dom'];
				$optional = ['zip', 'simplexml', 'intl', 'openssl', 'iconv', 'fileinfo'];
				$ext_rows = [];
				foreach ($required as $ext) {
					$loaded     = extension_loaded($ext);
					$ext_rows[] = self::row($ext, self::yes_no($loaded), $loaded ? 'ok' : 'bad');
				}
				foreach ($optional as $ext) {
					$loaded     = extension_loaded($ext);
					$ext_rows[] = self::row($ext, self::yes_no($loaded), $loaded ? 'ok' : 'warn');
				}
				$cards[] = [
					'title' => __('PHP Extensions', 'bus-ticket-booking-with-seat-reservation'),
					'icon'  => 'fas fa-puzzle-piece',
					'tone'  => 'amber',
					'rows'  => $ext_rows,
				];

				// ── Plugin & theme ───────────────────────────────────────────
				$theme     = wp_get_theme();
				$pro_on    = (class_exists('WBTM_Functions') && WBTM_Functions::is_pro_active());
				$wc_active = (class_exists('WBTM_Global_Function') && WBTM_Global_Function::check_woocommerce() == 1);
				$wc_ver    = ($wc_active && function_exists('WC')) ? WC()->version : '';

				$plugin_rows = [
					self::row(__('Bus Manager version', 'bus-ticket-booking-with-seat-reservation'), defined('WBTM_VERSION') ? WBTM_VERSION : __('Unknown', 'bus-ticket-booking-with-seat-reservation'), 'ok'),
					self::row(__('Pro add-on', 'bus-ticket-booking-with-seat-reservation'), $pro_on ? (defined('WBTM_PRO_VERSION') ? WBTM_PRO_VERSION : __('Active', 'bus-ticket-booking-with-seat-reservation')) : __('Not active', 'bus-ticket-booking-with-seat-reservation'), $pro_on ? 'ok' : 'info'),
					self::row(__('WooCommerce', 'bus-ticket-booking-with-seat-reservation'), $wc_active ? ($wc_ver ? $wc_ver : __('Active', 'bus-ticket-booking-with-seat-reservation')) : __('Not active', 'bus-ticket-booking-with-seat-reservation'), $wc_active ? 'ok' : 'warn'),
					self::row(__('Active theme', 'bus-ticket-booking-with-seat-reservation'), $theme->get('Name') . ' ' . $theme->get('Version'), 'info'),
					self::row(__('Child theme', 'bus-ticket-booking-with-seat-reservation'), self::yes_no(is_child_theme()), 'info'),
					self::row(__('Active plugins', 'bus-ticket-booking-with-seat-reservation'), count((array) get_option('active_plugins', [])), 'info'),
				];

				// WooCommerce sender identity — kept from the original Status screen.
				if ($wc_active) {
					$from_name  = get_option('woocommerce_email_from_name');
					$from_email = get_option('woocommerce_email_from_address');
					$plugin_rows[] = self::row(__('Email sender name', 'bus-ticket-booking-with-seat-reservation'), $from_name ? $from_name : __('Not set', 'bus-ticket-booking-with-seat-reservation'), $from_name ? 'ok' : 'warn');
					$plugin_rows[] = self::row(__('Email sender address', 'bus-ticket-booking-with-seat-reservation'), $from_email ? $from_email : __('Not set', 'bus-ticket-booking-with-seat-reservation'), $from_email ? 'ok' : 'warn');
				}

				$cards[] = [
					'title' => __('Plugin & Theme', 'bus-ticket-booking-with-seat-reservation'),
					'icon'  => 'fas fa-bus',
					'tone'  => 'pink',
					'rows'  => $plugin_rows,
				];

				return $cards;
			}

			/** Plain-text report for the "Copy report" button (support tickets). */
			private static function build_report($cards) {
				$lines = ['### ' . __('Bus Manager System Status', 'bus-ticket-booking-with-seat-reservation') . ' ###', ''];
				foreach ($cards as $card) {
					$lines[] = '== ' . wp_strip_all_tags($card['title']) . ' ==';
					foreach ($card['rows'] as $r) {
						$lines[] = wp_strip_all_tags($r['label']) . ': ' . wp_strip_all_tags($r['value']);
					}
					$lines[] = '';
				}
				return implode("\n", $lines);
			}

			/* ---------------------------------------------------------------
			 * Rendering
			 * ------------------------------------------------------------- */

			/**
			 * Render the whole Status tab. Called by WBTM_Global_settings for the
			 * "status" tab panel.
			 */
			public static function render_status_cards() {
				if (!current_user_can('manage_options')) {
					return;
				}

				// Legacy extension point (notices/actions from add-ons).
				do_action('wbtm_status_notice_sec');

				$cards = self::collect_cards();

				// Health tally across every collected row.
				$counts = ['ok' => 0, 'warn' => 0, 'bad' => 0];
				foreach ($cards as $card) {
					foreach ($card['rows'] as $r) {
						if (isset($counts[$r['state']])) {
							$counts[$r['state']]++;
						}
					}
				}
				$has_issue = ($counts['bad'] > 0);
				$has_warn  = ($counts['warn'] > 0);
				$hero_state = $has_issue ? 'bad' : ($has_warn ? 'warn' : 'ok');
				$hero_title = $has_issue
					? __('Action required', 'bus-ticket-booking-with-seat-reservation')
					: ($has_warn
						? __('Everything works — a few things could be improved', 'bus-ticket-booking-with-seat-reservation')
						: __('All systems healthy', 'bus-ticket-booking-with-seat-reservation'));

				// Add-on rows injected through the legacy table hook.
				ob_start();
				do_action('wbtm_status_table_item_sec');
				$legacy_rows = trim(ob_get_clean());

				self::print_styles();
				?>
				<div class="wbtm-st">

					<div class="wbtm-st-hero wbtm-st--<?php echo esc_attr($hero_state); ?>">
						<div class="wbtm-st-hero-icon">
							<span class="fas <?php echo esc_attr($has_issue ? 'fa-triangle-exclamation' : ($has_warn ? 'fa-circle-exclamation' : 'fa-circle-check')); ?>"></span>
						</div>
						<div class="wbtm-st-hero-body">
							<h3><?php echo esc_html($hero_title); ?></h3>
							<p><?php esc_html_e('Environment report for your server, WordPress install and Bus Manager setup. Share this with support when reporting an issue.', 'bus-ticket-booking-with-seat-reservation'); ?></p>
						</div>
						<div class="wbtm-st-hero-stats">
							<span class="wbtm-st-stat wbtm-st--ok"><b><?php echo esc_html($counts['ok']); ?></b><?php esc_html_e('Passed', 'bus-ticket-booking-with-seat-reservation'); ?></span>
							<span class="wbtm-st-stat wbtm-st--warn"><b><?php echo esc_html($counts['warn']); ?></b><?php esc_html_e('Notices', 'bus-ticket-booking-with-seat-reservation'); ?></span>
							<span class="wbtm-st-stat wbtm-st--bad"><b><?php echo esc_html($counts['bad']); ?></b><?php esc_html_e('Issues', 'bus-ticket-booking-with-seat-reservation'); ?></span>
						</div>
						<button type="button" class="wbtm-st-copy" id="wbtm-st-copy">
							<span class="fas fa-copy"></span> <?php esc_html_e('Copy report', 'bus-ticket-booking-with-seat-reservation'); ?>
						</button>
					</div>

					<div class="wbtm-st-grid">
						<?php foreach ($cards as $card): ?>
							<div class="wbtm-st-card">
								<div class="wbtm-st-card-head wbtm-st-tone--<?php echo esc_attr($card['tone']); ?>">
									<span class="wbtm-st-card-icon <?php echo esc_attr($card['icon']); ?>"></span>
									<span class="wbtm-st-card-title"><?php echo esc_html($card['title']); ?></span>
								</div>
								<ul class="wbtm-st-list">
									<?php foreach ($card['rows'] as $r): ?>
										<li class="wbtm-st-item">
											<span class="wbtm-st-label"><?php echo esc_html($r['label']); ?></span>
											<span class="wbtm-st-value wbtm-st--<?php echo esc_attr($r['state']); ?>">
												<span class="wbtm-st-dot"></span><?php echo esc_html($r['value']); ?>
											</span>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach; ?>

						<?php if ($legacy_rows !== ''): ?>
							<div class="wbtm-st-card">
								<div class="wbtm-st-card-head wbtm-st-tone--slate">
									<span class="wbtm-st-card-icon fas fa-cubes"></span>
									<span class="wbtm-st-card-title"><?php esc_html_e('Add-ons & Extensions', 'bus-ticket-booking-with-seat-reservation'); ?></span>
								</div>
								<?php // Add-ons inject <tr> rows through the legacy hook — wrap them in a real table so the markup stays valid. ?>
								<div class="wbtm-st-legacy">
									<table>
										<tbody><?php echo $legacy_rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- add-on markup, escaped at source. ?></tbody>
									</table>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<textarea id="wbtm-st-report" readonly><?php echo esc_textarea(self::build_report($cards)); ?></textarea>
				</div>
				<script>
				(function(){
					var btn = document.getElementById('wbtm-st-copy');
					var box = document.getElementById('wbtm-st-report');
					if (!btn || !box) { return; }
					btn.addEventListener('click', function(){
						var done = function(){
							var old = btn.innerHTML;
							btn.innerHTML = '<span class="fas fa-check"></span> <?php echo esc_js(__('Copied!', 'bus-ticket-booking-with-seat-reservation')); ?>';
							setTimeout(function(){ btn.innerHTML = old; }, 1800);
						};
						if (navigator.clipboard && navigator.clipboard.writeText) {
							navigator.clipboard.writeText(box.value).then(done, function(){ box.select(); document.execCommand('copy'); done(); });
						} else {
							box.select(); document.execCommand('copy'); done();
						}
					});
				})();
				</script>
				<?php
			}

			/** Self-contained styling for the Status tab. Printed once. */
			private static function print_styles() {
				static $printed = false;
				if ($printed) {
					return;
				}
				$printed = true;
				?>
				<style>
					.wbtm-st{--st-ok:#16a34a;--st-warn:#d97706;--st-bad:#dc2626;--st-info:#64748b;}
					.wbtm-st *{box-sizing:border-box;}
					.wbtm-st-hero{position:relative;display:flex;align-items:center;gap:18px;flex-wrap:wrap;padding:20px 22px;margin-bottom:20px;border:1px solid #e6e8ee;border-left:5px solid var(--st-ok);border-radius:14px;background:linear-gradient(135deg,#ffffff 0%,#f8fafc 100%);box-shadow:0 1px 3px rgba(16,24,40,.05);}
					.wbtm-st-hero.wbtm-st--warn{border-left-color:var(--st-warn);}
					.wbtm-st-hero.wbtm-st--bad{border-left-color:var(--st-bad);}
					.wbtm-st-hero-icon{flex:0 0 auto;width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;background:rgba(22,163,74,.12);color:var(--st-ok);}
					.wbtm-st-hero.wbtm-st--warn .wbtm-st-hero-icon{background:rgba(217,119,6,.12);color:var(--st-warn);}
					.wbtm-st-hero.wbtm-st--bad .wbtm-st-hero-icon{background:rgba(220,38,38,.12);color:var(--st-bad);}
					.wbtm-st-hero-body{flex:1 1 260px;min-width:0;}
					.wbtm-st-hero-body h3{margin:0 0 4px;font-size:17px;font-weight:700;color:#0f172a;}
					.wbtm-st-hero-body p{margin:0;font-size:13px;color:#64748b;line-height:1.55;}
					.wbtm-st-hero-stats{display:flex;gap:10px;flex:0 0 auto;}
					.wbtm-st-stat{display:flex;flex-direction:column;align-items:center;min-width:74px;padding:8px 12px;border-radius:10px;background:#f1f5f9;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.4px;color:#64748b;}
					.wbtm-st-stat b{font-size:19px;line-height:1.1;color:#0f172a;}
					.wbtm-st-stat.wbtm-st--ok b{color:var(--st-ok);}
					.wbtm-st-stat.wbtm-st--warn b{color:var(--st-warn);}
					.wbtm-st-stat.wbtm-st--bad b{color:var(--st-bad);}
					.wbtm-st-copy{flex:0 0 auto;display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border:1px solid #d7dae1;border-radius:9px;background:#fff;color:#334155;font-size:13px;font-weight:600;cursor:pointer;transition:all .15s ease;}
					.wbtm-st-copy:hover{border-color:#94a3b8;background:#f8fafc;}
					.wbtm-st-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(330px,1fr));gap:18px;}
					.wbtm-st-card{background:#fff;border:1px solid #e6e8ee;border-radius:14px;overflow:hidden;box-shadow:0 1px 3px rgba(16,24,40,.05);transition:box-shadow .18s ease,transform .18s ease;}
					.wbtm-st-card:hover{box-shadow:0 8px 22px rgba(16,24,40,.09);transform:translateY(-2px);}
					.wbtm-st-card-head{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid #eef0f4;}
					.wbtm-st-card-icon{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;}
					.wbtm-st-card-title{font-size:14px;font-weight:700;color:#0f172a;}
					.wbtm-st-tone--blue .wbtm-st-card-icon{background:rgba(37,99,235,.12);color:#2563eb;}
					.wbtm-st-tone--violet .wbtm-st-card-icon{background:rgba(124,58,237,.12);color:#7c3aed;}
					.wbtm-st-tone--teal .wbtm-st-card-icon{background:rgba(13,148,136,.12);color:#0d9488;}
					.wbtm-st-tone--amber .wbtm-st-card-icon{background:rgba(217,119,6,.14);color:#d97706;}
					.wbtm-st-tone--pink .wbtm-st-card-icon{background:rgba(219,39,119,.12);color:#db2777;}
					.wbtm-st-tone--slate .wbtm-st-card-icon{background:rgba(100,116,139,.14);color:#475569;}
					.wbtm-st-list{list-style:none;margin:0;padding:4px 0;}
					.wbtm-st-item{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:9px 18px;font-size:13px;border-bottom:1px solid #f5f6f8;}
					.wbtm-st-item:last-child{border-bottom:none;}
					.wbtm-st-label{color:#64748b;flex:0 1 auto;}
					.wbtm-st-value{display:inline-flex;align-items:center;gap:7px;font-weight:600;color:#0f172a;text-align:right;word-break:break-word;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;}
					.wbtm-st-dot{width:7px;height:7px;border-radius:50%;background:var(--st-info);flex:0 0 auto;}
					.wbtm-st-value.wbtm-st--ok .wbtm-st-dot{background:var(--st-ok);}
					.wbtm-st-value.wbtm-st--warn .wbtm-st-dot{background:var(--st-warn);}
					.wbtm-st-value.wbtm-st--bad .wbtm-st-dot{background:var(--st-bad);}
					.wbtm-st-value.wbtm-st--warn{color:var(--st-warn);}
					.wbtm-st-value.wbtm-st--bad{color:var(--st-bad);}
					.wbtm-st-legacy table{width:100%;border-collapse:collapse;}
					.wbtm-st-legacy th{text-align:left;font-weight:normal;color:#64748b;font-size:13px;padding:9px 18px;border-bottom:1px solid #f5f6f8;}
					.wbtm-st-legacy tr th:last-child{text-align:right;font-weight:600;color:#0f172a;}
					#wbtm-st-report{position:absolute;left:-9999px;width:1px;height:1px;opacity:0;}
					@media (max-width:782px){
						.wbtm-st-hero{gap:14px;}
						.wbtm-st-grid{grid-template-columns:1fr;}
						.wbtm-st-item{flex-direction:column;align-items:flex-start;gap:3px;}
						.wbtm-st-value{text-align:left;}
					}
				</style>
				<?php
			}
		}
	}
