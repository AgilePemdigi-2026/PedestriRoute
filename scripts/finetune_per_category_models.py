#!/usr/bin/env python3
"""
PedestriRoute - Per-Category Specialist Fine-Tuning Pipeline (384x384)
======================================================================
Fine-tunes the existing YOLO classification specialist models using new domain images
from `dataset_fine tune/` while preserving existing `datasets_per_category/`
coverage (including SPAM) to prevent catastrophic forgetting.

Category Mapping:
  - dataset_fine tune/trotoar       (normal->BAIK, rusak->RUSAK)     -> trotoar
  - dataset_fine tune/manhole       (normal->BAIK, rusak->RUSAK)     -> drainase
  - dataset_fine tune/guiding block (normal->BAIK, rusak->RUSAK)     -> guiding_block
  - dataset_fine tune/parkir liar   (normal->BAIK, Terhalang->RUSAK) -> parkir_liar
"""

import ast
import hashlib
import os
import random
import shutil
import sys
import time
from pathlib import Path

from PIL import Image

RANDOM_SEED = 42
IMG_SIZE = (384, 384)
CLASSES = ["BAIK", "RUSAK", "SPAM"]
VALID_EXTS = {".jpg", ".jpeg", ".png", ".webp", ".bmp"}

BASE_DIR = Path(__file__).resolve().parents[1]
FINETUNE_SRC = BASE_DIR / "dataset_fine tune"
ORIG_DATA_BASE = BASE_DIR / "datasets_per_category"
FT_DATA_BASE = BASE_DIR / "runs" / "finetune_datasets"
ORIG_RUNS_DIR = BASE_DIR / "runs" / "per_category"
FT_RUNS_DIR = BASE_DIR / "runs" / "finetune"
CV_MODELS_DIR = BASE_DIR / "cv-service" / "models"

FINETUNE_MAPPING = {
    "trotoar": {
        "folder": "trotoar",
        "classes": {"normal": "BAIK", "rusak": "RUSAK"},
    },
    "drainase": {
        "folder": "manhole",
        "classes": {"normal": "BAIK", "rusak": "RUSAK"},
    },
    "guiding_block": {
        "folder": "guiding block",
        "classes": {"normal": "BAIK", "rusak": "RUSAK"},
    },
    "parkir_liar": {
        "folder": "parkir liar",
        "classes": {"normal": "BAIK", "Terhalang": "RUSAK"},
    },
}


def load_dedup_images(folder_path: Path):
    """Load images from folder_path, convert to RGB 384x384, and deduplicate by content hash."""
    seen_hashes = set()
    images = []
    if not folder_path.exists():
        return images

    for p in sorted(folder_path.iterdir()):
        if not p.is_file() or p.suffix.lower() not in VALID_EXTS:
            continue
        try:
            with Image.open(p) as im:
                rgb = im.convert("RGB")
                h = hashlib.sha256(str(rgb.size).encode() + rgb.tobytes()).hexdigest()
                if h in seen_hashes:
                    continue
                seen_hashes.add(h)
                resized = rgb.resize(IMG_SIZE, Image.Resampling.BILINEAR)
                images.append((p.name, resized))
        except Exception as e:
            print(f"  Warning: could not read {p}: {e}")
    return images


def link_or_copy(src: Path, dst: Path):
    try:
        os.link(src, dst)
    except OSError:
        shutil.copy2(src, dst)


def prepare_category_dataset(category: str, cfg: dict):
    """Assemble combined train/val dataset and separate val_ft dataset for a category."""
    rng = random.Random(RANDOM_SEED)
    cat_ft_dir = FT_DATA_BASE / category
    if cat_ft_dir.exists():
        shutil.rmtree(cat_ft_dir)

    for split in ["train", "val", "val_ft"]:
        for cls in CLASSES:
            (cat_ft_dir / split / cls).mkdir(parents=True, exist_ok=True)

    # 1. Copy/link existing datasets_per_category/{category}/{train,val}
    orig_cat_dir = ORIG_DATA_BASE / category
    for split in ["train", "val"]:
        for cls in CLASSES:
            src_cls_dir = orig_cat_dir / split / cls
            dst_cls_dir = cat_ft_dir / split / cls
            if src_cls_dir.exists():
                paths = sorted(src_cls_dir.glob("*.jpg"))
                if split == "train" and len(paths) > 300:
                    rng.shuffle(paths)
                    paths = paths[:300]
                for img_path in paths:
                    link_or_copy(img_path, dst_cls_dir / img_path.name)

    # 2. Load, deduplicate, and split new dataset_fine tune images
    src_root = FINETUNE_SRC / cfg["folder"]
    stats = {}
    for subcat, target_cls in cfg["classes"].items():
        imgs = load_dedup_images(src_root / subcat)
        rng.shuffle(imgs)

        n_val = max(2, round(len(imgs) * 0.2)) if len(imgs) >= 4 else max(1, len(imgs) // 2)
        val_imgs = imgs[:n_val]
        train_imgs = imgs[n_val:]

        # Save held-out val images to both combined val/ and isolated val_ft/
        for idx, (orig_name, im) in enumerate(val_imgs):
            fname = f"ft_val_{subcat}_{idx:04d}.jpg"
            im.save(cat_ft_dir / "val" / target_cls / fname, "JPEG", quality=92)
            im.save(cat_ft_dir / "val_ft" / target_cls / fname, "JPEG", quality=92)

        # Oversample new train images so they have balanced gradient weight (~200 samples)
        repeat = max(1, min(15, round(200 / max(1, len(train_imgs)))))
        saved_train = 0
        for rep in range(repeat):
            for idx, (orig_name, im) in enumerate(train_imgs):
                fname = f"ft_tr_{subcat}_r{rep:02d}_{idx:04d}.jpg"
                im.save(cat_ft_dir / "train" / target_cls / fname, "JPEG", quality=92)
                saved_train += 1

        stats[target_cls] = {
            "unique_total": len(imgs),
            "ft_train_unique": len(train_imgs),
            "ft_train_oversampled": saved_train,
            "ft_val_unique": len(val_imgs),
        }

    return cat_ft_dir, stats


def evaluate_pt_model(model_path: Path, eval_dir: Path, device: str):
    """Evaluate a YOLO classification .pt model on an ImageFolder directory."""
    import gc
    import torch
    from ultralytics import YOLO

    model = YOLO(str(model_path))
    correct = 0
    total = 0
    per_class = {}

    for cls in CLASSES:
        cls_dir = eval_dir / cls
        paths = sorted(cls_dir.glob("*.jpg")) if cls_dir.exists() else []
        if not paths:
            continue
        cls_correct = 0
        for i in range(0, len(paths), 16):
            chunk = paths[i : i + 16]
            results = model.predict(
                source=[str(p) for p in chunk],
                imgsz=384,
                batch=16,
                device=device,
                verbose=False,
            )
            for res in results:
                pred_idx = int(res.probs.top1)
                pred_label = res.names[pred_idx]
                if pred_label == cls:
                    cls_correct += 1
        correct += cls_correct
        total += len(paths)
        per_class[cls] = (cls_correct, len(paths))

    del model
    gc.collect()
    if torch.cuda.is_available():
        torch.cuda.empty_cache()

    acc = (correct / total) if total else 0.0
    return acc, correct, total, per_class


def verify_onnx(onnx_path: Path):
    import numpy as np
    import onnxruntime as ort

    session = ort.InferenceSession(str(onnx_path), providers=["CPUExecutionProvider"])
    meta = session.get_modelmeta().custom_metadata_map
    names = ast.literal_eval(meta["names"])
    expected = ["BAIK", "RUSAK", "SPAM"]
    actual = [names[i] for i in range(3)]
    if actual != expected:
        raise ValueError(f"Unexpected ONNX class order in {onnx_path}: {actual}")
    dummy = np.zeros((1, 3, 384, 384), dtype=np.float32)
    out = session.run(None, {session.get_inputs()[0].name: dummy})
    if out[0].shape != (1, 3):
        raise ValueError(f"Unexpected ONNX output shape: {out[0].shape}")


def finetune_category(category: str, cfg: dict, device: str, resume: bool = False):
    from ultralytics import YOLO

    print("\n" + "=" * 72)
    print(f"FINE-TUNING SPECIALIST: {category.upper()} (source: dataset_fine tune/{cfg['folder']})")
    print("=" * 72)
    t0 = time.time()

    last_pt = FT_RUNS_DIR / f"model_{category}" / "weights" / "last.pt"
    resume_checkpoint = last_pt if resume and last_pt.exists() else None
    if resume_checkpoint:
        cat_ft_dir = FT_DATA_BASE / category
        for split in ["train", "val", "val_ft"]:
            if not (cat_ft_dir / split).is_dir():
                raise FileNotFoundError(f"Cannot resume without original dataset split: {cat_ft_dir / split}")
        stats = {}
        print(f"  Resuming checkpoint: {resume_checkpoint}")
    else:
        cat_ft_dir, stats = prepare_category_dataset(category, cfg)
    for cls, st in stats.items():
        print(
            f"  [{cls}] unique={st['unique_total']} | "
            f"ft_train={st['ft_train_unique']} (x{st['ft_train_oversampled'] // max(1, st['ft_train_unique'])}={st['ft_train_oversampled']}) | "
            f"ft_val={st['ft_val_unique']}"
        )

    base_pt = ORIG_RUNS_DIR / f"model_{category}" / "weights" / "best.pt"
    if not base_pt.exists():
        raise FileNotFoundError(f"Base weights not found: {base_pt}")

    orig_val_dir = ORIG_DATA_BASE / category / "val"
    ft_val_dir = cat_ft_dir / "val_ft"

    # Baseline evaluation
    base_orig_acc, base_oc, base_ot, base_orig_cls = evaluate_pt_model(base_pt, orig_val_dir, device)
    base_ft_acc, base_fc, base_ft, base_ft_cls = evaluate_pt_model(base_pt, ft_val_dir, device)
    print(f"  [BASELINE] orig_val={base_orig_acc:.2%} ({base_oc}/{base_ot}) | ft_val={base_ft_acc:.2%} ({base_fc}/{base_ft}) {base_ft_cls}")

    # Fine-tune from existing best.pt
    model = YOLO(str(resume_checkpoint or base_pt))
    if resume_checkpoint and (model.ckpt.get("epoch", -1) < 0 or model.ckpt.get("optimizer") is None):
        raise ValueError(f"Checkpoint is already finalized and cannot be resumed: {resume_checkpoint}")
    save_name = f"model_{category}"
    model.train(
        resume=bool(resume_checkpoint),
        data=str(cat_ft_dir),
        epochs=5,
        imgsz=384,
        batch=16,
        device=device,
        workers=6,
        freeze=4,
        patience=3,
        optimizer="AdamW",
        lr0=0.0003,
        degrees=10.0,
        scale=0.25,
        fliplr=0.5,
        perspective=0.001,
        hsv_h=0.015,
        hsv_s=0.35,
        hsv_v=0.35,
        erasing=0.2,
        save=True,
        project=str(FT_RUNS_DIR),
        name=save_name,
        exist_ok=True,
        verbose=False,
    )

    ft_best_pt = FT_RUNS_DIR / save_name / "weights" / "best.pt"
    if not ft_best_pt.exists():
        raise FileNotFoundError(f"Fine-tuned best.pt not found at {ft_best_pt}")

    # Post-finetune evaluation
    new_orig_acc, new_oc, new_ot, new_orig_cls = evaluate_pt_model(ft_best_pt, orig_val_dir, device)
    new_ft_acc, new_fc, new_ft, new_ft_cls = evaluate_pt_model(ft_best_pt, ft_val_dir, device)
    print(f"  [FINETUNED] orig_val={new_orig_acc:.2%} ({new_oc}/{new_ot}) | ft_val={new_ft_acc:.2%} ({new_fc}/{new_ft}) {new_ft_cls}")

    # Export to ONNX and deploy to cv-service/models/
    trained_model = YOLO(str(ft_best_pt))
    onnx_file = Path(trained_model.export(format="onnx", imgsz=384, simplify=True))
    dest_onnx = CV_MODELS_DIR / f"{category}_classifier.onnx"
    shutil.copy2(onnx_file, dest_onnx)
    verify_onnx(dest_onnx)

    import gc
    import torch
    del model, trained_model
    gc.collect()
    if torch.cuda.is_available():
        torch.cuda.empty_cache()

    elapsed = round(time.time() - t0, 1)
    print(f"  ✓ Exported & verified {dest_onnx.name} in {elapsed}s")

    return {
        "category": category,
        "base_orig_acc": base_orig_acc,
        "new_orig_acc": new_orig_acc,
        "base_ft_acc": base_ft_acc,
        "new_ft_acc": new_ft_acc,
        "ft_val_counts": f"{new_fc}/{new_ft}",
        "base_ft_counts": f"{base_fc}/{base_ft}",
        "orig_val_counts": f"{new_oc}/{new_ot}",
        "new_ft_cls": new_ft_cls,
        "new_orig_cls": new_orig_cls,
    }


def detect_device():
    import torch

    if torch.cuda.is_available():
        try:
            _ = (torch.zeros(1, device="cuda") + 1).item()
            return "0"
        except Exception:
            pass
    return "cpu"


def main():
    device = detect_device()
    print(f"Using device: {device}")

    targets = [arg for arg in sys.argv[1:] if arg in FINETUNE_MAPPING] or list(FINETUNE_MAPPING.keys())
    summaries = []
    for cat in targets:
        summaries.append(finetune_category(cat, FINETUNE_MAPPING[cat], device, resume="--resume" in sys.argv[1:]))

    print("\n" + "=" * 72)
    print("FINE-TUNING SUMMARY")
    print("=" * 72)
    for s in summaries:
        print(
            f"  {s['category']:14s} | "
            f"orig_val: {s['base_orig_acc']:.2%} -> {s['new_orig_acc']:.2%} ({s['orig_val_counts']}) | "
            f"ft_val: {s['base_ft_acc']:.2%} -> {s['new_ft_acc']:.2%} ({s['base_ft_counts']} -> {s['ft_val_counts']})"
        )


if __name__ == "__main__":
    main()
