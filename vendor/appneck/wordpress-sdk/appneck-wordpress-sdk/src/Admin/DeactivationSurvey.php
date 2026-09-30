<?php

namespace Appneck\Sdk\Admin;

use Appneck\Sdk\Survey;

/**
 * The deactivation survey as a site owner experiences it: a modal that
 * opens when they click Deactivate on the plugins screen.
 *
 * ## Why the plugins-screen intercept, and not a page of our own
 *
 * This is the pattern every commercial WordPress SDK uses (Freemius,
 * WPMU DEV, Yoast's variants), and for a good reason: the only moment a
 * site owner will ever tell you why they are leaving is the moment they
 * are leaving. A "please tell us why" screen after the fact is a screen
 * they never load, and a deactivated plugin cannot render one anyway —
 * WordPress stops loading it entirely.
 *
 * Implemented as a JS-driven modal over the existing screen rather than a
 * redirect to an interstitial page, which matters more than it looks:
 * the Deactivate link is a nonced GET, so bouncing through a page of our
 * own would mean carrying that nonce through a second request and handing
 * back a "continue" link — a deactivation flow we now own and can break.
 * Intercepting the click instead leaves WordPress's own link untouched and
 * unfollowed until the owner is done, and following it later is a plain
 * navigation to the URL WordPress itself put in the page.
 *
 * ## Deactivation always wins
 *
 * Every exit from the modal ends in deactivation except the explicit
 * cancel. Submit and Skip both navigate to the original link; a failed
 * submission navigates anyway; a fetch that returns nothing skips the
 * modal entirely. The survey is feedback collection, never a gate — same
 * principle as S4.2's "activation never waits on the API", applied to the
 * other end of the lifecycle.
 *
 * The close button, Escape and the backdrop are a CANCEL, not a skip:
 * they leave the plugin active. Treating "I closed the box" as "yes,
 * deactivate" would mean a stray Escape key uninstalling something.
 *
 * ## How a failed submission is communicated: it isn't, and it can't be
 *
 * If the POST fails the modal proceeds to deactivate and the failure is
 * only recorded through the SDK's Logger (opt-in, off by default). There
 * is no user-facing message, and that is forced rather than chosen: the
 * only place to show one would be the admin screen loaded *after*
 * deactivation, and a deactivated plugin runs no code, so it cannot
 * render a notice. The alternatives were worse — holding the modal open
 * to apologise makes a failure of ours into a delay of theirs, and
 * refusing to deactivate over a lost survey is the exact behaviour that
 * gets an SDK ripped out of a plugin. So the site owner's action
 * completes and Appneck absorbs the loss quietly.
 *
 * ## Assets are inline, on one screen
 *
 * No enqueued .js/.css file, because a bundled SDK cannot know its own
 * URL: it may sit in vendor/, in a custom directory, or in a mu-plugin,
 * and plugins_url() guesses would break for someone. The markup, style
 * and script print in the footer of plugins.php only — one screen, one
 * page load, no extra requests and no path assumptions.
 */
final class DeactivationSurvey {

	const ACTION_PREFIX = 'appneck_sdk_survey_';

	/** @var Survey */
	private $survey;

	/** @var string Per-product suffix, shared with the survey's storage. */
	private $key;

	/** @var string|null plugin_basename() of the host plugin. */
	private $plugin_basename = null;

	/** @var string */
	private $product_name = 'this plugin';

	public function __construct( Survey $survey, $key, $plugin_file = null, array $options = array() ) {
		$this->survey = $survey;
		$this->key    = (string) $key;

		if ( null !== $plugin_file && function_exists( 'plugin_basename' ) ) {
			$this->plugin_basename = plugin_basename( $plugin_file );
		}

		if ( isset( $options['product_name'] ) && '' !== $options['product_name'] ) {
			$this->product_name = (string) $options['product_name'];
		}
	}

	/** @param string $name */
	public function set_product_name( $name ) {
		if ( '' !== (string) $name ) {
			$this->product_name = (string) $name;
		}

		return $this;
	}

	/** @param string $basename e.g. "acme-bookings/acme-bookings.php". */
	public function set_plugin_basename( $basename ) {
		$this->plugin_basename = (string) $basename;

		return $this;
	}

	public function action() {
		return self::ACTION_PREFIX . $this->key;
	}

	public function register_hooks() {
		if ( ! function_exists( 'add_action' ) ) {
			return;
		}

		// The footer of the plugins screen only. Not admin_footer
		// generally: there is no Deactivate link to intercept anywhere
		// else, so printing a modal there would be markup on every admin
		// page for nothing.
		add_action( 'admin_footer-plugins.php', array( $this, 'render' ) );
		add_action( 'wp_ajax_' . $this->action(), array( $this, 'handle_ajax' ) );
	}

	// -----------------------------------------------------------------
	// Rendering
	// -----------------------------------------------------------------

	/**
	 * Prints the (empty) modal shell, its style, and the script that fills
	 * it. The questions are NOT printed here — they are fetched by the
	 * script when the modal opens, so the plugins screen itself never
	 * waits on anything.
	 */
	public function render() {
		if ( ! $this->can_render() ) {
			return;
		}

		$config = array(
			'action'      => $this->action(),
			'nonce'       => function_exists( 'wp_create_nonce' ) ? wp_create_nonce( $this->action() ) : '',
			'ajaxUrl'     => function_exists( 'admin_url' ) ? admin_url( 'admin-ajax.php' ) : '/wp-admin/admin-ajax.php',
			'plugin'      => (string) $this->plugin_basename,
			'productName' => $this->product_name,
			'maxLength'   => Survey::TEXT_AREA_MAX_LENGTH,
			'strings'     => array(
				'heading' => 'Before you go',
				'intro'   => 'If you have a moment, telling us why helps ' . $this->product_name . ' get better.',
				'submit'  => 'Submit & deactivate',
				'skip'    => 'Skip & deactivate',
				'cancel'  => 'Cancel',
				'close'   => 'Close',
				'sending' => 'Sending…',
				'loading' => 'Loading…',
			),
		);

		echo '<div id="appneck-sdk-survey-' . esc_attr( $this->key ) . '" class="appneck-sdk-survey" hidden>';
		echo '<div class="appneck-sdk-survey__backdrop" data-appneck-cancel></div>';
		echo '<div class="appneck-sdk-survey__dialog" role="dialog" aria-modal="true" aria-labelledby="appneck-sdk-survey-title-' . esc_attr( $this->key ) . '">';
		echo '<div class="appneck-sdk-survey__header">';
		echo $this->render_header_decor();
		echo '<button type="button" class="appneck-sdk-survey__close" data-appneck-cancel aria-label="' . esc_attr( $config['strings']['close'] ) . '">&times;</button>';
		echo '<h2 id="appneck-sdk-survey-title-' . esc_attr( $this->key ) . '"><span class="appneck-sdk-survey__icon" aria-hidden="true">&#128075;</span>' . esc_html( $config['strings']['heading'] ) . '</h2>';
		echo '<p class="appneck-sdk-survey__intro">' . esc_html( $config['strings']['intro'] ) . '</p>';
		echo '</div>';
		echo '<div class="appneck-sdk-survey__body">';
		echo '<form class="appneck-sdk-survey__form" novalidate><div data-appneck-fields></div></form>';
		echo '<p class="appneck-sdk-survey__note">&#128274; Your answer is only used to improve ' . esc_html( $this->product_name ) . ' &mdash; it is never shared or sold.</p>';
		echo '</div>';
		// A sibling of __body, not inside it: __body is the part that
		// scrolls when the question list is long, and this must stay
		// pinned at the bottom of the dialog regardless — the site
		// owner should never have to scroll past every question just
		// to find Submit.
		echo '<div class="appneck-sdk-survey__actions">';
		echo '<button type="button" class="button button-primary appneck-sdk-survey__btn appneck-sdk-survey__btn--primary" data-appneck-submit>' . esc_html( $config['strings']['submit'] ) . '</button>';
		echo '<button type="button" class="button appneck-sdk-survey__btn appneck-sdk-survey__btn--secondary" data-appneck-skip>' . esc_html( $config['strings']['skip'] ) . '</button>';
		echo '<button type="button" class="button-link appneck-sdk-survey__btn appneck-sdk-survey__btn--link" data-appneck-cancel>' . esc_html( $config['strings']['cancel'] ) . '</button>';
		echo '</div>';
		echo '</div></div>';

		$this->render_style();
		$this->render_script( $config );
	}

	/**
	 * Three line-icons scattered behind the header text — a star, a
	 * speech bubble, a checked clipboard — because this modal is
	 * specifically a rating/feedback/checklist moment, not a generic
	 * banner. Static markup, no user data, so no escaping is needed;
	 * `aria-hidden` and `pointer-events:none` keep it decorative only.
	 */
	private function render_header_decor() {
		return '<div class="appneck-sdk-survey__header-decor" aria-hidden="true">'
			. '<svg viewBox="0 0 24 24" fill="none"><path d="M12 3.5l2.6 5.4 5.9.7-4.3 4.1 1.1 5.8L12 16.6l-5.3 2.9 1.1-5.8-4.3-4.1 5.9-.7L12 3.5z" stroke="#fff" stroke-width="1.4" stroke-linejoin="round"/></svg>'
			. '<svg viewBox="0 0 24 24" fill="none"><path d="M4 5.5h16a1 1 0 0 1 1 1V15a1 1 0 0 1-1 1H9.8L5.6 19.6a.6.6 0 0 1-1-.46V16H4a1 1 0 0 1-1-1V6.5a1 1 0 0 1 1-1z" stroke="#fff" stroke-width="1.4" stroke-linejoin="round"/><path d="M7.5 9.5h9M7.5 12.5h6" stroke="#fff" stroke-width="1.4" stroke-linecap="round"/></svg>'
			. '<svg viewBox="0 0 24 24" fill="none"><rect x="5.5" y="4.5" width="13" height="16" rx="2" stroke="#fff" stroke-width="1.4"/><path d="M9 4.5h6v2a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-2z" stroke="#fff" stroke-width="1.4"/><path d="M8.5 13.2l2 2 4.5-4.8" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
			. '</div>';
	}

	private function render_style() {
		// Scoped to this component and leaning on core's own button
		// classes for anything a site owner would recognise, so the modal
		// looks like part of wp-admin rather than like a guest.
		echo '<style>
.appneck-sdk-survey{position:fixed;inset:0;z-index:100050;display:flex;align-items:center;justify-content:center;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
.appneck-sdk-survey[hidden]{display:none}
.appneck-sdk-survey__backdrop{position:absolute;inset:0;background:rgba(16,20,26,.55);-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px);animation:appneck-sdk-survey-fade .18s ease-out}
.appneck-sdk-survey__dialog{position:relative;display:flex;flex-direction:column;background:#fff;color:#1d2327;max-width:480px;width:calc(100% - 32px);max-height:calc(100vh - 64px);overflow:hidden;border-radius:16px;box-shadow:0 24px 60px -12px rgba(16,20,26,.35),0 0 0 1px rgba(16,20,26,.04);animation:appneck-sdk-survey-pop .22s cubic-bezier(.22,1,.36,1)}
.appneck-sdk-survey__header{position:relative;flex:0 0 auto;overflow:hidden;padding:28px 32px 22px;background:linear-gradient(135deg,#4f46e5 0%,#9333ea 55%,#db2777 100%);color:#fff}
.appneck-sdk-survey__header-decor{position:absolute;inset:0;pointer-events:none}
.appneck-sdk-survey__header-decor svg{position:absolute;stroke:#fff;opacity:.16}
.appneck-sdk-survey__header-decor svg:nth-child(1){width:56px;height:56px;top:-14px;right:62px;transform:rotate(-10deg)}
.appneck-sdk-survey__header-decor svg:nth-child(2){width:44px;height:44px;bottom:-12px;right:130px;transform:rotate(8deg)}
.appneck-sdk-survey__header-decor svg:nth-child(3){width:38px;height:38px;top:50%;right:14px;transform:translateY(-50%) rotate(-6deg)}
.appneck-sdk-survey__icon{display:inline-block;margin-right:8px;font-size:20px;line-height:1;vertical-align:-2px}
.appneck-sdk-survey__dialog h2{margin:0 40px 8px 0;font-size:20px;font-weight:700;line-height:1.3;color:#fff}
.appneck-sdk-survey__intro{margin:0 40px 0 0;color:rgba(255,255,255,.88);font-size:14px;line-height:1.5}
.appneck-sdk-survey__body{flex:1 1 auto;min-height:0;overflow-y:auto;padding:24px 32px 20px}
.appneck-sdk-survey__note{display:flex;align-items:center;gap:6px;margin:2px 0 0;padding:10px 12px;background:#f5f3ff;border-radius:8px;color:#5b21b6;font-size:12.5px;line-height:1.4}
.appneck-sdk-survey__close{position:absolute;top:16px;right:16px;width:30px;height:30px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.18);border:0;border-radius:50%;font-size:18px;line-height:1;cursor:pointer;color:#fff;transition:background .15s ease}
.appneck-sdk-survey__close:hover{background:rgba(255,255,255,.3)}
.appneck-sdk-survey__question{margin:0 0 18px}
.appneck-sdk-survey__question legend,.appneck-sdk-survey__question .appneck-sdk-survey__label{display:block;font-weight:600;font-size:13.5px;margin:0 0 8px;padding:0;color:#1d2327}
.appneck-sdk-survey__question fieldset{border:0;padding:0;margin:0}
.appneck-sdk-survey__question label{display:block;margin:0 0 6px;font-weight:400;font-size:14px}
.appneck-sdk-survey__question textarea,.appneck-sdk-survey__question select{width:100%;min-height:90px;border:1px solid #d5d8dc;border-radius:8px;padding:10px 12px;font-size:14px;font-family:inherit;box-sizing:border-box;transition:border-color .15s ease,box-shadow .15s ease}
.appneck-sdk-survey__question select{min-height:auto;max-width:100%}
.appneck-sdk-survey__question textarea:focus,.appneck-sdk-survey__question select:focus{outline:none;border-color:#9333ea;box-shadow:0 0 0 3px rgba(147,51,234,.15)}
.appneck-sdk-survey__followup{margin:2px 0 10px 24px}
.appneck-sdk-survey__followup textarea{width:100%;min-height:60px;border:1px solid #d5d8dc;border-radius:8px;padding:8px 10px;font-size:13px;font-family:inherit;box-sizing:border-box}
.appneck-sdk-survey__followup textarea:focus{outline:none;border-color:#9333ea;box-shadow:0 0 0 3px rgba(147,51,234,.15)}
.appneck-sdk-survey__rating{display:flex;gap:8px;flex-wrap:wrap}
.appneck-sdk-survey__rating label{display:flex;align-items:center;gap:5px;margin:0;height:38px;padding:0 12px;border:1px solid #d5d8dc;border-radius:8px;cursor:pointer;font-size:14px;transition:border-color .15s ease,background .15s ease}
.appneck-sdk-survey__rating label:has(input:checked){border-color:#9333ea;background:#f5f3ff;color:#6b21a8;font-weight:600}
.appneck-sdk-survey__error{color:#d63638;margin:6px 0 0;font-size:13px}
.appneck-sdk-survey__actions{flex:0 0 auto;padding:16px 32px;border-top:1px solid #eef0f2;background:#fff;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.appneck-sdk-survey__btn{border-radius:8px!important;padding:8px 16px!important;height:auto!important;font-size:13.5px!important;font-weight:500!important;line-height:1.4!important;transition:transform .12s ease,box-shadow .12s ease,background .15s ease!important}
.appneck-sdk-survey__btn--primary{border:0!important;background:linear-gradient(135deg,#4f46e5 0%,#9333ea 100%)!important;box-shadow:0 1px 2px rgba(88,28,135,.25)!important}
.appneck-sdk-survey__btn--primary:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(88,28,135,.35)!important}
.appneck-sdk-survey__btn--secondary{background:#fff!important;border:1px solid #d5d8dc!important;color:#1d2327!important}
.appneck-sdk-survey__btn--secondary:hover{background:#f6f7f7!important;border-color:#c3c6c9!important}
.appneck-sdk-survey__btn--link{margin-left:auto;color:#5f6773!important;text-decoration:none!important}
.appneck-sdk-survey__btn--link:hover{color:#1d2327!important;text-decoration:underline!important}
@media (max-width:480px){.appneck-sdk-survey__btn--link{margin-left:0}}
@keyframes appneck-sdk-survey-fade{from{opacity:0}to{opacity:1}}
@keyframes appneck-sdk-survey-pop{from{opacity:0;transform:translateY(8px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
@media (prefers-reduced-motion:reduce){.appneck-sdk-survey__backdrop,.appneck-sdk-survey__dialog{animation:none}}
</style>';
	}

	/** @param array<string, mixed> $config */
	private function render_script( array $config ) {
		$json = function_exists( 'wp_json_encode' ) ? wp_json_encode( $config ) : json_encode( $config );

		// No template literals, no arrow functions, no fetch(): this runs
		// in whatever browser the site owner's host machine has, and
		// wp-admin still supports old ones. XMLHttpRequest and ES5 keep it
		// working without a build step or a polyfill.
		echo '<script>(function(){
var cfg = ' . $json . ';
var root = document.getElementById("appneck-sdk-survey-" + ' . json_encode( $this->key ) . ');
if (!root || !cfg.plugin) { return; }

var fields = root.querySelector("[data-appneck-fields]");
var submitButton = root.querySelector("[data-appneck-submit]");
var skipButton = root.querySelector("[data-appneck-skip]");
var target = null;
var questions = [];
var lastFocus = null;

function deactivate() {
	if (target) { window.location.href = target; }
}

function close() {
	root.setAttribute("hidden", "hidden");
	target = null;
	if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
}

function open() {
	root.removeAttribute("hidden");
	var focusable = root.querySelector("input, select, textarea, button");
	if (focusable && focusable.focus) { focusable.focus(); }
}

function esc(text) {
	var div = document.createElement("div");
	div.appendChild(document.createTextNode(text == null ? "" : String(text)));
	return div.innerHTML;
}

function post(op, payload, done) {
	var xhr = new XMLHttpRequest();
	xhr.open("POST", cfg.ajaxUrl, true);
	xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
	xhr.onreadystatechange = function () {
		if (xhr.readyState !== 4) { return; }
		var parsed = null;
		try { parsed = JSON.parse(xhr.responseText); } catch (e) { parsed = null; }
		done(xhr.status >= 200 && xhr.status < 300, parsed);
	};
	var body = "action=" + encodeURIComponent(cfg.action) +
		"&nonce=" + encodeURIComponent(cfg.nonce) +
		"&op=" + encodeURIComponent(op) +
		"&answers=" + encodeURIComponent(JSON.stringify(payload || {}));
	xhr.send(body);
}

function renderQuestions() {
	var html = "";
	for (var i = 0; i < questions.length; i++) {
		var q = questions[i];
		var name = "appneck_q_" + i;
		var choices = (q.options && q.options.choices) || [];
		var body = "";
		if (q.type === "radio" || q.type === "checkbox") {
			body += "<fieldset><legend>" + esc(q.text) + "</legend>";
			for (var c = 0; c < choices.length; c++) {
				body += \'<label><input type="\' + (q.type === "radio" ? "radio" : "checkbox") + \'" name="\' + name + \'" value="\' + esc(choices[c]) + \'"> \' + esc(choices[c]) + "</label>";
			}
			body += "</fieldset>";
		} else if (q.type === "dropdown") {
			body += \'<label class="appneck-sdk-survey__label" for="\' + name + \'">\' + esc(q.text) + "</label>";
			body += \'<select id="\' + name + \'" name="\' + name + \'"><option value="">&mdash;</option>\';
			for (var d = 0; d < choices.length; d++) {
				body += \'<option value="\' + esc(choices[d]) + \'">\' + esc(choices[d]) + "</option>";
			}
			body += "</select>";
		} else if (q.type === "rating") {
			var max = (q.options && q.options.max) ? parseInt(q.options.max, 10) : 5;
			body += "<fieldset><legend>" + esc(q.text) + \'</legend><div class="appneck-sdk-survey__rating">\';
			for (var r = 1; r <= max; r++) {
				body += \'<label><input type="radio" name="\' + name + \'" value="\' + r + \'"> \' + r + "</label>";
			}
			body += "</div></fieldset>";
		} else if (q.type === "text_area") {
			body += \'<label class="appneck-sdk-survey__label" for="\' + name + \'">\' + esc(q.text) + "</label>";
			body += \'<textarea id="\' + name + \'" name="\' + name + \'" maxlength="\' + cfg.maxLength + \'"></textarea>\';
		} else if (q.type === "conditional") {
			body += "<fieldset><legend>" + esc(q.text) + "</legend>";
			for (var cc = 0; cc < choices.length; cc++) {
				var choice = choices[cc];
				var choiceText = (choice && typeof choice === "object") ? choice.text : choice;
				var needsText = !!(choice && typeof choice === "object" && choice.requires_text);
				body += \'<label><input type="radio" name="\' + name + \'" value="\' + esc(choiceText) + \'" data-appneck-choice-index="\' + cc + \'" data-appneck-needs-text="\' + (needsText ? "1" : "0") + \'"> \' + esc(choiceText) + "</label>";
				if (needsText) {
					body += \'<div class="appneck-sdk-survey__followup" data-appneck-followup-index="\' + cc + \'" hidden><textarea maxlength="\' + cfg.maxLength + \'" placeholder="Optional - tell us more"></textarea></div>\';
				}
			}
			body += "</fieldset>";
		}
		// An unknown type — a newer server than this copy of the SDK —
		// produces no body and is skipped rather than guessed at.
		if (body === "") { continue; }
		html += \'<div class="appneck-sdk-survey__question" data-appneck-question="\' + esc(q.id) + \'" data-appneck-type="\' + esc(q.type) + \'">\' + body + "</div>";
	}
	fields.innerHTML = html;
}

function closestQuestionBlock(el) {
	while (el && el !== fields) {
		if (el.getAttribute && el.hasAttribute("data-appneck-question")) { return el; }
		el = el.parentNode;
	}
	return null;
}

function collect() {
	var values = {};
	var blocks = fields.querySelectorAll("[data-appneck-question]");
	for (var i = 0; i < blocks.length; i++) {
		var block = blocks[i];
		var id = block.getAttribute("data-appneck-question");
		var type = block.getAttribute("data-appneck-type");
		if (type === "checkbox") {
			var checked = block.querySelectorAll("input:checked");
			var list = [];
			for (var c = 0; c < checked.length; c++) { list.push(checked[c].value); }
			if (list.length) { values[id] = list; }
		} else if (type === "conditional") {
			var picked = block.querySelector("input:checked");
			if (picked) {
				var entry = { value: picked.value };
				var idx = picked.getAttribute("data-appneck-choice-index");
				var followup = block.querySelector(\'[data-appneck-followup-index="\' + idx + \'"] textarea\');
				if (followup && !followup.parentNode.hasAttribute("hidden") && followup.value !== "") {
					entry.text = followup.value;
				}
				values[id] = entry;
			}
		} else if (type === "radio" || type === "rating") {
			var one = block.querySelector("input:checked");
			if (one) { values[id] = one.value; }
		} else {
			var field = block.querySelector("select, textarea");
			if (field && field.value !== "") { values[id] = field.value; }
		}
	}
	return values;
}

function showErrors(errors) {
	var old = fields.querySelectorAll(".appneck-sdk-survey__error");
	for (var i = 0; i < old.length; i++) { old[i].parentNode.removeChild(old[i]); }
	var first = null;
	for (var id in errors) {
		if (!errors.hasOwnProperty(id)) { continue; }
		var block = fields.querySelector(\'[data-appneck-question="\' + id + \'"]\');
		if (!block) { continue; }
		var p = document.createElement("p");
		p.className = "appneck-sdk-survey__error";
		p.appendChild(document.createTextNode(errors[id]));
		block.appendChild(p);
		if (!first) { first = block; }
	}
	if (first && first.scrollIntoView) { first.scrollIntoView({ block: "nearest" }); }
}

function intercept(event) {
	var link = event.target;
	while (link && link.tagName !== "A") { link = link.parentNode; }
	if (!link || !link.href) { return; }
	if (link.href.indexOf("action=deactivate") === -1) { return; }
	if (decodeURIComponent(link.href).indexOf("plugin=" + cfg.plugin) === -1 &&
		link.href.indexOf("plugin=" + encodeURIComponent(cfg.plugin)) === -1) { return; }

	event.preventDefault();
	lastFocus = link;
	target = link.href;
	fields.innerHTML = "<p>" + esc(cfg.strings.loading) + "</p>";
	open();

	var href = link.href;

	post("questions", {}, function (ok, data) {
		if (!ok || !data || !data.success || !data.data || !data.data.questions || !data.data.questions.length) {
			// No survey configured, or we could not load it. Get out of
			// the way — this is the common case for most products.
			root.setAttribute("hidden", "hidden");
			window.location.href = href;
			return;
		}
		questions = data.data.questions;
		renderQuestions();
		open();
	});
}

document.addEventListener("click", function (event) {
	var el = event.target;
	while (el && el !== root && el.getAttribute) {
		if (el.hasAttribute("data-appneck-cancel")) { event.preventDefault(); close(); return; }
		el = el.parentNode;
	}
}, false);

document.addEventListener("keydown", function (event) {
	if ((event.key === "Escape" || event.key === "Esc") && !root.hasAttribute("hidden")) { close(); }
}, false);

document.addEventListener("click", intercept, false);

// Reveals the one follow-up field belonging to whichever conditional
// choice is now selected, and hides every other follow-up in that same
// question — a plain "change" listener on the container rather than one
// per radio, since renderQuestions() rebuilds the whole list on every
// modal open.
fields.addEventListener("change", function (event) {
	var input = event.target;
	if (!input || !input.getAttribute || input.getAttribute("data-appneck-choice-index") === null) { return; }
	var block = closestQuestionBlock(input);
	if (!block) { return; }
	var followups = block.querySelectorAll("[data-appneck-followup-index]");
	for (var i = 0; i < followups.length; i++) { followups[i].setAttribute("hidden", "hidden"); }
	if (input.getAttribute("data-appneck-needs-text") === "1") {
		var shown = block.querySelector(\'[data-appneck-followup-index="\' + input.getAttribute("data-appneck-choice-index") + \'"]\');
		if (shown) { shown.removeAttribute("hidden"); }
	}
}, false);

skipButton.addEventListener("click", function () { deactivate(); }, false);

submitButton.addEventListener("click", function () {
	var values = collect();
	var pending = target;
	submitButton.disabled = true;
	submitButton.textContent = cfg.strings.sending;

	post("submit", values, function (ok, data) {
		if (ok && data && !data.success && data.data && data.data.errors) {
			// Local validation said no. The one case where the modal stays
			// open, because it is fixable and nothing has been sent.
			submitButton.disabled = false;
			submitButton.textContent = cfg.strings.submit;
			showErrors(data.data.errors);
			return;
		}
		// Everything else — success, a server rejection, a dead network —
		// proceeds. The survey must never be the reason a deactivation
		// does not happen.
		target = pending;
		deactivate();
	});
}, false);
})();</script>';
	}

	// -----------------------------------------------------------------
	// AJAX
	// -----------------------------------------------------------------

	/**
	 * Both operations behind one action: `questions` fills the modal,
	 * `submit` sends it. One handler because they share the nonce, the
	 * capability check and the per-product action name.
	 *
	 * @return array<string, mixed>|null The payload sent, for tests. Null
	 *                                   when the request was refused.
	 */
	public function handle_ajax() {
		if ( ! $this->current_user_can_deactivate() ) {
			return $this->fail( 'You are not allowed to do that.', 403 );
		}

		if ( function_exists( 'check_ajax_referer' ) && ! check_ajax_referer( $this->action(), 'nonce', false ) ) {
			return $this->fail( 'That page has expired. Please reload and try again.', 403 );
		}

		$op = isset( $_POST['op'] ) ? (string) $_POST['op'] : '';

		if ( 'questions' === $op ) {
			// Forced: this is the modal-open moment, and the cache is a
			// fallback for a failed/breaker-skipped fetch, never a gate on
			// a healthy one. A survey edited minutes ago must be visible
			// immediately, not after whatever is left of a stale 12-hour
			// window — see Survey::questions()'s own fallback chain for
			// what still protects this call when the network is down.
			return $this->send( array( 'questions' => $this->survey->questions( true ) ) );
		}

		if ( 'submit' !== $op ) {
			return $this->fail( 'Unknown operation.', 400 );
		}

		$values    = $this->posted_answers();
		$questions = $this->survey->questions();
		$errors    = $this->survey->validate( $values, $questions );

		if ( array() !== $errors ) {
			// The one refusal the modal acts on rather than proceeding
			// through: nothing has been sent and the owner can fix it.
			return $this->fail_with( array( 'errors' => $errors ) );
		}

		$response = $this->survey->submit( $values, $questions );

		// Deliberately reports success even when the submission failed.
		// The modal's only remaining job is to let the deactivation
		// happen, and there is nothing the site owner could do with a
		// network error at this point — see the class doc.
		return $this->send(
			array(
				'submitted' => null !== $response && $response->ok(),
				'status'    => null !== $response ? $response->status() : 0,
			)
		);
	}

	/**
	 * The answers come over as one JSON string rather than nested form
	 * fields, because a checkbox answer is an array and a question id is a
	 * uuid — encoding that through form-field names is exactly where an
	 * injection or a silently-dropped value comes from. Decoded and
	 * shape-checked here; every value is re-validated against the real
	 * question before anything is sent.
	 *
	 * @return array<string, mixed>
	 */
	private function posted_answers() {
		if ( ! isset( $_POST['answers'] ) || ! is_string( $_POST['answers'] ) ) {
			return array();
		}

		// WordPress slashes $_POST; a free-text answer containing a quote
		// would otherwise arrive with a backslash in front of it, and the
		// JSON would fail to decode.
		$raw = function_exists( 'wp_unslash' ) ? wp_unslash( $_POST['answers'] ) : $_POST['answers'];

		$decoded = json_decode( $raw, true );

		if ( ! is_array( $decoded ) ) {
			return array();
		}

		$values = array();

		foreach ( $decoded as $id => $value ) {
			if ( ! is_string( $id ) ) {
				continue;
			}

			// conditional's shape: {value: <chosen choice>, text?: <optional
			// follow-up>} — an assoc array with a 'value' key, unlike a
			// checkbox's plain numeric-indexed list. Checked first so it is
			// never mistaken for one.
			if ( is_array( $value ) && array_key_exists( 'value', $value ) ) {
				$clean = array();

				$clean['value'] = is_scalar( $value['value'] ) ? (string) $value['value'] : '';

				if ( isset( $value['text'] ) && is_scalar( $value['text'] ) ) {
					$clean['text'] = (string) $value['text'];
				}

				$values[ $id ] = $clean;

				continue;
			}

			if ( is_array( $value ) ) {
				$clean = array();

				foreach ( $value as $item ) {
					if ( is_scalar( $item ) ) {
						$clean[] = (string) $item;
					}
				}

				$values[ $id ] = $clean;

				continue;
			}

			if ( is_scalar( $value ) ) {
				$values[ $id ] = (string) $value;
			}
		}

		return $values;
	}

	// -----------------------------------------------------------------
	// Environment
	// -----------------------------------------------------------------

	/**
	 * `activate_plugins` — the same capability WordPress itself requires
	 * to deactivate a plugin. Anyone who can reach the link can answer the
	 * survey, and nobody else can; on multisite that correctly includes a
	 * single-site administrator.
	 */
	private function current_user_can_deactivate() {
		if ( ! function_exists( 'current_user_can' ) ) {
			return true;
		}

		return (bool) current_user_can( 'activate_plugins' );
	}

	private function can_render() {
		if ( ! function_exists( 'esc_html' ) || ! function_exists( 'esc_attr' ) ) {
			return false;
		}

		if ( null === $this->plugin_basename || '' === $this->plugin_basename ) {
			// Without the basename the script cannot tell which row's
			// Deactivate link is ours, and intercepting every plugin's
			// link would be unforgivable.
			return false;
		}

		return $this->current_user_can_deactivate();
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function send( array $data ) {
		if ( function_exists( 'wp_send_json_success' ) ) {
			wp_send_json_success( $data );
		}

		return $data;
	}

	/**
	 * @param array<string, mixed> $data
	 * @return array<string, mixed>
	 */
	private function fail_with( array $data ) {
		if ( function_exists( 'wp_send_json_error' ) ) {
			wp_send_json_error( $data );
		}

		$this->denied = isset( $data['errors'] ) ? 'validation' : 'error';

		return $data;
	}

	/**
	 * @param string $message
	 * @return null
	 */
	private function fail( $message, $status ) {
		$this->denied = (string) $message;

		if ( function_exists( 'wp_send_json_error' ) ) {
			wp_send_json_error( array( 'message' => $message ), $status );
		}

		return null;
	}

	/**
	 * The last refusal, recorded only when WordPress's wp_send_json_*
	 * helpers are unavailable (this package's own tests, where they would
	 * otherwise exit) so a refusal stays observable.
	 *
	 * @var string|null
	 */
	public $denied = null;
}
