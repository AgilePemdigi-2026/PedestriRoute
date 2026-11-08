#!/usr/bin/env python3
"""
PedestriRoute - Multi-Category Specialist Training & ONNX Export Pipeline (384x384)
===================================================================================
Trains dedicated YOLOv8s-cls specialist models for all 5 pedestrian defect categories:
  1. trotoar        -> cv-service/models/trotoar_classifier.onnx
  2. drainase       -> cv-service/models/drainase_classifier.onnx
  3. guiding_block  -> cv-service/models/guiding_block_classifier.onnx
  4. parkir_liar    -> cv-service/models/parkir_liar_classifier.onnx
  5. penyebrangan   -> cv-service/models/penyebrangan_classifier.onnx

Key Advantages:
  - Resolution 384x384: captures fine crack lines, tile dislodgements, and tactile paving studs.
  - Domain Isolation: zero cross-category conflict (e.g. sidewalk crack never competes with drainase).
  - High Capacity: YOLOv8s (5.08M params) with deep feature fine-tuning (freeze=4).
"""

import os
import sys
import shutil
import time

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_BASE = os.path.join(BASE_DIR, "datasets_per_category")
CV_MODELS_DIR = os.path.join(BASE_DIR, "cv-service", "models")
RUNS_DIR = os.path.join(BASE_DIR, "runs", "per_category")

CATEGORIES = [
    "trotoar",
    "drainase",
    "guiding_block",
    "parkir_liar",
    "penyebrangan"
]


def train_category(category_name, epochs=6, imgsz=384, batch=32):
    from ultralytics import YOLO

    cat_data_dir = os.path.join(DATA_BASE, category_name)
    if not os.path.exists(cat_data_dir):
        print(f"Skipping {category_name}: dataset directory not found at {cat_data_dir}")
        return False

    print("\n" + "=" * 70)
    print(f"STARTING TRAINING: {category_name.upper()} SPECIALIST (YOLOv8s-cls @ {imgsz}x{imgsz})")
    print("=" * 70)
    t0 = time.time()

    model = YOLO("yolov8s-cls.pt")
    save_run_name = f"model_{category_name}"

    model.train(
        data=cat_data_dir,
        epochs=epochs,
        imgsz=imgsz,
        batch=batch,
        device="cpu",
        workers=6,
        freeze=4,           # Fine-tune layers 4..9 for fine-grained paving & surface defects
        patience=3,
        optimizer="AdamW",
        lr0=0.0008,
        # Smartphone-realistic Augmentations
        degrees=10.0,
        scale=0.25,
        fliplr=0.5,
        perspective=0.001,
        hsv_h=0.015,
        hsv_s=0.35,
        hsv_v=0.35,
        erasing=0.2,
        save=True,
        project=RUNS_DIR,
        name=save_run_name,
        exist_ok=True,
    )

    best_pt = os.path.join(RUNS_DIR, save_run_name, "weights", "best.pt")
    if os.path.exists(best_pt):
        print(f"\nExporting {category_name} to ONNX ({imgsz}x{imgsz})...")
        trained_model = YOLO(best_pt)
        onnx_file = trained_model.export(format="onnx", imgsz=imgsz, simplify=True)

        os.makedirs(CV_MODELS_DIR, exist_ok=True)
        dest_onnx = os.path.join(CV_MODELS_DIR, f"{category_name}_classifier.onnx")
        shutil.copy(onnx_file, dest_onnx)
        elapsed = round((time.time() - t0) / 60, 2)
        print(f"✓ [{category_name}] Specialist Model saved to: {dest_onnx} (Took {elapsed} mins)")
        return True
    else:
        print(f"ERROR: best.pt not found for {category_name} at {best_pt}")
        return False


def main():
    try:
        from ultralytics import YOLO
    except ImportError:
        print("Error: ultralytics is not installed.")
        sys.exit(1)

    os.makedirs(CV_MODELS_DIR, exist_ok=True)

    # Allow targeting a single category or all 5
    if len(sys.argv) > 1 and sys.argv[1] in CATEGORIES:
        targets = [sys.argv[1]]
    else:
        targets = CATEGORIES

    print("\n" + "=" * 70)
    print(f"TRAINING PLAN: {len(targets)} SPECIALIST MODELS -> {targets}")
    print("=" * 70)

    total_start = time.time()
    results = {}

    for cat in targets:
        success = train_category(cat)
        results[cat] = "SUCCESS" if success else "FAILED"

    total_elapsed = round((time.time() - total_start) / 60, 2)
    print("\n" + "=" * 70)
    print(f"ALL SPECIALIST TRAININGS COMPLETED IN {total_elapsed} MINUTES!")
    for cat, status in results.items():
        print(f"  - {cat:15s}: {status}")
    print("=" * 70)


if __name__ == "__main__":
    main()
