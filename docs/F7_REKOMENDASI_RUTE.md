# F7 — Rekomendasi Rute Pejalan Kaki

Endpoint ini mencari rute pejalan kaki teraman atau tercepat di jaringan segmen
Mulyorejo. Setiap segmen sudah memiliki skor kelayakan hasil **Weighted Sum
Model (WSM)** yang diperbarui saat laporan divalidasi atau diperbaiki.

## Endpoint

```http
POST /api/v1/routes/recommend
Authorization: Bearer <token>
Content-Type: application/json
```

Body:

```json
{
  "origin": { "lat": -7.2660, "lng": 112.7950 },
  "destination": { "lat": -7.2680, "lng": 112.7960 },
  "preference": "safest"
}
```

Payload datar (`origin_lat`, `origin_lng`, `dest_lat`, `dest_lng`) juga diterima
agar kompatibel dengan peta Leaflet.

| Field | Wajib | Keterangan |
| :--- | :---: | :--- |
| `origin` / `destination` | Ya | Koordinat `lat` (-90..90) dan `lng` (-180..180). |
| `preference` | Tidak | `safest` (default) atau `fastest`. |

## Algoritma

1. Segmen kandidat dimuat dari PostGIS menggunakan bounding box koridor rute
   (`ST_Intersects` + `ST_MakeEnvelope`).
2. Geometri segmen (`ST_AsGeoJSON`) diubah menjadi graf simpul–sisi. Simpul
   jalan yang berdekatan tetapi memakai ID OSM berbeda disambungkan secara
   adaptif (20–300 m).
3. Titik awal dan tujuan di-snap ke sisi terdekat, lalu dicari dengan **A\***.
4. Biaya sisi pada mode `safest`:

   ```text
   biaya = jarak × (1 + ((100 - skor_wsm) / 100) × CFG_ROUTE_SCORE_PENALTY)
   ```

   Segmen yang sedang diperbaiki dikalikan `CFG_ROUTE_REPAIR_PENALTY`.
   Mode `fastest` memakai jarak murni sehingga memilih jalur terpendek.
5. Bila topologi OSM belum lengkap, layanan memakai koridor segmen terdekat,
   lalu garis langsung sebagai fallback terakhir.

## Respons Penting

```json
{
  "success": true,
  "route_id": "RTE-1A2B3C4D",
  "engine": "postgis_astar",
  "preference": "safest",
  "distance_m": 593.6,
  "duration_min": 8,
  "polyline": [[-7.266, 112.795], [-7.2665, 112.796]],
  "segments": [
    {
      "segmen_id": 16,
      "name": "Jl. Raya Mulyosari",
      "distance_m": 120.4,
      "score": 96.1,
      "tingkat": "SANGAT_LAYAK",
      "repair": false,
      "connector": false,
      "polyline": [[-7.266, 112.795], [-7.2665, 112.796]]
    }
  ],
  "summary": {
    "average_score": 96.1,
    "minimum_score": 82.0,
    "safe_distance_m": 593.6,
    "repair_distance_m": 0,
    "total_segments": 4,
    "fallback": false
  },
  "warnings": []
}
```

`warnings` dapat berisi `ACTIVE_REPAIR`, `LOW_FEASIBILITY`, dan
`NETWORK_FALLBACK`.

## Konfigurasi

| Variabel | Default | Fungsi |
| :--- | :---: | :--- |
| `CFG_ROUTE_SCORE_PENALTY` | `3.0` | Kekuatan penalti skor WSM pada mode `safest`. |
| `CFG_ROUTE_REPAIR_PENALTY` | `1.35` | Penalti tambahan untuk segmen yang sedang diperbaiki. |
| `CFG_ROUTE_CONNECTOR_PENALTY` | `1.2` | Penalti untuk jalur penghubung antar simpul. |

## Pengujian

- PHPUnit: `tests/Feature/Route/RouteRecommendationTest.php`
- BDD: `bdd/features/f7_rute_pejalan_kaki.feature`
- Jalankan BDD khusus F7: `./bdd/run_sprint3.sh`
