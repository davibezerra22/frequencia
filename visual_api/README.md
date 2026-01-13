# API Local de Verificação Visual (Auxiliar)

## Instalação
1. Python 3.10+
2. Em `visual_api/`:
   - `pip install -r requirements.txt`

## Execução
```
python visual_api/main.py
```
Ou:
```
uvicorn visual_api.main:app --host 127.0.0.1 --port 8787
```

## Endpoint
- POST `/verificar-compatibilidade`
```
{
  "student_id": 123,
  "frame_base64": "...",
  "official_url": "http://127.0.0.1:8000/path/da/foto.jpg"
}
```
Resposta:
```
{ "status": "ok", "compatibilidade": 68 }
```
Se a foto oficial não puder ser obtida, retorna:
```
{ "status": "ok" }
```

## Ambiente
- `SERVER_BASE_URL` (opcional): monta URL quando `official_url` vier como caminho relativo (ex.: `/uploads/...`).

## Observações
- Processamento apenas em memória (efêmero).
- Sem gravação de embeddings ou dados biométricos.
- Baixa latência e tolerância a falhas.
