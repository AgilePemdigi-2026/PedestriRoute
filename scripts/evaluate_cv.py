#!/usr/bin/env python3
"""Reproducible, read-only model evaluation using the existing CV Docker image.

Usage: python3 scripts/evaluate_cv.py --per-class 50
The worker mounts the project read-only and has no network access. The host writes
JSON predictions and a Markdown report under docs/evaluation_results/.
"""

import argparse
import asyncio
import ast
import contextlib
import csv
import hashlib
import importlib.util
import io
import json
import math
import os
from pathlib import Path
import random
import subprocess
import sys
import time
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
CATEGORIES = ['trotoar', 'drainase', 'guiding_block', 'parkir_liar', 'penyebrangan']
CLASSES = ['BAIK', 'RUSAK', 'SPAM']


def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def wilson(correct, total):
    if not total:
        return None
    z = 1.959963984540054
    p = correct / total
    denominator = 1 + z * z / total
    centre = (p + z * z / (2 * total)) / denominator
    half = z * math.sqrt(p * (1 - p) / total + z * z / (4 * total * total)) / denominator
    return [centre - half, centre + half]


def metrics(rows, key):
    matrix = [[0] * 3 for _ in CLASSES]
    for row in rows:
        matrix[CLASSES.index(row['truth'])][CLASSES.index(row[key]['label'])] += 1
    per_class = {}
    for i, label in enumerate(CLASSES):
        tp = matrix[i][i]
        support = sum(matrix[i])
        predicted = sum(r[i] for r in matrix)
        precision = tp / predicted if predicted else 0
        recall = tp / support if support else 0
        per_class[label] = dict(support=support, precision=precision, recall=recall,
                                f1=2 * precision * recall / (precision + recall) if precision + recall else 0,
                                recall_wilson=wilson(tp, support))
    correct = sum(matrix[i][i] for i in range(3))
    bins = []
    for i in range(10):
        members = [r for r in rows if min(9, int(r[key]['confidence'] * 10)) == i]
        if members:
            bins.append(dict(n=len(members), confidence=sum(r[key]['confidence'] for r in members) / len(members),
                             accuracy=sum(r[key]['label'] == r['truth'] for r in members) / len(members)))
    probability_rows = [r for r in rows if r[key].get('probabilities') is not None]
    brier = nll = None
    if probability_rows:
        brier = sum(sum((p - (j == CLASSES.index(r['truth']))) ** 2
                        for j, p in enumerate(r[key]['probabilities'])) for r in probability_rows) / len(probability_rows)
        nll = -sum(math.log(max(1e-12, r[key]['probabilities'][CLASSES.index(r['truth'])]))
                   for r in probability_rows) / len(probability_rows)
    latencies = sorted(r[key]['latency_ms'] for r in rows)
    return dict(n=len(rows), accuracy=correct / len(rows), accuracy_wilson=wilson(correct, len(rows)),
                macro_f1=sum(v['f1'] for v in per_class.values()) / 3,
                balanced_accuracy=sum(v['recall'] for v in per_class.values()) / 3,
                matrix=matrix, per_class=per_class,
                ece=sum(b['n'] / len(rows) * abs(b['accuracy'] - b['confidence']) for b in bins),
                reliability_bins=bins, brier=brier, nll=nll, probability_n=len(probability_rows),
                latency_p50_ms=latencies[len(latencies) // 2],
                latency_p95_ms=latencies[math.ceil(len(latencies) * .95) - 1],
                high_confidence_errors=sum(r[key]['confidence'] >= .9 and r[key]['label'] != r['truth'] for r in rows),
                review_coverage=[dict(threshold=t,
                                     automatic_n=sum(r[key]['confidence'] >= t for r in rows),
                                     automatic_errors=sum(r[key]['confidence'] >= t and r[key]['label'] != r['truth'] for r in rows))
                                 for t in [.5, .65, .8, .9, .95]])


def worker(args):
    import numpy as np
    import onnxruntime as ort
    from PIL import Image, ImageEnhance, ImageFilter, ImageOps
    from starlette.datastructures import UploadFile

    os.environ['CV_MOCK'] = 'false'
    spec = importlib.util.spec_from_file_location('evaluated_cv', ROOT / 'cv-service/app/main.py')
    service = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(service)
    service.CV_MOCK = False
    service.ort_session = None
    result = dict(started_at=datetime.now(timezone.utc).isoformat(), seed=args.seed,
                  requested_per_class=args.per_class, python=sys.version, onnxruntime=ort.__version__,
                  cpu_threads=2, source_sha256=digest(ROOT / 'cv-service/app/main.py'), categories={})
    for category in CATEGORIES:
        print(f'Evaluating {category}', file=sys.stderr, flush=True)
        model = ROOT / f'cv-service/models/{category}_classifier.onnx'
        options = ort.SessionOptions()
        options.intra_op_num_threads = 2
        options.inter_op_num_threads = 1
        session = ort.InferenceSession(str(model), sess_options=options, providers=['CPUExecutionProvider'])
        metadata = session.get_modelmeta().custom_metadata_map
        names = ast.literal_eval(metadata['names'])
        if [names[i] for i in range(3)] != CLASSES:
            raise ValueError(f'Unexpected model class order: {names}')
        service.SPECIALIST_SESSIONS = {category: session}
        data = ROOT / 'datasets_per_category' / category
        hash_sets = {}
        counts = {}
        for split in ['train', 'val']:
            hashes = {}
            counts[split] = {}
            for label in CLASSES:
                paths = sorted((data / split / label).glob('*.jpg'))
                counts[split][label] = len(paths)
                for path in paths:
                    with Image.open(path) as im:
                        rgb = im.convert('RGB')
                        fingerprint = hashlib.sha256(str(rgb.size).encode() + rgb.tobytes()).hexdigest()
                    hashes.setdefault(fingerprint, []).append(str(path.relative_to(ROOT)))
            hash_sets[split] = hashes
        overlap = sorted(set(hash_sets['train']) & set(hash_sets['val']))
        overlap_paths = {p for h in overlap for p in hash_sets['val'][h]}
        # Warm each actual model before measuring single-request latency.
        service.run_specialist_inference(session, Image.new('RGB', (384, 384), 'gray'))
        rows = []
        robustness = []
        rng = random.Random(args.seed)

        def pipeline(im):
            buffer = io.BytesIO()
            im.save(buffer, format='PNG')
            upload = UploadFile(filename='evaluation.png', file=io.BytesIO(buffer.getvalue()))
            start = time.perf_counter()
            response = asyncio.run(service.analyze_image(file=upload, category=category))
            elapsed = (time.perf_counter() - start) * 1000
            label = 'RUSAK' if response['label'] == 'TERHALANG' else response['label']
            probabilities = response.get('probabilities')
            return dict(label=label, confidence=response['confidence'], latency_ms=elapsed,
                        probabilities=[probabilities[c.lower()] for c in CLASSES] if probabilities else None)

        for label in CLASSES:
            paths = sorted((data / 'val' / label).glob('*.jpg'))
            rng.shuffle(paths)
            for index, path in enumerate(paths[:args.per_class] if args.per_class else paths):
                with Image.open(path) as source:
                    im = source.convert('RGB')
                start = time.perf_counter()
                probs = service.run_specialist_inference(session, im)
                elapsed = (time.perf_counter() - start) * 1000
                row = dict(image=str(path.relative_to(ROOT)), truth=label,
                           exact_train_overlap=str(path.relative_to(ROOT)) in overlap_paths,
                           full_image=dict(label=CLASSES[int(np.argmax(probs))], confidence=float(max(probs)),
                                           probabilities=[float(p) for p in probs], latency_ms=elapsed),
                           pipeline=pipeline(im), quality_guard=service.evaluate_image_heuristics(im) is not None)
                rows.append(row)
                if index < args.robustness_per_class:
                    variants = {
                        'brightness_0.5': ImageEnhance.Brightness(im).enhance(.5),
                        'blur_radius_2': im.filter(ImageFilter.GaussianBlur(2)),
                        'white_border_96px': ImageOps.expand(im, border=96, fill='white'),
                    }
                    for variant, transformed in variants.items():
                        robustness.append(dict(image=row['image'], truth=label, variant=variant,
                                               original_label=row['pipeline']['label'], pipeline=pipeline(transformed)))
        nonoverlap = [r for r in rows if not r['exact_train_overlap']]
        csv_path = ROOT / f'runs/per_category/model_{category}/results.csv'
        with csv_path.open() as handle:
            training = list(csv.DictReader(handle))
        result['categories'][category] = dict(
            model_sha256=digest(model), class_order=names, counts=counts,
            training_best_top1=max(float(r['metrics/accuracy_top1']) for r in training),
            training_last_top1=float(training[-1]['metrics/accuracy_top1']),
            decoded_train_val_overlap_unique=len(overlap),
            decoded_val_overlap_images=len(overlap_paths),
            overlap_examples=[dict(train=hash_sets['train'][h], val=hash_sets['val'][h]) for h in overlap[:10]],
            within_val_duplicate_excess=sum(len(v) - 1 for v in hash_sets['val'].values()),
            rows=rows, robustness_rows=robustness,
            full_image=metrics(rows, 'full_image'), pipeline=metrics(rows, 'pipeline'),
            nonoverlap_pipeline=metrics(nonoverlap, 'pipeline') if nonoverlap else None,
            quality_rejections=sum(r['quality_guard'] for r in rows),
            ablation_changed=sum(r['full_image']['label'] != r['pipeline']['label'] for r in rows),
            robustness={v: metrics([r for r in robustness if r['variant'] == v], 'pipeline')
                        for v in sorted({r['variant'] for r in robustness})})
    result['finished_at'] = datetime.now(timezone.utc).isoformat()
    print(json.dumps(result))


def report(data):
    lines = ['# CV evaluation results', '', f"Run started: {data['started_at']}", '',
             '## Scope and interpretation', '',
             'Measured the local ONNX specialists and the current `/analyze` function on a seeded sample of existing validation images. '
             'This is a validation re-evaluation, **not an independent held-out test**. Labels come from dataset directories and were not re-annotated. '
             'Existing source bias, synthetic crossing defects, and near-duplicate leakage remain possible. '
             'Wilson intervals below assume independent images and can be optimistic when images are related.', '',
             f"Seed: {data['seed']}; ONNX Runtime: {data['onnxruntime']}; CPU inference threads: 2. "
             'Worker network disabled; repository mounted read-only. Latency excludes HTTP/network transport, includes in-process image decode for pipeline, and excludes initial warm-up.', '',
             '## Main results', '',
             '| Category | N | Training best top-1 | Full-image accuracy | Pipeline accuracy (95% Wilson) | Pipeline macro-F1 | Defect recall | High-confidence errors ≥0.9 |',
             '|---|---:|---:|---:|---|---:|---:|---:|']
    for category, c in data['categories'].items():
        p = c['pipeline']
        ci = p['accuracy_wilson']
        lines.append(f"| {category} | {p['n']} | {c['training_best_top1']:.3f} | {c['full_image']['accuracy']:.3f} | {p['accuracy']:.3f} ({ci[0]:.3f}–{ci[1]:.3f}) | {p['macro_f1']:.3f} | {p['per_class']['RUSAK']['recall']:.3f} | {p['high_confidence_errors']} |")
    lines += ['', 'Training best top-1 is historical checkpoint-selection evidence; full-image and pipeline results use the current sampled inputs. '
              'Differences can reflect preprocessing, checkpoint choice, sampling, and pipeline rules; they do not isolate one cause.', '',
              '## Dataset overlap audit', '',
              'Exact overlap uses decoded RGB pixels and dimensions, not filenames. It does not detect flipped, cropped, or otherwise near-duplicate images.', '',
              '| Category | Train images | Validation images | Unique shared pixel hashes | Validation images shared with train | Extra within-validation duplicates | Non-overlap sampled accuracy |',
              '|---|---:|---:|---:|---:|---:|---:|']
    for category, c in data['categories'].items():
        clean = c['nonoverlap_pipeline']
        lines.append(f"| {category} | {sum(c['counts']['train'].values())} | {sum(c['counts']['val'].values())} | {c['decoded_train_val_overlap_unique']} | {c['decoded_val_overlap_images']} | {c['within_val_duplicate_excess']} | {clean['accuracy'] if clean else 'N/A'} |")
    for category, c in data['categories'].items():
        p = c['pipeline']
        lines += ['', f'## {category}', '', f"Model SHA-256: `{c['model_sha256']}`", '',
                  'Rows are ground truth, columns are predicted. Illegal parking TERHALANG is normalized to RUSAK for calculation.', '',
                  '| Truth / prediction | BAIK | RUSAK / TERHALANG | SPAM |', '|---|---:|---:|---:|']
        for label, values in zip(CLASSES, p['matrix']):
            lines.append(f"| {label} | {' | '.join(map(str, values))} |")
        lines += ['', '| Class | Support | Precision | Recall | F1 |', '|---|---:|---:|---:|---:|']
        for label, m in p['per_class'].items():
            lines.append(f"| {label} | {m['support']} | {m['precision']:.3f} | {m['recall']:.3f} | {m['f1']:.3f} |")
        matrix = p['matrix']
        lines += ['', f"Defects called BAIK: {matrix[1][0]}/{sum(matrix[1])}; defects called SPAM: {matrix[1][2]}/{sum(matrix[1])}. "
                  f"Quality-guard rejections: {c['quality_rejections']}; full-image → pipeline label changes: {c['ablation_changed']}.", '',
                  f"Pipeline p50/p95: {p['latency_p50_ms']:.1f}/{p['latency_p95_ms']:.1f} ms. "
                  f"Full-image p50/p95: {c['full_image']['latency_p50_ms']:.1f}/{c['full_image']['latency_p95_ms']:.1f} ms.", '',
                  f"10-bin top-label ECE: {p['ece']:.4f}. Brier score: {p['brier']}; NLL: {p['nll']}. "
                  f"Probability metrics use {p['probability_n']}/{p['n']} rows and exclude guard-only responses. "
                  'ECE includes fixed guard confidence values and is a decision-confidence diagnostic, not a calibrated risk guarantee.', '',
                  '### Review policy diagnostics', '',
                  'Thresholds are exploratory and were not tuned or accepted on these results.', '',
                  '| Confidence threshold | Automatic decisions | Errors among automatic decisions |', '|---:|---:|---:|']
        for r in p['review_coverage']:
            lines.append(f"| {r['threshold']} | {r['automatic_n']} | {r['automatic_errors']} |")
        lines += ['', '### Paired robustness subset', '',
                  '| Transform | N | Original subset accuracy | Transformed accuracy | Label flips |', '|---|---:|---:|---:|---:|']
        for variant, m in c['robustness'].items():
            rows = [r for r in c['robustness_rows'] if r['variant'] == variant]
            original = sum(r['original_label'] == r['truth'] for r in rows) / len(rows)
            flips = sum(r['original_label'] != r['pipeline']['label'] for r in rows)
            lines.append(f"| {variant} | {m['n']} | {original:.3f} | {m['accuracy']:.3f} | {flips} |")
        errors = [r for r in c['rows'] if r['pipeline']['label'] != r['truth']]
        lines += ['', '### Example errors (up to five)', '']
        for r in sorted(errors, key=lambda r: -r['pipeline']['confidence'])[:5]:
            lines.append(f"- `{r['image']}`: {r['truth']} → {r['pipeline']['label']}, confidence {r['pipeline']['confidence']:.4f}.")
    lines += ['', '## Not established by this run', '',
              '- Independent local-photo accuracy, geographic generalization, label agreement, or injury-risk reduction.',
              '- PyTorch/ONNX parity: PyTorch was not part of this worker evaluation.',
              '- Live HTTP throughput, queue integration, or field conditions.',
              '- Causal attribution of pipeline changes to quality guard versus crops; full-image and complete-pipeline comparison is a combined ablation.',
              '- Final acceptance against population thresholds. A fresh grouped and independently labeled test set is needed.', '',
              'Raw predictions, class supports, reliability bins, overlap examples, hashes, and metrics are in `cv_results.json`.']
    return '\n'.join(lines) + '\n'


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--worker', action='store_true')
    parser.add_argument('--image', default='infra-cv-service')
    parser.add_argument('--per-class', type=int, default=50, help='0 evaluates all validation images')
    parser.add_argument('--robustness-per-class', type=int, default=5)
    parser.add_argument('--seed', type=int, default=42)
    parser.add_argument('--output', type=Path, default=ROOT / 'docs/evaluation_results')
    args = parser.parse_args()
    if args.per_class < 0 or args.robustness_per_class < 0:
        parser.error('sample sizes must be nonnegative')
    if args.worker:
        with contextlib.redirect_stdout(sys.stderr):
            # Reserve stdout for the final JSON only.
            buffer = io.StringIO()
            with contextlib.redirect_stdout(buffer):
                worker(args)
        sys.stdout.write(buffer.getvalue())
        return
    command = ['docker', 'run', '--rm', '--network', 'none', '--read-only',
               '--tmpfs', '/tmp', '-e', 'PYTHONDONTWRITEBYTECODE=1',
               '-v', f'{ROOT}:/workspace:ro', '-w', '/workspace', '--entrypoint', 'python', args.image,
               'scripts/evaluate_cv.py', '--worker', '--per-class', str(args.per_class),
               '--robustness-per-class', str(args.robustness_per_class), '--seed', str(args.seed)]
    completed = subprocess.run(command, text=True, stdout=subprocess.PIPE, check=True)
    data = json.loads(completed.stdout)
    args.output.mkdir(parents=True, exist_ok=True)
    (args.output / 'cv_results.json').write_text(json.dumps(data, indent=2) + '\n')
    (args.output / 'CV_RESULTS.md').write_text(report(data))
    print(f"Wrote {args.output / 'CV_RESULTS.md'}")


if __name__ == '__main__':
    main()
