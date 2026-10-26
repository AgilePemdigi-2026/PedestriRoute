# Rencana Implementasi Computer Vision PedestriRoute (Sprint 2)

**Fitur Terkait:** F4 (Validasi Laporan Otomatis dengan Computer Vision), F6 (Peta Heatmap Kelayakan Pejalan Kaki), F9 (Pembaruan Status Validasi / Perbaikan)  
**Target Lingkungan:** Docker Container (`cv-service`), 2 vCPU, RAM 4–8 GB, CPU Inference (ONNX Runtime)  
**Batas SLA Waktu Inferensi:** $\le 10$ detik per citra (`CFG_CV_TIMEOUT_SECONDS = 10`)

---

## 1. Ringkasan Eksekutif & Integrasi Alur Sprint 2

Sistem PedestriRoute dirancang untuk menerima aduan kondisi fasilitas pejalan kaki dari warga (F3) beserta foto bukti, yang kemudian dianalisis secara asinkron oleh layanan Computer Vision (`cv-service`) melalui antrean worker Laravel (`ProcessReportCv`).

```
[ Warga Kirim Laporan di /lapor-kondisi (F3) ]
                     │
                     ▼
         [ Status Awal: DIPROSES ]
                     │
                     ▼ (Queue Job: ProcessReportCv)
      [ POST http://cv-service:8000/analyze ]
                     │
    ┌────────────────┴───────────────────────────────┐
    ▼ (label: RUSAK & conf >= 0.80)                  ▼ (conf < 0.80 / BAIK / SPAM / Timeout)
[ Status: TERVALIDASI ]                      [ Status: MENUNGGU_VALIDASI_ADMIN ]
    │                                                │
    ├─► Recalculate WSM (Skor segmen turun)          ├─► Ditinjau manual oleh Admin Dishub di /admin/validasi (F5)
    │   (F6: Heatmap otomatis terupdate)             │
    │                                                ▼ (Admin Setujui Laporan)
    │                                        [ Status: TERVALIDASI ]
    │                                                │
    └──────────────────┬─────────────────────────────┘
                       │
                       ▼
        [ Laporan Aktif di Jalur Pedestrian ]
                       │
                       ▼ (Admin Eksekusi F9: PATCH /repair-status)
          [ Status: SUDAH_DIPERBAIKI ]
                       │
                       └─► Recalculate WSM (Skor segmen pulih kembali ke Hijau)
```

---

## 2. Taksonomi 5 Kategori Masalah Terpilih

Berdasarkan keputusan pembaruan antarmuka pelaporan, terdapat 5 kategori masalah fasilitas pejalan kaki yang memiliki karakteristik visual tegas dan didukung penuh oleh model Computer Vision:

| No | Kategori Laporan | Deskripsi Sub-Masalah | Nama Kelas di Model YOLO |
|:--:|:---|:---|:---|
| **1** | **🛠️ Trotoar Rusak** | Paving amblas, paving pecah, retak beton, lubang trotoar | `0: trotoar_rusak` |
| **2** | **🕳️ Masalah Drainase** | Tutup gorong-gorong/manhole rusak, patah, atau terbuka | `1: drainase_rusak` |
| **3** | **🦯 Guiding Block Rusak** | Ubin pemandu tunanetra terkelupas, hilang, atau pecah | `2: guiding_block_rusak` |
| **4** | **🚦 Penyebrangan Rusak** | Marka garis putih aus/pudar, atau penyeberangan rusak | `3: penyebrangan_rusak` |
| **5** | **🛵 Parkir Liar** | Sepeda motor atau mobil di atas fasilitas pejalan kaki | `4: parkir_liar` |

---

## 3. Katalog Dataset Terbuka (*Open-Source Datasets*)

Seluruh dataset berikut bersifat publik, berlisensi terbuka (*open-access*), dan dapat diunduh untuk melatih model:

### A. Kategori 1: 🛠️ Trotoar Rusak
* **Dataset Utama:** **RDD2022 (Road Damage Dataset - Sekilab / IEEE Big Data Cup)**
  * **Repositori:** [https://github.com/sekilab/RoadDamageDetector](https://github.com/sekilab/RoadDamageDetector)
  * **Tautan Unduh Langsung:** [RDD2022.zip (AWS S3)](https://bigdatacup.s3.ap-northeast-1.amazonaws.com/2022/CRDDC2022/RDD2022/RDD2022.zip) *(atau subset iklim tropis [RDD2022_India.zip](https://bigdatacup.s3.ap-northeast-1.amazonaws.com/2022/CRDDC2022/RDD2022/Country_Specific_Data_CRDDC2022/RDD2022_India.zip) 502 MB)*
  * **Jumlah Data:** **47.420 gambar** (Train: ~26.000, Test: ~21.000).
  * **Format & Lisensi:** Pascal VOC XML / YOLO txt (CC BY-SA 4.0).
  * **Kelas yang Digunakan:** `D00` (Retak Memanjang), `D10` (Retak Melintang), `D20` (Retak Buaya), dan `D40` (Lubang Trotoar/Pothole).
* **Dataset Suplemen:** **Cracked Sidewalk Global Dataset (HuggingFace - EmbedEarth)**
  * **Tautan:** [https://huggingface.co/datasets/EmbedEarth/cracked-sidewalk](https://huggingface.co/datasets/EmbedEarth/cracked-sidewalk)
  * **Jumlah Data:** **>10.000 gambar** observasi trotoar retak/rusak di perkotaan.

### B. Kategori 2: 🕳️ Masalah Drainase
* **Dataset Utama:** **Sewer Manhole Cover Defect Detection Dataset (GitHub - lonlonago)**
  * **Repositori:** [https://github.com/lonlonago/Sewer-Manhole-Cover-Defect-Detection-Dataset-2660-imagesVOC-YOLO-format](https://github.com/lonlonago/Sewer-Manhole-Cover-Defect-Detection-Dataset-2660-imagesVOC-YOLO-format)
  * **Jumlah Data:** **2.660 gambar** lengkap dalam format VOC + YOLO.
  * **Kelas yang Digunakan:** `broke` (569 box), `lose/unaligned` (604 box), `uncovered` (594 box lubang saluran terbuka).
* **Dataset Suplemen:** **Manhole Covers Dataset (HuggingFace - delima87)**
  * **Tautan:** [https://huggingface.co/datasets/delima87/manhole_covers_dataset](https://huggingface.co/datasets/delima87/manhole_covers_dataset)
  * **Jumlah Data:** **2.730 gambar** tutup gorong-gorong dan drainase perkotaan (Apache-2.0).

### C. Kategori 3: 🦯 Guiding Block Rusak
* **Dataset Utama:** **Tactile Paving Detector (GitHub - Westley-Winks / Crowd4Access)**
  * **Repositori:** [https://github.com/Westley-Winks/tactile-paving-detector](https://github.com/Westley-Winks/tactile-paving-detector)
  * **Jumlah Data:** **2.119 gambar** trotoar nyata beranotasi bounding box (CC BY-SA 4.0).
* **Dataset Suplemen:** **Python-Qt Sidewalk Tactile Paving (GitHub - alicema-creator)**
  * **Repositori:** [https://github.com/alicema-creator/Python-Qt-Detection-and-recognition-of-sidewalk-tactile-paving](https://github.com/alicema-creator/Python-Qt-Detection-and-recognition-of-sidewalk-tactile-paving)
  * **Jumlah Data:** **~1.200 sampel** jalur pemandu tunanetra perkotaan.

### D. Kategori 4: 🚦 Penyebrangan Rusak
* **Dataset Utama:** **CDNet Crosswalk Detection Dataset (GitHub - zhangzhengde0225)**
  * **Repositori:** [https://github.com/zhangzhengde0225/CDNet](https://github.com/zhangzhengde0225/CDNet)
  * **Tautan Unduh:** [CDNet Trainset (Google Drive)](https://drive.google.com/file/d/1RIIpdrAUXZRuoOgHMIr-BSsJxSjqiKC8/view?usp=sharing) | [CDNet Testset (Google Drive)](https://drive.google.com/file/d/1DBVt81LehEyiTxuUn-BUGBoh3gXcwrEE/view?usp=sharing)
  * **Jumlah Data:** **4.850 gambar** (Train: 3.080 gambar, Test: 1.770 gambar) dalam format YOLOv5/YOLOv8.
  * **Fungsi:** Mengenali keberadaan marka zebra cross dan tanda keausan garis putih penyeberangan.
* **Dataset Suplemen:** **Project Sidewalk Crosswalk Accessibility (HuggingFace)**
  * **Tautan:** [https://huggingface.co/datasets/projectsidewalk/sidewalk-validator-ai-dataset-crosswalk](https://huggingface.co/datasets/projectsidewalk/sidewalk-validator-ai-dataset-crosswalk)
  * **Jumlah Data:** **1.590 gambar** evaluasi kelayakan marka penyeberangan jalan.

### E. Kategori 5: 🛵 Parkir Liar
* **Dataset Utama:** **Istanbul Sidewalk Parking Detection (HuggingFace - eneskarabulut)**
  * **Tautan:** [https://huggingface.co/datasets/eneskarabulut/istanbul-sidewalk-parking-detection](https://huggingface.co/datasets/eneskarabulut/istanbul-sidewalk-parking-detection)
  * **Jumlah Data:** **1.090 gambar** kendaraan nyata (motor & mobil) yang terparkir melanggar di badan trotoar pejalan kaki (Apache-2.0).
* **Dataset Suplemen:** **MS-COCO (Ultralytics Pretrained Base)**
  * **Tautan:** [https://cocodataset.org/](https://cocodataset.org/)
  * **Catatan:** Model bawaan `yolov8n.pt` sudah membawa bobot untuk `motorcycle`, `car`, dan `bicycle` dari **>330.000 gambar**.

### F. Sampel Data Lokal Surabaya (*Fine-Tuning Domain Adaptasi*)
* **Jumlah Sasaran:** **150 – 250 gambar** mandiri dari area Mulyosari, Gubeng, Kampus ITS/UNAIR, dan Jl. Pemuda.
* **Tujuan:** Menutup celah *domain shift* antara jalan aspal luar negeri dengan trotoar *paving block* lokal Surabaya yang sering tertutup debu/pasir.

---

## 4. Pemilihan Arsitektur Model & Infrastruktur Serving

### A. Model Terpilih: **YOLOv8-nano (YOLOv8n) di-ekspor ke ONNX**
* **Ukuran Bobot:** **~6.2 MB** (`pedestriroute_yolov8n.onnx`).
* **Framework Inferensi:** **ONNX Runtime (CPUExecutionProvider)** tanpa dependensi PyTorch/CUDA berat.
* **Kecepatan Inferensi:** **~75 – 110 ms per gambar** pada 2 vCPU Docker.
* **Konsumsi Memori:** **< 300 MB RAM** (sangat stabil untuk server 4–8 GB).

### B. Kontrak Layanan Microservice FastAPI (`cv-service`)
* **Framework:** Python 3.11 + FastAPI + Uvicorn + ONNX Runtime.
* **Endpoint:**
  * `POST /analyze`: menerima form-data `file` (berkas gambar biner), mengembalikan:
    ```json
    {
      "label": "RUSAK",
      "confidence": 0.88,
      "detected_category": "Trotoar Rusak",
      "detections": [
        {
          "class_id": 0,
          "class_name": "trotoar_rusak",
          "confidence": 0.88,
          "box": [120, 85, 340, 260]
        }
      ]
    }
    ```
  * `GET /health`: HTTP 200 `{ "status": "ok", "model_ready": true }`.
* **Mode Mocking (`CV_MOCK=true`):** Membaca nama berkas (misal `rusak_095.jpg` $\rightarrow$ `RUSAK, 0.95`) untuk testing otomatis PHPUnit di CI/CD tanpa beban AI.

---

## 5. Pipeline Pengambilan Keputusan & Nilai Ambang (*Confidence Logic*)

```
                     [ Input Citra Foto Laporan Warga ]
                                     │
                                     ▼
         [ Stage 0: Guardrail Spam & Kualitas Citra (Fast Heuristic) ]
           • Blur Check: Variansi Laplacian < 100 ──► label: "SPAM", conf: 0.85
           • Exposure Check: Kecerahan Rata-rata < 25 ──► label: "SPAM", conf: 0.90
                                     │
                                     ▼ (Lolos Pre-Filter)
           [ Stage 1: Inferensi Model YOLOv8n ONNX (640x640) ]
                                     │
         ┌───────────────────────────┴───────────────────────────┐
         ▼ (Terdeteksi $\ge 1$ BBox Cacat)                       ▼ (Nol BBox Cacat Terdeteksi)
    label = "RUSAK"                                         label = "BAIK"
    conf = max(detection_confidences)                       conf = 1.0 - max(noise_score, 0.05)
```

### Aturan Evaluasi di Job Laravel `ProcessReportCv`:
* `label == 'RUSAK'` dan `confidence >= 0.80` (`CFG_CV_THRESHOLD`):
  $\rightarrow$ **Auto-Validasi (`TERVALIDASI`)**, `admin_id = null`, pemicu rekalkulasi WSM `SegmentScoreService::recalculate()`, notifikasi `NOTIF_AUTO_VALIDATED`.
* `confidence < 0.80` ATAU `label in ('BAIK', 'SPAM')`:
  $\rightarrow$ **Dialihkan ke Antrean Admin (`MENUNGGU_VALIDASI_ADMIN`)**, menyimpan `cv_label` dan `confidence_score` sebagai bahan rujukan visual Admin Dishub (F5).
* Timeout ($> 10\text{s}$) atau kegagalan koneksi:
  $\rightarrow$ **Fallback Aman (`MENUNGGU_VALIDASI_ADMIN`)**, tidak menghilangkan laporan warga.

---

## 6. Rencana Pelatihan Model (*Training Pipeline*)

### A. Konfigurasi Dataset (`dataset_pedestriroute.yaml`)
```yaml
path: /content/dataset_pedestriroute
train: train/images
val: valid/images
test: test/images

names:
  0: trotoar_rusak
  1: drainase_rusak
  2: guiding_block_rusak
  3: penyebrangan_rusak
  4: parkir_liar
```

### B. Perintah Pelatihan (Google Colab / Local GPU)
```bash
# 1. Install dependensi
pip install ultralytics onnx onnxruntime

# 2. Jalankan transfer learning dari bobot COCO yolov8n.pt
yolo detect train \
  data=dataset_pedestriroute.yaml \
  model=yolov8n.pt \
  epochs=75 \
  imgsz=640 \
  batch=16 \
  optimizer=AdamW \
  lr0=0.002 \
  patience=15 \
  save=True

# 3. Ekspor ke ONNX teroptimasi untuk serving CPU
yolo export \
  model=runs/detect/train/weights/best.pt \
  format=onnx \
  imgsz=640 \
  simplify=True
```

---

## 7. Rencana Penyesuaian Backend Laravel (Sprint 2)

### A. Pembaruan Seeder Kategori (`KategoriMasalahSeeder.php`)
Sesuaikan tabel `kategori_masalah` dengan 5 kategori baru beserta bobot WSM yang dinormalkan ($\sum \text{bobot} = 1.000$):
* **Trotoar Rusak:** Bobot **0.300**
* **Masalah Drainase:** Bobot **0.250**
* **Guiding Block Rusak:** Bobot **0.200**
* **Penyebrangan Rusak:** Bobot **0.150**
* **Parkir Liar:** Bobot **0.100**

### B. Pembaruan Konfigurasi Lingkungan (`.env`)
```ini
CFG_CV_ENABLED=true
CFG_CV_THRESHOLD=0.80
CFG_CV_TIMEOUT_SECONDS=10
CFG_CV_CATEGORIES="Trotoar Rusak,Masalah Drainase,Guiding Block Rusak,Penyebrangan Rusak,Parkir Liar"
CV_SERVICE_URL=http://cv-service:8000
CV_MOCK=false
```

### C. Pembaruan Antarmuka Warga (`lapor.blade.php`)
Perbarui grid pilihan kategori 2x2 menjadi grid 5 kategori interaktif sesuai rancangan visual baru pada `[Image 1]`.
