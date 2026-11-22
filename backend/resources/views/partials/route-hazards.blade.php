<script>
window.PedestriRouteHazards = {
    render(route, lineLayer, markerLayer) {
        lineLayer.clearLayers();
        markerLayer.clearLayers();

        (route.hazard_sections || []).forEach(section => {
            if (!Array.isArray(section.polyline) || section.polyline.length < 2) return;
            // Mask the solid green underneath so the affected area reads as dashed.
            L.polyline(section.polyline, {
                color: '#fff7ed', weight: 8, opacity: 1, interactive: false,
                lineCap: 'butt',
            }).addTo(lineLayer);
            L.polyline(section.polyline, {
                color: '#ea580c', weight: 6, opacity: 1, dashArray: '7 9',
                lineCap: 'butt', interactive: false,
            }).addTo(lineLayer);
        });

        const seen = new Set();
        (route.colliding_points || []).forEach(report => {
            const lat = Number(report.lat);
            const lng = Number(report.lng);
            if (!Number.isFinite(lat) || !Number.isFinite(lng) || seen.has(report.laporan_id)) return;
            seen.add(report.laporan_id);
            const popup = document.createElement('div');
            popup.className = 'text-xs p-1';
            const title = document.createElement('strong');
            title.textContent = report.endpoint_unavoidable
                ? 'Laporan di area awal/tujuan'
                : (report.repair ? 'Perbaikan pada rute' : 'Laporan masalah pada rute');
            popup.appendChild(title);
            [report.kategori, report.alamat,
                report.endpoint_unavoidable ? 'Hanya bagian keluar/masuk area ini yang dilalui.' : 'Area terdampak ditandai garis oranye putus-putus.',
            ].forEach(text => {
                const row = document.createElement('p');
                row.textContent = text || '';
                popup.appendChild(row);
            });
            const icon = L.divIcon({
                className: 'route-hazard-pin',
                html: '<div style="width:26px;height:26px;border-radius:50%;background:#ea580c;color:white;border:2px solid white;box-shadow:0 2px 6px #0004;display:flex;align-items:center;justify-content:center;font-weight:900" aria-label="Laporan pada rute">!</div>',
                iconSize: [26, 26], iconAnchor: [13, 13],
            });
            L.marker([lat, lng], { icon, zIndexOffset: 500 }).addTo(markerLayer).bindPopup(popup);
        });
    },
};
</script>
