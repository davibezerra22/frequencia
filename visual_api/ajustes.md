Reduzir padding do rosto para 8–10%

 Aplicar máscara oval facial antes das métricas

 Calcular SSIM apenas na região central (~60%)

 Executar ORB somente após a máscara

 Ajustar ORB para nfeatures=200 e fastThreshold≈18

 Reponderar pesos do score final

ORB: 50%

SSIM: 35%

Histograma: 15%

 Atenuar score se qualidade da imagem for ruim (×0.85)

 Garantir que nenhum embedding/vetor seja salvo

 Comparação somente em memória (RAM)

 Retornar apenas o percentual final

 Não quebrar integração com PHP


 # Ajuste 1: reduzir padding do rosto para evitar fundo excessivo
pad = int(0.08 * max(w, h))

# Ajuste 2: aplicar máscara oval simples para focar apenas no rosto
mask = np.zeros(img.shape[:2], dtype=np.uint8)
cv2.ellipse(mask, center, axes, 0, 0, 360, 255, -1)
img = cv2.bitwise_and(img, img, mask=mask)

# Ajuste 3: SSIM calculado apenas na região central (mais discriminativa)
h, w = img.shape[:2]
img_center = img[int(0.2*h):int(0.8*h), int(0.2*w):int(0.8*w)]

# Ajuste 4: ORB executado somente após máscara, com menos ruído
orb = cv2.ORB_create(nfeatures=200, fastThreshold=18)

# Ajuste 5: pesos reponderados para melhorar separação
score = (
    0.50 * orb_score +
    0.35 * ssim_score +
    0.15 * hist_score
)

# Ajuste 6: atenuar score se a qualidade da imagem estiver ruim
if not quality_ok:
    score *= 0.85

# Observação importante:
# Este score é apenas um índice de compatibilidade visual auxiliar.
# Não há reconhecimento facial, identificação automática ou biometria persistente.
