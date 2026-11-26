const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

function navigationHarness() {
    const elements = new Map();
    function element(id) {
        if (!elements.has(id)) elements.set(id, {
            events: {}, style: {}, classList: { add() {}, remove() {}, toggle() {} },
            setAttribute() {}, addEventListener(name, callback) { this.events[name] = callback; },
        });
        return elements.get(id);
    }
    const pans = [];
    const timers = new Map();
    const watches = [];
    const map = { setView() { return this; }, panTo(point) { pans.push(point); } };
    const layer = { addTo() { return this; }, clearLayers() {}, hasLayer() { return false; } };
    const context = {
        window: { addEventListener() {} },
        document: { getElementById: element, querySelectorAll() { return []; } },
        localStorage: { getItem(key) { return key === 'token' ? 'test-token' : '1'; } },
        navigator: { geolocation: {
            watchPosition(callback) { watches.push(callback); return watches.length; }, clearWatch() {},
        } },
        L: {
            map() { return map; }, layerGroup() { return layer; }, divIcon(options) { return options; },
            polyline() { return layer; },
            marker(point) { return { point, addTo() { return this; }, setLatLng(value) { this.point = value; } }; },
        },
        PedestriBasemap: { add() {} },
        PedestriRouteServiceArea: { attach() { return {
            contains(lat, lng) { return lat > -7.3 && lat < -7.2 && lng > 112.76 && lng < 112.82; },
        }; } },
        PedestriRouteHazards: { render() {} },
        setTimeout() { return 1; }, clearTimeout() {},
        setInterval(callback) { const id = timers.size + 1; timers.set(id, callback); return id; },
        clearInterval(id) { timers.delete(id); }, showToast() {},
    };
    const source = fs.readFileSync(path.join(__dirname, '../resources/views/warga/navigasi.blade.php'), 'utf8');
    const script = source.slice(source.indexOf('(() => {'), source.lastIndexOf('</script>'));
    const boot = script.indexOf('    const incoming = readIncomingRoute();');
    vm.runInNewContext(script.slice(0, boot) + `
        window.navigation = { state, startNavigation, onPosition, onPositionError, prepareGeometry, pointAtAlong };
    })();`, context);
    const api = context.window.navigation;
    api.state.origin = { lat: -7.27, lng: 112.79 };
    api.state.route = { polyline: [[-7.27, 112.79], [-7.26, 112.79]], distance_m: 1100 };
    return { ...api, elements, pans, watches, tick() { for (const callback of timers.values()) callback(); } };
}

function position(lat, lng, accuracy) {
    return { coords: { latitude: lat, longitude: lng, accuracy, heading: null } };
}

test('simulation starts at the route origin even after GPS has moved elsewhere', async () => {
    const h = navigationHarness();
    await h.startNavigation();
    h.onPosition(position(-7.265, 112.78, 25000));
    assert.equal(h.state.lastPosition.lng, 112.78);
    assert.ok(h.state.along > 500);

    h.elements.get('btnDemo').events.click();
    assert.equal(h.state.userMarker.point[0], -7.27);
    assert.equal(h.state.userMarker.point[1], 112.79);
    h.tick();
    assert.ok(h.state.along > 7.9 && h.state.along < 8.1);
    assert.equal(h.state.userMarker.point[1], 112.79);
});

test('simulation ignores live GPS and resumes its own progress after stopping', async () => {
    const h = navigationHarness();
    await h.startNavigation();
    h.elements.get('btnDemo').events.click();
    h.tick();
    const along = h.state.demoAlong;
    h.onPosition(position(-7.269, 112.79, 5));
    assert.ok(Math.abs(h.state.along - along) < 0.01);
    h.elements.get('btnDemo').events.click();
    h.onPosition(position(-7.265, 112.78, 25000));
    h.elements.get('btnDemo').events.click();
    assert.equal(h.state.userMarker.point[1], 112.79);
    assert.ok(Math.abs(h.state.along - along) < 0.01);
    h.tick();
    assert.ok(Math.abs(h.state.along - along - 8) < 0.01);
});
