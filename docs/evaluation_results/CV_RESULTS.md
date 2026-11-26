# CV evaluation results

Run started: 2026-11-25T11:44:46.294526+00:00

## Scope and interpretation

Measured the local ONNX specialists and the current `/analyze` function on a seeded sample of existing validation images. This is a validation re-evaluation, **not an independent held-out test**. Labels come from dataset directories and were not re-annotated. Existing source bias, synthetic crossing defects, and near-duplicate leakage remain possible. Wilson intervals below assume independent images and can be optimistic when images are related.

Seed: 42; ONNX Runtime: 1.31.1; CPU inference threads: 2. Worker network disabled; repository mounted read-only. Latency excludes HTTP/network transport, includes in-process image decode for pipeline, and excludes initial warm-up.

## Main results

| Category | N | Training best top-1 | Full-image accuracy | Pipeline accuracy (95% Wilson) | Pipeline macro-F1 | Defect recall | High-confidence errors ≥0.9 |
|---|---:|---:|---:|---|---:|---:|---:|
| trotoar | 150 | 0.945 | 0.947 | 0.780 (0.707–0.839) | 0.764 | 0.920 | 12 |
| drainase | 150 | 0.895 | 0.900 | 0.853 (0.788–0.901) | 0.852 | 0.780 | 5 |
| guiding_block | 150 | 0.993 | 0.993 | 0.913 (0.857–0.949) | 0.912 | 1.000 | 13 |
| parkir_liar | 150 | 0.978 | 0.973 | 0.973 (0.933–0.990) | 0.973 | 0.960 | 0 |
| penyebrangan | 150 | 0.967 | 0.960 | 0.920 (0.865–0.954) | 0.919 | 1.000 | 7 |

Training best top-1 is historical checkpoint-selection evidence; full-image and pipeline results use the current sampled inputs. Differences can reflect preprocessing, checkpoint choice, sampling, and pipeline rules; they do not isolate one cause.

## Dataset overlap audit

Exact overlap uses decoded RGB pixels and dimensions, not filenames. It does not detect flipped, cropped, or otherwise near-duplicate images.

| Category | Train images | Validation images | Unique shared pixel hashes | Validation images shared with train | Extra within-validation duplicates | Non-overlap sampled accuracy |
|---|---:|---:|---:|---:|---:|---:|
| trotoar | 2380 | 420 | 4 | 4 | 0 | 0.78 |
| drainase | 2210 | 390 | 0 | 0 | 0 | 0.8533333333333334 |
| guiding_block | 2380 | 420 | 0 | 0 | 0 | 0.9133333333333333 |
| parkir_liar | 1824 | 321 | 0 | 0 | 0 | 0.9733333333333334 |
| penyebrangan | 1700 | 300 | 0 | 0 | 0 | 0.92 |

## trotoar

Model SHA-256: `14bf9b7ecfed1abe999a021a74732a5c5301df2c6160a67eb76abb9bf310c588`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 24 | 16 | 10 |
| RUSAK | 1 | 46 | 3 |
| SPAM | 1 | 2 | 47 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 0.923 | 0.480 | 0.632 |
| RUSAK | 50 | 0.719 | 0.920 | 0.807 |
| SPAM | 50 | 0.783 | 0.940 | 0.855 |

Defects called BAIK: 1/50; defects called SPAM: 3/50. Quality-guard rejections: 13; full-image → pipeline label changes: 28.

Pipeline p50/p95: 317.2/548.3 ms. Full-image p50/p95: 56.0/120.2 ms.

10-bin top-label ECE: 0.1787. Brier score: 0.22975110880850966; NLL: 0.41349319804988593. Probability metrics use 137/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 150 | 33 |
| 0.65 | 148 | 31 |
| 0.8 | 143 | 27 |
| 0.9 | 123 | 12 |
| 0.95 | 116 | 8 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 0.933 | 0.400 | 8 |
| brightness_0.5 | 15 | 0.933 | 0.667 | 4 |
| white_border_96px | 15 | 0.933 | 0.867 | 1 |

### Example errors (up to five)

- `datasets_per_category/trotoar/val/BAIK/BAIK_00080.jpg`: BAIK → RUSAK, confidence 0.9965.
- `datasets_per_category/trotoar/val/BAIK/BAIK_00083.jpg`: BAIK → RUSAK, confidence 0.9958.
- `datasets_per_category/trotoar/val/BAIK/BAIK_00052.jpg`: BAIK → RUSAK, confidence 0.9810.
- `datasets_per_category/trotoar/val/BAIK/BAIK_00123.jpg`: BAIK → RUSAK, confidence 0.9808.
- `datasets_per_category/trotoar/val/BAIK/BAIK_00162.jpg`: BAIK → RUSAK, confidence 0.9754.

## drainase

Model SHA-256: `11eb1c3fd9df84a229559892bc7a83285be6333b887bb5b6d2f3ec2a3776c028`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 41 | 6 | 3 |
| RUSAK | 8 | 39 | 3 |
| SPAM | 0 | 2 | 48 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 0.837 | 0.820 | 0.828 |
| RUSAK | 50 | 0.830 | 0.780 | 0.804 |
| SPAM | 50 | 0.889 | 0.960 | 0.923 |

Defects called BAIK: 8/50; defects called SPAM: 3/50. Quality-guard rejections: 5; full-image → pipeline label changes: 9.

Pipeline p50/p95: 196.6/322.9 ms. Full-image p50/p95: 55.7/110.3 ms.

10-bin top-label ECE: 0.0871. Brier score: 0.1738527413057239; NLL: 0.31621674505532676. Probability metrics use 145/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 150 | 22 |
| 0.65 | 145 | 19 |
| 0.8 | 132 | 13 |
| 0.9 | 118 | 5 |
| 0.95 | 109 | 5 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 0.933 | 0.467 | 8 |
| brightness_0.5 | 15 | 0.933 | 0.667 | 4 |
| white_border_96px | 15 | 0.933 | 0.667 | 4 |

### Example errors (up to five)

- `datasets_per_category/drainase/val/BAIK/BAIK_00076.jpg`: BAIK → RUSAK, confidence 0.9927.
- `datasets_per_category/drainase/val/RUSAK/RUSAK_00063.jpg`: RUSAK → BAIK, confidence 0.9927.
- `datasets_per_category/drainase/val/BAIK/BAIK_00066.jpg`: BAIK → RUSAK, confidence 0.9871.
- `datasets_per_category/drainase/val/BAIK/BAIK_00109.jpg`: BAIK → RUSAK, confidence 0.9702.
- `datasets_per_category/drainase/val/BAIK/BAIK_00091.jpg`: BAIK → RUSAK, confidence 0.9696.

## guiding_block

Model SHA-256: `24bee08558c67c87e0f8e2e07d5933387a5982aa1104bb0e18ea5af3db2826d9`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 37 | 13 | 0 |
| RUSAK | 0 | 50 | 0 |
| SPAM | 0 | 0 | 50 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 1.000 | 0.740 | 0.851 |
| RUSAK | 50 | 0.794 | 1.000 | 0.885 |
| SPAM | 50 | 1.000 | 1.000 | 1.000 |

Defects called BAIK: 0/50; defects called SPAM: 0/50. Quality-guard rejections: 0; full-image → pipeline label changes: 12.

Pipeline p50/p95: 203.5/305.2 ms. Full-image p50/p95: 57.0/92.0 ms.

10-bin top-label ECE: 0.0831. Brier score: 0.16212335027313923; NLL: 0.5124542005388338. Probability metrics use 150/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 150 | 13 |
| 0.65 | 150 | 13 |
| 0.8 | 150 | 13 |
| 0.9 | 150 | 13 |
| 0.95 | 146 | 9 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 0.800 | 0.667 | 7 |
| brightness_0.5 | 15 | 0.800 | 0.867 | 3 |
| white_border_96px | 15 | 0.800 | 1.000 | 3 |

### Example errors (up to five)

- `datasets_per_category/guiding_block/val/BAIK/BAIK_00174.jpg`: BAIK → RUSAK, confidence 1.0000.
- `datasets_per_category/guiding_block/val/BAIK/BAIK_00160.jpg`: BAIK → RUSAK, confidence 0.9989.
- `datasets_per_category/guiding_block/val/BAIK/BAIK_00095.jpg`: BAIK → RUSAK, confidence 0.9970.
- `datasets_per_category/guiding_block/val/BAIK/BAIK_00080.jpg`: BAIK → RUSAK, confidence 0.9966.
- `datasets_per_category/guiding_block/val/BAIK/BAIK_00036.jpg`: BAIK → RUSAK, confidence 0.9904.

## parkir_liar

Model SHA-256: `f6059b35a61766bf722d6743c839ca57136567facdadd4fbb27cd7bae3528567`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 48 | 2 | 0 |
| RUSAK | 2 | 48 | 0 |
| SPAM | 0 | 0 | 50 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 0.960 | 0.960 | 0.960 |
| RUSAK | 50 | 0.960 | 0.960 | 0.960 |
| SPAM | 50 | 1.000 | 1.000 | 1.000 |

Defects called BAIK: 2/50; defects called SPAM: 0/50. Quality-guard rejections: 0; full-image → pipeline label changes: 0.

Pipeline p50/p95: 51.1/62.9 ms. Full-image p50/p95: 41.5/52.0 ms.

10-bin top-label ECE: 0.0385. Brier score: 0.04128001692069239; NLL: 0.06981644981859186. Probability metrics use 150/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 149 | 3 |
| 0.65 | 145 | 3 |
| 0.8 | 138 | 0 |
| 0.9 | 134 | 0 |
| 0.95 | 128 | 0 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 1.000 | 0.800 | 3 |
| brightness_0.5 | 15 | 1.000 | 0.867 | 2 |
| white_border_96px | 15 | 1.000 | 1.000 | 0 |

### Example errors (up to five)

- `datasets_per_category/parkir_liar/val/RUSAK/RUSAK_00136.jpg`: RUSAK → BAIK, confidence 0.7885.
- `datasets_per_category/parkir_liar/val/BAIK/BAIK_00072.jpg`: BAIK → RUSAK, confidence 0.7281.
- `datasets_per_category/parkir_liar/val/RUSAK/RUSAK_00004.jpg`: RUSAK → BAIK, confidence 0.7075.
- `datasets_per_category/parkir_liar/val/BAIK/BAIK_00049.jpg`: BAIK → RUSAK, confidence 0.4130.

## penyebrangan

Model SHA-256: `424b200c432a6d6e499e4bf34783eca719c10b0fff29bb269d34a5c39cc63a12`

Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.

| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |
|---|---:|---:|---:|
| BAIK | 38 | 12 | 0 |
| RUSAK | 0 | 50 | 0 |
| SPAM | 0 | 0 | 50 |

| Class | Support | Precision | Recall | F1 |
|---|---:|---:|---:|---:|
| BAIK | 50 | 1.000 | 0.760 | 0.864 |
| RUSAK | 50 | 0.806 | 1.000 | 0.893 |
| SPAM | 50 | 1.000 | 1.000 | 1.000 |

Defects called BAIK: 0/50; defects called SPAM: 0/50. Quality-guard rejections: 0; full-image → pipeline label changes: 14.

Pipeline p50/p95: 140.0/215.8 ms. Full-image p50/p95: 42.0/66.8 ms.

10-bin top-label ECE: 0.0435. Brier score: 0.1354780882005968; NLL: 0.23617845022622086. Probability metrics use 150/150 rows and exclude guard-only responses. ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.

### Review policy diagnostics

Thresholds are exploratory and were not tuned or accepted on these results.

| Confidence threshold | Automatic decisions | Errors among automatic decisions |
|---:|---:|---:|
| 0.5 | 150 | 12 |
| 0.65 | 148 | 11 |
| 0.8 | 144 | 10 |
| 0.9 | 135 | 7 |
| 0.95 | 114 | 3 |

### Paired robustness subset

| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |
|---|---:|---:|---:|---:|
| blur_radius_2 | 15 | 0.933 | 0.267 | 11 |
| brightness_0.5 | 15 | 0.933 | 0.467 | 7 |
| white_border_96px | 15 | 0.933 | 0.867 | 3 |

### Example errors (up to five)

- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00058.jpg`: BAIK → RUSAK, confidence 0.9824.
- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00045.jpg`: BAIK → RUSAK, confidence 0.9727.
- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00065.jpg`: BAIK → RUSAK, confidence 0.9666.
- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00040.jpg`: BAIK → RUSAK, confidence 0.9438.
- `datasets_per_category/penyebrangan/val/BAIK/BAIK_00067.jpg`: BAIK → RUSAK, confidence 0.9210.

## Not established by this run

- Independent local-photo accuracy, geographic generalization, label agreement, or injury-risk reduction.
- PyTorch/ONNX parity: PyTorch was not part of this worker evaluation.
- Live HTTP throughput, queue integration, or field conditions.
- Causal attribution of pipeline changes to quality guard versus crops; full-image and complete-pipeline comparison is a combined ablation.
- Final acceptance against population thresholds. A fresh grouped and independently labeled test set is needed.

Raw predictions, class supports, reliability bins, overlap examples, hashes, and metrics are in `cv_results.json`.
