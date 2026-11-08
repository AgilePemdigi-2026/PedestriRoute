import os
import re
import io
import numpy as np
from PIL import Image
from fastapi import FastAPI, File, UploadFile, HTTPException
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

# ONNX Runtime Session
ort_session = None

def load_model():
    global ort_session
    if not CV_MOCK and os.path.exists(MODEL_PATH):
        try:
            import onnxruntime as ort
            ort_session = ort.InferenceSession(MODEL_PATH, providers=["CPUExecutionProvider"])
            print(f"Loaded ONNX model from {MODEL_PATH}")
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
        "model_ready": ort_session is not None or CV_MOCK,
        "mock_mode": CV_MOCK,
        "model_path": MODEL_PATH
    }

def softmax(x):
    e_x = np.exp(x - np.max(x))
    return e_x / e_x.sum(axis=0)

def evaluate_image_heuristics(img: Image.Image):
    """Stage 0 Heuristic Spam & Quality Guardrail."""
    # Convert to grayscale numpy
    gray = np.array(img.convert("L"), dtype=np.float32)
    # Brightness check
    mean_val = np.mean(gray)
    if mean_val < 25.0:  # Pitch black / underexposed
        return {"label": "SPAM", "confidence": 0.90, "detected_category": None}
    
    # Simple Laplacian blur check
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


def run_onnx_inference(pil_img: Image.Image) -> np.ndarray:
    """Preprocess PIL image to 224x224 RGB tensor and run ONNX forward pass."""
    img_resized = pil_img.resize((224, 224), Image.Resampling.BILINEAR)
    arr = np.array(img_resized, dtype=np.float32) / 255.0  # (224, 224, 3)
    arr = np.transpose(arr, (2, 0, 1))  # (3, 224, 224)
    input_tensor = np.expand_dims(arr, axis=0)  # (1, 3, 224, 224)
    
    input_name = ort_session.get_inputs()[0].name
    outputs = ort_session.run(None, {input_name: input_tensor})
    raw_out = outputs[0][0]  # shape (num_classes,)
    
    if np.isclose(np.sum(raw_out), 1.0, atol=1e-2):
        return raw_out
    return softmax(raw_out)


@app.post("/analyze")
async def analyze_image(file: UploadFile = File(...)):
    filename = file.filename or "image.jpg"
    contents = await file.read()
    
    # 1. Mock Mode (for CI/CD and testing)
    if CV_MOCK or ort_session is None:
        fn_lower = filename.lower()
        
        # Check if confidence encoded in filename (e.g. rusak_095.jpg -> 0.95)
        conf_match = re.search(r'_(\d{2,3})', fn_lower)
        conf = float(conf_match.group(1)) / 100.0 if conf_match else 0.88
        
        if "spam" in fn_lower:
            return {
                "label": "SPAM",
                "confidence": conf if conf_match else 0.95,
                "detected_category": None,
                "probabilities": {"spam_non_infrastructure": conf}
            }
        elif "baik" in fn_lower or "clean" in fn_lower:
            return {
                "label": "BAIK",
                "confidence": conf if conf_match else 0.85,
                "detected_category": None,
                "probabilities": {"normal_baik": conf}
            }
        else:
            # Default to RUSAK
            cat = "Trotoar Rusak"
            if "drainase" in fn_lower or "manhole" in fn_lower:
                cat = "Masalah Drainase"
            elif "guiding" in fn_lower or "tactile" in fn_lower:
                cat = "Guiding Block Rusak"
            elif "crosswalk" in fn_lower or "penyebrangan" in fn_lower:
                cat = "Penyebrangan Rusak"
            elif "parkir" in fn_lower or "parking" in fn_lower:
                cat = "Parkir Liar"
                
            return {
                "label": "RUSAK",
                "confidence": conf,
                "detected_category": cat,
                "probabilities": {"trotoar_rusak": conf}
            }

    # 2. Production ONNX Inference Mode
    try:
        img = Image.open(io.BytesIO(contents)).convert("RGB")
    except Exception as e:
        raise HTTPException(status_code=400, detail="Invalid image file")

    # Stage 0: Heuristic Quality & Spam Filter
    guard = evaluate_image_heuristics(img)
    if guard:
        return guard

    # Stage 1: Multi-Crop Spatial Pooling & Defect Priority
    # Pedestrian defects (cracks, potholes, broken drain covers, missing tiles)
    # always reside on the ground/lower plane (45°-70° downward smartphone angle),
    # while wide-angle photos often have 50-70% background (sky, trees, street).
    w, h = img.size
    full_probs = run_onnx_inference(img)

    tiles = []
    if h >= 150 and w >= 150:
        # Ground plane (bottom half)
        tiles.append(('bottom_half', img.crop((0, int(h * 0.45), w, h))))
        # Ground plane corner & center tiles
        gw = int(w * 0.5)
        gh = int(h * 0.5)
        tiles.append(('lower_left', img.crop((0, h - gh, gw, h))))
        tiles.append(('lower_center', img.crop((int(w * 0.25), h - gh, int(w * 0.75), h))))
        tiles.append(('lower_right', img.crop((w - gw, h - gh, w, h))))
        # Mid-level center & right (perspective angle)
        tiles.append(('mid_right', img.crop((int(w * 0.5), int(h * 0.25), w, int(h * 0.75)))))
        tiles.append(('mid_center', img.crop((int(w * 0.25), int(h * 0.25), int(w * 0.75), int(h * 0.75)))))

    tile_defects = []
    for name, tile in tiles:
        t_probs = run_onnx_inference(tile)
        t_top_idx = int(np.argmax(t_probs))
        t_top_class = CLASS_NAMES[t_top_idx] if t_top_idx < len(CLASS_NAMES) else "0_normal_baik"
        t_top_conf = float(t_probs[t_top_idx])
        if t_top_class in DEFECT_CLASSES and t_top_conf >= 0.70:
            tile_defects.append((name, t_top_class, t_top_conf, t_probs))

    if tile_defects:
        tile_defects.sort(key=lambda x: x[2], reverse=True)
        winner_name, raw_top_class, top_confidence, probs = tile_defects[0]
    else:
        top_idx = int(np.argmax(full_probs))
        raw_top_class = CLASS_NAMES[top_idx] if top_idx < len(CLASS_NAMES) else "0_normal_baik"
        top_confidence = float(round(full_probs[top_idx], 4))
        probs = full_probs

    prob_dict = {}
    for idx, name in enumerate(CLASS_NAMES):
        if idx < len(probs):
            clean_name = re.sub(r'^\d+_', '', name)
            prob_dict[clean_name] = float(round(probs[idx], 4))

    top_class = re.sub(r'^\d+_', '', raw_top_class)

    # Decision mapping
    if top_class == "spam_non_infrastructure":
        label = "SPAM"
        detected_category = None
    elif top_class == "normal_baik":
        label = "BAIK"
        detected_category = None
    else:
        label = "RUSAK"
        detected_category = CATEGORY_DISPLAY_NAMES.get(top_class, "Trotoar Rusak")

    return {
        "label": label,
        "confidence": top_confidence,
        "detected_category": detected_category,
        "probabilities": prob_dict
    }
