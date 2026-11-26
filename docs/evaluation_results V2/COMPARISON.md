# CV V2 evaluation and comparison

New run: 2026-11-26T22:33:05.116049+00:00 to 2026-11-26T22:39:19.762355+00:00

## Matched evaluation

750 existing validation images: 50 per class per category, seed 42. All image paths and labels match the old run; the CV service source SHA-256 is unchanged. All five ONNX model hashes changed. Only new models were executed; old predictions are read from the saved baseline. The old run has no image-content hashes, so historical pixel identity cannot be proven. Current image hashes are recorded in dataset_manifest.csv.

Real specialist ONNX sessions were loaded directly from the current local artifacts in an isolated Docker worker. The current service function was evaluated in-process, with mock mode disabled and no legacy fallback. The production container was not the inference target.

## Pipeline comparison

| Category | Old accuracy | New accuracy | Change (pp) | Paired 95% interval (pp) | Old → new macro-F1 | Old → new defect recall | Fixed / regressed |
|---|---:|---:|---:|---|---|---|---|
| trotoar | 78.00% | 88.67% | +10.67 | +5.33 to +16.00 | 0.7644 → 0.8854 | 92.00% → 94.00% | 18 / 2 |
| drainase | 85.33% | 88.00% | +2.67 | -2.00 to +8.00 | 0.8518 → 0.8788 | 78.00% → 84.00% | 9 / 5 |
| guiding_block | 91.33% | 88.67% | -2.67 | -6.67 to +0.67 | 0.9118 → 0.8847 | 100.00% → 100.00% | 2 / 6 |
| parkir_liar | 97.33% | 98.00% | +0.67 | -2.00 to +4.00 | 0.9733 → 0.9800 | 96.00% → 100.00% | 3 / 2 |
| penyebrangan | 92.00% | 96.00% | +4.00 | -1.33 to +9.33 | 0.9188 → 0.9599 | 100.00% → 98.00% | 12 / 6 |

Equal-size pooled diagnostic accuracy: **666/750 (88.80%) → 689/750 (91.87%)**, change +3.07 percentage points. This is not natural-use accuracy.

5000 seeded paired percentile bootstrap replicates grouped by decoded-pixel hash within category. Original-family/location groups unavailable; near-duplicate correlation is not addressed. Exact two-sided McNemar p-values assume independent images; exploratory, no multiplicity adjustment.

## Safety-related error rates

| Category | Defect → good old/new | Defect → spam old/new | Intact false alarm old/new | Legitimate rejection old/new | Spam accepted old/new |
|---|---|---|---|---|---|
| trotoar | 2.0% / 0.0% | 6.0% / 6.0% | 32.0% / 6.0% | 13.0% / 13.0% | 6.0% / 2.0% |
| drainase | 16.0% / 10.0% | 6.0% / 6.0% | 12.0% / 14.0% | 6.0% / 5.0% | 4.0% / 2.0% |
| guiding_block | 0.0% / 0.0% | 0.0% / 0.0% | 26.0% / 32.0% | 0.0% / 0.0% | 0.0% / 2.0% |
| parkir_liar | 4.0% / 0.0% | 0.0% / 0.0% | 4.0% / 6.0% | 0.0% / 0.0% | 0.0% / 0.0% |
| penyebrangan | 0.0% / 2.0% | 0.0% / 0.0% | 24.0% / 10.0% | 0.0% / 0.0% | 0.0% / 0.0% |

## Model-only versus complete pipeline

| Category | Full image old/new | Pipeline old/new | New full → pipeline label changes |
|---|---|---|---:|
| trotoar | 94.67% / 96.00% | 78.00% / 88.67% | 19 |
| drainase | 90.00% / 91.33% | 85.33% / 88.00% | 9 |
| guiding_block | 99.33% / 98.67% | 91.33% / 88.67% | 15 |
| parkir_liar | 97.33% / 98.00% | 97.33% / 98.00% | 0 |
| penyebrangan | 96.00% / 96.67% | 92.00% / 96.00% | 9 |

## Calibration and high-confidence errors

| Category | ECE old/new | Brier old/new | NLL old/new | Errors ≥0.9 old/new |
|---|---|---|---|---|
| trotoar | 0.1787 / 0.1030 | 0.2298 / 0.0639 | 0.4135 / 0.1344 | 12 / 4 |
| drainase | 0.0871 / 0.0980 | 0.1739 / 0.1585 | 0.3162 / 0.3483 | 5 / 6 |
| guiding_block | 0.0831 / 0.1079 | 0.1621 / 0.2102 | 0.5125 / 0.4618 | 13 / 17 |
| parkir_liar | 0.0385 / 0.0281 | 0.0413 / 0.0388 | 0.0698 / 0.0659 | 0 / 0 |
| penyebrangan | 0.0435 / 0.0270 | 0.1355 / 0.0789 | 0.2362 / 0.1788 | 7 / 5 |

## Matched robustness subset

Each transform uses the same 15 images per category (five per class) as the baseline; these are paired diagnostics, not additional independent images.

| Category | Transform | Old accuracy | New accuracy |
|---|---|---:|---:|
| trotoar | blur_radius_2 | 40.00% | 40.00% |
| trotoar | brightness_0.5 | 66.67% | 66.67% |
| trotoar | white_border_96px | 86.67% | 80.00% |
| drainase | blur_radius_2 | 46.67% | 60.00% |
| drainase | brightness_0.5 | 66.67% | 73.33% |
| drainase | white_border_96px | 66.67% | 86.67% |
| guiding_block | blur_radius_2 | 66.67% | 66.67% |
| guiding_block | brightness_0.5 | 86.67% | 80.00% |
| guiding_block | white_border_96px | 100.00% | 100.00% |
| parkir_liar | blur_radius_2 | 80.00% | 60.00% |
| parkir_liar | brightness_0.5 | 86.67% | 100.00% |
| parkir_liar | white_border_96px | 100.00% | 66.67% |
| penyebrangan | blur_radius_2 | 26.67% | 33.33% |
| penyebrangan | brightness_0.5 | 46.67% | 60.00% |
| penyebrangan | white_border_96px | 86.67% | 80.00% |

## Checkpoint/export parity

Checked nine matched images per category using both standard Ultralytics validation transforms and service full-image resize tensors (45 images, 90 paired forwards). Checkpoint exports match evaluated ONNX hashes. Identical-input top-label agreement: 100.00%; maximum absolute probability difference: 8.9406967e-07; all within diagnostic 1e-4 tolerance: True. Device: cuda. See parity_results.json.

## Interpretation and scope

- Existing validation data were used for checkpoint selection, including combined fine-tuning validation. These results are not an independent held-out test or proof of local-photo generalization.
- Dataset-directory labels were retained. Independent annotators, adjudication, location/family groups, natural-use cohorts and a newly locked final test set are unavailable for this run.
- Original base-training overlap audit is in CV_RESULTS.md. Fine-tuning adds other training images; the original audit does not establish absence of fine-tuning or near-duplicate overlap.
- TERHALANG is internally normalized to RUSAK only for illegal-parking metrics.
- The plan’s 90% defect recall and 0.85 macro-F1 are illustrative diagnostic targets, not agreed acceptance requirements. Confidence bounds and validation reuse preclude a population-level pass/fail claim.
- Calibration/review and robustness results are diagnostics; no thresholds or rules were tuned after evaluation. Brier/NLL exclude quality-guard responses; ECE includes their fixed confidence scores.
- CPU latency is warm, serial in-process inference, excluding HTTP/network transport and queue processing. Historical versus current runtime timing is descriptive, not a controlled speed benchmark.
- Only the full-image and complete-pipeline ablation was run. Separate quality-only/crop-only ablations, HTTP load and application integration are not measured. Routing was outside this evaluation.

## Files

- CV_RESULTS.md / cv_results.json: new per-category metrics, confusion counts, calibration, robustness and raw predictions.
- comparison.json / metrics_comparison.csv: paired statistics and old/new detailed metrics.
- confusion_matrices.csv / per_class_metrics.csv: confusion counts, row-normalized percentages and class metrics with recall intervals.
- dataset_manifest.csv / error_catalog.csv: current image hashes and all old/new error cases.
- parity_results.json: checkpoint/export hashes and identical-tensor results.
- comparison_plots.png: accuracy, reliability and review diagnostics.
