import cv2
import numpy as np
from pathlib import Path
import sys
sys.path.append(str(Path(__file__).resolve().parent.parent))
from visual_api.main import _detect_face, _enhance, _prep, _ssim, _hist_sim, _orb_match, _oval_mask, _hog_sim

def compat_with_pipeline(a_img: np.ndarray, b_img: np.ndarray) -> tuple[int, dict]:
    a_face = _detect_face(a_img)
    b_face = _detect_face(b_img)
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
    perc = int(round(max(0.0, min(1.0, score)) * 100))
    return perc, {"hog": hog_v, "ssim": ssim_v, "orb": orb_v, "hist": hist_v}

def read_img(p: Path) -> np.ndarray | None:
    return cv2.imread(str(p))

def main():
    base = Path(r"C:\Users\Suporte\Pictures\comparacao")
    p01 = base / "01.jpg"
    if not p01.exists():
        p01 = base / "01.jpeg"
    p02 = base / "02.jpg"
    ple = base / "leitura.jpg"
    i01 = read_img(p01)
    i02 = read_img(p02)
    ile = read_img(ple)
    if i02 is None or ile is None:
        print("Erro ao abrir imagens. Verifique caminhos:")
        print(p02, i02 is not None)
        print(ple, ile is not None)
        return
    c_02_le, m_02_le = compat_with_pipeline(i02, ile)
    if i01 is not None:
        c_01_le, m_01_le = compat_with_pipeline(i01, ile)
    print("Comparação 02 vs leitura")
    print("  HOG:", f"{m_02_le['hog']:.3f}", "SSIM:", f"{m_02_le['ssim']:.3f}", "ORB:", f"{m_02_le['orb']:.3f}", "Hist:", f"{m_02_le['hist']:.3f}")
    print("  Compatibilidade:", f"{c_02_le}%")
    if i01 is not None:
        print("Comparação 01 vs leitura")
        print("  HOG:", f"{m_01_le['hog']:.3f}", "SSIM:", f"{m_01_le['ssim']:.3f}", "ORB:", f"{m_01_le['orb']:.3f}", "Hist:", f"{m_01_le['hist']:.3f}")
        print("  Compatibilidade:", f"{c_01_le}%")
    print("Conclusão esperada: 02 vs leitura > 70, 01 vs leitura < 50 (se 01 existir)")

if __name__ == "__main__":
    main()
