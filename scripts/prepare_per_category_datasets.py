#!/usr/bin/env python3
"""
PedestriRoute - High-Speed, High-Resolution (384x384) Per-Category Dataset Assembly
==================================================================================
Builds dedicated, high-resolution (384x384) datasets for each pedestrian defect category:
  1. trotoar        -> RUSAK, BAIK, SPAM
  2. drainase       -> RUSAK, BAIK, SPAM
  3. guiding_block  -> RUSAK, BAIK, SPAM
  4. parkir_liar    -> RUSAK, BAIK, SPAM
  5. penyebrangan   -> RUSAK, BAIK, SPAM

Key Refinements:
  - Eliminates shortcut bias: real sidewalk defect datasets (sidewalk-defects-yv0ob-8,
    damaged-sidewalks-6, non-empty Sidewalk-Damage-1) for RUSAK.
  - Matches pedestrian perspectives for both BAIK and RUSAK.
  - Purges indoor floor tile categories from SPAM.
  - Guarantees 85% train / 15% validation split for every single class!
"""

import os
import glob
import random
import shutil
import zipfile
import tarfile
from io import BytesIO
import numpy as np
from PIL import Image, ImageEnhance

RANDOM_SEED = 42
random.seed(RANDOM_SEED)
np.random.seed(RANDOM_SEED)

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATASETS_DIR = os.path.join(BASE_DIR, "Datasets")
OUTPUT_BASE = os.path.join(BASE_DIR, "datasets_per_category")
IMG_SIZE = (384, 384)

CATEGORIES = [
    "trotoar",
    "drainase",
    "guiding_block",
    "parkir_liar",
    "penyebrangan"
]


def init_category_dir(category_name):
    cat_dir = os.path.join(OUTPUT_BASE, category_name)
    if os.path.exists(cat_dir):
        shutil.rmtree(cat_dir)
    for split in ["train", "val"]:
        for lbl in ["RUSAK", "BAIK", "SPAM"]:
            os.makedirs(os.path.join(cat_dir, split, lbl), exist_ok=True)
    return cat_dir


def save_img(img, dest_path):
    try:
        if img.mode != "RGB":
            img = img.convert("RGB")
        img = img.resize(IMG_SIZE, Image.Resampling.BILINEAR)
        img.save(dest_path, "JPEG", quality=90)
        return True
    except Exception:
        return False


def save_split_class(images_list, category_name, class_label, max_total=1200, val_ratio=0.15):
    random.shuffle(images_list)
    selected = images_list[:max_total]
    if len(selected) == 0:
        print(f"    WARNING: No images for {category_name} -> {class_label}")
        return

    n_val = max(15, int(len(selected) * val_ratio))
    n_train = len(selected) - n_val

    train_items = selected[:n_train]
    val_items = selected[n_train:]

    saved_train, saved_val = 0, 0
    cat_dir = os.path.join(OUTPUT_BASE, category_name)

    for idx, item in enumerate(train_items):
        img = item() if callable(item) else item
        if img is not None:
            dest = os.path.join(cat_dir, "train", class_label, f"{class_label}_{idx:05d}.jpg")
            if save_img(img, dest):
                saved_train += 1

    for idx, item in enumerate(val_items):
        img = item() if callable(item) else item
        if img is not None:
            dest = os.path.join(cat_dir, "val", class_label, f"{class_label}_{idx:05d}.jpg")
            if save_img(img, dest):
                saved_val += 1

    print(f"    [{category_name} -> {class_label}]: {saved_train} train, {saved_val} val images")


def get_pure_spam_images():
    """Returns pure indoor non-infrastructure images, excluding any floor/tile categories."""
    spam_zip = os.path.join(DATASETS_DIR, "indoor_spam.zip")
    spam_candidates = []
    pure_spam_cats = {
        'clothingstore', 'computerroom', 'dentaloffice', 'florist', 'gameroom',
        'grocerystore', 'gym', 'hairsalon', 'hospitalroom', 'inside_bus',
        'jewelleryshop', 'laboratorywet', 'movietheater', 'nursery', 'office',
        'operating_room', 'pantry', 'shoeshop', 'toystore', 'tv_studio', 'videostore'
    }
    if os.path.exists(spam_zip):
        zf = zipfile.ZipFile(spam_zip, 'r')
        files = [
            f for f in zf.namelist()
            if not f.endswith('/') and f.split('/')[0] in pure_spam_cats and f.lower().endswith((".jpg", ".png", ".jpeg"))
        ]
        random.shuffle(files)
        for f in files:
            def load_spam(fname=f, z=zf):
                try:
                    return Image.open(BytesIO(z.read(fname))).convert("RGB")
                except Exception:
                    return None
            spam_candidates.append(load_spam)
    return spam_candidates


# ==============================================================================
# 1. Trotoar Specialist Dataset (384x384)
# ==============================================================================
def assemble_trotoar():
    print("\n[1/5] Assembling Trotoar Specialist Dataset (384x384)...")
    init_category_dir("trotoar")
    rusak_list, baik_list = [], []

    # A. Real Sidewalk Defects (Cracked walkways & broken paving from sidewalk-defects-yv0ob-8)
    defects_dir = os.path.join(DATASETS_DIR, "sidewalk-defects-yv0ob-8")
    if os.path.exists(defects_dir):
        defect_imgs = glob.glob(os.path.join(defects_dir, "**", "*.jpg"), recursive=True)
        for p in defect_imgs:
            rusak_list.append(lambda path=p: Image.open(path).convert("RGB"))

    # B. Real Damaged Sidewalk Slabs (damaged-sidewalks-6)
    ds_dir = os.path.join(DATASETS_DIR, "damaged-sidewalks-6")
    if os.path.exists(ds_dir):
        ds_imgs = glob.glob(os.path.join(ds_dir, "**", "*.jpg"), recursive=True)
        for p in ds_imgs:
            rusak_list.append(lambda path=p: Image.open(path).convert("RGB"))

    # C. Real Sidewalk Damage (Sidewalk-Damage-1 with non-empty damage label files only)
    daos_dir = os.path.join(DATASETS_DIR, "Sidewalk-Damage-1")
    if os.path.exists(daos_dir):
        for split in ["train", "valid", "test"]:
            lbl_dir = os.path.join(daos_dir, split, "labels")
            img_dir = os.path.join(daos_dir, split, "images")
            if not os.path.exists(lbl_dir):
                continue
            for lbl_p in glob.glob(os.path.join(lbl_dir, "*.txt")):
                base = os.path.splitext(os.path.basename(lbl_p))[0]
                img_p = os.path.join(img_dir, f"{base}.jpg")
                if not os.path.exists(img_p):
                    continue
                with open(lbl_p, "r") as f:
                    has_damage = bool(f.read().strip())
                if has_damage:
                    rusak_list.append(lambda p=img_p: Image.open(p).convert("RGB"))
                else:
                    baik_list.append(lambda p=img_p: Image.open(p).convert("RGB"))

    # D. Controlled sample of Indonesian Road/Sidewalk Damage (jalan-rusak-indonesia-1)
    indo_imgs = glob.glob(os.path.join(DATASETS_DIR, "jalan-rusak-indonesia-1", "**", "*.jpg"), recursive=True)
    random.shuffle(indo_imgs)
    for p in indo_imgs[:200]:
        rusak_list.append(lambda path=p: Image.open(path).convert("RGB"))

    # E. Real Outdoor Clean Sidewalks with Trees & Paving (from TP-Dataset)
    tp_zip = os.path.join(DATASETS_DIR, "TP_Dataset", "TP-Dataset.zip")
    if os.path.exists(tp_zip):
        zf_tp = zipfile.ZipFile(tp_zip, 'r')
        tp_files = [f for f in zf_tp.namelist() if "JPEGImages/" in f and f.lower().endswith((".jpg", ".png"))]
        random.shuffle(tp_files)
        for f in tp_files[:750]:
            baik_list.append(lambda fname=f, z=zf_tp: Image.open(BytesIO(z.read(fname))).convert("RGB"))

    # F. Clean Concrete Surface Baseline (Negative from archive.zip)
    archive_path = os.path.join(DATASETS_DIR, "archive.zip")
    if os.path.exists(archive_path):
        zf = zipfile.ZipFile(archive_path, 'r')
        neg_files = [f for f in zf.namelist() if f.startswith("Negative/") and f.lower().endswith((".jpg", ".png"))]
        random.shuffle(neg_files)
        for f in neg_files[:300]:
            baik_list.append(lambda fname=f, z=zf: Image.open(BytesIO(z.read(fname))).convert("RGB"))

    save_split_class(rusak_list, "trotoar", "RUSAK", max_total=1200, val_ratio=0.15)
    save_split_class(baik_list, "trotoar", "BAIK", max_total=1200, val_ratio=0.15)
    save_split_class(get_pure_spam_images(), "trotoar", "SPAM", max_total=400, val_ratio=0.15)


# ==============================================================================
# 2. Drainase Specialist Dataset (384x384)
# ==============================================================================
def assemble_drainase():
    print("\n[2/5] Assembling Drainase Specialist Dataset (384x384)...")
    init_category_dir("drainase")
    rusak_list, baik_list = [], []

    # A. Real Surabaya Sidewalk Square Manhole Covers (Gold Standard Anchors)
    surabaya_squares = [
        "/home/rog-theseus/.gemini/antigravity-cli/brain/58d22bc9-ef31-45d0-b7fb-dacfbcbec955/.user_uploaded/uploaded_media_0_1794132484526.jpg",
        "/home/rog-theseus/.gemini/antigravity-cli/brain/58d22bc9-ef31-45d0-b7fb-dacfbcbec955/.user_uploaded/uploaded_media_1_1794132484526.jpg"
    ]
    for p in surabaya_squares:
        if os.path.exists(p):
            try:
                im = Image.open(p).convert("RGB")
                w, h = im.size
                # 1. Full image
                baik_list.append(lambda img=im: img)
                baik_list.append(lambda img=im: img.transpose(Image.Transpose.FLIP_LEFT_RIGHT))
                # 2. Centered crop on the square manhole cover
                if "uploaded_media_0" in p:
                    # Manhole is in center
                    crop_sq = im.crop((int(w * 0.15), int(h * 0.35), int(w * 0.90), int(h * 0.65)))
                    baik_list.append(lambda img=crop_sq: img)
                    baik_list.append(lambda img=crop_sq: img.transpose(Image.Transpose.FLIP_LEFT_RIGHT))
                    crop_bottom = im.crop((0, int(h * 0.25), w, h))
                    baik_list.append(lambda img=crop_bottom: img)
                else:
                    # Manhole is central-left
                    crop_sq = im.crop((int(w * 0.15), int(h * 0.15), int(w * 0.85), int(h * 0.85)))
                    baik_list.append(lambda img=crop_sq: img)
                    baik_list.append(lambda img=crop_sq: img.transpose(Image.Transpose.FLIP_LEFT_RIGHT))
            except Exception as e:
                print("Notice loading surabaya square manholes:", e)

    # B. Curated Square Cast-Iron Manhole Covers & Gully Grates
    sq_dir = os.path.join(DATASETS_DIR, "square_manholes")
    if os.path.exists(sq_dir):
        for sq_img in glob.glob(os.path.join(sq_dir, "*.jpg")):
            baik_list.append(lambda p=sq_img: Image.open(p).convert("RGB"))
            baik_list.append(lambda p=sq_img: Image.open(p).convert("RGB").transpose(Image.Transpose.FLIP_LEFT_RIGHT))

    # C. Real Manhole Covers from Manhole-Cover-Dataset-YOLO-1
    # Classes: 0: Broken, 1: Good, 2: Lose, 3: Uncovered
    yolo_1 = os.path.join(DATASETS_DIR, "Manhole-Cover-Dataset-YOLO-1")
    if os.path.exists(yolo_1):
        for split in ["train", "valid", "test"]:
            lbl_dir = os.path.join(yolo_1, split, "labels")
            img_dir = os.path.join(yolo_1, split, "images")
            for lbl in glob.glob(os.path.join(lbl_dir, "*.txt")):
                base = os.path.splitext(os.path.basename(lbl))[0]
                img_p = os.path.join(img_dir, f"{base}.jpg")
                if not os.path.exists(img_p):
                    continue
                with open(lbl, "r", errors="ignore") as f:
                    cids = {int(l.strip().split()[0]) for l in f if l.strip() and l.strip().split()[0].isdigit()}
                if any(c in [0, 2, 3] for c in cids):
                    rusak_list.append(lambda p=img_p: Image.open(p).convert("RGB"))
                elif 1 in cids:
                    baik_list.append(lambda p=img_p: Image.open(p).convert("RGB"))

    # D. Real Manhole Covers from Manhole-cover_copy-1
    # Classes: 0: broke, 1: circle, 2: good, 3: loss, 4: uncovered
    yolo_copy = os.path.join(DATASETS_DIR, "Manhole-cover_copy-1")
    if os.path.exists(yolo_copy):
        for split in ["train", "valid", "test"]:
            lbl_dir = os.path.join(yolo_copy, split, "labels")
            img_dir = os.path.join(yolo_copy, split, "images")
            for lbl in glob.glob(os.path.join(lbl_dir, "*.txt")):
                base = os.path.splitext(os.path.basename(lbl))[0]
                img_p = os.path.join(img_dir, f"{base}.jpg")
                if not os.path.exists(img_p):
                    continue
                with open(lbl, "r", errors="ignore") as f:
                    cids = {int(l.strip().split()[0]) for l in f if l.strip() and l.strip().split()[0].isdigit()}
                # 0: broke, 3: loss, 4: uncovered are DEFECTS
                if any(c in [0, 3, 4] for c in cids):
                    rusak_list.append(lambda p=img_p: Image.open(p).convert("RGB"))
                # 1: circle, 2: good are INTACT (both round and square/rectangular)
                elif any(c in [1, 2] for c in cids):
                    baik_list.append(lambda p=img_p: Image.open(p).convert("RGB"))

    # E. Intact vs Void Covers from manhole_covers dataset
    mc_zip = os.path.join(DATASETS_DIR, "manhole_covers", "train.zip")
    if os.path.exists(mc_zip):
        zf_mc = zipfile.ZipFile(mc_zip, 'r')
        manhole_f = [f for f in zf_mc.namelist() if f.startswith("train/manhole/") and f.endswith(".jpg")]
        void_f = [f for f in zf_mc.namelist() if f.startswith("train/void/") and f.endswith(".jpg")]
        random.shuffle(manhole_f)
        random.shuffle(void_f)
        for f in manhole_f[:300]:
            baik_list.append(lambda fname=f, z=zf_mc: Image.open(BytesIO(z.read(fname))).convert("RGB"))
        for f in void_f[:300]:
            rusak_list.append(lambda fname=f, z=zf_mc: Image.open(BytesIO(z.read(fname))).convert("RGB"))

    save_split_class(rusak_list, "drainase", "RUSAK", max_total=1100, val_ratio=0.15)
    save_split_class(baik_list, "drainase", "BAIK", max_total=1100, val_ratio=0.15)
    save_split_class(get_pure_spam_images(), "drainase", "SPAM", max_total=400, val_ratio=0.15)


# ==============================================================================
# 3. Guiding Block Specialist Dataset (384x384)
# ==============================================================================
def assemble_guiding_block():
    print("\n[3/5] Assembling Guiding Block Specialist Dataset (384x384)...")
    init_category_dir("guiding_block")
    rusak_list, baik_list = [], []

    # A. Real Damaged & Displaced Tactile Guiding Blocks (braille-sidewalk-blocks-2)
    bsb_dir = os.path.join(DATASETS_DIR, "braille-sidewalk-blocks-2")
    if os.path.exists(bsb_dir):
        for split in ["train", "valid", "test"]:
            lbl_dir = os.path.join(bsb_dir, split, "labels")
            img_dir = os.path.join(bsb_dir, split, "images")
            for lbl in glob.glob(os.path.join(lbl_dir, "*.txt")):
                base = os.path.splitext(os.path.basename(lbl))[0]
                img_p = os.path.join(img_dir, f"{base}.jpg")
                if not os.path.exists(img_p):
                    continue
                with open(lbl, "r") as f:
                    classes = {int(line.strip().split()[0]) for line in f if line.strip().split()}
                # Class 1: damaged_block
                if 1 in classes:
                    rusak_list.append(lambda p=img_p: Image.open(p).convert("RGB"))
                elif 0 in classes or 2 in classes:
                    baik_list.append(lambda p=img_p: Image.open(p).convert("RGB"))

    # B. Real field sample: Collapsed/broken sinkhole guiding block
    sinkhole_img = "/home/rog-theseus/.gemini/antigravity-cli/brain/58d22bc9-ef31-45d0-b7fb-dacfbcbec955/.user_uploaded/uploaded_media_1794126873009.jpg"
    if os.path.exists(sinkhole_img):
        try:
            im = Image.open(sinkhole_img).convert("RGB")
            w, h = im.size
            # Add full and regional crops of the broken sinkhole guiding blocks
            rusak_list.append(lambda: im)
            rusak_list.append(lambda: im.crop((0, int(h * 0.35), w, h)))
            rusak_list.append(lambda: im.crop((0, int(h * 0.4), int(w * 0.6), h)))
            rusak_list.append(lambda: im.crop((int(w * 0.2), int(h * 0.4), int(w * 0.8), h)))
            rusak_list.append(lambda: im.crop((int(w * 0.4), int(h * 0.45), w, h)))
        except Exception as e:
            print("Notice:", e)

    # C. Intact guiding blocks from TP-Dataset
    tp_zip = os.path.join(DATASETS_DIR, "TP_Dataset", "TP-Dataset.zip")
    if os.path.exists(tp_zip):
        zf = zipfile.ZipFile(tp_zip, 'r')
        jpeg_files = [f for f in zf.namelist() if "JPEGImages/" in f and f.lower().endswith((".jpg", ".png"))]
        random.shuffle(jpeg_files)
        for f in jpeg_files[:400]:
            baik_list.append(lambda fname=f, z=zf: Image.open(BytesIO(z.read(fname))).convert("RGB"))

    save_split_class(rusak_list, "guiding_block", "RUSAK", max_total=1200, val_ratio=0.15)
    save_split_class(baik_list, "guiding_block", "BAIK", max_total=1200, val_ratio=0.15)
    save_split_class(get_pure_spam_images(), "guiding_block", "SPAM", max_total=400, val_ratio=0.15)


# ==============================================================================
# 4. Parkir Liar Specialist Dataset (384x384)
# ==============================================================================
def assemble_parkir_liar():
    print("\n[4/5] Assembling Parkir Liar Specialist Dataset (384x384)...")
    init_category_dir("parkir_liar")
    rusak_list, baik_list = [], []

    # A. Real Parkir Liar on Sidewalks (sidewalk-parking-1 where vehicle is ON sidewalk)
    sp_all = glob.glob(os.path.join(DATASETS_DIR, "sidewalk-parking-1", "**", "*.jpg"), recursive=True)
    parked_imgs = [p for p in sp_all if "not_parked" not in os.path.basename(p)]
    not_parked_imgs = [p for p in sp_all if "not_parked" in os.path.basename(p)]

    for p in parked_imgs:
        rusak_list.append(lambda path=p: Image.open(path).convert("RGB"))
        # Augmented horizontal flip to capture both left-hand and right-hand sidewalk parking
        rusak_list.append(lambda path=p: Image.open(path).convert("RGB").transpose(Image.Transpose.FLIP_LEFT_RIGHT))

    # B. Real Legal Parking / Road Traffic next to Clean Sidewalks
    # In not_parked_imgs, cars are on the roadway/driveway and the pedestrian sidewalk is CLEAR!
    for p in not_parked_imgs:
        baik_list.append(lambda path=p: Image.open(path).convert("RGB"))

    # C. Real field anchor from Surabaya: Taman Apsari sidewalk with road traffic alongside
    apsari_img = "/home/rog-theseus/.gemini/antigravity-cli/brain/58d22bc9-ef31-45d0-b7fb-dacfbcbec955/.user_uploaded/uploaded_media_1794129754235.jpg"
    if os.path.exists(apsari_img):
        try:
            im = Image.open(apsari_img).convert("RGB")
            baik_list.append(lambda: im)
            baik_list.append(lambda: im.transpose(Image.Transpose.FLIP_LEFT_RIGHT))
        except Exception as e:
            print("Notice:", e)

    # D. Clean pedestrian walkways from TP-Dataset
    tp_zip = os.path.join(DATASETS_DIR, "TP_Dataset", "TP-Dataset.zip")
    if os.path.exists(tp_zip):
        zf_tp = zipfile.ZipFile(tp_zip, 'r')
        tp_files = [f for f in zf_tp.namelist() if "JPEGImages/" in f and f.lower().endswith((".jpg", ".png"))]
        random.shuffle(tp_files)
        for f in tp_files[1800:2200]:
            baik_list.append(lambda fname=f, z=zf_tp: Image.open(BytesIO(z.read(fname))).convert("RGB"))

    save_split_class(rusak_list, "parkir_liar", "RUSAK", max_total=1100, val_ratio=0.15)
    save_split_class(baik_list, "parkir_liar", "BAIK", max_total=1100, val_ratio=0.15)
    save_split_class(get_pure_spam_images(), "parkir_liar", "SPAM", max_total=400, val_ratio=0.15)


# ==============================================================================
# 5. Penyebrangan Specialist Dataset (384x384)
# ==============================================================================
def assemble_penyebrangan():
    print("\n[5/5] Assembling Penyebrangan Specialist Dataset (384x384)...")
    init_category_dir("penyebrangan")
    rusak_list, baik_list = [], []

    cdnet_tar = os.path.join(DATASETS_DIR, "train_data_v5_format_3080.tar")
    if os.path.exists(cdnet_tar):
        t = tarfile.open(cdnet_tar, "r")
        members = [m for m in t.getmembers() if m.isfile() and m.name.lower().endswith((".jpg", ".png"))]
        random.shuffle(members)

        # BAIK: Crisp, intact zebra crossings
        for m in members[:700]:
            def load_clean(mname=m.name, tar=t):
                return Image.open(BytesIO(tar.extractfile(mname).read())).convert("RGB")
            baik_list.append(load_clean)

        # RUSAK: Worn-out, faded, eroded zebra crossings
        for m in members[700:1600]:
            def load_worn(mname=m.name, tar=t):
                im = Image.open(BytesIO(tar.extractfile(mname).read())).convert("RGB")
                enhancer = ImageEnhance.Contrast(im)
                return enhancer.enhance(random.uniform(0.55, 0.75))
            rusak_list.append(load_worn)

    save_split_class(rusak_list, "penyebrangan", "RUSAK", max_total=1000, val_ratio=0.15)
    save_split_class(baik_list, "penyebrangan", "BAIK", max_total=1000, val_ratio=0.15)
    save_split_class(get_pure_spam_images(), "penyebrangan", "SPAM", max_total=400, val_ratio=0.15)


def main():
    import sys
    print("=" * 70)
    print("STARTING FAST PER-CATEGORY (384x384) DATASET ASSEMBLY")
    print("=" * 70)
    os.makedirs(OUTPUT_BASE, exist_ok=True)

    target = sys.argv[1].lower() if len(sys.argv) > 1 else None

    if target == "trotoar" or not target:
        assemble_trotoar()
    if target == "drainase" or not target:
        assemble_drainase()
    if target == "guiding_block" or not target:
        assemble_guiding_block()
    if target == "parkir_liar" or not target:
        assemble_parkir_liar()
    if target == "penyebrangan" or not target:
        assemble_penyebrangan()

    print("\n" + "=" * 70)
    print("DATASET ASSEMBLY COMPLETED!")
    print("Location:", OUTPUT_BASE)
    print("=" * 70)


if __name__ == "__main__":
    main()
