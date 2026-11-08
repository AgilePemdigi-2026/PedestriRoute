#!/usr/bin/env python3
"""
PedestriRoute - Model Training & ONNX Export Script
==================================================
Trains a YOLOv8n-cls classifier on the assembled PedestriRoute dataset,
optimized for NVIDIA GeForce GTX 1050 (4GB VRAM) or CPU execution.

Includes realistic smartphone augmentations:
  - Perspective Tilt: Simulates steep downward phone angles (45°–70°).
  - HSV Jitter: Simulates midday tropical sun vs shaded sidewalks under trees.
  - Scale & Rotation: Simulates hand orientation shifts while walking.
  - Random Erasing: Simulates partial occlusions (feet, leaves, trash).

Outputs:
  - runs/classify/pedestriroute_model/weights/best.pt
  - pedestriroute_classifier.onnx (Ready for Docker cv-service ONNX Runtime)
"""

import os
import sys

BASE_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_DIR = os.path.join(BASE_DIR, "pedestriroute_classification_data")
OUTPUT_ONNX = os.path.join(BASE_DIR, "pedestriroute_classifier.onnx")


def main():
    try:
        from ultralytics import YOLO
        import torch
    except ImportError:
        print("Error: 'ultralytics' or 'torch' not installed.")
        print("Please run: pip install ultralytics torch")
        sys.exit(1)

    if not os.path.exists(DATA_DIR):
        print(f"Error: Dataset directory not found at {DATA_DIR}")
        print("Please run: python scripts/prepare_classification_dataset.py first.")
        sys.exit(1)

    # CPU Execution Configuration
    # Note: Modern PyTorch on Linux drops Pascal (sm_61 / GTX 1050) support.
    # Fine-tuning with backbone frozen (freeze=8) on i5-9300H CPU is extremely fast (~1.5-2 min/epoch).
    device = "cpu"
    print(f"Using Device: CPU (Intel i5-9300H multi-core, workers=4, batch=64)")

    print("\n" + "=" * 65)
    print("STARTING PEDESTRIRoute MODEL TRAINING (YOLOv8n-cls)")
    print("=" * 65)

    # Load pre-trained model (ImageNet weights)
    model = YOLO("yolov8n-cls.pt")

    # Train with realistic smartphone data augmentations
    results = model.train(
        data=DATA_DIR,
        epochs=12,
        imgsz=224,
        batch=64,
        device=device,
        workers=4,
        freeze=8,           # Freeze ImageNet feature extractor backbone; train classification head
        patience=4,
        optimizer="AdamW",
        lr0=0.001,
        # Smartphone-realistic Augmentations
        degrees=10.0,       # Slight rotation while holding phone
        scale=0.2,          # Camera zoom & distance variations
        fliplr=0.5,         # Horizontal mirroring
        perspective=0.001,  # Downward phone angle perspective tilt (45°-70°)
        hsv_h=0.015,        # Hue jitter
        hsv_s=0.4,          # Saturation jitter (washed out vs vivid tropical colors)
        hsv_v=0.4,          # Brightness jitter (harsh midday sun vs tree shade)
        erasing=0.2,        # Random Cutout (feet, dirt, leaves occlusion)
        save=True,
        project=os.path.join(BASE_DIR, "runs", "classify"),
        name="pedestriroute_model",
        exist_ok=True,
    )

    print("\n" + "=" * 65)
    print("TRAINING COMPLETE! EXPORTING TO ONNX FOR CPU SERVING...")
    print("=" * 65)

    best_pt = os.path.join(BASE_DIR, "runs", "classify", "pedestriroute_model", "weights", "best.pt")
    if os.path.exists(best_pt):
        trained_model = YOLO(best_pt)
        onnx_file = trained_model.export(format="onnx", imgsz=224, simplify=True)
        print(f"\nExported ONNX model successfully: {onnx_file}")
        
        # Copy to root deployment location and cv-service models directory
        import shutil
        if os.path.exists(onnx_file):
            shutil.copy(onnx_file, OUTPUT_ONNX)
            print(f"Copied final deployment model to: {OUTPUT_ONNX}")

            cv_service_models_dir = os.path.join(BASE_DIR, "cv-service", "models")
            os.makedirs(cv_service_models_dir, exist_ok=True)
            cv_service_onnx = os.path.join(cv_service_models_dir, "pedestriroute_classifier.onnx")
            shutil.copy(onnx_file, cv_service_onnx)
            print(f"Copied final deployment model to CV service: {cv_service_onnx}")
    else:
        print(f"Warning: Could not find best.pt at {best_pt}")


if __name__ == "__main__":
    main()
