import os
import re
import io
from typing import Optional
import numpy as np
from PIL import Image
from fastapi import FastAPI, File, UploadFile, Form, HTTPException
from fastapi.responses import JSONResponse

app = FastAPI(title="PedestriRoute CV Service", version="1.0.0")

# Configuration
CV_MOCK = os.getenv("CV_MOCK", "false").lower() in ("true", "1", "yes")
MODEL_PATH = os.getenv("CV_MODEL_PATH", "/app/models/pedestriroute_classifier.onnx")

CLASS_NAMES = [
    "0_normal_baik",
    "1_trotoar_rusak",
    "2_drainase_rusak",
    "3_guiding_block_rusak",
    "4_penyebrangan_rusak",
    "5_parkir_liar",
    "6_spam_non_infrastructure"
]

CATEGORY_DISPLAY_NAMES = {
    "trotoar_rusak": "Trotoar Rusak",
    "drainase_rusak": "Masalah Drainase",
    "guiding_block_rusak": "Guiding Block Rusak",
    "penyebrangan_rusak": "Penyebrangan Rusak",
    "parkir_liar": "Parkir Liar"
}

# ONNX Runtime Sessions
ort_session = None
SPECIALIST_SESSIONS = {}
SPECIALIST_CLASSES = ["BAIK", "RUSAK", "SPAM"]

CATEGORY_SLUG_MAP = {
    "trotoar rusak": "trotoar",
    "trotoar": "trotoar",
    "masalah drainase": "drainase",
    "drainase": "drainase",
    "drainase rusak": "drainase",
    "guiding block rusak": "guiding_block",
    "guiding block": "guiding_block",
    "guiding_block": "guiding_block",
    "parkir liar": "parkir_liar",
    "parkir liar di trotoar": "parkir_liar",
    "parkir": "parkir_liar",
    "penyebrangan rusak": "penyebrangan",
    "penyebrangan": "penyebrangan",
    "penyeberangan rusak": "penyebrangan",
    "penyeberangan": "penyebrangan",
}

def resolve_category_slug(cat_str: Optional[str]) -> str:
    if not cat_str:
        return "trotoar"
    norm = cat_str.strip().lower()
    if norm in CATEGORY_SLUG_MAP:
        return CATEGORY_SLUG_MAP[norm]
    if "parkir" in norm:
        return "parkir_liar"
    if "guiding" in norm or "tunanetra" in norm or "braille" in norm:
        return "guiding_block"
    if "drainase" in norm or "saluran" in norm or "got" in norm:
        return "drainase"
    if "penyebrang" in norm or "penyeberang" in norm or "zebra" in norm:
        return "penyebrangan"
    return "trotoar"

def load_model():
    global ort_session, SPECIALIST_SESSIONS
    SPECIALIST_SESSIONS = {}
    if not CV_MOCK:
        try:
            import onnxruntime as ort
            models_dir = os.path.dirname(MODEL_PATH)
            
            # 1. Load per-category specialist models (384x384)
            for cat_key in ["trotoar", "drainase", "guiding_block", "parkir_liar", "penyebrangan"]:
                cat_path = os.path.join(models_dir, f"{cat_key}_classifier.onnx")
                if os.path.exists(cat_path):
                    try:
                        SPECIALIST_SESSIONS[cat_key] = ort.InferenceSession(cat_path, providers=["CPUExecutionProvider"])
                        print(f"Loaded specialist ONNX model: {cat_key} from {cat_path}")
                    except Exception as ex:
                        print(f"Warning: Failed loading specialist {cat_path}: {ex}")
            
            # 2. Legacy / fallback monolithic model (if present)
            if os.path.exists(MODEL_PATH):
                ort_session = ort.InferenceSession(MODEL_PATH, providers=["CPUExecutionProvider"])
                print(f"Loaded fallback ONNX model from {MODEL_PATH}")
        except Exception as e:
            print(f"Warning: Failed to load ONNX model ({e}). Will fallback to mock.")
            ort_session = None
    else:
        ort_session = None

@app.on_event("startup")
def startup_event():
    load_model()

@app.get("/health")
def health_check():
    return {
        "status": "ok",
        "model_ready": len(SPECIALIST_SESSIONS) > 0 or ort_session is not None or CV_MOCK,
        "specialists_loaded": list(SPECIALIST_SESSIONS.keys()),
        "mock_mode": CV_MOCK,
        "model_path": MODEL_PATH
    }

def softmax(x):
    e_x = np.exp(x - np.max(x))
    return e_x / e_x.sum(axis=0)

def evaluate_image_heuristics(img: Image.Image):
    """Stage 0 Heuristic Spam & Quality Guardrail."""
    gray = np.array(img.convert("L"), dtype=np.float32)
    mean_val = np.mean(gray)
    if mean_val < 25.0:  # Pitch black / underexposed
        return {"label": "SPAM", "confidence": 0.90, "detected_category": None}
    
    gy, gx = np.gradient(gray)
    gnorm = np.sqrt(gx**2 + gy**2)
    var_laplacian = np.var(gnorm)
    if var_laplacian < 15.0:  # Extremely blurry
        return {"label": "SPAM", "confidence": 0.85, "detected_category": None}
    
    return None

DEFECT_CLASSES = {
    "1_trotoar_rusak",
    "2_drainase_rusak",
    "3_guiding_block_rusak",
    "4_penyebrangan_rusak",
    "5_parkir_liar",
}


def run_specialist_inference(session, pil_img: Image.Image, imgsz: int = 384) -> np.ndarray:
    """Preprocess PIL image to 384x384 RGB tensor and run ONNX forward pass."""
    img_resized = pil_img.resize((imgsz, imgsz), Image.Resampling.BILINEAR)
    arr = np.array(img_resized, dtype=np.float32) / 255.0
    arr = np.transpose(arr, (2, 0, 1))
    input_tensor = np.expand_dims(arr, axis=0)
    input_name = session.get_inputs()[0].name
    outputs = session.run(None, {input_name: input_tensor})
    raw_out = outputs[0][0]
    if np.isclose(np.sum(raw_out), 1.0, atol=1e-2):
        return raw_out
    return softmax(raw_out)


def run_onnx_inference(pil_img: Image.Image) -> np.ndarray:
    """Preprocess PIL image to 224x224 RGB tensor and run legacy forward pass."""
    img_resized = pil_img.resize((224, 224), Image.Resampling.BILINEAR)
    arr = np.array(img_resized, dtype=np.float32) / 255.0
    arr = np.transpose(arr, (2, 0, 1))
    input_tensor = np.expand_dims(arr, axis=0)
    input_name = ort_session.get_inputs()[0].name
    outputs = ort_session.run(None, {input_name: input_tensor})
    raw_out = outputs[0][0]
    if np.isclose(np.sum(raw_out), 1.0, atol=1e-2):
        return raw_out
    return softmax(raw_out)


@app.post("/analyze")
async def analyze_image(
    file: UploadFile = File(...),
    category: Optional[str] = Form(None)
):
    filename = file.filename or "image.jpg"
    contents = await file.read()
    
    # 1. Mock Mode (for CI/CD and testing)
    if CV_MOCK or (ort_session is None and len(SPECIALIST_SESSIONS) == 0):
        fn_lower = filename.lower()
        conf_match = re.search(r'_(\d{2,3})', fn_lower)
        conf = float(conf_match.group(1)) / 100.0 if conf_match else 0.88
        
        if "spam" in fn_lower:
            return {
                "label": "SPAM",
                "confidence": conf if conf_match else 0.95,
                "detected_category": None,
                "probabilities": {"spam": conf}
            }
        elif "baik" in fn_lower or "clean" in fn_lower:
            return {
                "label": "BAIK",
                "confidence": conf if conf_match else 0.85,
                "detected_category": None,
                "probabilities": {"baik": conf}
            }
        else:
            cat = category or "Trotoar Rusak"
            slug = resolve_category_slug(cat)
            lbl = "TERHALANG" if slug == "parkir_liar" else "RUSAK"
            return {
                "label": lbl,
                "confidence": conf,
                "detected_category": cat,
                "probabilities": {
                    "rusak": conf,
                    ("terhalang" if slug == "parkir_liar" else "rusak"): conf
                }
            }

    # 2. Production Inference Mode
    try:
        img = Image.open(io.BytesIO(contents)).convert("RGB")
    except Exception as e:
        raise HTTPException(status_code=400, detail="Invalid image file")

    # Stage 0: Heuristic Quality & Spam Filter
    guard = evaluate_image_heuristics(img)
    if guard:
        return guard

    # Check if a specialized model is available for this category
    target_slug = resolve_category_slug(category)
    specialist = SPECIALIST_SESSIONS.get(target_slug)

    if specialist is not None:
        # High-Resolution Specialist Inference (384x384)
        w, h = img.size
        full_probs = run_specialist_inference(specialist, img, imgsz=384)

        tiles = []
        # Spatial Sub-tiling rules:
        # - trotoar & drainase: micro-scale surface defects (cracks, missing grates) -> full tiles
        # - guiding_block: linear feature down path centerline -> bottom_half & lower_center only (margins excluded)
        # - parkir_liar: macro-scale relational condition (vehicles ON walkway vs road).
        #   Corner crops isolate cars on the road from the sidewalk, destroying spatial relationship.
        #   Therefore, parkir_liar operates on full-scene perspective.
        if target_slug not in ("parkir_liar",):
            if h >= 180 and w >= 180:
                tiles.append(('bottom_half', img.crop((0, int(h * 0.45), w, h))))
                gw = int(w * 0.5)
                gh = int(h * 0.5)
                if target_slug not in ("guiding_block", "penyebrangan", "drainase"):
                    tiles.append(('lower_left', img.crop((0, h - gh, gw, h))))
                    tiles.append(('lower_right', img.crop((w - gw, h - gh, w, h))))
                tiles.append(('lower_center', img.crop((int(w * 0.25), h - gh, int(w * 0.75), h))))

        # Classes: ['BAIK', 'RUSAK', 'SPAM']
        rusak_idx = SPECIALIST_CLASSES.index("RUSAK")
        baik_idx = SPECIALIST_CLASSES.index("BAIK")
        best_rusak_conf = float(full_probs[rusak_idx])
        winning_probs = full_probs

        # When the overall scene perspective is clearly intact (BAIK >= 0.85),
        # require a strong defect confirmation (>= 0.88) to prevent localized corner shadows
        # or side-curb grime from falsely overriding an intact facility.
        override_threshold = 0.88 if float(full_probs[baik_idx]) >= 0.85 else 0.70

        for name, tile in tiles:
            t_probs = run_specialist_inference(specialist, tile, imgsz=384)
            t_rusak_conf = float(t_probs[rusak_idx])
            if t_rusak_conf > best_rusak_conf and t_rusak_conf >= override_threshold:
                best_rusak_conf = t_rusak_conf
                winning_probs = t_probs

        top_idx = int(np.argmax(winning_probs))
        top_label = SPECIALIST_CLASSES[top_idx]
        top_conf = float(round(winning_probs[top_idx], 4))

        prob_dict = {
            c.lower(): float(round(winning_probs[i], 4))
            for i, c in enumerate(SPECIALIST_CLASSES)
        }

        # For Parkir Liar, translate defect label 'RUSAK' to 'TERHALANG'
        # to accurately denote an obstructed path rather than physical damage
        if target_slug == "parkir_liar":
            prob_dict["terhalang"] = prob_dict.get("rusak", 0.0)
            if top_label == "RUSAK":
                top_label = "TERHALANG"

        detected_category = (category or "Trotoar Rusak") if top_label in ("RUSAK", "TERHALANG") else None

        return {
            "label": top_label,
            "confidence": top_conf,
            "detected_category": detected_category,
            "probabilities": prob_dict
        }

    # Fallback to Legacy Monolithic Model if specialist not yet loaded
    if ort_session is not None:
        w, h = img.size
        full_probs = run_onnx_inference(img)
        top_idx = int(np.argmax(full_probs))
        raw_top_class = CLASS_NAMES[top_idx] if top_idx < len(CLASS_NAMES) else "0_normal_baik"
        top_confidence = float(round(full_probs[top_idx], 4))
        top_class = re.sub(r'^\d+_', '', raw_top_class)

        if top_class == "spam_non_infrastructure":
            label = "SPAM"
            detected_category = None
        elif top_class == "normal_baik":
            label = "BAIK"
            detected_category = None
        else:
            label = "RUSAK"
            detected_category = category or "Trotoar Rusak"

        prob_dict = {re.sub(r'^\d+_', '', name): float(round(full_probs[i], 4)) for i, name in enumerate(CLASS_NAMES) if i < len(full_probs)}

        return {
            "label": label,
            "confidence": top_confidence,
            "detected_category": detected_category,
            "probabilities": prob_dict
        }

    raise HTTPException(status_code=503, detail="No active CV model loaded")
