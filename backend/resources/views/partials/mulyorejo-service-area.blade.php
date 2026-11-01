{{-- Batas Kecamatan Mulyorejo, Surabaya. Dasar: OSM relation 8225259, sisi timur laut disesuaikan manual dengan referensi visual batas Google Maps (perkiraan, bukan data resmi). --}}
<script>
    window.PedestriRouteServiceArea = (() => {
        @php
            $serviceAreaGeojson = json_decode(
                file_get_contents(resource_path('data/mulyorejo-service-area.geojson')),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        @endphp

        const geojson = @json($serviceAreaGeojson);

        function ringContains(ring, lat, lon) {
            let inside = false;
            for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
                const xi = ring[i][0], yi = ring[i][1];
                const xj = ring[j][0], yj = ring[j][1];
                if ((yi > lat) !== (yj > lat) && lon < (xj - xi) * (lat - yi) / (yj - yi) + xi) {
                    inside = !inside;
                }
            }
            return inside;
        }

        // Cek titik terhadap polygon batas Kecamatan Mulyorejo (bukan kotak)
        function onRing(ring, lat, lon) {
            for (let i = 1; i < ring.length; i++) {
                const [ax, ay] = ring[i - 1], [bx, by] = ring[i];
                const dx = bx - ax, dy = by - ay;
                const length = Math.hypot(dx, dy);
                if (length === 0) continue;
                const cross = (lon - ax) * dy - (lat - ay) * dx;
                if (Math.abs(cross) <= 1e-12 * length &&
                    lon >= Math.min(ax, bx) - 1e-12 && lon <= Math.max(ax, bx) + 1e-12 &&
                    lat >= Math.min(ay, by) - 1e-12 && lat <= Math.max(ay, by) + 1e-12) return true;
            }
            return false;
        }

        function contains(lat, lon) {
            if (!Number.isFinite(lat) || !Number.isFinite(lon)) return false;
            return geojson.geometry.coordinates.some(poly =>
                poly.some(ring => onRing(ring, lat, lon)) ||
                (ringContains(poly[0], lat, lon) &&
                 !poly.slice(1).some(hole => ringContains(hole, lat, lon)))
            );
        }

        function attach(map) {
            if (!map.getPane('serviceAreaPane')) {
                map.createPane('serviceAreaPane');
                map.getPane('serviceAreaPane').style.zIndex = '350';
            }
            const layer = L.geoJSON(geojson, {
                pane: 'serviceAreaPane',
                bubblingMouseEvents: true,
                style: {
                    color: '#006257',
                    weight: 2.5,
                    opacity: 1,
                    fillColor: '#008775',
                    fillOpacity: 0.12
                },
                onEachFeature(feature, polygon) {
                    const text = document.createElement('div');
                    const title = document.createElement('strong');
                    title.textContent = feature.properties.name;
                    text.append(title, document.createElement('br'), feature.properties.description);
                    polygon.bindTooltip(text.cloneNode(true), {sticky: true});
                    polygon.bindPopup(text, {autoPan: false});
                }
            }).addTo(map);
            const bounds = layer.getBounds();
            const navigationBounds = bounds.pad(0.05);
            map.setMaxBounds(navigationBounds);
            map.options.maxBoundsViscosity = 1;
            map.setMinZoom(Math.max(0, map.getBoundsZoom(navigationBounds)));
            map.fitBounds(bounds, {padding: [16, 16], animate: false});

            // Recalculate zoom floor on container resize
            map.on('resize', () => {
                map.setMinZoom(Math.max(0, map.getBoundsZoom(navigationBounds)));
                map.panInsideBounds(navigationBounds, {animate: false});
            });
            return {layer, bounds, navigationBounds, contains};
        }
        return {geojson, attach};
    })();
</script>