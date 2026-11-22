const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function searchHarness(fetch, places = []) {
    const timers = new Map();
    let nextTimer = 0;
    function element(tag) {
        const classes = new Set();
        return {
            tag, children: [], events: {}, attributes: {}, value: '', id: tag,
            classList: { add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name) },
            setAttribute(key, value) { this.attributes[key] = value; },
            appendChild(child) { this.children.push(child); },
            replaceChildren() { this.children = []; },
            addEventListener(name, fn) { this.events[name] = fn; },
            querySelectorAll() { return this.children.flatMap(row => row.children.filter(child => child.tag === 'button')); },
            contains(child) { return this.children.includes(child); },
            click() { this.events.click?.(); },
            scrollIntoView() {},
        };
    }
    const input = element('input');
    const list = element('ul');
    const edits = [];
    const choices = [];
    const context = {
        window: {}, document: { createElement: element, activeElement: input }, fetch, AbortController,
        setTimeout(fn) { const id = ++nextTimer; timers.set(id, fn); return id; },
        clearTimeout(id) { timers.delete(id); },
    };
    const source = fs.readFileSync(path.join(__dirname, '../resources/views/partials/route-location-search.blade.php'), 'utf8');
    vm.runInNewContext(source.replace(/<\/?script>/g, ''), context);
    context.window.PedestriRouteLocationSearch.attach({
        input, list, kind: 'origin', token: 'test-token', places,
        contains: (lat, lng) => lat < 0 && lng > 112,
        onEdit: kind => edits.push(kind), onChoose: (kind, place) => choices.push({ kind, place }),
    });
    return {
        input, list, edits, choices,
        type(value) { input.value = value; input.events.input(); },
        async flush() { const callbacks = [...timers.values()]; timers.clear(); await Promise.all(callbacks.map(fn => fn())); },
    };
}

test('typed addresses invalidate the old point and return selectable, safely rendered results', async () => {
    const requests = [];
    const h = searchHarness(async (url, options) => {
        requests.push({ url, options });
        return { ok: true, json: async () => ({ places: [
            { label: '<img src=x onerror=alert(1)>', lat: -7.266, lng: 112.795 },
            { label: 'Outside area', lat: 1, lng: 1 },
        ] }) };
    });
    h.type('Jalan Saya 12');
    assert.deepEqual(h.edits, ['origin']);
    await h.flush();
    assert.equal(requests[0].url, '/api/v1/places/search?q=Jalan%20Saya%2012');
    assert.equal(requests[0].options.headers.Authorization, 'Bearer test-token');
    const buttons = h.list.querySelectorAll();
    assert.equal(buttons.length, 1);
    assert.equal(buttons[0].textContent, '<img src=x onerror=alert(1)>');
    assert.equal(buttons[0].innerHTML, undefined);
    buttons[0].click();
    assert.equal(h.choices[0].kind, 'origin');
    assert.equal(h.choices[0].place.lat, -7.266);
});

test('older address responses cannot replace the latest query results', async () => {
    let finishOld;
    let oldSignal;
    const h = searchHarness((url, options) => {
        if (url.includes('Older')) {
            oldSignal = options.signal;
            return new Promise(resolve => { finishOld = resolve; });
        }
        return Promise.resolve({ ok: true, json: async () => ({ places: [{ label: 'New address', lat: -7.266, lng: 112.795 }] }) });
    });
    h.type('Older address');
    const oldRequest = h.flush();
    h.type('Newer address');
    await h.flush();
    assert.equal(oldSignal.aborted, true);
    finishOld({ ok: true, json: async () => ({ places: [{ label: 'Old address', lat: -7.267, lng: 112.796 }] }) });
    await oldRequest;
    assert.equal(h.list.querySelectorAll()[0].textContent, 'New address');
});

test('unavailable address search never inserts map-picker controls', async () => {
    const h = searchHarness(async () => { throw new Error('Geocoder offline'); });
    h.type('My address');
    await h.flush();
    h.input.events.keydown({ key: 'Enter', preventDefault() {} });
    assert.equal(h.choices.length, 0);
    assert.equal(h.list.querySelectorAll().length, 0);
});

test('unmatched free-form addresses offer selectable Mulyorejo location recommendations', async () => {
    const h = searchHarness(async () => ({ ok: true, json: async () => ({ places: [] }) }), [
        { name: 'RS Universitas Airlangga', type: 'Rumah Sakit', lat: -7.2651, lng: 112.7897 },
        { name: 'Pasar Mulyosari', type: 'Pasar', lat: -7.2605, lng: 112.795 },
    ]);
    h.type('Alamat rumah saya nomor 123');
    await h.flush();
    assert.equal(h.list.querySelectorAll().length, 2);
    assert.equal(h.list.querySelectorAll()[0].textContent, 'RS Universitas Airlangga');
    h.input.events.keydown({ key: 'Enter', preventDefault() {} });
    assert.equal(h.choices[0].place.name, 'RS Universitas Airlangga');
});
