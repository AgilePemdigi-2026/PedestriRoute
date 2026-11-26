# CV evaluation results

Run started: 2026-11-26T22:33:05.116049+00:00

## Scope and interpretation

Measured the local ONNX specialists and the current `/analyze` function on a seeded sample of existing validation images. This is a validation re-evaluation, **not an independent held-out test**. Labels come from dataset directories and were not re-annotated. Existing source bias, synthetic crossing defects, and near-duplicate leakage remain possible. Wilson intervals below assume independent images and can be optimistic when images are related.

Seed: 42; ONNX Runtime: 1.32.0; CPU inference threads: 2. Worker network disabled; repository mounted read-only. Latency excludes HTTP/network transport, includes in-process image decode for pipeline, and excludes initial warm-up.

## Main results

| Category | N | Training best top-1 | Full-image accuracy | Pipeline accuracy (95% Wilson) | Pipeline macro-F1 | Defect recall | High-confidence errors ≥0.9 |
|---|---:|---:|---:|---|---:|---:|---:|
| trotoar | 150 | 0.957 | 0.960 | 0.887 (0.826–0.928) | 0.885 | 0.940 | 4 |
| drainase | 150 | 0.913 | 0.913 | 0.880 (0.818–0.923) | 0.879 | 0.840 | 6 |
| guiding_block | 150 | 0.993 | 0.987 | 0.887 (0.826–0.928) | 0.885 | 1.000 | 17 |
| parkir_liar | 150 | 0.988 | 0.980 | 0.980 (0.943–0.993) | 0.980 | 1.000 | 0 |
| penyebrangan | 150 | 0.950 | 0.967 | 0.960 (0.915–0.982) | 0.960 | 0.980 | 5 |

Training best top-1 is historical checkpoint-selection evidence; full-image and pipeline results use the current sampled inputs. Differences can reflect preprocessing, checkpoint choice, sampling, and pipeline rules; they do not isolate one cause.

## Dataset overlap audit

Exact overlap uses decoded RGB pixels and dimensions, not filenames. It does not detect flipped, cropped, or otherwise near-duplicate images.

| Category | Train images | Validation images | Unique shared pixel hashes | Validation images shared with train | Extra within-validation duplicates | Non-overlap sampled accuracy |
|---|---:|---:|---:|---:|---:|---:|
| trotoar | 2380 | 420 | 4 | 4 | 0 | 0.8866666666666667 |
| drainase | 2210 | 390 | 0 | 0 | 0 | 0.88 |
| guiding_block | 2380 | 420 | 0 | 0 | 0 | 0.8866666666666667 |
| parkir_liar | 1824 | 321 | 0 | 0 | 0 | 0.98 |
| penyebrangan | 1700 | 300 | 0 | 0 | 0 | 0.96 |

## trotoar

Model SHA-256: `1e736d35fc3b397e7016050a5fc77fa197c7ab704cf8c0bd7e06e2630dbfa99b`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 37 | 3 | 10 |
| RUSAK | 0 | 47 | 3 |
| SPAM | 0 | 1 | 49 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 1.000 | 0.740 | 0.851 |
| RUSAK | 50 | 0.922 | 0.940 | 0.931 |
| SPAM | 50 | 0.790 | 0.980 | 0.875 |

Defects called BAIK: 0/50; defects called SPAM: 3/50. Quality-guard rejections: 13; full-image → pipeline label changes: 19.

Pipeline p50/p95: 423.2/448.9 ms. Full-image p50/p95: 82.3/92.3 ms.

10-bin top-label ECE: 0.1030. Brier score: 0.0638625960441641; NLL: 0.13440663992431529. Probability metrics use 137/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 150 | 17 |
| 0.65 | 148 | 17 |
| 0.8 | 145 | 17 |
| 0.9 | 130 | 4 |
| 0.95 | 124 | 3 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 0.933 | 0.400 | 8 |
| brightness_0.5 | 15 | 0.933 | 0.667 | 4 |
| white_border_96px | 15 | 0.933 | 0.800 | 2 |

### Example errors (up to five)

- `datasets_per_category/trotoar/val/BAIK/BAIK_00019.jpg`: BAIK → RUSAK, confidence 0.9877.
- `datasets_per_category/trotoar/val/BAIK/BAIK_00140.jpg`: BAIK → RUSAK, confidence 0.9790.
- `datasets_per_category/trotoar/val/SPAM/SPAM_00049.jpg`: SPAM → RUSAK, confidence 0.9616.
- `datasets_per_category/trotoar/val/BAIK/BAIK_00080.jpg`: BAIK → RUSAK, confidence 0.9367.
- `datasets_per_category/trotoar/val/BAIK/BAIK_00053.jpg`: BAIK → SPAM, confidence 0.8500.

## drainase

Model SHA-256: `cd26ee49bb7b0bb6116d82da4050712a7105bd84dfdf1284859bd9d4e5d1ef18`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 41 | 7 | 2 |
| RUSAK | 5 | 42 | 3 |
| SPAM | 0 | 1 | 49 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 0.891 | 0.820 | 0.854 |
| RUSAK | 50 | 0.840 | 0.840 | 0.840 |
| SPAM | 50 | 0.907 | 0.980 | 0.942 |

Defects called BAIK: 5/50; defects called SPAM: 3/50. Quality-guard rejections: 5; full-image → pipeline label changes: 9.

Pipeline p50/p95: 263.4/315.5 ms. Full-image p50/p95: 85.4/123.1 ms.

10-bin top-label ECE: 0.0980. Brier score: 0.1585196178061397; NLL: 0.34825835225337015. Probability metrics use 145/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 149 | 18 |
| 0.65 | 146 | 17 |
| 0.8 | 142 | 15 |
| 0.9 | 127 | 6 |
| 0.95 | 124 | 5 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 0.933 | 0.600 | 6 |
| brightness_0.5 | 15 | 0.933 | 0.733 | 3 |
| white_border_96px | 15 | 0.933 | 0.867 | 1 |

### Example errors (up to five)

- `datasets_per_category/drainase/val/BAIK/BAIK_00091.jpg`: BAIK → RUSAK, confidence 0.9993.
- `datasets_per_category/drainase/val/BAIK/BAIK_00076.jpg`: BAIK → RUSAK, confidence 0.9992.
- `datasets_per_category/drainase/val/RUSAK/RUSAK_00156.jpg`: RUSAK → BAIK, confidence 0.9944.
- `datasets_per_category/drainase/val/BAIK/BAIK_00066.jpg`: BAIK → RUSAK, confidence 0.9925.
- `datasets_per_category/drainase/val/BAIK/BAIK_00109.jpg`: BAIK → RUSAK, confidence 0.9900.

## guiding_block

Model SHA-256: `e575aa9dcd6c2e9530f6a36f43568667dfb6878b9eda9007163dea3784f8513f`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 34 | 16 | 0 |
| RUSAK | 0 | 50 | 0 |
| SPAM | 0 | 1 | 49 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 1.000 | 0.680 | 0.810 |
| RUSAK | 50 | 0.746 | 1.000 | 0.855 |
| SPAM | 50 | 1.000 | 0.980 | 0.990 |

Defects called BAIK: 0/50; defects called SPAM: 0/50. Quality-guard rejections: 0; full-image → pipeline label changes: 15.

Pipeline p50/p95: 266.8/297.9 ms. Full-image p50/p95: 85.9/94.3 ms.

10-bin top-label ECE: 0.1079. Brier score: 0.2102200867468173; NLL: 0.46175668770763434. Probability metrics use 150/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 150 | 17 |
| 0.65 | 150 | 17 |
| 0.8 | 150 | 17 |
| 0.9 | 150 | 17 |
| 0.95 | 143 | 10 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 0.800 | 0.667 | 7 |
| brightness_0.5 | 15 | 0.800 | 0.800 | 2 |
| white_border_96px | 15 | 0.800 | 1.000 | 3 |

### Example errors (up to five)

- `datasets_per_category/guiding_block/val/BAIK/BAIK_00174.jpg`: BAIK → RUSAK, confidence 0.9999.
- `datasets_per_category/guiding_block/val/BAIK/BAIK_00038.jpg`: BAIK → RUSAK, confidence 0.9968.
- `datasets_per_category/guiding_block/val/BAIK/BAIK_00036.jpg`: BAIK → RUSAK, confidence 0.9954.
- `datasets_per_category/guiding_block/val/BAIK/BAIK_00080.jpg`: BAIK → RUSAK, confidence 0.9932.
- `datasets_per_category/guiding_block/val/BAIK/BAIK_00015.jpg`: BAIK → RUSAK, confidence 0.9928.

## parkir_liar

Model SHA-256: `ec95ffc35fb1223305c3b650d0cf191536ec82bb2cb6188ac936b257f0934a2b`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 47 | 3 | 0 |
| RUSAK | 0 | 50 | 0 |
| SPAM | 0 | 0 | 50 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 1.000 | 0.940 | 0.969 |
| RUSAK | 50 | 0.943 | 1.000 | 0.971 |
| SPAM | 50 | 1.000 | 1.000 | 1.000 |

Defects called BAIK: 0/50; defects called SPAM: 0/50. Quality-guard rejections: 0; full-image → pipeline label changes: 0.

Pipeline p50/p95: 92.6/107.9 ms. Full-image p50/p95: 85.9/100.9 ms.

10-bin top-label ECE: 0.0281. Brier score: 0.03881827897708662; NLL: 0.06594548236385951. Probability metrics use 150/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 150 | 3 |
| 0.65 | 147 | 3 |
| 0.8 | 142 | 2 |
| 0.9 | 133 | 0 |
| 0.95 | 128 | 0 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 1.000 | 0.600 | 6 |
| brightness_0.5 | 15 | 1.000 | 1.000 | 0 |
| white_border_96px | 15 | 1.000 | 0.667 | 5 |

### Example errors (up to five)

- `datasets_per_category/parkir_liar/val/BAIK/BAIK_00068.jpg`: BAIK → RUSAK, confidence 0.8520.
- `datasets_per_category/parkir_liar/val/BAIK/BAIK_00049.jpg`: BAIK → RUSAK, confidence 0.8027.
- `datasets_per_category/parkir_liar/val/BAIK/BAIK_00076.jpg`: BAIK → RUSAK, confidence 0.7160.

## penyebrangan

Model SHA-256: `3ba244932fb6596caa8c060aaf0224b0ef1430150cee13885f307c16bb8f6a95`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 45 | 5 | 0 |
| RUSAK | 1 | 49 | 0 |
| SPAM | 0 | 0 | 50 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 0.978 | 0.900 | 0.938 |
| RUSAK | 50 | 0.907 | 0.980 | 0.942 |
| SPAM | 50 | 1.000 | 1.000 | 1.000 |

Defects called BAIK: 1/50; defects called SPAM: 0/50. Quality-guard rejections: 0; full-image → pipeline label changes: 9.

Pipeline p50/p95: 266.0/321.9 ms. Full-image p50/p95: 85.4/110.8 ms.

10-bin top-label ECE: 0.0270. Brier score: 0.0789457938311646; NLL: 0.17878416012281745. Probability metrics use 150/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 150 | 6 |
| 0.65 | 149 | 6 |
| 0.8 | 148 | 6 |
| 0.9 | 142 | 5 |
| 0.95 | 132 | 4 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 0.867 | 0.333 | 10 |
| brightness_0.5 | 15 | 0.867 | 0.600 | 7 |
| white_border_96px | 15 | 0.867 | 0.800 | 3 |

### Example errors (up to five)

- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00089.jpg`: BAIK → RUSAK, confidence 0.9715.
- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00009.jpg`: BAIK → RUSAK, confidence 0.9680.
- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00062.jpg`: BAIK → RUSAK, confidence 0.9628.
- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00073.jpg`: BAIK → RUSAK, confidence 0.9610.
- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00063.jpg`: BAIK → RUSAK, confidence 0.9013.

## Not established by this run

- Independent local-photo accuracy, geographic generalization, label agreement, or injury-risk reduction.
- PyTorch/ONNX parity: PyTorch was not part of this worker evaluation.
- Live HTTP throughput, queue integration, or field conditions.
- Causal attribution of pipeline changes to quality guard versus crops; full-image and complete-pipeline comparison is a combined ablation.
- Final acceptance against population thresholds. A fresh grouped and independently labeled test set is needed.

Raw predictions, class supports, reliability bins, overlap examples, hashes, and metrics are in `cv_results.json`.
