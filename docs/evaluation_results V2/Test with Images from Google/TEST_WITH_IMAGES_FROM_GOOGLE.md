# Test with Images from Google

Dataset: `/home/rog-theseus/Downloads/dataset_evaluasi`

Run: 2026-11-26T22:48:29.079250+00:00 to 2026-11-26T22:51:23.609685+00:00

## Folder-label agreement

**251/268 images match their folder labels (93.66%).** Mismatches: 17; processing errors: 0.

Mapping: normal → BAIK; rusak → RUSAK; parkir liar/Terhalang → TERHALANG; manhole → drainase.

| Category | Images | Matches | Mismatches | Agreement | Full-image-only matches |
|---|---:|---:|---:|---:|---:|
| guiding_block | 44 | 36 | 8 | 81.82% | 43 |
| drainase | 64 | 62 | 2 | 96.88% | 64 |
| parkir_liar | 46 | 45 | 1 | 97.83% | 45 |
| trotoar | 114 | 108 | 6 | 94.74% | 114 |

### By source folder

| Folder | Matches / evaluated | Agreement |
|---|---:|---:|
| guiding block/normal | 26/33 | 78.79% |
| guiding block/rusak | 10/11 | 90.91% |
| manhole/normal | 50/52 | 96.15% |
| manhole/rusak | 12/12 | 100.00% |
| parkir liar/Terhalang | 22/23 | 95.65% |
| parkir liar/normal | 23/23 | 100.00% |
| trotoar/normal | 84/89 | 94.38% |
| trotoar/rusak | 24/25 | 96.00% |

## Interpretation and duplicate audit

- 268 evaluated entries contain 202 distinct decoded-pixel images across categories.
- 268 entries match known category-specific training, validation, or fine-tuning source images. The audit checks decoded pixels and a reconstructed 384×384/JPEG-quality-92 preparation transform. All matching paths are in results.json; source overlap alone does not prove an image was used for fitting.
- **Actual fine-tuning split matches: 214 training entries and 54 validation entries. This collection is reused model-development data, not an independent unseen-image test.**
- No-known-exact-match subset: unavailable (no qualifying images). This subset may still contain near-duplicates; independent test status is not established.
- Quality-guard rejections: 5; high-confidence mismatches (≥0.9): 10.
- Full-image-only predictions match 266/268 folder labels; complete-pipeline predictions match 251/268. The two paths disagree on 15 entries. The pipeline includes quality rejection and category-specific defect crop overrides.
- The supplied folder categorization is the reference, not independently adjudicated ground truth. The test checks condition labels for the user-selected facility category; it does not independently identify the facility category.
- The requested Google-image title describes this supplied collection. Individual Google source URLs were not supplied or verified; WhatsApp-named images are included.
- No SPAM-labeled or pedestrian-crossing folders were supplied. SPAM predictions are recorded as mismatches; spam recall and crossing accuracy cannot be measured.
- Current local ONNX artifacts were tested in an isolated CPU worker using the actual service function. Mock and legacy fallback were disabled. Latency excludes HTTP transport; folder labels do not influence predictions.

## Mismatches

| Image | Expected | Predicted | Confidence |
|---|---|---|---:|
| guiding block/normal/WhatsApp Image 2026-11-26 at 9.28.47 PM (2).jpeg | BAIK | RUSAK | 0.9609 |
| guiding block/normal/WhatsApp Image 2026-11-26 at 9.28.47 PM.jpeg | BAIK | RUSAK | 0.9012 |
| guiding block/normal/WhatsApp Image 2026-11-26 at 9.28.49 PM.jpeg | BAIK | RUSAK | 0.8844 |
| guiding block/normal/WhatsApp Image 2026-11-26 at 9.28.50 PM (1).jpeg | BAIK | RUSAK | 0.9470 |
| guiding block/normal/WhatsApp Image 2026-11-26 at 9.28.51 PM (1).jpeg | BAIK | RUSAK | 0.8847 |
| guiding block/normal/WhatsApp Image 2026-11-26 at 9.28.51 PM (2).jpeg | BAIK | RUSAK | 0.9994 |
| guiding block/normal/imgi_136_fungsi-guiding-block-untuk-tunanetra-1200x900.webp | BAIK | RUSAK | 0.9991 |
| guiding block/rusak/imgi_158_15532-kondisi-guiding-block-xcomatrxsvrpg.jpg | RUSAK | BAIK | 0.9999 |
| manhole/normal/WhatsApp Image 2026-11-26 at 9.29.29 PM.jpeg | BAIK | SPAM | 0.8500 |
| manhole/normal/WhatsApp Image 2026-11-26 at 9.40.28 PM.jpeg | BAIK | SPAM | 0.8500 |
| parkir liar/Terhalang/imgi_147_WhatsApp-Image-2025-06-04-at-15.23.jpg | TERHALANG | BAIK | 0.9598 |
| trotoar/normal/WhatsApp Image 2026-11-26 at 9.29.29 PM.jpeg | BAIK | SPAM | 0.8500 |
| trotoar/normal/WhatsApp Image 2026-11-26 at 9.40.27 PM (1).jpeg | BAIK | RUSAK | 0.9848 |
| trotoar/normal/WhatsApp Image 2026-11-26 at 9.40.27 PM.jpeg | BAIK | RUSAK | 0.9008 |
| trotoar/normal/WhatsApp Image 2026-11-26 at 9.40.28 PM.jpeg | BAIK | SPAM | 0.8500 |
| trotoar/normal/imgi_128_20211101bro-trotoar2_1635759367.jpg | BAIK | RUSAK | 0.9289 |
| trotoar/rusak/imgi_158_Sxw3uintbRP0XqqfGBAzhApwHC22MfYVUjaY6exR.jpg | RUSAK | SPAM | 0.8500 |

## Models

CV service source SHA-256: `5bfe163e79d472a1ef3de126a2a12c2fc5f330b0d0d4c55edef6b470d94306a0`

- guiding_block: `e575aa9dcd6c2e9530f6a36f43568667dfb6878b9eda9007163dea3784f8513f`
- drainase: `cd26ee49bb7b0bb6116d82da4050712a7105bd84dfdf1284859bd9d4e5d1ef18`
- parkir_liar: `ec95ffc35fb1223305c3b650d0cf191536ec82bb2cb6188ac936b257f0934a2b`
- trotoar: `1e736d35fc3b397e7016050a5fc77fa197c7ab704cf8c0bd7e06e2630dbfa99b`

See per_image_results.csv for all predictions, results.json for raw metrics and audit evidence, and image_review.html for a visual match/mismatch gallery.
