# PedestriRoute - OSRM Routing Backend

Repositori ini berisi backend OSRM (Open Source Routing Machine) yang telah disesuaikan untuk PedestriRoute dengan profil pejalan kaki khusus (`pedestriroute_foot.lua`).

## Fitur Profil PedestriRoute (`pedestriroute_foot.lua`)
1. **Prioritas Jalur Pejalan Kaki**: Memberikan bobot lebih tinggi pada `pedestrian`, `footway`, `crossing`, dan jalan yang memiliki trotoar (`sidewalk=both|left|right`).
2. **Penghindaran Bahaya & Hambatan**:
   - Menghindari jalan tol/jalan cepat tanpa trotoar (`motorway`, `trunk`).
   - Mendukung kelas `damaged`, `construction`, `hazard` yang dapat di-exclude secara dinamis via API OSRM `?exclude=damaged,construction`.
   - Mengurangi kecepatan/memberi penalti pada permukaan tidak rata/rusak (`smoothness=bad`, kerikil, jalan tanah).
3. **Integrasi Dinamis dengan Laporan Warga di Laravel**:
   - Di layer Laravel (`RouteRecommendationService.php`), koordinat titik laporan (`laporan` dan `lokasi` dengan status `TERVALIDASI` / `SEDANG_DIPERBAIKI`) dicocokkan langsung terhadap rute OSRM.
   - Jika rute melewati radius zona bahaya/perbaikan (misal 25-30m), sistem secara otomatis memilih rute alternatif OSRM atau menghitung waypoint jalan pintas aman untuk menghindari titik koordinat tersebut.

## Menjalankan OSRM Backend via Docker

Untuk menjalankan OSRM backend lokal di port 5000:

```bash
# 1. Unduh data OSM Surabaya / Jawa Timur (.osm.pbf) ke folder data/
# 2. Extract menggunakan profil pedestriroute_foot.lua:
docker run -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-extract -p /data/profiles/pedestriroute_foot.lua /data/surabaya.osm.pbf

# 3. Partition & Customize (MLD) atau Contract (CH):
docker run -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-partition /data/surabaya.osrm
docker run -t -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-customize /data/surabaya.osrm

# 4. Jalankan server OSRM:
docker run -t -i -p 5000:5000 -v "${PWD}:/data" ghcr.io/project-osrm/osrm-backend osrm-routed --algorithm mld /data/surabaya.osrm
```

Secara default, Laravel PedestriRoute akan otomatis menggunakan OSRM foot engine publik (`http://router.project-osrm.org/route/v1/foot`) atau OSRM lokal (`http://localhost:5000/route/v1/foot` jika `OSRM_BASE_URL` diatur di `.env`).
