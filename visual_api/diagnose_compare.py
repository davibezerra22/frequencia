import cv2
import numpy as np
import sys
from pathlib import Path

def prep(img: np.ndarray, size=(240, 240)) -> np.ndarray:
    return cv2.resize(img, size, interpolation=cv2.INTER_AREA)

def ssim(a: np.ndarray, b: np.ndarray) -> float:
    a = cv2.cvtColor(a, cv2.COLOR_BGR2GRAY).astype(np.float64)
    b = cv2.cvtColor(b, cv2.COLOR_BGR2GRAY).astype(np.float64)
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
    return float(max(0.0, min(1.0, ssim_map.mean())))

def hist_sim(a: np.ndarray, b: np.ndarray) -> float:
    a_hsv = cv2.cvtColor(a, cv2.COLOR_BGR2HSV)
    b_hsv = cv2.cvtColor(b, cv2.COLOR_BGR2HSV)
    h_bins, s_bins = 32, 32
    a_hist = cv2.calcHist([a_hsv], [0, 1], None, [h_bins, s_bins], [0, 180, 0, 256])
    b_hist = cv2.calcHist([b_hsv], [0, 1], None, [h_bins, s_bins], [0, 180, 0, 256])
    cv2.normalize(a_hist, a_hist, 0, 1, cv2.NORM_MINMAX)
    cv2.normalize(b_hist, b_hist, 0, 1, cv2.NORM_MINMAX)
    score = cv2.compareHist(a_hist, b_hist, cv2.HISTCMP_CORREL)
    score = max(-1.0, min(1.0, float(score)))
    return (score + 1.0) / 2.0

def orb_match(a: np.ndarray, b: np.ndarray) -> float:
    orb = cv2.ORB_create(nfeatures=300, fastThreshold=12)
    akp, ades = orb.detectAndCompute(a, None)
    bkp, bdes = orb.detectAndCompute(b, None)
    if ades is None or bdes is None:
        return 0.0
    bf = cv2.BFMatcher(cv2.NORM_HAMMING, crossCheck=True)
    matches = bf.match(ades, bdes)
    if not matches:
        return 0.0
    dists = [m.distance for m in matches]
    mean_d = float(np.mean(dists))
    sim = max(0.0, min(1.0, (75.0 - mean_d) / 75.0))
    qty = min(1.0, len(matches) / 60.0)
    return 0.5 * sim + 0.5 * qty

def compatibility(a_img: np.ndarray, b_img: np.ndarray) -> int:
    s = ssim(a_img, b_img)
    h = hist_sim(a_img, b_img)
    o = orb_match(a_img, b_img)
    score = 0.45 * s + 0.35 * o + 0.20 * h
    return int(round(max(0.0, min(1.0, score)) * 100))

def main():
    if len(sys.argv) < 3:
        print("Uso: python visual_api/diagnose_compare.py <frame_path> <official_path>")
        sys.exit(1)
    p1 = Path(sys.argv[1])
    p2 = Path(sys.argv[2])
    img1 = cv2.imread(str(p1))
    img2 = cv2.imread(str(p2))
    if img1 is None:
        print("Erro: não foi possível abrir frame", p1)
        sys.exit(2)
    if img2 is None:
        print("Erro: não foi possível abrir oficial", p2)
        sys.exit(3)
    a = prep(img1)
    b = prep(img2)
    s = ssim(a, b)
    o = orb_match(a, b)
    h = hist_sim(a, b)
    c = compatibility(a, b)
    print("Frame:", p1)
    print("Oficial:", p2)
    print("Shapes:", a.shape, b.shape)
    print("SSIM:", f"{s:.3f}")
    print("ORB:", f"{o:.3f}")
    print("Hist:", f"{h:.3f}")
    print("Compatibilidade:", f"{c}%")

if __name__ == "__main__":
    main()
