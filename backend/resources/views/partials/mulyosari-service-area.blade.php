{{-- Visual approximation around Mulyosari; not an administrative boundary or submission rule. --}}
<script>
    window.PedestriRouteServiceArea = (() => {
        const geojson = {
            type: 'Feature',
            properties: {
                name: 'Koridor Mulyosari, Surabaya',
                boundary_status: 'visual_approximation',
                description: 'Batas visual perkiraan, bukan batas administratif resmi.'
            },
            geometry: {
                type: 'Polygon',
                coordinates: [[
                    [112.7900, -7.2780],
                    [112.8050, -7.2780],
                    [112.8050, -7.2540],
                    [112.7900, -7.2540],
                    [112.7900, -7.2780]
                ]]
            }
        };

        function attach(map) {
            // Keep the fill below road overlays and pins, without blocking map clicks.
            if (!map.getPane('serviceAreaPane')) {
                map.createPane('serviceAreaPane');
                map.getPane('serviceAreaPane').style.zIndex = '350';
            }
            const layer = L.geoJSON(geojson, {
                pane: 'serviceAreaPane',
                bubblingMouseEvents: true,
                style: {
                    color: '#006257',
                    weight: 2,
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
            const navigationBounds = bounds.pad(0.25);
            map.setMaxBounds(navigationBounds);
            map.options.maxBoundsViscosity = 1;
            map.setMinZoom(Math.max(0, map.getBoundsZoom(navigationBounds)));
            map.fitBounds(bounds, {padding: [16, 16], animate: false});

            // Recalculate the zoom floor after mobile orientation changes.
            map.on('resize', () => {
                map.setMinZoom(Math.max(0, map.getBoundsZoom(navigationBounds)));
                map.panInsideBounds(navigationBounds, {animate: false});
            });
            return {layer, bounds, navigationBounds};
        }
        return {geojson, attach};
    })();
</script>
