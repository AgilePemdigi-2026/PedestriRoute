{{--
    Shared Leaflet basemap. Uses Google Maps (via the official Maps JavaScript API
    and Leaflet.GoogleMutant) when GOOGLE_MAPS_API_KEY is set, otherwise falls back
    to OpenStreetMap tiles. Include after leaflet.js, then call
    PedestriBasemap.add(map, { maxZoom: 19 }) instead of L.tileLayer(...).
--}}
@php
    $googleMapsKey = (string) config('services.google_maps.key');
    $googleMapsType = (string) config('services.google_maps.type', 'roadmap');
    if (! in_array($googleMapsType, ['roadmap', 'satellite', 'hybrid', 'terrain'], true)) {
        $googleMapsType = 'roadmap';
    }
    $googleMapsEnabled = $googleMapsKey !== '';
@endphp
@once
@if($googleMapsEnabled)
<script src="https://unpkg.com/leaflet.gridlayer.googlemutant@0.16.0/dist/Leaflet.GoogleMutant.js"></script>
<script async src="https://maps.googleapis.com/maps/api/js?key={{ urlencode($googleMapsKey) }}&loading=async&language=id&region=ID&callback=Function.prototype"></script>
@endif
<script>
window.PedestriBasemap = (() => {
    const googleEnabled = @json($googleMapsEnabled);
    const googleType = @json($googleMapsType);
    const osmUrl = 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png';
    const osmAttribution = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';
    const maps = [];
    let googleFailed = false;

    function osmLayer(options) {
        return L.tileLayer(osmUrl, { maxZoom: options.maxZoom ?? 19, attribution: options.attribution ?? osmAttribution });
    }

    function useOsm(entry) {
        if (entry.layer && entry.map.hasLayer(entry.layer)) {
            entry.map.removeLayer(entry.layer);
        }
        entry.layer = osmLayer(entry.options).addTo(entry.map);
        entry.layer.bringToBack();
    }

    // Called by the Maps JavaScript API when the key is invalid or the domain is not allowed.
    window.gm_authFailure = () => {
        googleFailed = true;
        console.warn('[PedestriRoute] Google Maps API key ditolak; peta dasar beralih ke OpenStreetMap.');
        maps.forEach(useOsm);
    };

    return {
        provider: () => (googleEnabled && ! googleFailed && L.gridLayer.googleMutant ? 'google' : 'osm'),

        /** Add the basemap to a Leaflet map and return the layer. */
        add(map, options = {}) {
            const entry = { map, options, layer: null };
            maps.push(entry);
            if (this.provider() === 'google') {
                entry.layer = L.gridLayer.googleMutant({ type: googleType, maxZoom: options.maxZoom ?? 19 }).addTo(map);
            } else {
                useOsm(entry);
            }

            return entry.layer;
        },
    };
})();
</script>
@endonce
