#!/usr/bin/env python3
"""Evaluate a folder-labeled external image collection with current CV specialists."""

import argparse
import asyncio
import ast
import csv
import hashlib
import html
import importlib.util
import io
import json
import os
from pathlib import Path
import subprocess
import sys
import time
from collections import Counter, defaultdict
from datetime import datetime, timezone

ROOT = Path(__file__).resolve().parents[1]
MAPPING = {'guiding block': 'guiding_block', 'manhole': 'drainase', 'parkir liar': 'parkir_liar', 'trotoar': 'trotoar'}
LABELS = {'normal': 'BAIK', 'rusak': 'RUSAK', 'terhalang': 'TERHALANG'}
EXTENSIONS = {'.jpg', '.jpeg', '.png', '.webp', '.bmp', '.tif', '.tiff'}


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def pixels(image):
    return hashlib.sha256(str(image.size).encode() + image.tobytes()).hexdigest()


def summarize(rows):
    valid = [r for r in rows if r['status'] == 'ok']
    correct = sum(r['matches_folder'] for r in valid)
    labels = ['BAIK', 'RUSAK', 'TERHALANG', 'SPAM']
    matrix = {truth: {prediction: sum(r['expected'] == truth and r['prediction'] == prediction for r in valid)
                      for prediction in labels} for truth in labels if any(r['expected'] == truth for r in valid)}
    classes = {}
    for truth, counts in matrix.items():
        support = sum(counts.values())
        tp = counts[truth]
        predicted = sum(r['prediction'] == truth for r in valid)
        precision = tp / predicted if predicted else 0
        recall = tp / support
        classes[truth] = dict(support=support, precision=precision, recall=recall,
                             f1=2*precision*recall/(precision+recall) if precision+recall else 0)
    return dict(submitted=len(rows), evaluated=len(valid), errors=len(rows)-len(valid), correct=correct,
                mismatches=len(valid)-correct, accuracy=correct/len(valid) if valid else None,
                full_image_correct=sum(r['full_image_prediction'] == r['expected'] for r in valid),
                high_confidence_mismatches=sum(not r['matches_folder'] and r['confidence'] >= .9 for r in valid),
                quality_guard_rejections=sum(r['quality_guard'] is not None for r in valid),
                class_metrics=classes, confusion_matrix=matrix,
                macro_f1_supported_classes=sum(c['f1'] for c in classes.values())/len(classes) if classes else None)


def worker(dataset):
    import numpy as np
    import onnxruntime as ort
    from PIL import Image
    from starlette.datastructures import UploadFile

    os.environ['CV_MOCK'] = 'false'
    spec = importlib.util.spec_from_file_location('cv_google_eval', ROOT / 'cv-service/app/main.py')
    service = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(service)
    service.ort_session = None
    service.CV_MOCK = False
    result = dict(title='Test with Images from Google', started_at=datetime.now(timezone.utc).isoformat(),
                  source_sha256=sha(ROOT / 'cv-service/app/main.py'), onnxruntime=ort.__version__,
                  cpu_threads=2, models={}, rows=[], ignored_files=[], audit_errors=[])
    options = ort.SessionOptions()
    options.intra_op_num_threads = 2
    options.inter_op_num_threads = 1
    # Audits are category-specific and compare decoded pixels, not filename hints.
    for folder, category in MAPPING.items():
        model = ROOT / f'cv-service/models/{category}_classifier.onnx'
        session = ort.InferenceSession(str(model), sess_options=options, providers=['CPUExecutionProvider'])
        names = ast.literal_eval(session.get_modelmeta().custom_metadata_map['names'])
        assert [names[i] for i in range(3)] == ['BAIK', 'RUSAK', 'SPAM']
        result['models'][category] = dict(sha256=sha(model), class_order=names)
        service.SPECIALIST_SESSIONS = {category: session}
        service.run_specialist_inference(session, Image.new('RGB', (384, 384), 'gray'))
        known = defaultdict(list)
        roots = [ROOT / 'datasets_per_category' / category / split for split in ['train', 'val']]
        roots += [ROOT / 'runs/finetune_datasets' / category / split for split in ['train', 'val', 'val_ft']]
        roots += [ROOT / 'dataset_fine tune' / folder]
        for source_root in roots:
            if not source_root.exists():
                continue
            for path in sorted(source_root.rglob('*')):
                if not path.is_file() or path.suffix.lower() not in EXTENSIONS:
                    continue
                try:
                    with Image.open(path) as source:
                        fingerprint = pixels(source.convert('RGB'))
                    known[fingerprint].append(str(path.relative_to(ROOT)))
                except Exception as exc:
                    result['audit_errors'].append(dict(path=str(path.relative_to(ROOT)), error=str(exc)))
        for path in sorted((dataset / folder).rglob('*')):
            if not path.is_file():
                continue
            if path.suffix.lower() not in EXTENSIONS:
                result['ignored_files'].append(str(path.relative_to(dataset)))
                continue
            subfolder = path.parent.name.lower()
            if subfolder not in LABELS:
                raise ValueError(f'Unmapped label folder: {path.parent}')
            row = dict(image=str(path.relative_to(dataset)), category=category, expected=LABELS[subfolder],
                       file_sha256=sha(path), status='ok')
            try:
                with Image.open(path) as source:
                    image = source.convert('RGB')
                row.update(width=image.width, height=image.height, pixel_sha256=pixels(image))
                # Reconstruct the fine-tuning preparation transform to find exact
                # train/validation matches even when the source was resized/re-encoded.
                encoded = io.BytesIO()
                image.resize((384, 384), Image.Resampling.BILINEAR).save(encoded, 'JPEG', quality=92)
                encoded.seek(0)
                with Image.open(encoded) as prepared:
                    prepared_hash = pixels(prepared.convert('RGB'))
                row['known_dataset_matches'] = sorted(set(known.get(row['pixel_sha256'], []) + known.get(prepared_hash, [])))
                start = time.perf_counter()
                probs = service.run_specialist_inference(session, image)
                row['full_image_latency_ms'] = (time.perf_counter()-start)*1000
                top = ['BAIK', 'RUSAK', 'SPAM'][int(np.argmax(probs))]
                row['full_image_prediction'] = 'TERHALANG' if category == 'parkir_liar' and top == 'RUSAK' else top
                row['full_image_probabilities'] = [float(p) for p in probs]
                row['quality_guard'] = service.evaluate_image_heuristics(image)
                # Neutral filename: class/category words never drive inference.
                upload = UploadFile(filename='evaluation_image', file=io.BytesIO(path.read_bytes()))
                start = time.perf_counter()
                response = asyncio.run(service.analyze_image(file=upload, category=category))
                row.update(latency_ms=(time.perf_counter()-start)*1000, prediction=response['label'],
                           confidence=response['confidence'], probabilities=response.get('probabilities'),
                           matches_folder=response['label'] == row['expected'],
                           execution_path='quality_guard' if row['quality_guard'] else 'real_specialist_with_crop_rules')
            except Exception as exc:
                row.update(status='error', error=str(exc))
            result['rows'].append(row)
        print(f"Evaluated {category}", file=sys.stderr, flush=True)
    result['finished_at'] = datetime.now(timezone.utc).isoformat()
    result['summary'] = summarize(result['rows'])
    result['by_category'] = {category: summarize([r for r in result['rows'] if r['category'] == category]) for category in MAPPING.values()}
    result['by_folder'] = {folder: summarize([r for r in result['rows'] if str(Path(r['image']).parent) == folder])
                           for folder in sorted({str(Path(r['image']).parent) for r in result['rows']})}
    return result


def save(data, output, dataset):
    from PIL import Image
    output.mkdir(parents=True, exist_ok=True)
    (output / 'results.json').write_text(json.dumps(data, indent=2) + '\n')
    valid = [r for r in data['rows'] if r['status'] == 'ok']
    with (output / 'per_image_results.csv').open('w', newline='') as handle:
        keys = ['image', 'category', 'expected', 'prediction', 'matches_folder', 'confidence',
                'full_image_prediction', 'execution_path', 'latency_ms', 'status', 'error', 'known_dataset_matches']
        writer = csv.DictWriter(handle, fieldnames=keys, extrasaction='ignore')
        writer.writeheader()
        writer.writerows(data['rows'])
    s = data['summary']
    lines = ['# Test with Images from Google', '', f"Dataset: `{dataset}`", '',
             f"Run: {data['started_at']} to {data['finished_at']}", '',
             '## Folder-label agreement', '',
             f"**{s['correct']}/{s['evaluated']} images match their folder labels ({s['accuracy']:.2%}).** "
             f"Mismatches: {s['mismatches']}; processing errors: {s['errors']}.", '',
             'Mapping: normal → BAIK; rusak → RUSAK; parkir liar/Terhalang → TERHALANG; manhole → drainase.', '',
             '| Category | Images | Matches | Mismatches | Agreement | Full-image-only matches |', '|---|---:|---:|---:|---:|---:|']
    for category, c in data['by_category'].items():
        lines.append(f"| {category} | {c['evaluated']} | {c['correct']} | {c['mismatches']} | {c['accuracy']:.2%} | {c['full_image_correct']} |")
    lines += ['', '### By source folder', '', '| Folder | Matches / evaluated | Agreement |', '|---|---:|---:|']
    for folder, c in data['by_folder'].items():
        lines.append(f"| {folder} | {c['correct']}/{c['evaluated']} | {c['accuracy']:.2%} |")
    overlapping = [r for r in valid if r['known_dataset_matches']]
    unique = {r['pixel_sha256'] for r in valid}
    clean = [r for r in valid if not r['known_dataset_matches']]
    train_matches = sum(any(p.startswith('runs/finetune_datasets/') and '/train/' in p
                            for p in r['known_dataset_matches']) for r in valid)
    val_matches = sum(any(p.startswith('runs/finetune_datasets/') and '/val/' in p
                          for p in r['known_dataset_matches']) for r in valid)
    clean_result = f"{sum(r['matches_folder'] for r in clean)}/{len(clean)} matches" if clean else 'unavailable (no qualifying images)'
    lines += ['', '## Interpretation and duplicate audit', '',
              f"- {len(valid)} evaluated entries contain {len(unique)} distinct decoded-pixel images across categories.",
              f"- {len(overlapping)} entries match known category-specific training, validation, or fine-tuning source images. "
              'The audit checks decoded pixels and a reconstructed 384×384/JPEG-quality-92 preparation transform. '
              'All matching paths are in results.json; source overlap alone does not prove an image was used for fitting.',
              f"- **Actual fine-tuning split matches: {train_matches} training entries and {val_matches} validation entries. "
              'This collection is reused model-development data, not an independent unseen-image test.**',
              f"- No-known-exact-match subset: {clean_result}. "
              'This subset may still contain near-duplicates; independent test status is not established.',
              f"- Quality-guard rejections: {s['quality_guard_rejections']}; high-confidence mismatches (≥0.9): {s['high_confidence_mismatches']}.",
              f"- Full-image-only predictions match {s['full_image_correct']}/{s['evaluated']} folder labels; "
              f"complete-pipeline predictions match {s['correct']}/{s['evaluated']}. "
              f"The two paths disagree on {sum(r['prediction'] != r['full_image_prediction'] for r in valid)} entries. "
              'The pipeline includes quality rejection and category-specific defect crop overrides.',
              '- The supplied folder categorization is the reference, not independently adjudicated ground truth. '
              'The test checks condition labels for the user-selected facility category; it does not independently identify the facility category.',
              '- The requested Google-image title describes this supplied collection. Individual Google source URLs were not supplied or verified; WhatsApp-named images are included.',
              '- No SPAM-labeled or pedestrian-crossing folders were supplied. SPAM predictions are recorded as mismatches; spam recall and crossing accuracy cannot be measured.',
              '- Current local ONNX artifacts were tested in an isolated CPU worker using the actual service function. '
              'Mock and legacy fallback were disabled. Latency excludes HTTP transport; folder labels do not influence predictions.', '',
              '## Mismatches', '', '| Image | Expected | Predicted | Confidence |', '|---|---|---|---:|']
    for r in valid:
        if not r['matches_folder']:
            lines.append(f"| {r['image'].replace('|', '/')} | {r['expected']} | {r['prediction']} | {r['confidence']:.4f} |")
    lines += ['', '## Models', '', f"CV service source SHA-256: `{data['source_sha256']}`", '']
    for category, model in data['models'].items():
        lines.append(f"- {category}: `{model['sha256']}`")
    lines += ['', 'See per_image_results.csv for all predictions, results.json for raw metrics and audit evidence, '
              'and image_review.html for a visual match/mismatch gallery.', '']
    (output / 'TEST_WITH_IMAGES_FROM_GOOGLE.md').write_text('\n'.join(lines))
    thumbs = output / 'thumbnails'
    thumbs.mkdir(exist_ok=True)
    cards = []
    for i, row in enumerate(sorted(valid, key=lambda r: (r['matches_folder'], r['category'], r['image']))):
        with Image.open(dataset / row['image']) as source:
            image = source.convert('RGB')
        image.thumbnail((300, 220))
        image.save(thumbs / f'{i:04d}.jpg', quality=85)
        status = 'SESUAI' if row['matches_folder'] else 'TIDAK SESUAI'
        color = '#217a37' if row['matches_folder'] else '#b32b2b'
        cards.append(f'<article style="border:2px solid {color}"><img src="thumbnails/{i:04d}.jpg" loading="lazy">'
                     f'<strong>{status}</strong><p>{html.escape(row["image"])}</p><p>Folder: {row["expected"]}<br>'
                     f'Prediction: {row["prediction"]} ({row["confidence"]:.2%})</p></article>')
    (output / 'image_review.html').write_text('<!doctype html><meta charset="utf-8"><title>Test with Images from Google</title>'
        '<style>body{font-family:sans-serif;margin:24px}main{display:flex;flex-wrap:wrap;gap:15px}'
        'article{width:300px;padding:10px;overflow-wrap:anywhere}img{max-width:300px;display:block;height:220px;object-fit:contain}'
        'strong{display:block;margin-top:8px}</style><h1>Test with Images from Google</h1>'
        f'<p>{s["correct"]}/{s["evaluated"]} sesuai; {s["mismatches"]} tidak sesuai. Mismatches shown first.</p>'
        '<main>' + ''.join(cards) + '</main>')
    print('\n'.join(lines[:25]))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--dataset', type=Path, default=Path('/home/rog-theseus/Downloads/dataset_evaluasi'))
    parser.add_argument('--output', type=Path, default=ROOT / 'docs/evaluation_results V2/Test with Images from Google')
    parser.add_argument('--worker', action='store_true')
    args = parser.parse_args()
    if args.worker:
        print(json.dumps(worker(args.dataset)))
        return
    command = ['docker', 'run', '--rm', '--network', 'none', '--read-only', '--tmpfs', '/tmp',
               '-e', 'PYTHONDONTWRITEBYTECODE=1', '-v', f'{ROOT}:/workspace:ro',
               '-v', f'{args.dataset.resolve()}:/evaluation:ro', '-w', '/workspace', '--entrypoint', 'python',
               'infra-cv-service', 'scripts/evaluate_google_images.py', '--worker', '--dataset', '/evaluation']
    completed = subprocess.run(command, text=True, stdout=subprocess.PIPE, check=True)
    data = json.loads(completed.stdout)
    data['dataset_path'] = str(args.dataset.resolve())
    save(data, args.output, args.dataset)


if __name__ == '__main__':
    main()
