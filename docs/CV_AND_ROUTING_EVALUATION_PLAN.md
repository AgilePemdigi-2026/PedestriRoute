# PedestriRoute: Computer Vision and Pedestrian Routing Evaluation

**Document type:** Current-method review and proposed evaluation protocol  
**Prepared:** 25 November 2026  
**Scope:** CV classification, deployed CV decision pipeline, safest routing, fastest routing, and their end-to-end interaction  
**Execution status:** This document is a plan. No new training, inference, route requests, benchmarks, field trials, or tests were executed to prepare it.

## 1. Executive summary

PedestriRoute requires two related but distinct forms of evaluation:

1. **CV evaluation:** Determine whether infrastructure photographs are classified correctly, whether confidence scores are trustworthy, and whether preprocessing and application integration change the result.
2. **Routing evaluation:** Determine whether returned paths are valid pedestrian routes, whether fastest chooses an efficient path, and whether safest reduces avoidable exposure to known hazards without misleading users about residual exposure.

The repository contains training-time validation and an exploratory CV weakness report. The latter tested 11 initial photographs across three categories and recorded 8 correct predictions. This is useful evidence of failure modes, but it does not establish representative model accuracy.

Routing evaluation must account for a documentation discrepancy: the F7 document describes WSM-weighted PostGIS A*, while the current service uses OSRM pedestrian candidates and validated-report avoidance. The methods below evaluate the inspected implementation and separately identify what would be needed to validate broader claims about real-world safety and speed.

All sample sizes and acceptance thresholds proposed here are planning recommendations, not existing requirements or measured results.

---

## 2. Evidence and implementation baseline

### 2.1 CV implementation

The specialist training script uses five YOLOv8s-cls classifiers at 384 × 384 pixels:

| Facility category | Internal category | Classes presented to users |
|---|---|---|
| Sidewalk | `trotoar` | BAIK, RUSAK, SPAM |
| Drainage | `drainase` | BAIK, RUSAK, SPAM |
| Tactile paving | `guiding_block` | BAIK, RUSAK, SPAM |
| Illegal parking | `parkir_liar` | BAIK, TERHALANG, SPAM |
| Pedestrian crossing | `penyebrangan` | BAIK, RUSAK, SPAM |

The illegal-parking specialist internally uses the RUSAK class, translated to TERHALANG by the service. This is image classification, not bounding-box detection or segmentation. Classification metrics are therefore appropriate; detection mAP and mask IoU are not primary metrics for these models.

The deployed `/analyze` pipeline includes:

1. Model availability and mock-mode handling.
2. Image decoding and RGB conversion.
3. A quality filter that returns SPAM for mean grayscale brightness below 25 or gradient-magnitude variance below 15.
4. Category resolution and specialist selection.
5. Full-image resizing and inference.
6. Category-dependent sub-image inference, except for illegal parking.
7. Defect override rules using thresholds of 0.70 or 0.88, depending on the full-image BAIK probability.
8. Final label, confidence, category, and probability formatting.

The quality filter's blur statistic is gradient-magnitude variance, despite the variable being named `var_laplacian`. Evaluation should describe the actual calculation.

**Sources:** `scripts/train_per_category_models.py`; `cv-service/app/main.py`.

### 2.2 Existing CV evaluation

#### Training-time validation

The specialist dataset script creates `train` and `val` directories, with a nominal 85%/15% split per class. Ultralytics training performs validation and the training script exports `best.pt` to ONNX. The inspected specialist script does not define a separate independent test evaluation or report final held-out test metrics.

Potential threats to validity include:

- Flipped images and crops can be created before random splitting, allowing related images into both partitions.
- Some source datasets' original train, validation, and test images are pooled before repartitioning.
- Certain whole-image class assignments rely on source annotations or collection membership rather than a new independent image-level review.
- Some crossing defect examples are synthesized by lowering contrast; performance on these examples may not transfer to real worn crossings.
- Random image splitting does not establish independence across nearby locations, photo bursts, source collections, or image derivatives.

These are risks suggested by the preparation code, not proof that every dataset partition contains leakage or incorrect labels.

**Source:** `scripts/prepare_per_category_datasets.py`.

#### Manual weakness evaluation

The report dated 8 November 2026 describes direct requests to the real CV service:

| Item | Documented method or finding |
|---|---|
| Input | Internet-sourced photographs with labels assigned by the tester |
| Initial sample | 11 photographs |
| Categories | Drainage, sidewalk, crossing |
| Initial outcome | 8 correct and 3 incorrect, approximately 73% |
| Follow-up | One image was cropped and another replaced; both follow-ups were correct |
| Untested specialists | Tactile paving and illegal parking |
| Main weaknesses | Confident sidewalk errors, sensitivity to image presentation, low confidence on some valid examples |
| Integration finding | UI reporting did not invoke CV during that historical evaluation |

The follow-up 10/11 result is not equivalent to accuracy on the original fixed test set. Both original failures must remain in the original denominator, with transformed or replacement inputs reported separately. Confidence values of 0.997 and 0.999 on incorrect sidewalk predictions also show why confidence cannot simply be interpreted as verified correctness.

**Source:** `docs/cv_model_weakness_report.md`.

### 2.3 Routing implementation

The inspected route service:

- Requests routes from OSRM using a pedestrian endpoint.
- Filters candidate routes for validity.
- Selects fastest by duration, then distance.
- For safest, assesses intersection with circles around active validated reports; the default radius is 25 m.
- Searches additional waypoint-based detours under a configurable request budget.
- Ranks non-clear candidates by blocking-report count, assessed exposure distance, duration, then distance.
- Permits contiguous unavoidable exposure at departure or arrival; later re-entry is blocking.
- Returns the best available candidate if clear-route search is unsuccessful, with outcome metadata/warnings to be evaluated.
- Rejects unavailable connected routes rather than inventing a straight-line pedestrian path.

The `endpoint_exposure_m` search field is populated from all assessed hazard-section lengths in the current implementation. For reporting, use independently computed terms such as total exposure, endpoint exposure, and avoidable exposure rather than inferring semantics from this internal field name.

Active routing reports are validated reports with no repair status or with `SEDANG_DIPERBAIKI`. Pending, rejected, or other repair-status records do not satisfy this query.

**Sources:** `backend/app/Services/RouteRecommendationService.php`; `backend/app/Services/RouteHazardGeometry.php`; `backend/app/Services/RouteReportRepository.php`.

### 2.4 Documentation and test alignment

`docs/F7_REKOMENDASI_RUTE.md` describes WSM-weighted PostGIS A* routing and straight-line fallback. The current service uses OSRM/report avoidance. The BDD F7 feature still expects a direct fallback outside the network, whereas the PHPUnit route test expects a 503 response without a polyline.

Before future evaluation execution, record the chosen specification and label legacy expectations explicitly. Do not interpret a passing historical test as proof that the current algorithm satisfies a different specification.

---

## 3. CV evaluation objectives and experimental design

### 3.1 Questions to answer

1. How accurately does each specialist distinguish intact, defective/obstructed, and irrelevant images?
2. How often does it miss a real defect, especially by calling it BAIK?
3. How often does it falsely flag an intact facility?
4. How often are legitimate infrastructure photographs rejected as SPAM?
5. Does sub-tiling improve defect recall at an acceptable false-positive cost?
6. Does service preprocessing preserve the behavior of the trained checkpoint?
7. Are confidence scores calibrated, and can review thresholds reduce errors?
8. How well do results generalize to local photographs and difficult conditions?
9. Does the application invoke and store real CV results correctly?

### 3.2 Evaluate four levels separately

| Level | Evaluation target | Purpose |
|---|---|---|
| A | Trained checkpoint using its standard evaluation transform | Measure classifier behavior without service heuristics |
| B | Exported ONNX model using matched inputs | Detect export/runtime differences |
| C | Actual `/analyze` pipeline | Measure the deployed decision, including quality rejection and crops |
| D | Application report flow | Measure queue processing, persistence, validation, and downstream effects |

Run comparisons on matched photographs. If A succeeds and C fails, investigate preprocessing, crop selection, quality filtering, category routing, or output interpretation rather than attributing the failure to model weights alone.

### 3.3 Dataset construction and independence

Create three disjoint partitions:

- **Training:** model fitting and training-only augmentation.
- **Validation:** model selection, crop/quality rule tuning, and confidence/review threshold selection.
- **Final test:** one locked evaluation after those decisions are frozen.

Split by original-image family and, where possible, location, capture session, and source collection. All derivatives of an original photograph must remain in the same partition. Deduplicate exact copies and review near-duplicates before splitting. Hold out local capture locations to test geographic generalization.

Maintain a manifest containing image ID, original-family ID, source/license or collection reference, facility category, label, location group, capture conditions, annotators, adjudication status, and split. Do not use label-bearing filenames as the mechanism for model evaluation.

### 3.4 Sampling strategy

Use two test cohorts:

1. **Balanced diagnostic cohort:** approximately 50 independent original images per class per category, giving an initial target of 750 images across five three-class specialists. This enables per-class comparisons, but remains a pilot for rare subgroups.
2. **Natural-use cohort:** a separately sampled set reflecting actual submission frequencies, including irrelevant photographs and incorrect category selections. This estimates operational behavior under realistic class prevalence.

Do not mix these cohorts into one headline accuracy without explicit weighting. A balanced set helps compare classes but does not reproduce real-world precision when class prevalence differs.

Record conditions such as daylight, night, wet surfaces, glare, shadows, oblique angles, partial obstruction, distance, low resolution, compression, screenshots, borders, and grayscale. Include real worn crossings, intact dark paving, drain covers, vehicles beside a clear sidewalk, and small defects in otherwise intact scenes.

Do not require every condition × category × class combination if it is unrealistic. Publish actual subgroup denominators and identify sparse groups.

### 3.5 Ground-truth labeling

Define a short annotation rubric before labeling:

- **BAIK:** the relevant visible facility is intact or unobstructed under the category definition.
- **RUSAK/TERHALANG:** a relevant defect or obstruction is visible.
- **SPAM:** unrelated content under the agreed policy.
- **Unassessable:** the photograph does not support a reliable condition judgment; retain this annotation separately rather than forcing an arbitrary condition label.

Use at least two independent annotators who cannot see model predictions. Adjudicate disagreements with a third reviewer or a documented consensus process. Record ambiguous mixed-condition scenes and severity separately from the main class.

Report raw agreement and Cohen's kappa for two raters, alongside the label distribution. Agreement is a label-quality check, not a model metric. Decide in advance how unassessable images contribute to service rejection metrics and which are excluded from pure condition-classification metrics; publish all exclusions.

### 3.6 Freeze the evaluation configuration

Record model/checkpoint hashes, ONNX hashes, category-to-class ordering, dependency versions, image transforms, crop rules, quality thresholds, CPU/hardware, and service configuration.

Verify real model availability for every tested category. In the current code, `mock_mode: false` alone is insufficient evidence of real inference: if no models load, `/analyze` can fall back to filename-based mock behavior. Also, a missing specialist can use a legacy model if available. Record which execution path each category uses, and classify unavailable/legacy/mock cases separately.

---

## 4. CV metrics and analysis methods

### 4.1 Confusion matrices and class metrics

Produce a 3 × 3 confusion matrix for each category with rows as ground truth and columns as predictions. Include counts and row-normalized percentages. Keep illegal parking's TERHALANG class semantically distinct in reports.

For each class, use one-versus-rest definitions:

```text
Precision = TP / (TP + FP)
Recall    = TP / (TP + FN)
F1        = 2 × Precision × Recall / (Precision + Recall)
Accuracy  = correct predictions / evaluated predictions
Macro-F1  = mean F1 across classes
Balanced accuracy = mean recall across classes
```

State how zero denominators are handled. Report class support, per-category macro-F1, balanced accuracy, and overall accuracy. If presenting an aggregate across categories, show the aggregation rule and retain individual category results so a strong specialist cannot hide a weak one.

### 4.2 Safety-relevant CV error rates

Report these explicitly rather than relying only on overall accuracy:

| Metric | Numerator | Denominator |
|---|---|---|
| Defect missed as good | Defective images predicted BAIK | All defective images |
| Defect rejected as spam | Defective images predicted SPAM | All defective images |
| Intact false alarm | Intact images predicted RUSAK/TERHALANG | All intact images |
| Legitimate-image rejection | Assessable infrastructure images predicted SPAM | All assessable infrastructure images |
| Spam acceptance | True spam images predicted BAIK or defective | All true spam images |

Defect recall treats both BAIK and SPAM predictions as failures to identify a defect. Separate breakdowns identify whether the main cause is incorrect condition classification or rejection.

### 4.3 Confidence calibration and manual review

Use reliability diagrams to compare predicted confidence with observed correctness. Report sample counts per bin and an expected calibration error with its binning definition.

For probability-bearing model outputs, also consider multiclass Brier score and negative log likelihood. Clip probabilities only for numerical stability and document the clipping constant. Do not treat the quality guard's fixed 0.85/0.90 scores as a complete classifier probability distribution; analyze those rejection outputs separately.

Evaluate a selective-review policy:

1. Choose review thresholds on validation data only.
2. Mark uncertain results for manual review rather than relabeling them automatically as BAIK or SPAM.
3. Measure automatic-decision coverage, error rate among automatic decisions, defect misses, and review workload.
4. Freeze the threshold and evaluate it once on the final test cohort.

Plot coverage versus error rate. A high-confidence error remains an error and may evade a simple confidence threshold; threshold selection is not a substitute for correcting systematic model failures.

### 4.4 Export and preprocessing parity

Use identical tensors to compare checkpoint and ONNX outputs, then separately compare their full preprocessing pipelines. Measure top-label agreement and maximum/mean absolute probability differences using tolerances agreed in advance.

Inspect resize, aspect-ratio handling, crop, RGB channel order, normalization, tensor layout, class order, and whether outputs already contain probabilities. Disagreement with identical inputs suggests export/runtime behavior; disagreement only after preprocessing suggests a transform mismatch.

### 4.5 Ablation experiments

Use a paired validation experiment with the same original photographs:

| Variant | Purpose |
|---|---|
| Full image only | Establish classifier baseline |
| Full image plus quality filter | Quantify rejection benefits and false rejections |
| Full image plus sub-tiling | Measure localized-defect recall and false alarms |
| Complete deployed pipeline | Measure combined operational behavior |

Compare the same image families, not unrelated samples. Report defect-recall changes, intact false-alarm changes, SPAM behavior, and latency. Use validation for selecting a variant; keep the final test set locked.

### 4.6 Robustness experiments

Maintain a clean original and controlled variants for brightness, blur, compression, borders, and perspective changes. Change one factor at a time when diagnosing causes. Include naturally captured difficult photographs because artificial transforms do not fully reproduce field conditions.

Report paired prediction changes and performance degradation relative to the original. Do not replace failed originals with cleaned images in the primary accuracy calculation. Cluster uncertainty estimates by original-image family so many transforms of one photograph do not appear to be independent evidence.

### 4.7 Statistical uncertainty

Report 95% confidence intervals for major proportions, using Wilson intervals for independent-image rates. Use a cluster bootstrap over location/capture groups or original families when examples are correlated. For macro-F1, use an appropriate grouped bootstrap and disclose the resampling procedure.

As a rough planning illustration, estimating a proportion near 90% within ±5 percentage points at 95% confidence requires about 139 independent examples under a normal-approximation calculation. Fifty images in a subgroup provide much less precision. Final sample-size planning must use the actual target metric, expected rate, desired precision, and grouping structure.

For paired model versions, report the paired improvement with uncertainty; McNemar's test can compare correctness on independent paired images, while grouped resampling is more appropriate when images are correlated. Practical effect size matters more than a p-value alone.

### 4.8 Latency and integration evaluation

Measure model forward-pass time, full `/analyze` latency, and end-to-end report-processing latency separately. Include warm-up policy, cold starts, image sizes, concurrency, failures, and p50/p95 values. Do not remove timeout cases from operational reporting.

For the application flow, verify image/category transmission, real-model execution, queue retries, duplicate job handling, result persistence, failure states, and the validation decision. A functioning endpoint does not prove that the UI reporting workflow reaches it.

---

## 5. CV evaluation procedure and deliverables

### 5.1 Proposed sequence

1. Finalize the label rubric, relevant error priorities, and dataset manifest.
2. Audit duplicates and construct independent grouped partitions.
3. Label independently, adjudicate, and freeze test labels.
4. Record model artifacts and execution paths for all five specialists.
5. Perform parity, ablation, and threshold selection on validation data.
6. Evaluate the frozen configuration on the balanced and natural-use test cohorts.
7. Analyze subgroup errors, calibration, rejection, latency, and integration.
8. Produce a versioned report with limitations and unresolved cases.

### 5.2 Proposed acceptance framework

Choose numerical targets before looking at final test results. An illustrative pilot target is defect recall of at least 90% and macro-F1 of at least 0.85 for every specialist. These are suggestions, not repository requirements, and should be accompanied by confidence intervals and explicit tolerances for intact false alarms and legitimate-image rejection.

Do not pass a specialist solely because the five-model aggregate meets a target. Do not claim a population-level threshold is established unless the sample size and confidence bound support it. Review workload and high-confidence failures should be reported even if average accuracy is acceptable.

### 5.3 CV results package

- Dataset manifest and split/duplicate audit.
- Label rubric, agreement, adjudication, and exclusion counts.
- Five confusion matrices and per-class metric tables with uncertainty.
- Balanced-cohort and natural-use-cohort results reported separately.
- Calibration, coverage/review, and robustness plots.
- Export/preprocessing parity and ablation results.
- Latency and end-to-end integration findings.
- Error catalog with original input, truth, prediction, confidence, execution path, and suspected cause.

---

## 6. Routing evaluation objectives and benchmark design

### 6.1 Separate three claims

1. **Implementation correctness:** The service applies its declared selection and report-intersection rules correctly.
2. **Search effectiveness:** The service finds good alternatives rather than missing them because of candidate generation or search-budget limits.
3. **Real-world usefulness:** The selected route is walkable, faster or safer in practice, and accurately represented to the user.

A route can satisfy the first claim while failing the others. A circular report buffer is an approximation of a hazard, not direct evidence that every nearby path is unsafe.

### 6.2 Benchmark layers

| Layer | Suggested scope | Primary purpose |
|---|---|---|
| Controlled fixtures | Small networks and fixed route candidates | Exact expected behavior and edge cases |
| Frozen local-network benchmark | 30–50 origin–destination pairs | Paired routing quality and search effectiveness |
| Field study | 10–15 representative pairs, expanded if needed | Walkability, actual travel time, and observed hazards |
| Operational load benchmark | Fixed workload with varied report density/concurrency | Latency, failure handling, and request-budget effects |

Sample origins/destinations across route length, connectivity, pedestrian crossings, residential detours, report density, and map completeness. Separate tuning cases from final benchmark cases. Include repeated requests for stability, but do not count repetitions of one pair as independent route examples.

### 6.3 Freeze inputs

Record map extract/version, OSRM build and walking profile, routing-service version, active-report snapshot, radius, request budget, residential-detour settings, snapping settings, origin/destination, and environment.

Run both preferences against the same frozen snapshot. Live report changes or remote map updates can otherwise be mistaken for preference effects. Where remote services cannot be frozen, record responses/timestamps and identify this limitation.

### 6.4 Independent reference answers

Use three references with distinct meanings:

- **Candidate-selection reference:** Independently rank the exact valid candidates returned by OSRM. This verifies fastest selection without claiming global optimality.
- **Network reference:** On small fixtures or a frozen inspectable pedestrian graph, use an independent shortest-path implementation or exhaustive enumeration to compute the reference route.
- **Field reference:** Human-audited walkability, observed hazards, and measured traversal times.

The evaluator should not call the production assessment code as its only oracle. Use independently calculated geometry, known analytical examples, or a separately implemented GIS method.

If the OSRM graph/profile is not available for independent optimization, label the real-world baseline as best available reference rather than proven globally fastest or safest.

---

## 7. Routing correctness scenarios

| Scenario | Evaluation expectation |
|---|---|
| No active reports | Both modes return a valid efficient pedestrian route; compare actual objectives, not assumed identical polylines |
| Hazardous shortcut and clear detour | Fastest selects minimum-duration candidate; safest finds the discoverable clear detour |
| Shortest-distance route is slower | Fastest follows duration, using distance only as a tie-breaker |
| Equal duration candidates | Distance tie-breaker behaves consistently |
| Multiple separated hazards | Detours are checked against all active reports |
| Clustered or overlapping hazards | Exposure length is not double-counted; report-count ranking is documented |
| Clear alternative outside initial corridor | Candidate search is evaluated for whether it discovers that route |
| All candidates exposed | Lowest-ranked available candidate and residual-risk disclosure are checked |
| Start/destination inside buffer | Only contiguous departure/arrival exposure is excepted |
| Leave and re-enter endpoint buffer | Re-entry remains blocking |
| Tangency and just-inside/outside points | Intersection follows an explicitly documented tolerance |
| Hazard between sparse polyline vertices | Edge intersection detects it even without a nearby vertex |
| Pending/rejected/repaired reports | Only statuses meeting the active-report query influence avoidance |
| Duplicate reports at one location | Measure the effect of report-count ranking; do not confuse duplicates with distinct physical hazards |
| Parallel sidewalk across a street | Evaluate false blocking caused by circular buffers |
| Disconnected graph or excessive snap | Explicit unavailable outcome; no fabricated direct route |
| Engine timeout during detour search | Returned candidate, interruption metadata, and warnings are accurate |
| Search-budget exhaustion | Identify unfinished search; do not imply that no clear route exists globally |
| Identical or near-identical endpoints | Document and check intended response behavior |

Controlled cases should establish exact expected results. Real-network cases should distinguish algorithm failure from absent map links or inaccurate report coordinates.

---

## 8. Fastest-route evaluation methods

### 8.1 Duration optimality

For a nonzero reference duration:

```text
Duration gap (%) = 100 × (returned duration − reference duration) / reference duration
```

Report this separately for:

- Best duration among the same supplied candidates.
- Independently computed network optimum, where available.
- Best field-measured reference, with its measurement uncertainty.

Candidate-selection gap should be zero within numerical tolerance. A zero gap does not prove global optimality if the candidate set omitted a better path. Exclude zero-duration cases from ratio calculations and report them separately.

### 8.2 Route validity and distance

Verify that the polyline follows connected pedestrian-permitted links, starts/ends within permitted snapping bounds, does not cross barriers or disconnected geometry, and has consistent distance/segment accounting.

Compare independently measured polyline length with reported distance using a predeclared tolerance that accounts for geometry simplification. Investigate large differences. Do not require fastest to have the shortest distance if travel-time weights differ.

### 8.3 ETA accuracy

For field traversals, record route ID, predicted seconds, actual seconds, participant, time of day, direction, weather, crossing delays, and deviations.

Report mean absolute error in seconds, signed bias, median absolute percentage error for nontrivial durations, and error by route-length group. Analyze crossing delays separately where possible. If timing represents constant-speed walking rather than door-to-door travel, state that explicitly.

Repeated, counterbalanced walks help control participant speed and time-of-day effects. Do not expose participants to a hazardous route solely to time it; use observation or a feasible alternative reference where appropriate.

---

## 9. Safest-route evaluation methods

### 9.1 Define known-report exposure

For each route, independently intersect every polyline edge with the frozen active-report buffers. Merge overlapping intervals before summing distance.

Compute:

- Number of intersected report records.
- Number of distinct audited physical hazards, when known.
- Total exposed distance in metres.
- Contiguous unavoidable departure/arrival exposure.
- Avoidable exposure: exposure outside the permitted endpoint portions.
- Minimum distance to each relevant report.

Use both report-record and physical-hazard counts when duplicates exist. Report count is relevant to the implementation, but distinct physical hazards are more meaningful for field interpretation.

### 9.2 Clear-route discovery and search quality

```text
Clear-route discovery rate =
  cases returning a clear valid route /
  cases with an independently verified clear valid route
```

Also report the rate restricted to cases where the clear route lies within the declared benchmark search domain. Compare with a reference search that explores more candidates or an independently constrained graph.

For failures, record whether a clear route was absent from OSRM alternatives, missed by waypoint generation, rejected by snapping rules, or not reached before budget exhaustion. Repeat on validation cases across several budgets to show the quality/latency trade-off, then freeze the chosen budget for final testing.

The current search can return after finding a clear route in an early batch. It should not be described as globally minimizing duration among every possible clear route without evidence.

### 9.3 Exposure improvement and detour cost

For each matched pair:

```text
Exposure reduction (m) = fastest exposed distance − safest exposed distance
Time overhead (%) = 100 × (safest duration − fastest duration) / fastest duration
Distance overhead (%) = 100 × (safest distance − fastest distance) / fastest distance
```

When fastest exposure is positive, additionally report percentage exposure reduction. If it is zero, percentage reduction is undefined; report absolute exposure and whether safest introduced exposure instead. Use unrounded durations/distances where available and disclose rounding effects.

Present paired results and distributions rather than averages alone. Include median and worst-case detour, cases with no improvement, and cases where safest is worse. The current search has no explicit distance cap, so exceptionally long detours are an important usability outcome.

### 9.4 Radius sensitivity and coordinate error

On validation cases, compare a predeclared set of radii around the current 25 m default, for example 10, 25, and 40 m. Inspect:

- Missed nearby hazards at small radii.
- False blocking of parallel or opposite-side paths at large radii.
- Additional detour time and search requests.
- Sensitivity to realistic location uncertainty.

Use recorded location accuracy or a justified perturbation model. Repeat perturbations as sensitivity analysis, not as independent route samples. Choose the radius on validation data and keep the final evaluation fixed.

### 9.5 Independent field safety assessment

Known-report avoidance does not capture hazards that were never reported. Have assessors, blinded to route preference where feasible, audit both paths using a predefined rubric:

- Continuous usable walking space.
- Surface defects and uncovered drains.
- Vehicle obstruction and forced roadway walking.
- Crossing availability and crossing difficulty.
- Tactile-paving continuity where applicable.
- Barriers, inaccessible links, and missing map connections.

Record hazard locations, type, severity, and exposed length. Analyze safety and accessibility outcomes separately when different users face different barriers. If a composite score is used, define weights before evaluation and publish component scores; otherwise an arbitrary weighted total can hide a serious defect.

Measure assessor agreement and adjudicate disagreements. Describe results as improved assessed conditions or reduced observed exposure, not demonstrated accident reduction; the proposed study is not designed to estimate injury outcomes.

### 9.6 Warning and metadata correctness

Check the response against independent geometry and actual execution outcomes:

- Are all residual hazard intersections represented?
- Are repair warnings accurate?
- Are endpoint exceptions restricted to the correct portions?
- Are interruption and safer-fallback indicators truthful?
- Are clear routes incorrectly warned about?
- Are unavailable routes returned as errors rather than drawable fake paths?

Calculate warning precision and recall against the defined warning conditions. Check that the map renders hazard sections consistently with the returned coordinates and distance intervals.

---

## 10. Routing performance, statistics, and acceptance

### 10.1 Performance experiment

Use a fixed workload stratified by route length, active-report density, and preference. Run cold and warm conditions separately. Vary concurrency and search budget on tuning workloads; use the final fixed configuration for acceptance.

Measure:

- API p50/p95 latency and total completion rate.
- OSRM request count and downstream latency.
- Search-budget exhaustion and interruption frequency.
- Timeout, unavailable-network, and other error rates.
- Database report-loading and geometry-assessment cost where observable.
- Response size and resource use where relevant.

Report latency for successful requests together with timeout/error rates and counts. Otherwise a system that times out on difficult cases can misleadingly appear fast. Repeated live benchmarking should use an appropriate dedicated/local routing environment rather than uncontrolled load against a shared public endpoint.

### 10.2 Statistical analysis

Treat an origin–destination pair as the primary paired unit. Use paired differences for time, exposure, and distance. Bootstrap pairs for uncertainty; if many pairs share a corridor, resample corridor groups. For repeated field traversals, account for participant and route effects or summarize within pair before aggregation.

Report median, interquartile range, confidence intervals, sample counts, and failures. Keep unavailable cases in completion-rate denominators. Publish results by route length, report density, endpoint exception, and map-quality subgroup.

Thirty to fifty pairs are a practical pilot, not a universal sample-size justification. Use pilot variance and a meaningful target effect to plan a larger study if precise generalization claims are required.

### 10.3 Proposed acceptance framework

| Area | Proposed decision rule |
|---|---|
| Controlled correctness | All specified deterministic cases pass within agreed tolerances |
| Fastest candidate ranking | Zero duration gap against the same valid candidate set, allowing numerical tolerance |
| Network optimality | Report independent-reference gap; set a target only where a valid reference exists |
| Safest controlled avoidance | No avoidable intersections when a clear route is available within the controlled search fixture |
| Search effectiveness | Publish clear-route discovery with uncertainty and reasons for misses |
| Geometry | No fabricated links; report-intersection and exposed-length calculations match independent references |
| Residual-risk disclosure | Required warnings and exceptions are accurate in controlled cases |
| Field utility | Show observed exposure improvement and detour cost together, including adverse cases |
| Responsiveness | Meet an agreed p95 target on specified hardware and workload, with an error-rate target |

Do not select an arbitrary universal acceptable detour percentage without stakeholder input. Present the safety/time trade-off and agree a usability threshold before final acceptance.

---

## 11. End-to-end CV-to-routing evaluation

### 11.1 Trace the complete decision chain

For fixed example reports, evaluate:

```text
Photograph and selected category
  → CV output or failure
  → stored report state
  → validation decision
  → active-report eligibility
  → routing avoidance/exposure assessment
  → rendered route and warnings
  → repair completion
  → updated active-report eligibility and route
```

Do not assume the CV output directly changes routing eligibility: the current routing repository selects validated active reports. Record the actual validation workflow and any human intervention.

### 11.2 Scenario matrix

| Case | End-to-end question |
|---|---|
| True defect correctly classified | Does it reach the intended validation state and affect routing after validation? |
| Defect classified BAIK | Can review correct the missed condition before downstream routing is evaluated? |
| Intact image classified defective | Does validation prevent a false active hazard? |
| Legitimate image rejected as SPAM | Is the report recoverable and is the rejection represented accurately? |
| Wrong user-selected category | Is model selection/output handled consistently and can review correct it? |
| Model unavailable or legacy fallback | Is the execution path visible in evaluation evidence? |
| Queue retry or duplicate execution | Are result and downstream effects consistent? |
| Report rejected or repair completed | Does it cease affecting active-report avoidance? |

### 11.3 Quantify propagated errors

Use a fixed ground-truth report snapshot, then compare routing outcomes under:

1. Correct adjudicated report states.
2. Observed CV-assisted workflow states.
3. Controlled omissions and false active hazards.

Measure route changes, missed real-hazard exposure, unnecessary detours, warning errors, and manual-review workload. This distinguishes classifier error from workflow safeguards and routing sensitivity. Synthetic fault injection should be labeled as an experiment, not reported as observed production frequency.

---

## 12. Reporting templates and reproducibility

### 12.1 Per-image CV record

```text
image_id, original_family_id, category, ground_truth, condition_tags,
model_hash, execution_path, predicted_label, probabilities,
quality_guard_result, confidence, latency_ms, correct, error_type
```

Fields not exposed by the current API may require future instrumentation; mark them unavailable rather than inferring them from confidence alone.

### 12.2 Per-route record

```text
case_id, corridor_group, origin, destination, map_version, report_snapshot,
preference, returned_route_id, distance_m, duration_s,
total_exposure_m, endpoint_exposure_m, avoidable_exposure_m,
intersected_report_count, physical_hazard_count,
reference_duration_s, clear_reference_exists, search_outcome,
latency_ms, osrm_request_count, warnings, completion_status
```

### 12.3 Final report structure

1. Frozen implementation, data, and objectives.
2. Dataset/benchmark composition and exclusions.
3. Existing evidence distinguished from newly measured results.
4. CV per-category metrics, calibration, robustness, and integration.
5. Routing correctness, reference gaps, clear-route discovery, and field outcomes.
6. Paired safety/time trade-off and operational latency.
7. End-to-end error propagation.
8. Failure catalog, uncertainty, and limits of generalization.
9. Acceptance decisions against predeclared criteria.

### 12.4 Recommended execution order

1. Align routing expectations with the current implementation.
2. Freeze label definitions, route objectives, and acceptance criteria.
3. Build independent CV partitions and routing fixtures.
4. Validate real-model execution and export/preprocessing parity.
5. Tune CV thresholds and routing parameters on validation cases only.
6. Execute locked offline CV and routing evaluations.
7. Execute field, integration, and operational performance studies.
8. Publish results and failure analysis without replacing failed cases or changing thresholds retrospectively.

## 13. Repository references

- `docs/cv_model_weakness_report.md` — historical exploratory CV findings.
- `scripts/prepare_per_category_datasets.py` — specialist dataset construction and splitting.
- `scripts/train_per_category_models.py` — specialist training and ONNX export.
- `cv-service/app/main.py` — deployed preprocessing, inference, override, and fallback behavior.
- `docs/F7_REKOMENDASI_RUTE.md` — earlier routing algorithm documentation.
- `backend/app/Services/RouteRecommendationService.php` — current route selection and detour search.
- `backend/app/Services/RouteHazardGeometry.php` — report-circle intersection and endpoint exceptions.
- `backend/app/Services/RouteReportRepository.php` — active-report eligibility.
- `backend/tests/Feature/Route/RouteRecommendationTest.php` — existing API and route-selection expectations.
- `bdd/features/f7_rute_pejalan_kaki.feature` — existing BDD scenarios, including legacy fallback expectations.
