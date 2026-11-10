/*
 * npm install --prefix /tmp/wbtm-datepicker-qa jsdom@26 --ignore-scripts
 * NODE_PATH=/tmp/wbtm-datepicker-qa/node_modules node --test tests/search-datepicker.test.cjs
 */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');

const plugin = path.resolve(__dirname, '..');
const wp = path.resolve(plugin, '../../..');
const render = (selector, dates, soldout = []) => execFileSync('php', ['-r', `
define('ABSPATH', __DIR__);
function add_action(...$args) {}
function esc_attr($value) { return htmlspecialchars((string) $value, ENT_QUOTES); }
function esc_js($value) { return addslashes($value); }
function __($value, ...$args) { return $value; }
function wp_kses_post($value) { return $value; }
class WBTM_Translations {
    public static function text_date_available_status() { return 'Available'; }
    public static function text_date_unavailable_status() { return 'Unavailable'; }
}
require ${JSON.stringify(path.join(plugin, 'mp_global/class/WBTM_Global_Function.php'))};
(new WBTM_Global_Function())->date_picker_js(
    ${JSON.stringify(selector)},
    json_decode('${JSON.stringify(dates)}', true),
    json_decode('${JSON.stringify(soldout)}', true)
);
`], { encoding: 'utf8' });

async function fixture(dates = ['2026-11-10', '2026-11-11', '2026-11-12'], soldout = []) {
    const dom = new JSDOM(`<label><input type="hidden" name="j_date"><input id="journey" readonly></label>
        <label><input type="hidden" name="r_date"><input id="return" readonly></label>`, {
        runScripts: 'outside-only', pretendToBeVisual: true, url: 'https://example.test/'
    });
    const w = dom.window;
    w.eval(fs.readFileSync(path.join(wp, 'wp-includes/js/jquery/jquery.min.js'), 'utf8'));
    w.eval(fs.readFileSync(path.join(wp, 'wp-includes/js/jquery/ui/datepicker.min.js'), 'utf8'));
    w.wbtm_date_format = 'D d M , yy';
    const $ = w.jQuery;
    $.fx.off = true;
    // jsdom has no layout; only stub positioning, retaining the real datepicker events.
    $.datepicker._findPos = () => [0, 0];
    const initialize = (selector) => {
        const markup = render(selector, dates, soldout);
        const scriptDom = new JSDOM(markup);
        const script = scriptDom.window.document.querySelector('script').textContent;
        scriptDom.window.close();
        w.eval(script);
    };
    initialize('#journey');
    initialize('#return');
    await new Promise(resolve => $(resolve));
    return { dom, w, $, initialize };
}

test('both already-focused search date fields can reopen after selecting a date', async () => {
    const { w, $, dom } = await fixture();
    try {
        for (const id of ['journey', 'return']) {
            const input = $('#' + id);
            input.datepicker('setDate', new w.Date(2026, 10, 10));
            input[0].focus();
            $('#ui-datepicker-div td[data-handler="selectDay"] a[data-date="11"]').trigger('click');
            assert.equal(input.closest('label').find('input[type=hidden]').val(), '2026-11-11');
            assert.equal($.datepicker._datepickerShowing, false);
            assert.equal(w.document.activeElement, input[0]);
            input.trigger('click');
            assert.equal($.datepicker._datepickerShowing, true, `${id} should reopen without a blur/focus cycle`);
            $('#ui-datepicker-div td[data-handler="selectDay"] a[data-date="12"]').trigger('click');
            assert.equal(input.closest('label').find('input[type=hidden]').val(), '2026-11-12');
            assert.equal(input.prop('readOnly'), true);
            input[0].blur();
        }
    } finally { dom.window.close(); }
});

test('reopening preserves available, unavailable and sold-out dates', async () => {
    const { w, $, dom } = await fixture(['2026-11-10', '2026-11-11', '2026-11-12'], ['2026-11-11']);
    try {
        const input = $('#return');
        const allowed = input.datepicker('option', 'beforeShowDay');
        assert.equal(allowed(new w.Date(2026, 10, 10))[0], true);
        assert.equal(allowed(new w.Date(2026, 10, 9))[0], false);
        assert.deepEqual(Array.from(allowed(new w.Date(2026, 10, 11))).slice(0, 2), [false, 'wbtm-soldout-date']);
        input[0].focus();
        input.datepicker('hide');
        input.trigger('click');
        assert.equal($.datepicker._datepickerShowing, true);
        assert.equal(input.datepicker('option', 'beforeShowDay'), allowed);
    } finally { dom.window.close(); }
});

test('reinitialization does not accumulate click handlers', async () => {
    const { w, $, dom, initialize } = await fixture();
    try {
        initialize('#journey');
        await new Promise(resolve => $(resolve));
        const events = $._data($('#journey')[0], 'events');
        assert.equal(events.click.filter(event => event.namespace === 'wbtmDatePicker').length, 1);
    } finally { dom.window.close(); }
});

test('the results-page calendar stays above its page-builder stacking context', async () => {
    const { w, $, dom } = await fixture();
    try {
        $('label').wrapAll('<main class="jupiterx-main" style="position: relative; z-index: 20"></main>');
        $('main').append('<div class="wbtm_search_result" style="background: white">Bus results</div>');
        const style = w.document.createElement('style');
        style.textContent = fs.readFileSync(path.join(plugin, 'assets/frontend/wbtm.css'), 'utf8');
        w.document.head.appendChild(style);

        const importantLayers = (rules, element) => Array.from(rules).flatMap(rule => {
            if (rule.cssRules) {
                return importantLayers(rule.cssRules, element);
            }
            if (rule.selectorText && rule.style.getPropertyPriority('z-index') === 'important') {
                try { return element.matches(rule.selectorText) ? [rule.style.getPropertyValue('z-index')] : []; }
                catch { return []; }
            }
            return [];
        });

        for (const pageLayer of [20, 200]) {
            $('main').css('z-index', pageLayer);
            $('#journey')[0].focus();
            $('#journey').trigger('click');
            const calendar = $('#ui-datepicker-div')[0];
            // jsdom does not resolve media-query !important rules against inline styles.
            // Read the real stylesheet's overrides via CSSOM for its desktop rules.
            const forced = importantLayers(style.sheet.cssRules, calendar);
            const effectiveLayer = Number(forced.at(-1) || calendar.style.zIndex);
            assert.ok(effectiveLayer > pageLayer, `Calendar layer ${effectiveLayer} is hidden behind page layer ${pageLayer}`);
            assert.equal(calendar.style.position, 'absolute');
            $('#journey').datepicker('hide');
            $('#journey')[0].blur();
        }
    } finally { dom.window.close(); }
});
