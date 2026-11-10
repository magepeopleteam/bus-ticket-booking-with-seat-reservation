/* NODE_PATH=/tmp/wbtm-datepicker-qa/node_modules node --test tests/search-route-dates.test.cjs */
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const plugin = path.resolve(__dirname, '..');
const wp = path.resolve(plugin, '../../..');
const initial = { start_route: 'Firenze', end_route: 'Asmana', j_date: '2026-11-18', r_date: '2026-11-19' };
const render = data => execFileSync('php', [path.join(__dirname, 'fixtures/search-date-picker.php'), JSON.stringify({ nonce: 'test', ...data })], { encoding: 'utf8' });
const options = '<ul class="wbtm_input_select_list"><li data-value="Firenze">Firenze</li><li data-value="Asmana">Asmana</li><li data-value="Gigli">Gigli</li></ul>';

async function fixture() {
    const dom = new JSDOM('<body></body>', { runScripts: 'dangerously', pretendToBeVisual: true, url: 'https://example.test/' });
    const w = dom.window;
    w.eval(fs.readFileSync(path.join(wp, 'wp-includes/js/jquery/jquery.min.js'), 'utf8'));
    w.eval(fs.readFileSync(path.join(wp, 'wp-includes/js/jquery/ui/datepicker.min.js'), 'utf8'));
    const $ = w.jQuery;
    $.fx.off = true;
    $.datepicker._findPos = () => [0, 0];
    w.wbtm_date_format = 'D d M , yy';
    w.wbtm_ajax_url = '/ajax';
    w.wbtm_nonce = 'test';
    w.wbtm_loader_xs = w.wbtm_loaderRemove = w.wbtm_alert = () => {};
    const notices = [];
    w.wbtm_toast = message => notices.push(message);
    $('body').html(`<div id="wbtm_area"><div class="wbtm_search_area"><form>
        <input name="wbtm_post_id" value="0"><input name="wbtm_bus_scope" value="7" data-date="2026-11-18">
        <div class="wbtm_start_point"><input class="formControl" name="bus_start_route" value="Firenze">${options}</div>
        <div class="wbtm_dropping_point"><input class="formControl" name="bus_end_route" value="Asmana">${options}</div>
        <div class="wbtm_journey_date">${render({ ...initial, action: 'get_wbtm_journey_date' })}</div>
        <div class="wbtm_return_date">${render({ ...initial, action: 'get_wbtm_return_date' })}</div>
        </form></div><div class="wbtm_search_result"><input name="bus_start_route" value="Old result"><input name="j_date" value="2026-11-17"></div></div>`);
    const requests = [];
    $.ajax = settings => {
        const request = { ...settings, aborted: false };
        request.abort = () => { request.aborted = true; settings.error?.({}, 'abort'); };
        requests.push(request);
        settings.beforeSend?.();
        return request;
    };
    w.eval(fs.readFileSync(path.join(plugin, 'assets/global/wbtm_global.js'), 'utf8'));
    const ready = () => new Promise(resolve => $(resolve));
    await ready();
    const form = $('.wbtm_search_area');
    const change = (name, value) => form.find(`[name="${name}"]`).val(value).trigger(name === 'j_date' ? 'change' : 'mp_change');
    const reply = async (request, extra = {}) => {
        request.success(request.data.action === 'get_wbtm_dropping_point' ? options : render({ ...request.data, ...extra }));
        await ready();
    };
    return { dom, w, $, form, change, reply, requests, notices, ready };
}

test('From then To after a search retains valid dates and refreshes both real datepickers', async () => {
    const f = await fixture();
    try {
        f.change('bus_start_route', 'Asmana');
        await f.reply(f.requests.at(-1));
        f.change('bus_end_route', 'Firenze');
        const outbound = f.requests.at(-1);
        assert.equal(outbound.data.j_date, initial.j_date);
        assert.equal(outbound.data.wbtm_bus_scope, '7');
        await f.reply(outbound);
        const inbound = f.requests.at(-1);
        assert.equal(inbound.data.action, 'get_wbtm_return_date');
        assert.equal(inbound.data.j_date, initial.j_date);
        assert.equal(inbound.data.r_date, initial.r_date);
        await f.reply(inbound);
        assert.equal(f.form.find('[name=j_date]').val(), initial.j_date);
        assert.equal(f.form.find('[name=r_date]').val(), initial.r_date);
        for (const id of ['wbtm_journey_date', 'wbtm_return_date']) {
            const input = f.form.find('#' + id);
            assert.equal(input.prop('disabled'), false);
            input.trigger('click');
            assert.equal(f.$.datepicker._datepickerShowing, true);
            input.datepicker('hide');
        }
        assert.equal(f.$('.wbtm_search_result [name=j_date]').val(), '2026-11-17');
    } finally { f.dom.window.close(); }
});

test('unavailable departure and return dates are cleared instead of retaining stale selections', async () => {
    const f = await fixture();
    try {
        f.change('bus_end_route', 'Gigli');
        await f.reply(f.requests.at(-1), { dates: ['2026-11-20'] });
        assert.equal(f.form.find('[name=j_date]').val(), '');
        assert.equal(f.form.find('[name=r_date]').val(), '');
        assert.equal(f.form.find('#wbtm_return_date').hasClass('hasDatepicker'), false);
        f.change('j_date', '2026-11-20');
        await f.reply(f.requests.at(-1), { dates: ['2026-11-20', '2026-11-21'], r_date: '2026-11-19' });
        assert.equal(f.form.find('[name=r_date]').val(), '');
        const allowed = f.$('#wbtm_return_date').datepicker('option', 'beforeShowDay');
        assert.equal(allowed(new f.w.Date(2026, 10, 19))[0], false);
        assert.equal(allowed(new f.w.Date(2026, 10, 21))[0], true);
    } finally { f.dom.window.close(); }
});

test('old route and return-date responses cannot overwrite newer selections', async () => {
    const f = await fixture();
    try {
        f.change('bus_end_route', 'Gigli');
        const oldRoute = f.requests.at(-1);
        f.change('bus_end_route', 'Firenze');
        const newRoute = f.requests.at(-1);
        assert.equal(oldRoute.aborted, true);
        await f.reply(newRoute);
        const firstReturn = f.requests.at(-1);
        f.change('j_date', '2026-11-20');
        const latestReturn = f.requests.at(-1);
        await f.reply(latestReturn, { r_date: '2026-11-21' });
        await f.reply(firstReturn);
        await f.reply(oldRoute, { dates: [] });
        assert.equal(f.form.find('[name=j_date]').val(), '2026-11-20');
        assert.equal(f.form.find('[name=r_date]').val(), '2026-11-21');
        assert.deepEqual(f.notices, []);
    } finally { f.dom.window.close(); }
});

test('a failed date reload removes the loading state and allows a retry', async () => {
    const f = await fixture();
    try {
        f.change('bus_end_route', 'Gigli');
        f.requests.at(-1).error({}, 'error');
        assert.equal(f.form.find('#wbtm_journey_date').prop('disabled'), false);
        assert.equal(f.notices.length, 1);
        f.change('bus_end_route', 'Gigli');
        await f.reply(f.requests.at(-1));
        await f.reply(f.requests.at(-1));
        assert.equal(f.form.find('[name=j_date]').val(), initial.j_date);
        assert.equal(f.form.find('[name=r_date]').val(), initial.r_date);
    } finally { f.dom.window.close(); }
});

test('destination responses from an earlier From selection are ignored', async () => {
    const f = await fixture();
    try {
        f.change('bus_start_route', 'Asmana');
        const old = f.requests.at(-1);
        f.change('bus_start_route', 'Gigli');
        const current = f.requests.at(-1);
        await f.reply(current);
        const html = f.form.find('.wbtm_dropping_point').html();
        old.success('<ul><li data-value="Obsolete">Obsolete</li></ul>');
        assert.equal(f.form.find('.wbtm_dropping_point').html(), html);
        assert.equal(old.aborted, true);
    } finally { f.dom.window.close(); }
});

test('the swap button refreshes only its form and keeps valid dates', async () => {
    const f = await fixture();
    try {
        f.form.append('<button id="wbtm_search_location_toggle" type="button">Swap</button>');
        f.w.eval(fs.readFileSync(path.join(plugin, 'assets/frontend/wbtm.js'), 'utf8'));
        await f.ready();
        const timers = [];
        const originalTimer = f.w.setTimeout;
        f.w.setTimeout = callback => { timers.push(callback); };
        f.$('#wbtm_search_location_toggle').trigger('click');
        f.w.setTimeout = originalTimer;
        timers.forEach(callback => callback());
        assert.equal(f.requests.at(-1).data.action, 'get_wbtm_dropping_point');
        await f.reply(f.requests.at(-1));
        assert.equal(f.requests.at(-1).data.end_route, 'Firenze');
        await f.reply(f.requests.at(-1));
        await f.reply(f.requests.at(-1));
        assert.equal(f.form.find('[name=j_date]').val(), initial.j_date);
        assert.equal(f.form.find('[name=r_date]').val(), initial.r_date);
        assert.equal(f.$('.wbtm_search_result [name=bus_start_route]').val(), 'Old result');
    } finally { f.dom.window.close(); }
});

test('one-way searches still refresh the departure without a return-date request', async () => {
    const f = await fixture();
    try {
        f.form.find('.wbtm_return_date').remove();
        f.change('bus_end_route', 'Gigli');
        await f.reply(f.requests.at(-1));
        assert.equal(f.requests.length, 1);
        assert.equal(f.form.find('[name=j_date]').val(), initial.j_date);
    } finally { f.dom.window.close(); }
});

test('event scope still rejects a preset outside the allowed outbound date', () => {
    const dom = new JSDOM(render({ ...initial, action: 'get_wbtm_journey_date', scope_date: '2026-11-20' }));
    assert.equal(dom.window.document.querySelector('[name=j_date]').value, '');
    dom.window.close();
});
