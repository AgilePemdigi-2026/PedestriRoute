# PedestriRoute — Document revision

Revision date: **27 November 2026**.

This folder describes the current CV artifacts, their recorded evaluation, and
the implemented walking-route backend. Implementation details were checked
against source; evaluation figures are from the saved 25–26 November runs,
not a new evaluation performed for this documentation.

## Documents

1. [CV model and backend](01_CV_MODEL_AND_BACKEND.md) — specialist architecture,
   training/fine-tuning, preprocessing, crop rules, API, report decisions, and
   Git LFS deployment.
2. [CV evaluation method and results](02_CV_EVALUATION_METHOD_AND_RESULTS.md) —
   reproducible protocol, metrics, old/new results, confusion matrices,
   runtime, robustness, export parity, and the supplied Google-image collection.
3. [Routing modes and technical backend](03_ROUTING_MODES_AND_BACKEND.md) —
   safest, balanced, and fastest; WSM/A*, engine fallback, report zones,
   configuration, API contract, and existing evaluation evidence.

## Main findings

- Five **YOLOv8m-cls-based ONNX specialists**, each receiving a 384×384 image,
  classify the condition of the facility category selected by the user.
- The saved V2 complete-pipeline evaluation scores **689/750 (91.87%)**, versus
  **666/750 (88.80%)** previously. This is balanced validation-sample accuracy,
  not an estimate of real-world deployment accuracy.
- The supplied Google-image collection scores **251/268 (93.66%)** folder-label
  agreement, but all entries overlap known development data. It is not an
  unseen-image test.
- **Safest** blocks avoidable active-report zones without a distance cap.
  **Balanced** trades WSM condition, report exposure, and distance within a cap.
  **Fastest** ignores safety penalties when selecting a pedestrian route.
- Default routing engine order is **PostGIS A* → Valhalla pedestrian → OSRM foot**.

## Reading conventions

Paths and links are relative to this folder. Defaults are source defaults;
environment variables and deployed configuration may override them. Current
executable behavior takes precedence over older README text and stale code
comments. In particular, some older material calls the specialists YOLOv8s,
describes only two route modes, caps safest detours, or says all BAIK/SPAM
reports go to administrators. Those descriptions do not match the current
implementation documented here.

The original evidence remains in
[`docs/evaluation_results V2`](../docs/evaluation_results%20V2/) and
[`docs/evaluation_results`](../docs/evaluation_results/).
