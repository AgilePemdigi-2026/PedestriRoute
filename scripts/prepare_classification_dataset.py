#!/usr/bin/env python3
"""
PedestriRoute - Dataset Assembly & Preprocessing Script
======================================================
This script extracts, samples, balances, and organizes the raw datasets in Datasets/
into the unified YOLO classification directory:

pedestriroute_classification_data/
├── train/
│   ├── 0_normal_baik/
│   ├── 1_trotoar_rusak/
│   ├── 2_drainase_rusak/
│   ├── 3_guiding_block_rusak/
│   ├── 4_penyebrangan_rusak/
│   └── 5_parkir_liar/
└── val/
    ├── 0_normal_baik/
    ...

Target: ~2,000 images/class for train, ~250 images/class for val.
"""

import os
import glob
import random
import shutil
import zipfile
import tarfile
from io import BytesIO
import numpy as np
from PIL import Image, ImageEnhance, ImageFilter

# Configuration
RANDOM_SEED = 42
random.seed(RANDOM_SEED)
np.random.seed(RANDOM_SEED)

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATASETS_DIR = os.path.join(BASE_DIR, "Datasets")
OUTPUT_DIR = os.path.join(BASE_DIR, "pedestriroute_classification_data")

TRAIN_PER_CLASS = 2000
VAL_PER_CLASS = 250
IMG_SIZE = (224, 224)

CLASSES = [
    "0_normal_baik",
    "1_trotoar_rusak",
    "2_drainase_rusak",
    "3_guiding_block_rusak",
    "4_penyebrangan_rusak",
    "5_parkir_liar",
]


def init_dirs():
    """Create clean output directory structure."""
    if os.path.exists(OUTPUT_DIR):
        print(f"Cleaning existing directory: {OUTPUT_DIR}")
        shutil.rmtree(OUTPUT_DIR)
    for split in ["train", "val"]:
        for c in CLASSES:
            os.makedirs(os.path.join(OUTPUT_DIR, split, c), exist_ok=True)
    print("Created directory structure for pedestriroute_classification_data/")


def save_img(img, dest_path):
    """Ensure RGB, resize to 224x224, save JPEG."""
    try:
        if img.mode != "RGB":
            img = img.convert("RGB")
        img = img.resize(IMG_SIZE, Image.Resampling.BILINEAR)
        img.save(dest_path, "JPEG", quality=85)
        return True
    except Exception as e:
        return False


def save_split_images(images_list, class_name):
    """
    Given a list of PIL Images (or callables returning PIL Images),
    shuffles and splits into train and val folders.
    """
    random.shuffle(images_list)
    total_needed = TRAIN_PER_CLASS + VAL_PER_CLASS
    selected = images_list[:total_needed]

    train_items = selected[:TRAIN_PER_CLASS]
    val_items = selected[TRAIN_PER_CLASS:TRAIN_PER_CLASS + VAL_PER_CLASS]

    # Save Train
    saved_train = 0
    for idx, item in enumerate(train_items):
        img = item() if callable(item) else item
        if img is not None:
            dest = os.path.join(OUTPUT_DIR, "train", class_name, f"{class_name}_{idx:05d}.jpg")
            if save_img(img, dest):
                saved_train += 1

    # Save Val
    saved_val = 0
    for idx, item in enumerate(val_items):
        img = item() if callable(item) else item
        if img is not None:
            dest = os.path.join(OUTPUT_DIR, "val", class_name, f"{class_name}_{idx:05d}.jpg")
            if save_img(img, dest):
                saved_val += 1

    print(f"  -> {class_name}: Saved {saved_train} train images, {saved_val} val images")


# ==============================================================================
# 1. Trotoar Rusak & Normal Baik (Kaggle Surface Crack Detection + Sidewalk Damage)
# ==============================================================================
def process_surface_cracks():
    print("\n[1/5] Processing Surface Crack Detection & Sidewalk Damage...")
    archive_path = os.path.join(DATASETS_DIR, "archive.zip")
    normal_candidates = []
    crack_candidates = []

    if os.path.exists(archive_path):
        with zipfile.ZipFile(archive_path, 'r') as zf:
            all_files = zf.namelist()
            neg_files = [f for f in all_files if f.startswith("Negative/") and f.lower().endswith((".jpg", ".png"))]
            pos_files = [f for f in all_files if f.startswith("Positive/") and f.lower().endswith((".jpg", ".png"))]

            print(f"  archive.zip: Found {len(neg_files)} Negative, {len(pos_files)} Positive images")
            random.shuffle(neg_files)
            random.shuffle(pos_files)

            # Lazy loaders from zip
            for f in neg_files[:2200]:
                def load_neg(fname=f):
                    with zipfile.ZipFile(archive_path, 'r') as z:
                        return Image.open(BytesIO(z.read(fname)))
                normal_candidates.append(load_neg)

            for f in pos_files[:1600]:
                def load_pos(fname=f):
                    with zipfile.ZipFile(archive_path, 'r') as z:
                        return Image.open(BytesIO(z.read(fname)))
                crack_candidates.append(load_pos)

    # Supplement trotoar_rusak with Daos Sidewalk-Damage-1 (real sidewalk slabs)
    daos_imgs = glob.glob(os.path.join(DATASETS_DIR, "Sidewalk-Damage-1", "**", "*.jpg"), recursive=True)
    print(f"  Sidewalk-Damage-1: Found {len(daos_imgs)} real sidewalk damage images")
    for img_path in daos_imgs:
        def load_daos(p=img_path):
            try:
                return Image.open(p)
            except Exception:
                return None
        crack_candidates.append(load_daos)

    save_split_images(crack_candidates, "1_trotoar_rusak")
    return normal_candidates


# ==============================================================================
# 2. Drainase Rusak (Manhole Defect Datasets)
# ==============================================================================
def process_manholes(normal_candidates):
    print("\n[2/5] Processing Manhole & Drainage Defect Datasets...")
    drainase_rusak_candidates = []

    # A. Manhole-Cover-Dataset-YOLO-1
    # classes: 0: Broken, 1: Good, 2: Lose, 3: Uncovered
    yolo_dir = os.path.join(DATASETS_DIR, "Manhole-Cover-Dataset-YOLO-1")
    if os.path.exists(yolo_dir):
        for split in ["train", "valid", "test"]:
            lbl_dir = os.path.join(yolo_dir, split, "labels")
            img_dir = os.path.join(yolo_dir, split, "images")
            for lbl_path in glob.glob(os.path.join(lbl_dir, "*.txt")):
                base_name = os.path.splitext(os.path.basename(lbl_path))[0]
                img_path = os.path.join(img_dir, f"{base_name}.jpg")
                if not os.path.exists(img_path):
                    continue

                with open(lbl_path, "r") as f:
                    cids = {int(line.strip().split()[0]) for line in f if line.strip().split()}

                def load_mh(p=img_path):
                    try:
                        return Image.open(p)
                    except Exception:
                        return None

                if any(c in [0, 2, 3] for c in cids):
                    drainase_rusak_candidates.append(load_mh)
                elif 1 in cids:
                    normal_candidates.append(load_mh)

    # B. Manhole-cover_copy-1
    # classes: 0: broke, 1: circle, 2: good, 3: loss, 4: uncovered
    copy_dir = os.path.join(DATASETS_DIR, "Manhole-cover_copy-1")
    if os.path.exists(copy_dir):
        for split in ["train", "valid", "test"]:
            lbl_dir = os.path.join(copy_dir, split, "labels")
            img_dir = os.path.join(copy_dir, split, "images")
            for lbl_path in glob.glob(os.path.join(lbl_dir, "*.txt")):
                base_name = os.path.splitext(os.path.basename(lbl_path))[0]
                img_path = os.path.join(img_dir, f"{base_name}.jpg")
                if not os.path.exists(img_path):
                    continue

                with open(lbl_path, "r") as f:
                    cids = {int(line.strip().split()[0]) for line in f if line.strip().split()}

                def load_mh_copy(p=img_path):
                    try:
                        return Image.open(p)
                    except Exception:
                        return None

                if any(c in [0, 3, 4] for c in cids):
                    drainase_rusak_candidates.append(load_mh_copy)
                elif any(c in [1, 2] for c in cids):
                    normal_candidates.append(load_mh_copy)

    print(f"  Extracted {len(drainase_rusak_candidates)} broken/open drain images")
    save_split_images(drainase_rusak_candidates, "2_drainase_rusak")


# ==============================================================================
# 3. Guiding Block Rusak (TP-Dataset + Defect Simulation)
# ==============================================================================
def process_tactile_paving(normal_candidates):
    print("\n[3/5] Processing Tactile Paving Guiding Blocks...")
    tp_zip = os.path.join(DATASETS_DIR, "TP_Dataset", "TP-Dataset.zip")
    guiding_block_candidates = []

    if os.path.exists(tp_zip):
        with zipfile.ZipFile(tp_zip, 'r') as zf:
            jpeg_files = [f for f in zf.namelist() if "JPEGImages/" in f and f.lower().endswith((".jpg", ".png"))]
            print(f"  TP-Dataset.zip: Found {len(jpeg_files)} tactile paving photos")
            random.shuffle(jpeg_files)

            # Intact guiding blocks go to normal_baik
            for f in jpeg_files[:400]:
                def load_clean_tp(fname=f):
                    with zipfile.ZipFile(tp_zip, 'r') as z:
                        return Image.open(BytesIO(z.read(fname)))
                normal_candidates.append(load_clean_tp)

            # Damaged / broken guiding blocks (synthetic chipping/patching over yellow strips)
            for f in jpeg_files[400:2800]:
                def load_damaged_tp(fname=f):
                    with zipfile.ZipFile(tp_zip, 'r') as z:
                        im = Image.open(BytesIO(z.read(fname))).convert("RGB")
                    # Synthesize broken tile/chipping by overlaying concrete gray patches
                    arr = np.array(im)
                    h, w, _ = arr.shape
                    # Apply 1 to 3 random defect patches
                    for _ in range(random.randint(1, 3)):
                        ph, pw = random.randint(h // 6, h // 3), random.randint(w // 6, w // 3)
                        py, px = random.randint(0, h - ph), random.randint(0, w - pw)
                        # Dark gray concrete / asphalt patch
                        gray_val = random.randint(70, 110)
                        noise = np.random.normal(gray_val, 15, (ph, pw, 3)).clip(0, 255).astype(np.uint8)
                        arr[py:py+ph, px:px+pw] = noise
                    return Image.fromarray(arr)
                guiding_block_candidates.append(load_damaged_tp)

    save_split_images(guiding_block_candidates, "3_guiding_block_rusak")


# ==============================================================================
# 4. Penyebrangan Rusak (CDNet + Wear/Erosion Augmentation)
# ==============================================================================
def process_crosswalks(normal_candidates):
    print("\n[4/5] Processing Crosswalk / Zebra Crossing Dataset...")
    cdnet_tar = os.path.join(DATASETS_DIR, "train_data_v5_format_3080.tar")
    crosswalk_candidates = []

    if os.path.exists(cdnet_tar):
        with tarfile.open(cdnet_tar, "r") as tf:
            img_members = [m for m in tf.getmembers() if m.name.endswith(".jpg") and "images/train/" in m.name]
            print(f"  CDNet tar: Found {len(img_members)} crosswalk photos")
            random.shuffle(img_members)

            # Intact crisp crosswalks go to normal_baik
            for m in img_members[:400]:
                def load_clean_cw(member_name=m.name):
                    with tarfile.open(cdnet_tar, "r") as t:
                        f = t.extractfile(member_name)
                        return Image.open(BytesIO(f.read()))
                normal_candidates.append(load_clean_cw)

            # Faded, worn-out zebra crossings
            for m in img_members[400:2800]:
                def load_worn_cw(member_name=m.name):
                    with tarfile.open(cdnet_tar, "r") as t:
                        f = t.extractfile(member_name)
                        im = Image.open(BytesIO(f.read())).convert("RGB")
                    # Synthesize paint wear: lower contrast, reduce white saturation, slight asphalt color blend
                    enhancer = ImageEnhance.Contrast(im)
                    im = enhancer.enhance(random.uniform(0.65, 0.85))
                    color_enh = ImageEnhance.Color(im)
                    im = color_enh.enhance(random.uniform(0.7, 0.9))
                    # Add subtle surface erosion noise
                    arr = np.array(im)
                    h, w, _ = arr.shape
                    mask = (np.random.rand(h, w) > 0.88).astype(np.float32)
                    for c in range(3):
                        arr[:, :, c] = np.clip(arr[:, :, c] * (1.0 - mask * 0.35) + mask * 60, 0, 255)
                    return Image.fromarray(arr.astype(np.uint8))
                crosswalk_candidates.append(load_worn_cw)

    save_split_images(crosswalk_candidates, "4_penyebrangan_rusak")


# ==============================================================================
# 5. Parkir Liar (Sidewalk Parking & Illegal Parking)
# ==============================================================================
def process_parking():
    print("\n[5/5] Processing Parkir Liar (Sidewalk & Illegal Parking)...")
    parking_candidates = []

    # A. sidewalk-parking-1
    sp_imgs = glob.glob(os.path.join(DATASETS_DIR, "sidewalk-parking-1", "**", "*.jpg"), recursive=True)
    print(f"  sidewalk-parking-1: Found {len(sp_imgs)} vehicle on sidewalk photos")
    for img_path in sp_imgs:
        def load_sp(p=img_path):
            try:
                return Image.open(p)
            except Exception:
                return None
        parking_candidates.append(load_sp)

    # B. illegal-parking-detection-1
    ip_imgs = glob.glob(os.path.join(DATASETS_DIR, "illegal-parking-detection-1", "**", "*.jpg"), recursive=True)
    print(f"  illegal-parking-detection-1: Found {len(ip_imgs)} illegal parking photos")
    for img_path in ip_imgs:
        def load_ip(p=img_path):
            try:
                return Image.open(p)
            except Exception:
                return None
        parking_candidates.append(load_ip)

    save_split_images(parking_candidates, "5_parkir_liar")


def main():
    print("=" * 70)
    print("PEDESTRIRoute DATASET ASSEMBLY PIPELINE")
    print("=" * 70)
    init_dirs()

    # Process all categories
    normal_candidates = process_surface_cracks()
    process_manholes(normal_candidates)
    process_tactile_paving(normal_candidates)
    process_crosswalks(normal_candidates)
    process_parking()

    # Finally, save normal_baik (collected from clean baseline across infrastructure)
    print("\n[Final] Saving Class 0: normal_baik (Sound Infrastructure Baseline)...")
    save_split_images(normal_candidates, "0_normal_baik")

    # Summary
    print("\n" + "=" * 70)
    print("DATASET ASSEMBLY SUMMARY")
    print("=" * 70)
    print(f"{'Class Name':<28} | {'Train Images':<14} | {'Val Images':<12}")
    print("-" * 60)
    total_train = 0
    total_val = 0
    for c in CLASSES:
        n_train = len(glob.glob(os.path.join(OUTPUT_DIR, "train", c, "*.jpg")))
        n_val = len(glob.glob(os.path.join(OUTPUT_DIR, "val", c, "*.jpg")))
        total_train += n_train
        total_val += n_val
        print(f"{c:<28} | {n_train:<14} | {n_val:<12}")
    print("-" * 60)
    print(f"{'TOTAL':<28} | {total_train:<14} | {total_val:<12}")
    print("=" * 70)
    print(f"Ready for training in: {OUTPUT_DIR}")


if __name__ == "__main__":
    main()
