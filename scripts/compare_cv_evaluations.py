#!/usr/bin/env python3
"""Compare matched saved CV runs, optionally checking new checkpoint/ONNX parity."""

import argparse
import csv
import hashlib
import json
import math
import platform
import random
from collections import defaultdict
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parents[1]
CLASSES = ['BAIK', 'RUSAK', 'SPAM']


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def write_csv(path, rows):
    with path.open('w', newline='') as handle:
        writer = csv.DictWriter(handle, fieldnames=list(rows[0]))
        writer.writeheader()
        writer.writerows(rows)


def safety(matrix):
    good, defect, spam = map(sum, matrix)
    return {
        'defect_missed_as_good': matrix[1][0] / defect,
        'defect_rejected_as_spam': matrix[1][2] / defect,
        'intact_false_alarm': matrix[0][1] / good,
        'legitimate_rejection': (matrix[0][2] + matrix[1][2]) / (good + defect),
        'spam_acceptance': (matrix[2][0] + matrix[2][1]) / spam,
    }


def paired(old, new, seed=42):
    groups = defaultdict(list)
    fixed = regressed = 0
    for a, b in zip(old, new):
        ca = a['pipeline']['label'] == a['truth']
        cb = b['pipeline']['label'] == b['truth']
        fixed += not ca and cb
        regressed += ca and not cb
        groups[b['pixel_sha256']].append(int(cb) - int(ca))
    rng = random.Random(seed)
    units = list(groups.values())
    draws = []
    for _ in range(5000):
        sample = [units[rng.randrange(len(units))] for _ in units]
        draws.append(sum(map(sum, sample)) / sum(map(len, sample)))
    draws.sort()
    discordant = fixed + regressed
    p = min(1.0, 2 * sum(math.comb(discordant, k) for k in range(min(fixed, regressed) + 1)) / 2 ** discordant) if discordant else 1.0
    return dict(fixed=fixed, regressed=regressed, net_correct=fixed - regressed,
                accuracy_delta=(fixed - regressed) / len(new),
                bootstrap_95=[draws[125], draws[4874]], bootstrap_replicates=5000,
                decoded_pixel_groups=len(units), mcnemar_exact_p=p)


def parity(data, output):
    import numpy as np
    import onnxruntime as ort
    import torch
    import ultralytics
    from ultralytics import YOLO
    from ultralytics.data.augment import classify_transforms

    torch.set_num_threads(2)
    device = 'cuda' if torch.cuda.is_available() else 'cpu'
    records = []
    artifacts = {}
    for category, c in data['categories'].items():
        checkpoint = ROOT / f'runs/finetune/model_{category}/weights/best.pt'
        if not checkpoint.exists():
            checkpoint = ROOT / f'runs/per_category/model_{category}/weights/best.pt'
        onnx = ROOT / f'cv-service/models/{category}_classifier.onnx'
        # Establish that the checkpoint's own export is the evaluated artifact.
        assert sha(checkpoint.with_suffix('.onnx')) == c['model_sha256'] == sha(onnx)
        model = YOLO(str(checkpoint)).model.float().eval().to(device)
        options = ort.SessionOptions()
        options.intra_op_num_threads = 2
        options.inter_op_num_threads = 1
        session = ort.InferenceSession(str(onnx), sess_options=options, providers=['CPUExecutionProvider'])
        transform = classify_transforms(size=384)
        artifacts[category] = dict(checkpoint=str(checkpoint.relative_to(ROOT)),
                                   checkpoint_sha256=sha(checkpoint), onnx_sha256=sha(onnx))
        for label in CLASSES:
            for row in [r for r in c['rows'] if r['truth'] == label][:3]:
                with Image.open(ROOT / row['image']) as source:
                    image = source.convert('RGB')
                standard = transform(image).unsqueeze(0)
                service_tensor = np.asarray(image.resize((384, 384), Image.Resampling.BILINEAR), dtype=np.float32)
                service_tensor = np.transpose(service_tensor / 255.0, (2, 0, 1))[None]
                for mode, tensor in [('standard_transform', standard.numpy()), ('service_resize', service_tensor)]:
                    with torch.inference_mode():
                        prediction = model(torch.from_numpy(tensor.copy()).to(device))
                    if isinstance(prediction, (tuple, list)):
                        prediction = prediction[0]
                    pt = prediction.cpu().numpy()[0]
                    ox = session.run(None, {session.get_inputs()[0].name: tensor})[0][0]
                    records.append(dict(category=category, image=row['image'], transform=mode,
                                        pt_probabilities=pt.tolist(), onnx_probabilities=ox.tolist(),
                                        top_label_agrees=bool(pt.argmax() == ox.argmax()),
                                        max_abs_difference=float(np.abs(pt - ox).max())))
        del model, session
        if device == 'cuda':
            torch.cuda.empty_cache()
    return dict(device=device, torch=torch.__version__, ultralytics=ultralytics.__version__,
                onnxruntime=ort.__version__, probability_tolerance=1e-4,
                tolerance_status='Diagnostic tolerance declared before parity execution; not a product acceptance requirement.',
                images_per_category=9, artifacts=artifacts, rows=records,
                top_label_agreement=sum(r['top_label_agrees'] for r in records) / len(records),
                max_abs_difference=max(r['max_abs_difference'] for r in records),
                all_within_tolerance=all(r['max_abs_difference'] <= 1e-4 for r in records))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--baseline', type=Path, default=ROOT / 'docs/evaluation_results/cv_results.json')
    parser.add_argument('--output', type=Path, default=ROOT / 'docs/evaluation_results V2')
    parser.add_argument('--parity', action='store_true')
    args = parser.parse_args()
    old = json.loads(args.baseline.read_text())
    new = json.loads((args.output / 'cv_results.json').read_text())
    assert old['source_sha256'] == new['source_sha256'], 'CV pipeline source changed'
    assert old['seed'] == new['seed'] and old['requested_per_class'] == new['requested_per_class']
    manifest, errors, comparisons, metric_rows = [], [], {}, []
    confusion_rows, class_rows = [], []
    for category, c in new['categories'].items():
        baseline = old['categories'][category]
        assert [(r['image'], r['truth']) for r in baseline['rows']] == [(r['image'], r['truth']) for r in c['rows']]
        assert baseline['model_sha256'] != c['model_sha256'], f'{category} model unchanged'
        for row, previous in zip(c['rows'], baseline['rows']):
            path = ROOT / row['image']
            with Image.open(path) as image:
                rgb = image.convert('RGB')
                pixel_hash = hashlib.sha256(str(rgb.size).encode() + rgb.tobytes()).hexdigest()
            row['pixel_sha256'] = pixel_hash
            manifest.append(dict(image=row['image'], category=category, truth=row['truth'],
                                 file_sha256=sha(path), pixel_sha256=pixel_hash,
                                 split='existing_validation', exact_base_train_overlap=row['exact_train_overlap']))
            if row['pipeline']['label'] != row['truth'] or previous['pipeline']['label'] != previous['truth']:
                errors.append(dict(category=category, image=row['image'], truth=row['truth'],
                                   old_prediction=previous['pipeline']['label'], new_prediction=row['pipeline']['label'],
                                   old_confidence=previous['pipeline']['confidence'], new_confidence=row['pipeline']['confidence'],
                                   quality_guard=row['quality_guard'], execution_path='real_specialist_plus_service_rules',
                                   status='fixed' if row['pipeline']['label'] == row['truth'] else
                                   ('regressed' if previous['pipeline']['label'] == row['truth'] else 'still_wrong')))
        comparisons[category] = paired(baseline['rows'], c['rows'])
        for version, value in [('old', baseline), ('new', c)]:
            for level in ['full_image', 'pipeline']:
                m = value[level]
                metric_rows.append(dict(category=category, version=version, level=level, n=m['n'],
                                        accuracy=m['accuracy'], macro_f1=m['macro_f1'],
                                        balanced_accuracy=m['balanced_accuracy'], defect_recall=m['per_class']['RUSAK']['recall'],
                                        **safety(m['matrix']), ece=m['ece'], brier=m['brier'], nll=m['nll'],
                                        high_confidence_errors=m['high_confidence_errors'],
                                        latency_p50_ms=m['latency_p50_ms'], latency_p95_ms=m['latency_p95_ms']))
                for i, label in enumerate(CLASSES):
                    display = lambda name: 'TERHALANG' if category == 'parkir_liar' and name == 'RUSAK' else name
                    cm = m['per_class'][label]
                    class_rows.append(dict(category=category, version=version, level=level,
                                           label=display(label), support=cm['support'], precision=cm['precision'],
                                           recall=cm['recall'], f1=cm['f1'],
                                           recall_95_low=cm['recall_wilson'][0], recall_95_high=cm['recall_wilson'][1]))
                    for j, prediction in enumerate(CLASSES):
                        confusion_rows.append(dict(category=category, version=version, level=level,
                                                   truth=display(label), prediction=display(prediction),
                                                   count=m['matrix'][i][j],
                                                   row_percentage=100*m['matrix'][i][j]/cm['support']))
    write_csv(args.output / 'dataset_manifest.csv', manifest)
    write_csv(args.output / 'error_catalog.csv', errors)
    write_csv(args.output / 'metrics_comparison.csv', metric_rows)
    write_csv(args.output / 'confusion_matrices.csv', confusion_rows)
    write_csv(args.output / 'per_class_metrics.csv', class_rows)
    summary = dict(baseline_sha256=sha(args.baseline), new_results_sha256=sha(args.output / 'cv_results.json'),
                   source_unchanged=True, matched_images=len(manifest), categories=comparisons,
                   hardware=dict(platform=platform.platform(), processor=platform.processor()),
                   uncertainty='5000 seeded paired percentile bootstrap replicates grouped by decoded-pixel hash within category. '
                   'Original-family/location groups unavailable; near-duplicate correlation is not addressed. '
                   'Exact two-sided McNemar p-values assume independent images; exploratory, no multiplicity adjustment.')
    if args.parity:
        summary['parity'] = parity(new, args.output)
        (args.output / 'parity_results.json').write_text(json.dumps(summary['parity'], indent=2) + '\n')
    (args.output / 'comparison.json').write_text(json.dumps(summary, indent=2) + '\n')
    lines = ['# CV V2 evaluation and comparison', '',
             f"New run: {new['started_at']} to {new['finished_at']}", '',
             '## Matched evaluation', '',
             '750 existing validation images: 50 per class per category, seed 42. All image paths and labels match the old '
             'run; the CV service source SHA-256 is unchanged. All five ONNX model hashes changed. Only new models were '
             'executed; old predictions are read from the saved baseline. The old run has no image-content hashes, so '
             'historical pixel identity cannot be proven. Current image hashes are recorded in dataset_manifest.csv.', '',
             'Real specialist ONNX sessions were loaded directly from the current local artifacts in an isolated Docker '
             'worker. The current service function was evaluated in-process, with mock mode disabled and no legacy fallback. '
             'The production container was not the inference target.', '',
             '## Pipeline comparison', '',
             '| Category | Old accuracy | New accuracy | Change (pp) | Paired 95% interval (pp) | Old → new macro-F1 | Old → new defect recall | Fixed / regressed |',
             '|---|---:|---:|---:|---|---|---|---|']
    for category, c in new['categories'].items():
        a, b, d = old['categories'][category]['pipeline'], c['pipeline'], comparisons[category]
        lines.append(f"| {category} | {a['accuracy']:.2%} | {b['accuracy']:.2%} | {100*d['accuracy_delta']:+.2f} | "
                     f"{100*d['bootstrap_95'][0]:+.2f} to {100*d['bootstrap_95'][1]:+.2f} | {a['macro_f1']:.4f} → {b['macro_f1']:.4f} | "
                     f"{a['per_class']['RUSAK']['recall']:.2%} → {b['per_class']['RUSAK']['recall']:.2%} | {d['fixed']} / {d['regressed']} |")
    total_old = sum(round(c['pipeline']['accuracy'] * c['pipeline']['n']) for c in old['categories'].values())
    total_new = sum(round(c['pipeline']['accuracy'] * c['pipeline']['n']) for c in new['categories'].values())
    lines += ['', f'Equal-size pooled diagnostic accuracy: **{total_old}/750 ({total_old/750:.2%}) → '
              f'{total_new}/750 ({total_new/750:.2%})**, change {(total_new-total_old)/750*100:+.2f} percentage points. '
              'This is not natural-use accuracy.', '', summary['uncertainty'], '',
              '## Safety-related error rates', '',
              '| Category | Defect → good old/new | Defect → spam old/new | Intact false alarm old/new | Legitimate rejection old/new | Spam accepted old/new |',
              '|---|---|---|---|---|---|']
    for category, c in new['categories'].items():
        a, b = safety(old['categories'][category]['pipeline']['matrix']), safety(c['pipeline']['matrix'])
        lines.append('| ' + category + ' | ' + ' | '.join(f'{a[k]:.1%} / {b[k]:.1%}' for k in a) + ' |')
    lines += ['', '## Model-only versus complete pipeline', '',
              '| Category | Full image old/new | Pipeline old/new | New full → pipeline label changes |',
              '|---|---|---|---:|']
    for category, c in new['categories'].items():
        a = old['categories'][category]
        lines.append(f"| {category} | {a['full_image']['accuracy']:.2%} / {c['full_image']['accuracy']:.2%} | "
                     f"{a['pipeline']['accuracy']:.2%} / {c['pipeline']['accuracy']:.2%} | {c['ablation_changed']} |")
    lines += ['', '## Calibration and high-confidence errors', '',
              '| Category | ECE old/new | Brier old/new | NLL old/new | Errors ≥0.9 old/new |',
              '|---|---|---|---|---|']
    for category, c in new['categories'].items():
        a, b = old['categories'][category]['pipeline'], c['pipeline']
        lines.append(f"| {category} | {a['ece']:.4f} / {b['ece']:.4f} | {a['brier']:.4f} / {b['brier']:.4f} | "
                     f"{a['nll']:.4f} / {b['nll']:.4f} | {a['high_confidence_errors']} / {b['high_confidence_errors']} |")
    lines += ['', '## Matched robustness subset', '',
              'Each transform uses the same 15 images per category (five per class) as the baseline; these are '
              'paired diagnostics, not additional independent images.', '',
              '| Category | Transform | Old accuracy | New accuracy |', '|---|---|---:|---:|']
    for category, c in new['categories'].items():
        a = old['categories'][category]
        assert [(r['image'], r['truth'], r['variant']) for r in a['robustness_rows']] == [
            (r['image'], r['truth'], r['variant']) for r in c['robustness_rows']]
        for variant, m in c['robustness'].items():
            lines.append(f"| {category} | {variant} | {a['robustness'][variant]['accuracy']:.2%} | {m['accuracy']:.2%} |")
    if args.parity:
        p = summary['parity']
        lines += ['', '## Checkpoint/export parity', '',
                  f"Checked nine matched images per category using both standard Ultralytics validation transforms and "
                  f"service full-image resize tensors (45 images, 90 paired forwards). Checkpoint exports match evaluated "
                  f"ONNX hashes. Identical-input top-label agreement: {p['top_label_agreement']:.2%}; maximum absolute "
                  f"probability difference: {p['max_abs_difference']:.8g}; all within diagnostic 1e-4 tolerance: "
                  f"{p['all_within_tolerance']}. Device: {p['device']}. See parity_results.json."]
    lines += ['', '## Interpretation and scope', '',
              '- Existing validation data were used for checkpoint selection, including combined fine-tuning validation. '
              'These results are not an independent held-out test or proof of local-photo generalization.',
              '- Dataset-directory labels were retained. Independent annotators, adjudication, location/family groups, '
              'natural-use cohorts and a newly locked final test set are unavailable for this run.',
              '- Original base-training overlap audit is in CV_RESULTS.md. Fine-tuning adds other training images; '
              'the original audit does not establish absence of fine-tuning or near-duplicate overlap.',
              '- TERHALANG is internally normalized to RUSAK only for illegal-parking metrics.',
              '- The plan’s 90% defect recall and 0.85 macro-F1 are illustrative diagnostic targets, not agreed acceptance requirements. '
              'Confidence bounds and validation reuse preclude a population-level pass/fail claim.',
              '- Calibration/review and robustness results are diagnostics; no thresholds or rules were tuned after evaluation. '
              'Brier/NLL exclude quality-guard responses; ECE includes their fixed confidence scores.',
              '- CPU latency is warm, serial in-process inference, excluding HTTP/network transport and queue processing. '
              'Historical versus current runtime timing is descriptive, not a controlled speed benchmark.',
              '- Only the full-image and complete-pipeline ablation was run. Separate quality-only/crop-only ablations, '
              'HTTP load and application integration are not measured. Routing was outside this evaluation.', '',
              '## Files', '',
              '- CV_RESULTS.md / cv_results.json: new per-category metrics, confusion counts, calibration, robustness and raw predictions.',
              '- comparison.json / metrics_comparison.csv: paired statistics and old/new detailed metrics.',
              '- confusion_matrices.csv / per_class_metrics.csv: confusion counts, row-normalized percentages and class metrics with recall intervals.',
              '- dataset_manifest.csv / error_catalog.csv: current image hashes and all old/new error cases.',
              '- parity_results.json: checkpoint/export hashes and identical-tensor results.',
              '- comparison_plots.png: accuracy, reliability and review diagnostics.', '']
    (args.output / 'COMPARISON.md').write_text('\n'.join(lines))
    import matplotlib
    matplotlib.use('Agg')
    import matplotlib.pyplot as plt
    fig, axes = plt.subplots(1, 3, figsize=(18, 5))
    categories = list(new['categories'])
    for offset, run, label in [(-.2, old, 'Old'), (.2, new, 'V2')]:
        axes[0].bar([i + offset for i in range(5)], [run['categories'][c]['pipeline']['accuracy'] for c in categories], width=.4, label=label)
    axes[0].set_xticks(range(5), categories, rotation=35, ha='right')
    axes[0].set_title('Matched pipeline accuracy'); axes[0].legend()
    axes[1].plot([0, 1], [0, 1], '--', color='gray')
    for category, c in new['categories'].items():
        bins = c['pipeline']['reliability_bins']
        axes[1].plot([b['confidence'] for b in bins], [b['accuracy'] for b in bins], 'o-', label=category)
        review = c['pipeline']['review_coverage']
        axes[2].plot([r['automatic_n']/150 for r in review],
                     [r['automatic_errors']/r['automatic_n'] if r['automatic_n'] else 0 for r in review], 'o-', label=category)
    axes[1].set(title='V2 decision-confidence reliability', xlabel='Mean confidence', ylabel='Observed accuracy')
    axes[2].set(title='V2 exploratory review policy', xlabel='Automatic decision coverage', ylabel='Error rate among automatic decisions')
    axes[2].legend(fontsize=8)
    fig.tight_layout(); fig.savefig(args.output / 'comparison_plots.png', dpi=150); plt.close(fig)
    print('\n'.join(lines[:25]))


if __name__ == '__main__':
    main()
