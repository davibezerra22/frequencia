import os
import sys
import time
import json
import cv2
import numpy as np
import requests
import face_recognition
def load_encoding(student_id: int):
    base = os.environ.get("SERVER_BASE_URL", "http://127.0.0.1:8000")
    url = f"{base.rstrip('/')}/api/alunos/get_encoding.php?id={student_id}"
    r = requests.get(url, timeout=2.0)
    if r.status_code != 200:
        return None
    j = r.json()
    if j.get("status") != "ok":
        return None
    enc = j.get("encoding")
    if not isinstance(enc, list) or len(enc) != 128:
        return None
    return np.array(enc, dtype=np.float32)
def find_official_photo(student_id: int):
    pub = os.path.join(os.path.dirname(__file__), "..", "public", "uploads", "alunos")
    pub = os.path.abspath(pub)
    if not os.path.isdir(pub):
        return None
    best = None
    for f in os.listdir(pub):
        if f.startswith(f"aluno_{student_id}_") and f.lower().endswith((".jpg",".jpeg",".png",".webp",".webp")):
            p = os.path.join(pub, f)
            if best is None or os.path.getmtime(p) > os.path.getmtime(best):
                best = p
    return best
def resize_max(image, max_size=800):
    h, w = image.shape[:2]
    if max(h, w) > max_size:
        s = max_size / float(max(h, w))
        nh = int(round(h * s))
        nw = int(round(w * s))
        return cv2.resize(image, (nw, nh), interpolation=cv2.INTER_AREA)
    return image
def encode_image(image_path: str, num_jitters: int = 1):
    img = cv2.imread(image_path)
    if img is None:
        return None
    img = resize_max(img, 800)
    rgb = cv2.cvtColor(img, cv2.COLOR_BGR2RGB)
    locs = face_recognition.face_locations(rgb, number_of_times_to_upsample=0, model="hog")
    if not locs:
        return None
    if len(locs) > 1:
        areas = [abs((t - b) * (r - l)) for (t, r, b, l) in locs]
        locs = [locs[int(np.argmax(areas))]]
    encs = face_recognition.face_encodings(rgb, locs, num_jitters=num_jitters)
    if not encs:
        return None
    return np.array(encs[0], dtype=np.float32)
def compare(ref_enc: np.ndarray, img_path: str, tol: float = 0.45):
    t0 = time.time()
    enc = encode_image(img_path, num_jitters=1)
    if enc is None:
        return {"file": os.path.basename(img_path), "error": "no_face"}
    dist = float(np.linalg.norm(ref_enc - enc))
    comp = max(0.0, (1.0 - dist) * 100.0)
    return {
        "file": os.path.basename(img_path),
        "distance": round(dist, 4),
        "compatibility": round(comp, 2),
        "is_match": dist <= tol,
        "time": round(time.time() - t0, 3)
    }
def main():
    import argparse
    p = argparse.ArgumentParser()
    p.add_argument("--student", type=int, required=True)
    p.add_argument("--dir", type=str, required=True)
    p.add_argument("--tol", type=float, default=0.45)
    args = p.parse_args()
    ref = load_encoding(args.student)
    if ref is None:
        photo = find_official_photo(args.student)
        if photo is None:
            print(json.dumps({"status": "error", "message": "no_reference"}))
            sys.exit(1)
        ref = encode_image(photo, num_jitters=10)
        if ref is None:
            print(json.dumps({"status": "error", "message": "no_reference"}))
            sys.exit(1)
    files = []
    for f in os.listdir(args.dir):
        if f.lower().endswith((".jpg", ".jpeg", ".png", ".webp")):
            files.append(os.path.join(args.dir, f))
    files.sort()
    results = []
    for f in files:
        r = compare(ref, f, tol=args.tol)
        results.append(r)
    print("RESULTADOS")
    for r in results:
        if "error" in r:
            print(f"{r['file']}: erro={r['error']}")
        else:
            print(f"{r['file']}: dist={r['distance']} compat={r['compatibility']}% match={'SIM' if r['is_match'] else 'NAO'} time={r['time']}s")
if __name__ == "__main__":
    main()
