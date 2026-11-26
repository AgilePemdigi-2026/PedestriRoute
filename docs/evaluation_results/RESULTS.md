# PedestriRoute evaluation: measured results

**Date:** 25 November 2026  
**Reference protocol:** [CV_AND_ROUTING_EVALUATION_PLAN.md](../CV_AND_ROUTING_EVALUATION_PLAN.md)  
**Status:** Local executable evaluation completed; independent test-data collection, live provider comparisons, field studies, and load testing remain outstanding.

## 1. What was implemented and run

### CV

- Evaluated all five local ONNX specialist models using CPU inference with two intra-op threads.
- Selected 50 validation images per class per category using seed 42: **750 original validation inputs**.
- Compared full-image inference with the actual current `/analyze` decision function, including quality rejection and category-specific crop rules.
- Evaluated three controlled transformations on 5 images per class per category: **225 additional transformed inputs**.
- Audited all existing specialist training/validation images for exact decoded-pixel overlap.
- Produced confusion matrices, class precision/recall/F1, macro-F1, accuracy intervals, calibration diagnostics, review-threshold diagnostics, inference latency, and example errors.

### Routing

- Ran the existing geometry, recommendation, and navigation suites: **15 tests and 159 assertions, all passed**.
- Added a controlled paired benchmark: **27 origin–destination/scenario/radius fixtures**, each evaluated as fastest and safest, for **54 route outcomes**.
- Covered no reports, validated reports, pending reports, rejected reports, repaired reports, active repairs, endpoint exposure, unavoidable exposure, and duration-versus-distance selection.
- Evaluated avoidance radii of 10, 25, and 40 metres.
- Used fixed HTTP responses and analytically defined exposure expectations; no external routing requests were needed.
- Used a new isolated PostGIS database for each routing run, then dropped it. Current source was mounted read-only and framework writes went to disposable tmpfs storage.

The complete plan includes activities that cannot be replaced by existing validation images and synthetic routes. This report distinguishes measured results from those outstanding activities.

## 2. CV headline results

| Category | Inputs | Full-image accuracy | Complete-pipeline accuracy | Pipeline macro-F1 | Defect recall |
|---|---:|---:|---:|---:|---:|
| Sidewalk / trotoar | 150 | 94.7% | **78.0%** | 0.764 | 92.0% |
| Drainage / drainase | 150 | 90.0% | **85.3%** | 0.852 | **78.0%** |
| Tactile paving / guiding_block | 150 | 99.3% | **91.3%** | 0.912 | 100.0% |
| Illegal parking / parkir_liar | 150 | 97.3% | **97.3%** | 0.973 | 96.0% |
| Crossing / penyebrangan | 150 | 96.0% | **92.0%** | 0.919 | 100.0% |

Across this equally sampled cohort, full-image inference correctly classified **716/750 images (95.47%)**, while the complete pipeline correctly classified **666/750 (88.80%)**. This aggregate is descriptive of the balanced sample, not an estimate of accuracy under real submission frequencies.

### 2.1 The pipeline is a material source of errors

On these same inputs, the complete pipeline reduced accuracy in four categories. Sidewalk accuracy fell by **16.7 percentage points**. Only **24/50 intact sidewalk images** remained classified as BAIK:

- 16 were classified as RUSAK.
- 10 were classified as SPAM.

The complete pipeline rejected 13 sidewalk samples through its quality guard, and changed the full-image predicted label on 28 sidewalk samples.

These comparisons show that service-level evaluation matters. They do not individually isolate the effect of every crop or filter; a future component-by-component ablation is needed before selecting a production change.

### 2.2 Drainage defect misses deserve attention

Of 50 drainage defect images:

- 39 were correctly classified RUSAK.
- 8 were classified BAIK.
- 3 were classified SPAM.

Thus, drainage defect recall was **78%** in this cohort. Its overall accuracy alone would obscure these 11 missed defects.

### 2.3 High confidence is not a correctness guarantee

Incorrect predictions with confidence at least 0.9 occurred in the following counts:

| Category | High-confidence errors |
|---|---:|
| Sidewalk | 12 |
| Drainage | 5 |
| Tactile paving | 13 |
| Illegal parking | 0 |
| Crossing | 7 |

All 13 tactile-paving errors had confidence of at least 0.9. A simple review threshold of 0.9 would therefore leave all of those errors among automatic decisions. Threshold diagnostics are exploratory; no new operational threshold was selected.

### 2.4 Exact dataset leakage was detected

The complete decoded-pixel audit found **four sidewalk validation images that exactly match training images**. Other categories had no exact decoded-pixel overlap in this audit. None of those four sidewalk validation images occurred in the selected 150-image evaluation sample.

Absence of exact matches does not rule out flipped images, alternative crops, photo bursts, shared locations, or other near-duplicates. A grouped, independently labeled test set is still needed.

### 2.5 Robustness observations

The transformations were paired with their own original subsets. Each category/transform has only 15 inputs and should be treated as a diagnostic rather than a stable population estimate.

- Sidewalk subset accuracy fell from **93.3% to 40.0%** under Gaussian blur with radius 2.
- Drainage subset accuracy fell from **93.3% to 46.7%** under the same blur.
- Darkening and white borders also changed predictions; the detailed report includes each paired denominator and label-flip count.

These results combine quality-guard and classifier behavior. Some blurred inputs become SPAM by design. A future assessment must define acceptable rejection behavior separately from infrastructure-condition classification.

### 2.6 Deployment observation

The running `pedestriroute_cv` container reported:

```json
{
  "status": "ok",
  "model_ready": false,
  "mock_mode": false,
  "model_path": "/app/models/pedestriroute_classifier.onnx"
}
```

The container's `/app` directory listed application files but no `models` directory. Its Docker health status was healthy even though `model_ready` was false. The live container response also differs from the current source's health schema, so current-source behavior should not be assumed identical to the deployed image.

The CV measurements in this report used the **local ONNX files mounted into a separate disposable worker**, with real model loading and class-order verification. They are not evidence that the running deployment currently serves those models. The live deployment was not modified during this evaluation.

## 3. Routing headline results

### 3.1 Existing suites

| Suite | Tests | Assertions | Outcome |
|---|---:|---:|---|
| Hazard geometry | 4 | 13 | Passed |
| Route recommendation | 7 | 75 | Passed |
| Navigation | 4 | 71 | Passed |
| **Total** | **15** | **159** | **Passed** |

These tests include sparse-edge intersections, overlapping buffers without double-counting, endpoint/re-entry handling, API access/validation, route selection, repair warnings, and explicit network-unavailable behavior.

### 3.2 Added paired benchmark

**52/54 route outcomes met all declared benchmark expectations.** All 27 fastest outcomes selected a minimum-duration supplied candidate, giving a maximum candidate-duration gap of **0.000%**.

The duration-first fixture deliberately provides a longer-distance route with a lower duration. Fastest correctly selects that route: the implementation optimizes supplied duration, not merely geometric distance.

For validated and active-repair fixtures with a supplied clear alternative, safest removed the hazard intersection. Analytic direct-route exposures were **20, 50, and 80 metres** at radii of 10, 25, and 40 m. The clear detour had zero exposure. In these deliberately constructed fixtures, the detour duration was 400 s rather than 200 s: a **100% time overhead fixed by the fixture inputs**, not a claim about actual local routes.

Pending, rejected, and completed-repair reports did not affect avoidance. Unavoidable-route fixtures retained exposure and the expected residual-risk warning.

### 3.3 Two endpoint expectations failed

At endpoint radii of 10 m and 40 m, safest selected the 400-second detour rather than the 200-second direct route, despite endpoint exposures being equivalent within the benchmark's 0.2 m tolerance.

The production-computed candidate exposure diagnostics were:

| Radius | Direct exposure (m) | Detour exposure (m) | Selected duration |
|---|---:|---:|---:|
| 10 m | 9.999993403578486 | 9.999993403025265 | 400 s |
| 40 m | 39.99997361274547 | 39.999973611924226 | 400 s |

The differences are below one billionth of a metre. The selector compares unrounded exposure before duration, so numerical noise can defeat the intended practical tie-break.

This is a failed **evaluation expectation**, not a claim that the code violates its literal floating-point ordering. The observed behavior is nevertheless undesirable for practical route selection. A future fix should define an exposure-equivalence tolerance before comparing duration and add regression coverage. No application routing logic was changed in this work.

### 3.4 What these routing results do not establish

- Global fastest or safest paths over a complete pedestrian network.
- Ability to discover a clear route through waypoint search: the new benchmark sets search budget to zero to isolate candidate selection.
- Live route API latency, throughput, OSRM availability, or quota behavior.
- Actual walking times, physically usable sidewalks, unreported hazards, or injury reduction.
- Representative results for 30–50 real Mulyorejo origin–destination pairs.

The fixtures are synthetic and related. A 52/54 outcome count is a controlled check count, not an estimated real-world success rate.

## 4. External API access for routing comparisons

**No external API access was needed for the measurements above.**

For an independent live route comparison, the next step needs:

1. A pedestrian-routing provider endpoint, such as an available GraphHopper/Valhalla service or another walking-route provider.
2. An API key if that provider requires one, supplied through environment configuration rather than committed files.
3. A walking profile and sufficient request quota for the agreed benchmark.
4. A fixed list of real origin–destination pairs and a frozen active-report snapshot.

A self-hosted comparison engine can avoid commercial credentials if the local map extract and walking profile are available. Comparing with another OSRM instance is useful for consistency but is not fully independent of OSRM's algorithm and map assumptions.

Provider geometry, travel-time estimates, snapping, and map versions should be recorded. Provider differences are not automatically defects in this application's route selector.

For **safest** comparisons, an external routing API alone is insufficient. Independently labeled hazard locations or field-audited paths are needed to determine whether a route is genuinely safer rather than merely farther from known reports.

## 5. Reproduction and artifacts

From the repository root:

```bash
python3 scripts/evaluate_cv.py --per-class 50
python3 scripts/evaluate_routing.py
```

Requirements: Docker, the existing `infra-cv-service` and `infra-app` images, and for routing the running `pedestriroute_db` container on `infra_default`. The routing runner uses the repository's development database credentials to create/drop uniquely named evaluation databases. It does not change production configuration or dependencies. The CV worker has no network access.

The CV command can use `--per-class 0` to evaluate every existing validation image. Re-running overwrites the corresponding result files; archive a run first if version-to-version comparisons are required.

| Artifact | Contents |
|---|---|
| [CV_RESULTS.md](CV_RESULTS.md) | Detailed CV metrics, matrices, diagnostics, and limitations |
| [cv_results.json](cv_results.json) | Per-image predictions, transforms, model/source hashes, overlap evidence, and machine-readable metrics |
| [ROUTING_RESULTS.md](ROUTING_RESULTS.md) | Routing suite evidence, paired outcomes, numerical edge case, and comparison requirements |
| [routing_results.json](routing_results.json) | Executed commands, outcomes, benchmark checks, timings, and raw exposure diagnostics |
| `scripts/evaluate_cv.py` | Reproducible CV evaluation runner and report generator |
| `scripts/evaluate_routing.py` | Isolated routing evaluation runner and report generator |
| `scripts/evaluate_routing_cases.php` | Paired synthetic route benchmark |

## 6. Recommended next actions based on evidence

1. Reconcile the deployed CV image/model packaging with the locally available models, then verify real inference through the application's reporting workflow.
2. Isolate quality filtering and crop overrides before changing them; prioritize sidewalk false alarms/rejections and drainage defect misses.
3. Remove exact leakage and create an independently labeled, location-grouped local test cohort.
4. Define a meaningful exposure tie tolerance for routing and evaluate the endpoint numerical edge case after any future fix.
5. Select a pedestrian-routing comparison endpoint and real origin–destination manifest, then execute paired live comparisons.
6. Audit a representative subset in the field and evaluate queue/validation/report-to-route propagation.

No final population-level acceptance claim is made from reused validation data or synthetic routing fixtures.

## 7. Verification of the evaluation tooling

- Independently recomputed confusion matrices, accuracy, and macro-F1 using scikit-learn for all ten category/variant metric sets; all matched the stored results.
- Verified that the paired benchmark's stored pass count equals the count of passing outcome records: 52/54.
- Python syntax checks, PHP syntax check, PHP formatting, and Git whitespace checks passed.
- The two failed routing expectations are retained as findings; a successful runner process exit does not mean every benchmark expectation passed.
