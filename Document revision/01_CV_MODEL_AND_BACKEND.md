# 1. CV model and backend

## 1.1 Purpose and architecture

PedestriRoute evaluates a submitted photo in the context of the **facility
category selected by the reporter**. It uses image classification, not object
detection or segmentation: there are no bounding boxes or pixel masks, and the
system does not independently establish which facility category is present.

The training script uses **YOLOv8m-cls**, initialized from `yolov8m-cls.pt`.
Five specialist ONNX artifacts are served by FastAPI with ONNX Runtime's
`CPUExecutionProvider`:

| Specialist | File under `cv-service/models/` | Meaning of defect |
|---|---|---|
| Sidewalk | `trotoar_classifier.onnx` | Damaged sidewalk surface |
| Drainage | `drainase_classifier.onnx` | Damaged drainage/manhole infrastructure |
| Tactile paving | `guiding_block_classifier.onnx` | Damaged guiding blocks |
| Illegal parking | `parkir_liar_classifier.onnx` | Obstructed pedestrian space |
| Pedestrian crossing | `penyebrangan_classifier.onnx` | Damaged/faded crossing infrastructure |

Every model has class order **`BAIK`, `RUSAK`, `SPAM`**, input shape
`[1, 3, 384, 384]`, and three output scores. Illegal parking's internal `RUSAK`
is exposed as **`TERHALANG`**. Each artifact is approximately 63 MB.

Category aliases such as `Masalah Drainase`, `Guiding Block Rusak`, and
`Parkir Liar` are normalized by `resolve_category_slug()`. An absent or
unrecognized category resolves to `trotoar`; callers should supply the correct
category rather than treating this fallback as category recognition.

Source: [`cv-service/app/main.py`](../cv-service/app/main.py),
[`scripts/train_per_category_models.py`](../scripts/train_per_category_models.py).

## 1.2 Base training and domain fine-tuning

### Base specialist training

`scripts/train_per_category_models.py` trains categories separately using
`datasets_per_category/<category>/{train,val}/{BAIK,RUSAK,SPAM}`.

| Setting | Script value |
|---|---|
| Initial checkpoint | `yolov8m-cls.pt` |
| Epochs / image size / batch | 6 / 384 / 16 |
| Optimizer / initial learning rate | AdamW / 0.0008 |
| Freeze / patience / workers | 4 / 3 / 6 |
| Augmentation | degrees 10; scale 0.25; horizontal flip 0.5; perspective 0.001 |
| HSV augmentation | hue 0.015; saturation 0.35; value 0.35 |
| Random erasing | 0.2 |
| Deployment artifact | Best checkpoint exported to simplified ONNX |

These are script settings, not evidence that every run completed every epoch.
CUDA is selected when usable; otherwise base training uses CPU.

### Additional domain fine-tuning

[`scripts/finetune_per_category_models.py`](../scripts/finetune_per_category_models.py)
starts from each existing base `best.pt` and maps additional folders as follows:

| Source folder under `dataset_fine tune/` | Specialist | Folder-label mapping |
|---|---|---|
| `trotoar` | `trotoar` | normal → BAIK; rusak → RUSAK |
| `manhole` | `drainase` | normal → BAIK; rusak → RUSAK |
| `guiding block` | `guiding_block` | normal → BAIK; rusak → RUSAK |
| `parkir liar` | `parkir_liar` | normal → BAIK; Terhalang → RUSAK |

The fine-tuning mapping covers **four categories**; crossing is not included.
The evaluation's observation that all five ONNX hashes changed does not by
itself mean all five went through this domain fine-tuning pipeline.

Dataset construction uses seed 42, deduplicates decoded source RGB pixels
within each source folder, resizes to 384×384, and saves JPEG at quality 92.
Approximately 20% of each new condition folder is reserved for validation,
with explicit small-folder minimums. New validation images appear in both the
combined `val` and diagnostic `val_ft` directories; these are the same images,
not two independent evaluation cohorts.

Original training images are retained up to 300 per class, together with
original validation data and SPAM coverage. New training images are repeated
approximately toward 200 samples per condition, with repetition capped at 15.
Repeated files increase training weight, not unique-image count.

Fine-tuning uses five epochs, learning rate **0.0003**, batch 16, image size
384, freeze 4, patience 3, and the same augmentation settings above. The script
evaluates base and fine-tuned checkpoints, exports the best fine-tuned model,
and checks ONNX class order and output shape. Combined validation is used in
model development/checkpoint selection, so it is not a locked final test set.

## 1.3 Photo-to-prediction pipeline

```text
Uploaded photo + selected category
  → decode with Pillow and convert to RGB
  → dark/low-texture quality guard
  → select category specialist
  → full-image inference
  → category-specific crop inference and defect override
  → top class, confidence, probabilities
  → Laravel threshold-based report decision
```

### Quality guard

Before neural inference, the service converts the image to grayscale:

- Mean brightness below **25** returns `SPAM`, confidence **0.90**.
- Otherwise it computes grayscale gradients, gradient magnitude, and its
  variance. Variance below **15** returns `SPAM`, confidence **0.85**.

The latter is a gradient-magnitude variance heuristic; despite a local
variable name, it is not a Laplacian-variance calculation. Guard responses
return before specialist inference and do not include a probability vector.

### Model preprocessing

Each full image or crop is resized directly to 384×384 using bilinear
resampling, converted to float32 in `[0,1]`, transposed from HWC to CHW, and
batched. Direct square resize can alter aspect ratio; no letterboxing or
ImageNet mean/standard-deviation normalization is applied in this function.
Output scores are kept if they sum approximately to one (tolerance 0.01),
otherwise softmax is applied.

### Spatial crop rules

For images at least 180 pixels in both dimensions:

| Category | Additional views after the full image | Maximum forwards |
|---|---|---:|
| trotoar | bottom half, lower-left, lower-right, lower-center | 5 |
| drainase | bottom half, lower-center | 3 |
| guiding_block | bottom half, lower-center | 3 |
| penyebrangan | bottom half, lower-center | 3 |
| parkir_liar | none; retain full-scene vehicle/sidewalk relationship | 1 |

The bottom view begins at 45% of image height. Corner crops use half width
and half height; lower-center uses horizontal 25–75% and the bottom half.

The full-image probability vector is initially the winner. A crop replaces it
only if its `RUSAK` probability is higher than the current best and reaches:

- **0.88**, if the full image has `P(BAIK) ≥ 0.85`;
- **0.70**, otherwise.

The returned class is the argmax of the winning vector. This is a
defect-probability override, not averaging or voting across views. The chosen
confidence is rounded to four decimals. For parking, defect label/probability
is also represented as `TERHALANG`/`terhalang`.

## 1.4 API and startup behavior

`POST /analyze` accepts multipart `file` and optional `category`. A typical
specialist response is:

```json
{
  "label": "RUSAK",
  "confidence": 0.94,
  "detected_category": "Trotoar Rusak",
  "probabilities": {"baik": 0.04, "rusak": 0.94, "spam": 0.02}
}
```

`detected_category` reflects the caller's category context for a defect; it
does not establish independent category detection. Invalid image decoding
returns HTTP 400 in the production inference path.

Startup loads specialist files from the directory containing `CV_MODEL_PATH`.
The default path `/app/models/pedestriroute_classifier.onnx` names an optional
legacy seven-class model; that file can be absent when specialists are used.
Legacy inference, if available for a missing specialist, uses 224×224 inputs.

`GET /health` returns `model_ready`, `specialists_loaded`, `mock_mode`,
`model_path`, and `model_load_errors`. Current readiness requires at least one
specialist, a legacy session, or explicit mock mode; it does **not** guarantee
that all five specialists are available. For this deployment, verify all five
names and an empty error list.

The existing `/analyze` implementation uses filename-based mock predictions
when `CV_MOCK=true` **or when no sessions are loaded**. Production health checks
reject the latter state when mock mode is false. Evaluations explicitly
disable mock mode and inject real specialist sessions.

## 1.5 Laravel backend and report lifecycle

[`CvService.php`](../backend/app/Services/CvService.php) reads the photo from
the public storage disk and sends multipart HTTP to the CV service, including
the reported category. The default HTTP timeout is **10 seconds**.

[`ProcessReportCv.php`](../backend/app/Jobs/ProcessReportCv.php) is a queued job
with one try and a 15-second job timeout. It acts only while the report is
`DIPROSES`, then uses a transaction and row lock to avoid applying a decision
twice. The source-default decision threshold is **0.80**:

| Outcome | Resulting report status |
|---|---|
| RUSAK or TERHALANG with confidence ≥ threshold | `TERVALIDASI` |
| BAIK or SPAM with confidence ≥ threshold | `DITOLAK` automatically |
| Confidence below threshold | `MENUNGGU_VALIDASI_ADMIN` |
| Unsupported category, missing photo, CV failure/timeout, job failure | Administrator fallback |

The job saves CV label/confidence, status-history entries, and notifications.
Automatic validation recalculates the linked segment's WSM feasibility score.
This is the connection between photo analysis and routing costs/report zones.

Configuration is in [`backend/config/services.php`](../backend/config/services.php):

- `CFG_CV_ENABLED` defaults to true.
- `CV_SERVICE_URL` defaults to `http://cv-service:8000`.
- `CFG_CV_THRESHOLD` defaults to 0.80.
- `CFG_CV_TIMEOUT_SECONDS` defaults to 10.
- `CFG_CV_CATEGORIES` defaults to Trotoar Rusak, Masalah Drainase, Guiding Block
  Rusak, and Parkir Liar. **Crossing is not in the default Laravel allowlist**,
  even though the Python service has a crossing specialist.

## 1.6 Git LFS and deployment

The ONNX files are Git LFS objects. A checkout may initially contain tiny text
pointers. The updated Docker build uses
[`resolve_lfs_models.py`](../cv-service/resolve_lfs_models.py) to request the
exact pointer OIDs from GitHub's LFS batch API, download the binary objects,
and verify size and SHA-256 before replacing the pointers. Materialized files
need no download. An unavailable object or integrity mismatch fails the build.

The Compose model bind mount has been removed so server checkout pointers
cannot hide models baked into the image. CV health checks allow 120 seconds
for startup and require `model_ready`. The deployed service should use
`CV_MOCK=false`. Detailed operational steps are in
[`cv-service/DEPLOYMENT.md`](../cv-service/DEPLOYMENT.md).
