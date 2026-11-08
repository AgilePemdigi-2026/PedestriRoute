#!/usr/bin/env python3
"""
Finalize PedestriRoute Dataset:
1. Re-balances validation splits for classes with 0 val images (3_guiding_block_rusak, 5_parkir_liar).
2. Adds '6_spam_non_infrastructure' class (~500 images) from Datasets/indoor_spam.zip.
"""

import os
import glob
import random
import shutil
import zipfile
from io import BytesIO
from PIL import Image

RANDOM_SEED = 42
random.seed(RANDOM_SEED)

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATASETS_DIR = os.path.join(BASE_DIR, "Datasets")
OUTPUT_DIR = os.path.join(BASE_DIR, "pedestriroute_classification_data")
IMG_SIZE = (224, 224)


def rebalance_val_splits():
    print("[1/2] Checking and balancing validation splits for all classes...")
    for class_name in ["3_guiding_block_rusak", "5_parkir_liar"]:
        train_dir = os.path.join(OUTPUT_DIR, "train", class_name)
        val_dir = os.path.join(OUTPUT_DIR, "val", class_name)
        os.makedirs(val_dir, exist_ok=True)

        train_files = glob.glob(os.path.join(train_dir, "*.jpg"))
        val_files = glob.glob(os.path.join(val_dir, "*.jpg"))

        if len(val_files) == 0 and len(train_files) > 100:
            # Move 12% to val
            num_val = int(len(train_files) * 0.12)
            random.shuffle(train_files)
            to_move = train_files[:num_val]
            for f in to_move:
                dest = os.path.join(val_dir, os.path.basename(f))
                shutil.move(f, dest)
            print(f"  -> {class_name}: Moved {len(to_move)} images from train to val (Train: {len(train_files) - len(to_move)}, Val: {len(to_move)})")
        else:
            print(f"  -> {class_name}: Already has {len(train_files)} train, {len(val_files)} val")


def add_spam_class():
    print("\n[2/2] Adding '6_spam_non_infrastructure' Class (~500 images)...")
    spam_zip = os.path.join(DATASETS_DIR, "indoor_spam.zip")
    if not os.path.exists(spam_zip):
        print("  Warning: indoor_spam.zip not found in Datasets/")
        return

    train_spam_dir = os.path.join(OUTPUT_DIR, "train", "6_spam_non_infrastructure")
    val_spam_dir = os.path.join(OUTPUT_DIR, "val", "6_spam_non_infrastructure")
    os.makedirs(train_spam_dir, exist_ok=True)
    os.makedirs(val_spam_dir, exist_ok=True)

    with zipfile.ZipFile(spam_zip, 'r') as zf:
        img_files = [f for f in zf.namelist() if f.lower().endswith((".jpg", ".png", ".jpeg"))]
        print(f"  indoor_spam.zip: Found {len(img_files)} candidate non-pedestrian images")
        random.shuffle(img_files)

        total_needed = 500
        selected = img_files[:total_needed]
        train_files = selected[:440]
        val_files = selected[440:]

        # Save train
        saved_train = 0
        for idx, f in enumerate(train_files):
            try:
                im = Image.open(BytesIO(zf.read(f))).convert("RGB")
                im = im.resize(IMG_SIZE, Image.Resampling.BILINEAR)
                dest = os.path.join(train_spam_dir, f"spam_{idx:05d}.jpg")
                im.save(dest, "JPEG", quality=85)
                saved_train += 1
            except Exception:
                pass

        # Save val
        saved_val = 0
        for idx, f in enumerate(val_files):
            try:
                im = Image.open(BytesIO(zf.read(f))).convert("RGB")
                im = im.resize(IMG_SIZE, Image.Resampling.BILINEAR)
                dest = os.path.join(val_spam_dir, f"spam_{idx:05d}.jpg")
                im.save(dest, "JPEG", quality=85)
                saved_val += 1
            except Exception:
                pass

        print(f"  -> 6_spam_non_infrastructure: Saved {saved_train} train images, {saved_val} val images")


def print_summary():
    print("\n" + "=" * 70)
    print("FINAL DATASET SUMMARY")
    print("=" * 70)
    print(f"{'Class Name':<30} | {'Train Images':<14} | {'Val Images':<12}")
    print("-" * 62)
    total_train = 0
    total_val = 0
    all_classes = sorted(os.listdir(os.path.join(OUTPUT_DIR, "train")))
    for c in all_classes:
        n_train = len(glob.glob(os.path.join(OUTPUT_DIR, "train", c, "*.jpg")))
        n_val = len(glob.glob(os.path.join(OUTPUT_DIR, "val", c, "*.jpg")))
        total_train += n_train
        total_val += n_val
        print(f"{c:<30} | {n_train:<14} | {n_val:<12}")
    print("-" * 62)
    print(f"{'TOTAL':<30} | {total_train:<14} | {total_val:<12}")
    print("=" * 70)


if __name__ == "__main__":
    rebalance_val_splits()
    add_spam_class()
    print_summary()
