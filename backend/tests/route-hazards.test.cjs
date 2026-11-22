const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

function renderer() {
    const layer = () => ({ items: [], clearLayers() { this.items = []; } });
    const lines = layer();
    const markers = layer();
    const L = {
        polyline(points, options) {
            return { points, options, addTo(target) { target.items.push(this); return this; } };
        },
        marker(point, options) {
            return { point, options, addTo(target) { target.items.push(this); return this; }, bindPopup(popup) { this.popup = popup; return this; } };
        },
        divIcon(options) { return options; },
    };
    const document = { createElement(tag) { return { tag, children: [], appendChild(child) { this.children.push(child); } }; } };
    const context = { window: {}, document, L };
    const source = fs.readFileSync(path.join(__dirname, '../resources/views/partials/route-hazards.blade.php'), 'utf8');
    vm.runInNewContext(source.replace(/<\/?script>/g, ''), context);
    return { render: route => context.window.PedestriRouteHazards.render(route, lines, markers), lines, markers };
}

test('only encountered reports get pins and only clipped hazard geometry is dashed', () => {
    const { render, lines, markers } = renderer();
    const section = [[0, -0.0002], [0, 0.0002]];
    const maliciousAddress = '<img src=x onerror=alert(1)>';
    render({
        hazard_sections: [{ polyline: section }],
        colliding_points: [{ laporan_id: 1, lat: 0, lng: 0, alamat: maliciousAddress }],
        obstacles: [{ laporan_id: 2, lat: 1, lng: 1 }],
        avoided_points: [{ laporan_id: 3, lat: 2, lng: 2 }],
    });
    assert.equal(markers.items.length, 1);
    assert.deepEqual(Array.from(markers.items[0].point), [0, 0]);
    assert.equal(markers.items[0].popup.children[2].textContent, maliciousAddress);
    assert.equal(markers.items[0].popup.children[2].innerHTML, undefined);
    assert.equal(lines.items.length, 2);
    assert.equal(lines.items[1].options.dashArray, '7 9');
    assert.equal(lines.items[1].points, section);
});

test('switching to a report-free route clears every old pin and dashed overlay', () => {
    const { render, lines, markers } = renderer();
    render({ hazard_sections: [{ polyline: [[0, 0], [0, 1]] }], colliding_points: [{ laporan_id: 1, lat: 0, lng: 0 }] });
    render({ hazard_sections: [], colliding_points: [] });
    assert.equal(lines.items.length, 0);
    assert.equal(markers.items.length, 0);
});

test('route-search and navigation page scripts parse successfully', () => {
    for (const page of ['rute', 'navigasi']) {
        const source = fs.readFileSync(path.join(__dirname, `../resources/views/warga/${page}.blade.php`), 'utf8');
        const scripts = [...source.matchAll(/<script>([\s\S]*?)<\/script>/g)];
        assert.ok(scripts.length > 0);
        scripts.forEach(([, script]) => assert.doesNotThrow(() => new vm.Script(script, { filename: page })));
    }
});
