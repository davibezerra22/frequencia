import base64
import requests

def b64_of_file(path: str) -> str:
    with open(path, "rb") as f:
        return base64.b64encode(f.read()).decode("ascii")

def main():
    # Ajuste os caminhos conforme necessário para um teste local
    frame_b64 = b64_of_file("sample_frame.jpg")
    payload = {
        "student_id": 123,
        "frame_base64": frame_b64,
        "official_url": "http://127.0.0.1:8000/adminfrequencia/avatar.svg"
    }
    r = requests.post("http://127.0.0.1:8787/verificar-compatibilidade", json=payload)
    print(r.status_code, r.text)

if __name__ == "__main__":
    main()
