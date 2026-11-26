# 2. CV evaluation method and results

## 2.1 Evidence and scope

The main V2 run started **26 November 2026, 22:33:05 UTC** and finished at
22:39:19 UTC. The reference artifacts are:

- [V2 summary](../docs/evaluation_results%20V2/CV_RESULTS.md) and
  [raw predictions/metrics](../docs/evaluation_results%20V2/cv_results.json).
- [Old/new comparison](../docs/evaluation_results%20V2/COMPARISON.md),
  [metric CSV](../docs/evaluation_results%20V2/metrics_comparison.csv),
  [class metrics](../docs/evaluation_results%20V2/per_class_metrics.csv), and
  [confusion counts](../docs/evaluation_results%20V2/confusion_matrices.csv).
- [Image manifest](../docs/evaluation_results%20V2/dataset_manifest.csv),
  [error catalog](../docs/evaluation_results%20V2/error_catalog.csv), and
  [checkpoint/ONNX parity](../docs/evaluation_results%20V2/parity_results.json).

This is a re-evaluation of development validation data, **not an independent
held-out test**. Dataset-directory labels were retained, without independent
re-annotation. Results describe the saved artifacts and protocol, not a live
production endpoint or a field study.

## 2.2 Evaluation protocol

[`scripts/evaluate_cv.py`](../scripts/evaluate_cv.py) performs these steps:

1. Load each actual ONNX specialist on CPU; verify output class order.
2. Disable mock mode and legacy fallback. Inject one real specialist into the
   actual service module for the category under evaluation.
3. Shuffle sorted validation paths with **seed 42**, sampling **50 per class**
   from BAIK/RUSAK/SPAM: **150 per category, 750 total**.
4. Warm the model, then evaluate both **full-image-only inference** and the
   **complete `/analyze` function** including decoding, quality guard, and crops.
5. Normalize parking `TERHALANG` to `RUSAK` only for three-class metrics.
6. Record predictions, confidence, probability vectors where present, latency,
   model/source hashes, and decoded train/validation overlap.
7. Apply paired robustness transforms to five images per class (15 per category).

Worker environment: **Python 3.11.18**, **ONNX Runtime 1.32.0**, intra-op CPU
threads 2 and inter-op threads 1. Docker worker networking is disabled and the
repository is mounted read-only. Timings are warm, serial, in-process timings;
they exclude HTTP/network transport, queue delay, model startup, and load testing.

The old/new comparison uses saved baseline predictions on matching image paths
and labels. It did not rerun old models. All five model hashes changed while
the inference-source hash was identical between the two saved runs. The old
run lacks image-content hashes, so historical pixel identity is not proven.
The later deployment/readiness edits change the source file hash; the saved
evaluation is not a measurement of those newer source bytes.

## 2.3 Metrics and interpretation

For each class, precision = TP/(TP+FP), recall = TP/(TP+FN), and
F1 = 2×precision×recall/(precision+recall). Accuracy is correct/N; macro-F1
averages the three class F1 scores; balanced accuracy averages class recalls.

Additional diagnostics:

- **Defect recall:** fraction of true RUSAK predicted RUSAK/TERHALANG.
- **Defect → good/spam:** missed defects, separated by wrong prediction.
- **Intact false alarm:** true BAIK predicted RUSAK.
- **Legitimate rejection:** BAIK or RUSAK predicted SPAM.
- **Spam acceptance:** SPAM predicted BAIK or RUSAK.
- **95% Wilson intervals:** uncertainty for accuracy and class recall, assuming
  independent observations; related images can make these optimistic.
- **ECE:** ten-bin top-confidence calibration diagnostic.
- **Brier/NLL:** probability quality, calculated only when probability vectors
  exist. Guard-only responses are excluded; ECE includes fixed guard confidence.
- **High-confidence errors:** incorrect predictions with confidence ≥0.90.
- **p50/p95 latency:** median and 95th-percentile measured inference time.

Paired changes use **5,000 seeded bootstrap replicates**, grouping exact decoded
pixel hashes within category. Exact two-sided McNemar tests are exploratory,
assume independent images, and have no multiple-comparison adjustment.

## 2.4 Main V2 results

All rows below use 150 images, balanced as 50 per class.

| Category | Full-image accuracy | Complete-pipeline accuracy | Pipeline 95% Wilson interval | Macro-F1 | Defect recall |
|---|---:|---:|---:|---:|---:|
| trotoar | 96.00% | 88.67% | 82.6–92.8% | 0.8854 | 94.00% |
| drainase | 91.33% | 88.00% | 81.8–92.3% | 0.8788 | 84.00% |
| guiding_block | 98.67% | 88.67% | 82.6–92.8% | 0.8847 | 100.00% |
| parkir_liar | 98.00% | 98.00% | 94.3–99.3% | 0.9800 | 100.00% |
| penyebrangan | 96.67% | 96.00% | 91.5–98.2% | 0.9599 | 98.00% |

The complete pipeline gets **689/750 = 91.87%** correct. Equal class/category
sampling makes this a diagnostic pooled accuracy, not natural-use accuracy.
Historical training best top-1 is a separate checkpoint-selection measurement;
it must not be substituted for complete-pipeline accuracy.

### Old versus new complete pipeline

| Category | Old | V2 | Change, percentage points | Paired 95% interval, pp | Fixed / regressed |
|---|---:|---:|---:|---:|---:|
| trotoar | 78.00% | 88.67% | +10.67 | +5.33 to +16.00 | 18 / 2 |
| drainase | 85.33% | 88.00% | +2.67 | −2.00 to +8.00 | 9 / 5 |
| guiding_block | 91.33% | 88.67% | −2.67 | −6.67 to +0.67 | 2 / 6 |
| parkir_liar | 97.33% | 98.00% | +0.67 | −2.00 to +4.00 | 3 / 2 |
| penyebrangan | 92.00% | 96.00% | +4.00 | −1.33 to +9.33 | 12 / 6 |

Pooled: **666/750 (88.80%) → 689/750 (91.87%)**, a gain of **3.07 pp**.
Improvement is not uniform: guiding-block accuracy falls, and most per-category
paired intervals include zero.

## 2.5 Confusion matrices and consequential errors

Rows are truth, columns are predictions in order **BAIK / RUSAK / SPAM**.
Each truth row contains 50 images; parking RUSAK means TERHALANG externally.

| Category | True BAIK → [B,R,S] | True RUSAK → [B,R,S] | True SPAM → [B,R,S] |
|---|---|---|---|
| trotoar | [37, 3, 10] | [0, 47, 3] | [0, 1, 49] |
| drainase | [41, 7, 2] | [5, 42, 3] | [0, 1, 49] |
| guiding_block | [34, 16, 0] | [0, 50, 0] | [0, 1, 49] |
| parkir_liar | [47, 3, 0] | [0, 50, 0] | [0, 0, 50] |
| penyebrangan | [45, 5, 0] | [1, 49, 0] | [0, 0, 50] |

Key observations:

- Guiding-block defect recall is 100%, but **16/50 intact images become false
  defect alarms (32%)**. High recall does not imply balanced performance.
- Drainage misses **5/50 defects as BAIK** and **3/50 as SPAM**.
- Sidewalk rejects **10/50 intact images** and **3/50 defects** as SPAM.
- Crossing has **one defect predicted BAIK** and five intact false alarms.
- Parking identifies every sampled obstruction, but three intact scenes are
  classified as obstructed.

These matter because Laravel can automatically validate defects or reject
BAIK/SPAM at confidence ≥0.80; confidence alone does not eliminate mistakes.

## 2.6 Full-image versus complete-pipeline behavior

| Category | Full → pipeline label changes | Quality-guard rejections | Pipeline p50 / p95, ms | Errors ≥0.90 |
|---|---:|---:|---:|---:|
| trotoar | 19 | 13 | 423.2 / 448.9 | 4 |
| drainase | 9 | 5 | 263.4 / 315.5 | 6 |
| guiding_block | 15 | 0 | 266.8 / 297.9 | 17 |
| parkir_liar | 0 | 0 | 92.6 / 107.9 | 0 |
| penyebrangan | 9 | 0 | 266.0 / 321.9 | 5 |

The complete pipeline is less accurate than full-image inference on this
sample for four categories. It intentionally boosts localized defect evidence,
but can override a correct intact classification. This comparison combines
quality filtering and crop overrides; separate quality-only and crop-only
ablations were not run, so it does not isolate each component's effect.

Guiding-block ECE is 0.1079, drainage 0.0980, sidewalk 0.1030, parking 0.0281,
and crossing 0.0270. A high model confidence is not a calibrated guarantee.

## 2.7 Robustness and export verification

Each robustness condition uses the same **15 images/category**, not new
independent test images. Transformations: brightness ×0.5, Gaussian blur radius
2, and a 96-pixel white border.

| Category | Brightness ×0.5 accuracy | Blur accuracy | White-border accuracy |
|---|---:|---:|---:|
| trotoar | 66.67% | 40.00% | 80.00% |
| drainase | 73.33% | 60.00% | 86.67% |
| guiding_block | 80.00% | 66.67% | 100.00% |
| parkir_liar | 100.00% | 60.00% | 66.67% |
| penyebrangan | 60.00% | 33.33% | 80.00% |

Blur and low illumination remain weaknesses. The small transformed subsets
are diagnostic, not population estimates.

Checkpoint/ONNX parity used **nine images/category**, with both Ultralytics
validation transforms and service-resize tensors: **45 images, 90 paired
forwards**. Identical-input top-label agreement was **100%**; maximum absolute
probability difference was **8.9406967×10⁻⁷**, within the diagnostic 10⁻⁴ tolerance.
This supports export fidelity on those inputs, not correctness of the labels.

## 2.8 Dataset-overlap audit

| Category | Original training images | Original validation images | Validation images exactly shared with training |
|---|---:|---:|---:|
| trotoar | 2,380 | 420 | 4 |
| drainase | 2,210 | 390 | 0 |
| guiding_block | 2,380 | 420 | 0 |
| parkir_liar | 1,824 | 321 | 0 |
| penyebrangan | 1,700 | 300 | 0 |

Hashes use decoded RGB pixels and dimensions, not filenames. This audit covers
the original category datasets; zero exact overlap does not establish absence
of fine-tuning overlap, transformed duplicates, source-family correlation, or
location leakage. The original validation data also contributed to model
development. The sampled non-overlap pipeline accuracy is unchanged in the
recorded run, but that does not create an independent test.

## 2.9 Supplied “Images from Google” collection

Run: **26 November 2026, 22:48:29–22:51:23 UTC**. Source evidence:
[collection report](../docs/evaluation_results%20V2/Test%20with%20Images%20from%20Google/TEST_WITH_IMAGES_FROM_GOOGLE.md),
[raw results](../docs/evaluation_results%20V2/Test%20with%20Images%20from%20Google/results.json),
and [visual review](../docs/evaluation_results%20V2/Test%20with%20Images%20from%20Google/image_review.html).

| Category | Entries | Matching folder label | Agreement | Full-image-only matches |
|---|---:|---:|---:|---:|
| guiding_block | 44 | 36 | 81.82% | 43 |
| drainase | 64 | 62 | 96.88% | 64 |
| parkir_liar | 46 | 45 | 97.83% | 45 |
| trotoar | 114 | 108 | 94.74% | 114 |
| **Total** | **268** | **251** | **93.66%** | **266** |

There were 17 mismatches, zero processing errors, five quality rejections,
and ten mismatches with confidence ≥0.90. The full-image and complete-pipeline
paths disagree on 15 entries.

Important interpretation: these 268 entries contain **202 distinct decoded
images**. All entries match known category-specific development sources;
**214 match actual fine-tuning training entries and 54 validation entries**.
No no-known-match subset exists. Consequently, 93.66% is folder-label agreement
on reused development data, not independent Google-image generalization.

Folder labels are the reference, not adjudicated ground truth. No SPAM-labeled
or crossing folders were supplied, so spam recall and crossing accuracy are
not measured. Individual Google source URLs were not supplied or verified.

## 2.10 Reproduction and remaining measurement gaps

From the repository root, write a fresh run to a new directory to preserve the
historical results:

```bash
docker build -t pedestriroute-cv:evaluation cv-service
python3 scripts/evaluate_cv.py --image pedestriroute-cv:evaluation --per-class 50 --seed 42 --output "Document revision/evaluation-rerun"
```

This requires local validation datasets and historical training `results.csv`
files; they are not all included in an ordinary lightweight deployment clone.
It evaluates current artifacts/source, so changed hashes or dependencies can
produce different results. Comparison/parity tooling is in
[`scripts/compare_cv_evaluations.py`](../scripts/compare_cv_evaluations.py);
the supplied-folder evaluator is
[`scripts/evaluate_google_images.py`](../scripts/evaluate_google_images.py).

Not established by the recorded runs: independent local-photo accuracy,
geographic generalization, natural class prevalence, live HTTP throughput,
queue-to-status accuracy, or pedestrian injury-risk reduction. A fresh,
independently labeled, source/location-grouped test set is needed for those
claims. The evaluation plan's illustrative recall/F1 thresholds are not
accepted product requirements or a basis for a population-level pass/fail.
