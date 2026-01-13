import base64
import io
import os
import time
from typing import Optional

import cv2
import numpy as np
import requests
import logging
import face_recognition
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
app = FastAPI(title="Verificador Visual Auxiliar", version="1.0.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


class VerifyPayload(BaseModel):
    student_id: Optional[int] = None
    frame_base64: str
    official_url: Optional[str] = None
    official_base64: Optional[str] = None

class GeneratePayload(BaseModel):
    image_base64: Optional[str] = None
    image_url: Optional[str] = None
    student_id: Optional[int] = None


def _decode_b64_to_img(b64: str) -> Optional[np.ndarray]:
    try:
        data = base64.b64decode(b64)
        arr = np.frombuffer(data, dtype=np.uint8)
        img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
        if img is None:
            logging.info("decode_b64_to_img: failed to decode base64 (len=%s)", len(b64 or ""))
        return img
    except Exception:
        logging.exception("decode_b64_to_img: exception")
        return None


def _fetch_official(url: str, timeout: float = 0.8) -> Optional[np.ndarray]:
    if not url:
        return None
    try:
        # Se vier caminho relativo, monta base
        if url.startswith("/"):
            base = os.environ.get("SERVER_BASE_URL", "http://127.0.0.1:8000")
            url = base.rstrip("/") + url
        r = requests.get(url, timeout=timeout)
        if r.status_code != 200:
            logging.info("fetch_official: status %s for %s", r.status_code, url)
            return None
        arr = np.frombuffer(r.content, dtype=np.uint8)
        img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
        if img is None:
            logging.info("fetch_official: failed to decode url image bytes=%s", len(r.content or b""))
        return img
    except Exception:
        logging.exception("fetch_official: exception url=%s", url)
        return None


def _prep(img: np.ndarray, size=(240, 240)) -> np.ndarray:
    if img is None:
        return img
    img = cv2.resize(img, size, interpolation=cv2.INTER_AREA)
    return img

def _detect_face(img: np.ndarray) -> Optional[np.ndarray]:
    try:
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        face_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")
        faces = face_cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(60, 60))
        if len(faces) == 0:
            h, w = gray.shape[:2]
            cx, cy = w // 2, h // 2
            bw, bh = int(w * 0.5), int(h * 0.5)
            x1 = max(0, cx - bw // 2)
            y1 = max(0, cy - bh // 2)
            x2 = min(w, x1 + bw)
            y2 = min(h, y1 + bh)
            roi = img[y1:y2, x1:x2]
            return roi
        x, y, w, h = sorted(faces, key=lambda r: r[2] * r[3], reverse=True)[0]
        pad = int(0.10 * max(w, h))
        x1 = max(0, x - pad)
        y1 = max(0, y - pad)
        x2 = min(img.shape[1], x + w + pad)
        y2 = min(img.shape[0], y + h + pad)
        roi = img[y1:y2, x1:x2]
        return roi
    except Exception:
        return img

def _oval_mask(img: np.ndarray) -> np.ndarray:
    try:
        h, w = img.shape[:2]
        center = (w // 2, h // 2)
        axes = (int(w * 0.45), int(h * 0.55))
        mask = np.zeros((h, w), dtype=np.uint8)
        cv2.ellipse(mask, center, axes, 0, 0, 360, 255, -1)
        return cv2.bitwise_and(img, img, mask=mask)
    except Exception:
        return img

def _enhance(img: np.ndarray) -> np.ndarray:
    try:
        lab = cv2.cvtColor(img, cv2.COLOR_BGR2LAB)
        l, a, b = cv2.split(lab)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
        cl = clahe.apply(l)
        limg = cv2.merge((cl, a, b))
        out = cv2.cvtColor(limg, cv2.COLOR_LAB2BGR)
        return out
    except Exception:
        return img

def _quality(gray: np.ndarray) -> dict:
    lap = cv2.Laplacian(gray, cv2.CV_64F).var()
    mean = float(np.mean(gray))
    return {"blur": lap, "brightness": mean, "ok": (lap >= 35 and 60 <= mean <= 200)}

def _align_face(img: np.ndarray) -> np.ndarray:
    try:
        roi = _detect_face(img)
        g = cv2.cvtColor(roi, cv2.COLOR_BGR2GRAY)
        eye_cascade = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_eye_tree_eyeglasses.xml")
        eyes = eye_cascade.detectMultiScale(g, scaleFactor=1.15, minNeighbors=6, minSize=(24, 24))
        if len(eyes) < 2:
            return roi
        eyes_sorted = sorted(eyes, key=lambda r: r[2]*r[3], reverse=True)[:2]
        (x1, y1, w1, h1) = eyes_sorted[0]
        (x2, y2, w2, h2) = eyes_sorted[1]
        c1 = (x1 + w1/2.0, y1 + h1/2.0)
        c2 = (x2 + w2/2.0, y2 + h2/2.0)
        dy = c2[1] - c1[1]
        dx = c2[0] - c1[0]
        angle = np.degrees(np.arctan2(dy, dx))
        center = (roi.shape[1]//2, roi.shape[0]//2)
        M = cv2.getRotationMatrix2D(center, angle, 1.0)
        aligned = cv2.warpAffine(roi, M, (roi.shape[1], roi.shape[0]), flags=cv2.INTER_LINEAR, borderMode=cv2.BORDER_REPLICATE)
        return aligned
    except Exception:
        return img

def _hog_vec(img: np.ndarray) -> np.ndarray:
    try:
        sz = (128, 128)
        im = cv2.resize(img, sz, interpolation=cv2.INTER_AREA)
        g = cv2.cvtColor(im, cv2.COLOR_BGR2GRAY)
        hog = cv2.HOGDescriptor(sz, (32,32), (16,16), (16,16), 9)
        v = hog.compute(g)
        v = v.reshape(-1).astype(np.float32)
        n = np.linalg.norm(v)
        if n > 0:
            v = v / n
        return v
    except Exception:
        return np.zeros((1,), dtype=np.float32)

def _hog_sim(a: np.ndarray, b: np.ndarray) -> float:
    va = _hog_vec(a)
    vb = _hog_vec(b)
    da = float(np.dot(va, vb))
    da = max(-1.0, min(1.0, da))
    return (da + 1.0) / 2.0

def _hog_sim_to_encoding(a: np.ndarray, enc: np.ndarray) -> float:
    va = _hog_vec(a)
    if enc is None or len(enc)==0:
        return 0.0
    vb = np.array(enc, dtype=np.float32).reshape(-1)
    # normaliza
    na = np.linalg.norm(va); nb = np.linalg.norm(vb)
    if na>0: va = va/na
    if nb>0: vb = vb/nb
    da = float(np.dot(va, vb))
    da = max(-1.0, min(1.0, da))
    return (da + 1.0) / 2.0

def _fr_encode(image: np.ndarray, num_jitters: int = 1) -> Optional[np.ndarray]:
    try:
        h, w = image.shape[:2]
        if max(h, w) > 800:
            scale = 800.0 / float(max(h, w))
            nh = int(round(h * scale)); nw = int(round(w * scale))
            image = cv2.resize(image, (nw, nh), interpolation=cv2.INTER_AREA)
        rgb = cv2.cvtColor(image, cv2.COLOR_BGR2RGB)
        locs = face_recognition.face_locations(rgb, number_of_times_to_upsample=0, model="hog")
        if not locs:
            return None
        if len(locs) > 1:
            areas = [abs((t-b)*(r-l)) for (t, r, b, l) in locs]
            locs = [locs[int(np.argmax(areas))]]
        encs = face_recognition.face_encodings(rgb, locs, num_jitters=num_jitters)
        if not encs:
            return None
        return np.array(encs[0], dtype=np.float32)
    except Exception:
        return None

def _fetch_encoding(student_id: int, timeout: float=0.8) -> Optional[np.ndarray]:
    try:
        base = os.environ.get("SERVER_BASE_URL", "http://127.0.0.1:8000")
        url = f"{base.rstrip('/')}/api/alunos/get_encoding.php?id={student_id}"
        r = requests.get(url, timeout=timeout)
        if r.status_code != 200:
            logging.info("fetch_encoding: status %s for %s", r.status_code, url)
            return None
        j = r.json()
        if (j.get("status")!="ok") or ("encoding" not in j):
            logging.info("fetch_encoding: bad response for %s", url)
            return None
        arr = j["encoding"]
        if not isinstance(arr, list) or not arr:
            return None
        return np.array(arr, dtype=np.float32)
    except Exception:
        logging.exception("fetch_encoding: exception")
        return None

def _ssim(a: np.ndarray, b: np.ndarray) -> float:
    try:
        a = cv2.cvtColor(a, cv2.COLOR_BGR2GRAY)
        b = cv2.cvtColor(b, cv2.COLOR_BGR2GRAY)
        ah, aw = a.shape[:2]
        bh, bw = b.shape[:2]
        a = a[int(0.2*ah):int(0.8*ah), int(0.2*aw):int(0.8*aw)]
        b = b[int(0.2*bh):int(0.8*bh), int(0.2*bw):int(0.8*bw)]
        a = a.astype(np.float64)
        b = b.astype(np.float64)
        c1 = (0.01 * 255) ** 2
        c2 = (0.03 * 255) ** 2
        mu_a = cv2.GaussianBlur(a, (7, 7), 1.5)
        mu_b = cv2.GaussianBlur(b, (7, 7), 1.5)
        mu_a2 = mu_a * mu_a
        mu_b2 = mu_b * mu_b
        mu_ab = mu_a * mu_b
        sigma_a2 = cv2.GaussianBlur(a * a, (7, 7), 1.5) - mu_a2
        sigma_b2 = cv2.GaussianBlur(b * b, (7, 7), 1.5) - mu_b2
        sigma_ab = cv2.GaussianBlur(a * b, (7, 7), 1.5) - mu_ab
        ssim_map = ((2 * mu_ab + c1) * (2 * sigma_ab + c2)) / ((mu_a2 + mu_b2 + c1) * (sigma_a2 + sigma_b2 + c2))
        val = ssim_map.mean()
        return float(max(0.0, min(1.0, val)))
    except Exception:
        return 0.0


def _hist_sim(a: np.ndarray, b: np.ndarray) -> float:
    try:
        a_hsv = cv2.cvtColor(a, cv2.COLOR_BGR2HSV)
        b_hsv = cv2.cvtColor(b, cv2.COLOR_BGR2HSV)
        h_bins, s_bins = 32, 32
        a_hist = cv2.calcHist([a_hsv], [0, 1], None, [h_bins, s_bins], [0, 180, 0, 256])
        b_hist = cv2.calcHist([b_hsv], [0, 1], None, [h_bins, s_bins], [0, 180, 0, 256])
        cv2.normalize(a_hist, a_hist, 0, 1, cv2.NORM_MINMAX)
        cv2.normalize(b_hist, b_hist, 0, 1, cv2.NORM_MINMAX)
        score = cv2.compareHist(a_hist, b_hist, cv2.HISTCMP_CORREL)
        # CORREL pode sair do range [-1,1], clamp
        score = max(-1.0, min(1.0, float(score)))
        # normaliza para [0,1]
        return (score + 1.0) / 2.0
    except Exception:
        return 0.0


def _orb_match(a: np.ndarray, b: np.ndarray) -> float:
    try:
        orb = cv2.ORB_create(nfeatures=200, fastThreshold=18)
        ah, aw = a.shape[:2]
        bh, bw = b.shape[:2]
        ma = np.zeros((ah, aw), dtype=np.uint8)
        mb = np.zeros((bh, bw), dtype=np.uint8)
        cv2.ellipse(ma, (aw//2, ah//2), (int(aw*0.45), int(ah*0.55)), 0, 0, 360, 255, -1)
        cv2.ellipse(mb, (bw//2, bh//2), (int(bw*0.45), int(bh*0.55)), 0, 0, 360, 255, -1)
        akp, ades = orb.detectAndCompute(a, ma)
        bkp, bdes = orb.detectAndCompute(b, mb)
        if ades is None or bdes is None:
            return 0.0
        bf = cv2.BFMatcher(cv2.NORM_HAMMING, crossCheck=True)
        matches = bf.match(ades, bdes)
        if not matches:
            return 0.0
        # distância menor -> melhor; converte em simetria simples
        dists = [m.distance for m in matches]
        mean_d = float(np.mean(dists))
        # escala para [0,1]
        sim = max(0.0, min(1.0, (75.0 - mean_d) / 75.0))
        # pondera por quantidade de matches
        qty = min(1.0, len(matches) / 60.0)
        return 0.5 * sim + 0.5 * qty
    except Exception:
        return 0.0


def _compatibility(a_img: np.ndarray, b_img: np.ndarray) -> int:
    a_face = _align_face(a_img)
    b_face = _align_face(b_img)
    a_enh = _enhance(a_face)
    b_enh = _enhance(b_face)
    a_p = _prep(a_enh, (256, 256))
    b_p = _prep(b_enh, (256, 256))
    a_m = _oval_mask(a_p)
    b_m = _oval_mask(b_p)
    hog_v = _hog_sim(a_m, b_m)
    ssim_v = _ssim(a_m, b_m)
    hist_v = _hist_sim(a_m, b_m)
    orb_v = _orb_match(a_m, b_m)
    score = 0.60 * hog_v + 0.25 * orb_v + 0.10 * ssim_v + 0.05 * hist_v
    ga = cv2.cvtColor(a_p, cv2.COLOR_BGR2GRAY)
    gb = cv2.cvtColor(b_p, cv2.COLOR_BGR2GRAY)
    qa = _quality(ga)
    qb = _quality(gb)
    quality_ok = qa["ok"] and qb["ok"]
    if not quality_ok:
        score *= 0.85
    perc = int(round(max(0.0, min(1.0, score)) * 100))
    logging.info("compatibility: hog=%.3f ssim=%.3f orb=%.3f hist=%.3f perc=%s", hog_v, ssim_v, orb_v, hist_v, perc)
    return perc


@app.post("/verificar-compatibilidade")
def verificar(payload: VerifyPayload):
    t0 = time.time()
    try:
        frame_img = _decode_b64_to_img(payload.frame_base64 or "")
        if frame_img is None:
            logging.info("verificar: frame_img missing/invalid")
            return {"status": "ok"}
        gray_f = cv2.cvtColor(frame_img, cv2.COLOR_BGR2GRAY)
        qf = _quality(gray_f)
        # primeiro tenta usar encoding do aluno
        enc = None
        if payload.student_id:
            enc = _fetch_encoding(int(payload.student_id), timeout=0.8)
        if enc is not None:
            if enc.shape[0] == 128:
                fr = _fr_encode(frame_img, num_jitters=1)
                if fr is None:
                    logging.info("verificar: fr_encode missing")
                    return {"status":"ok","reason":"no_face"}
                dist = float(np.linalg.norm(enc - fr))
                comp = max(0.0, (1.0 - dist) * 100.0)
                perc = int(round(comp))
                logging.info("verificar: fr_encoding_match student_id=%s dist=%.4f perc=%s", payload.student_id, dist, perc)
                return {"status":"ok","compatibilidade":perc,"quality":{"frame":qf},"reason":"fr_encoding"}
            else:
                face = _align_face(frame_img)
                enh = _enhance(face)
                prep = _prep(enh, (256, 256))
                masked = _oval_mask(prep)
                sim = _hog_sim_to_encoding(masked, enc)
                perc = int(round(max(0.0, min(1.0, sim)) * 100))
                logging.info("verificar: encoding_match student_id=%s perc=%s", payload.student_id, perc)
                return {"status":"ok","compatibilidade":perc,"quality":{"frame":qf},"reason":"encoding"}
        # fallback: comparar com foto oficial
        official_img = None
        if payload.official_base64:
            official_img = _decode_b64_to_img(payload.official_base64 or "")
        if official_img is None and payload.official_url:
            official_img = _fetch_official(payload.official_url, timeout=0.8)
        if official_img is None:
            # sem foto oficial -> indisponível, não retorna compatibilidade
            logging.info("verificar: official_img unavailable (url=%s b64_len=%s)", payload.official_url, len(payload.official_base64 or ""))
            return {"status": "ok", "reason": "no_official"}
        gray_o = cv2.cvtColor(official_img, cv2.COLOR_BGR2GRAY)
        qo = _quality(gray_o)
        if not qf["ok"]:
            logging.info("verificar: low_quality frame blur=%.1f bright=%.1f", qf["blur"], qf["brightness"])
        if not qo["ok"]:
            logging.info("verificar: low_quality official blur=%.1f bright=%.1f", qo["blur"], qo["brightness"])
        perc = _compatibility(frame_img, official_img)
        logging.info("verificar: done student_id=%s perc=%s", payload.student_id, perc)
        return {"status": "ok", "compatibilidade": perc, "quality": {"frame": qf, "official": qo}}
    except Exception:
        logging.exception("verificar: exception")
        return {"status": "ok"}
    finally:
        # garante baixa latência
        _ = time.time() - t0

@app.post("/gerar-encoding")
def gerar_encoding(payload: GeneratePayload):
    try:
        img = None
        if payload.image_base64:
            img = _decode_b64_to_img(payload.image_base64 or "")
        if img is None and payload.image_url:
            img = _fetch_official(payload.image_url, timeout=1.2)
        if img is None:
            return {"status": "error", "message": "image_unavailable"}
        enc_vec = _fr_encode(img, num_jitters=10)
        if enc_vec is None:
            return {"status": "error", "message": "no_face"}
        enc = [float(x) for x in enc_vec.tolist()]
        return {"status": "ok", "encoding": enc}
    except Exception:
        logging.exception("gerar_encoding: exception")
        return {"status": "error"}


if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="127.0.0.1", port=8787)
