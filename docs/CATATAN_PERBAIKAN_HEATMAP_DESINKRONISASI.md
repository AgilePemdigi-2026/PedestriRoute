# Catatan Perbaikan Bug: Desinkronisasi Titik Heatmap & Pin Laporan (F6)

## 1. Ringkasan Isu
Pada tampilan peta heatmap warga (`/beranda-warga`), ditemukan kondisi di mana gumpalan gradien termal panas (*heatmap point*) muncul di suatu koordinat jalan, namun tidak terdapat pin/marker laporan interaktif di atas koordinat tersebut. Ketika titik tersebut diklik, tidak ada respons atau kartu rincian laporan yang terbuka. Sementara itu, di dekatnya (seperti di Jl. Raya Mulyosari) muncul pin klaster angka (misal `2`) yang menaungi laporan di area tersebut.

---

## 2. Akar Masalah (Root Cause)
Terdapat desinkronisasi antara dua lapisan visualisasi spasial pada `resources/views/warga/beranda.blade.php`:

1. **Lapisan Kanvas Termal (`L.heatLayer`):**
   - Mengambil seluruh titik laporan individu mentah (`reports.map(r => [r.latitude, r.longitude, weight])`).
   - Setiap laporan aktif (termasuk `#LAP-0604` di Jl. Raya Mulyosari No. 32) digambar gumpalan panasnya secara individual di koordinat aslinya.

2. **Lapisan Marker / Klaster Interaktif (`clusters`):**
   - Menggunakan ambang batas jarak bumi tetap (**35 meter**).
   - Laporan `#LAP-0604` berjarak ~16,5 meter dari laporan `#LAP-0603` / `#LAP-0612` di sebelah utara. Karena $16,5\text{ m} \le 35\text{ m}$, algoritma menggabungkan laporan selatan ke dalam satu klaster di utara.
   - Marker interaktif (`L.marker`) hanya dirender satu buah pada titik awal klaster (`cluster.center` di utara).

3. **Efek pada Layar:**
   - Pada zoom tinggi (zoom 18–19), jarak 16,5 meter setara dengan **~50–60 piksel** di layar perangkat.
   - Akibatnya, di koordinat selatan muncul gumpalan panas dari kanvas `leaflet.heat`, namun tidak memiliki marker/pin interaktif (terlihat seperti titik panas "hantu" tanpa laporan).

---

## 3. Solusi yang Diterapkan

Perubahan diterapkan pada `backend/resources/views/warga/beranda.blade.php`:

1. **Sinkronisasi 1:1 `L.heatLayer` dengan Klaster Aktif:**
   - Sumber titik kanvas `heatPoints` diubah agar mengambil data langsung dari `clusters` (`[c.center.latitude, c.center.longitude, weight]`), bukan laporan mentah yang tersembunyi.
   - Bobot termal (`weight`) diselaraskan dengan jumlah laporan klaster dan legenda (1 aduan = hijau 0.35, 2–3 aduan = oranye 0.60, $\ge 4$ hotspot = merah 0.95, selesai diperbaiki = hijau zamrud 0.20).
   - Setiap gumpalan panas pada peta dijamin selalu memiliki pin/marker interaktif di atasnya.

2. **Klasterisasi Adaptif Berbasis Piksel Layar:**
   - Klasterisasi spasial dihitung dinamis menggunakan proyeksi piksel layar Leaflet (`map.latLngToLayerPoint`) dengan ambang batas **28 piksel**.
   - Pada zoom tinggi (zoom 18–19), laporan yang terpisah belasan meter memiliki jarak layar $> 28\text{ px}$, sehingga masing-masing titik memiliki marker dan kartu popup sendiri.
   - Pada zoom rendah/sedang, titik yang bertumpukan secara visual tetap dikelompokkan secara rapi dengan titik tengah (*geometric centroid*) yang terhitung presisi.

3. **Penambahan Event Listener `zoomend`:**
   - Menambahkan listener `zoomend` bersama `moveend` pada Leaflet map agar pembaharuan klasterisasi dan pembagian titik selalu responsif saat pengguna memperbesar/memperkecil peta.

---

## 4. Hasil Verifikasi
- Seluruh 57 pengujian otomatis backend Laravel PHPUnit (`tests/Feature/Heatmap/HeatmapTest` dan seluruh suite) **Lulus 100% (434 assertions)**.
- Desinkronisasi terselesaikan secara permanen tanpa titik panas yatim/anomali.
