import face_recognition
import numpy as np
import pickle
import os
import time
import cv2
import json
from pathlib import Path
from typing import Optional, Dict

class FaceComparisonSystem:
    """Sistema de reconhecimento facial otimizado para comparação individual"""
    
    def __init__(self, storage_path: str = "face_data.pkl"):
        """
        Inicializa o sistema de comparação facial
        
        Args:
            storage_path: Caminho para salvar os dados de referência
        """
        self.storage_path = storage_path
        self.reference_encoding = None
        self.reference_name = None
        
    def train_reference_face(self, image_path: str, person_name: str = "Referência") -> bool:
        """
        Treina o sistema com uma foto de referência
        
        Args:
            image_path: Caminho da imagem de referência
            person_name: Nome da pessoa (opcional)
            
        Returns:
            True se treinou com sucesso, False caso contrário
        """
        try:
            start = time.time()
            
            # Carrega com OpenCV (mais rápido que PIL)
            image_cv = cv2.imread(image_path)
            if image_cv is None:
                print(f"❌ Não foi possível carregar: {image_path}")
                return False
            
            # Redimensiona se muito grande (acelera MUITO o processamento)
            image_cv = self._resize_image(image_cv, max_size=800)
            
            # Converte BGR para RGB
            image = cv2.cvtColor(image_cv, cv2.COLOR_BGR2RGB)
            
            # Detecta faces com HOG (10x mais rápido que CNN)
            face_locations = face_recognition.face_locations(
                image, 
                number_of_times_to_upsample=0,
                model="hog"
            )
            
            if len(face_locations) == 0:
                print("❌ Nenhum rosto detectado na imagem de referência!")
                return False
            
            if len(face_locations) > 1:
                print(f"⚠️  Múltiplos rostos detectados ({len(face_locations)}). Usando o maior.")
                # Seleciona o maior rosto (área)
                face_locations = [self._get_largest_face(face_locations)]
            
            # Extrai características com num_jitters=1 (100x mais rápido que padrão)
            face_encodings = face_recognition.face_encodings(
                image, 
                face_locations,
                num_jitters=10
            )
            
            if len(face_encodings) == 0:
                print("❌ Não foi possível extrair características faciais!")
                return False
            
            # Salva o encoding de referência
            self.reference_encoding = face_encodings[0]
            self.reference_name = person_name
            
            # Persiste os dados
            self._save_reference()
            
            elapsed = time.time() - start
            print(f"✅ Rosto de '{person_name}' treinado em {elapsed:.3f}s!")
            print(f"📊 Características extraídas: {len(self.reference_encoding)} pontos")
            return True
            
        except Exception as e:
            print(f"❌ Erro ao processar imagem: {str(e)}")
            return False
    
    def compare_single_face(self, image_path: str, tolerance: float = 0.45, verbose: bool = True) -> Dict:
        """
        Compara UMA imagem com a referência (ULTRA otimizado)
        
        Args:
            image_path: Caminho da imagem para comparar
            tolerance: Tolerância para considerar match (menor = mais rigoroso)
            verbose: Se True, imprime resultado detalhado
            
        Returns:
            Dicionário com resultado da comparação
        """
        if self.reference_encoding is None:
            print("❌ Nenhuma referência treinada! Execute train_reference_face() primeiro.")
            return self._empty_result(image_path, "Referência não treinada")
        
        start_time = time.time()
        
        result = {
            'image_path': image_path,
            'image_name': os.path.basename(image_path),
            'compatibility': 0.0,
            'is_match': False,
            'face_detected': False,
            'error': None,
            'processing_time': 0.0
        }
        
        try:
            # Carrega com OpenCV (mais rápido)
            image_cv = cv2.imread(image_path)
            if image_cv is None:
                result['error'] = f"Não foi possível carregar: {image_path}"
                if verbose:
                    print(f"❌ {result['error']}")
                return result
            
            # OTIMIZAÇÃO CRÍTICA: Redimensiona imagens grandes
            image_cv = self._resize_image(image_cv, max_size=800)
            
            # Converte para RGB
            image = cv2.cvtColor(image_cv, cv2.COLOR_BGR2RGB)
            
            # Detecta faces com HOG (10x mais rápido que CNN)
            face_locations = face_recognition.face_locations(
                image, 
                number_of_times_to_upsample=0,
                model="hog"
            )
            
            if len(face_locations) == 0:
                result['error'] = "Nenhum rosto detectado"
                if verbose:
                    print(f"❌ {result['error']}")
                return result
            
            result['face_detected'] = True
            
            # Se múltiplos rostos, pega o maior
            if len(face_locations) > 1:
                face_locations = [self._get_largest_face(face_locations)]
            
            # OTIMIZAÇÃO CRÍTICA: num_jitters=1 
            # Reduz de 2-3s para ~0.1-0.3s por imagem!
            face_encodings = face_recognition.face_encodings(
                image, 
                face_locations,
                num_jitters=1
            )
            
            if len(face_encodings) == 0:
                result['error'] = "Características não extraídas"
                if verbose:
                    print(f"❌ {result['error']}")
                return result
            
            # Compara com a referência usando numpy (otimizado)
            face_distance = np.linalg.norm(self.reference_encoding - face_encodings[0])
            
            # Converte para porcentagem de compatibilidade
            compatibility = max(0, (1 - face_distance) * 100)
            
            result['compatibility'] = round(compatibility, 2)
            result['is_match'] = face_distance <= tolerance
            result['distance'] = round(face_distance, 4)
            result['processing_time'] = round(time.time() - start_time, 4)
            
            # Imprime resultado se verbose
            if verbose:
                self._print_single_result(result)
            
        except Exception as e:
            result['error'] = str(e)
            if verbose:
                print(f"❌ Erro: {str(e)}")
        
        return result
    
    def _resize_image(self, image_cv, max_size: int = 800):
        """Redimensiona imagem se for maior que max_size"""
        height, width = image_cv.shape[:2]
        if max(height, width) > max_size:
            scale = max_size / max(height, width)
            new_width = int(width * scale)
            new_height = int(height * scale)
            return cv2.resize(image_cv, (new_width, new_height), interpolation=cv2.INTER_AREA)
        return image_cv
    
    def _get_largest_face(self, face_locations):
        """Retorna a localização do maior rosto"""
        return max(face_locations, key=lambda loc: (loc[2]-loc[0]) * (loc[1]-loc[3]))
    
    def _empty_result(self, image_path: str, error: str) -> Dict:
        """Retorna resultado vazio com erro"""
        return {
            'image_path': image_path,
            'image_name': os.path.basename(image_path),
            'compatibility': 0.0,
            'is_match': False,
            'face_detected': False,
            'error': error,
            'processing_time': 0.0
        }
    
    def _print_single_result(self, result: Dict):
        """Imprime resultado de uma comparação"""
        print("\n" + "="*70)
        print(f"📊 COMPARAÇÃO: {result['image_name']} vs {self.reference_name}")
        print("="*70)
        
        if result['error']:
            print(f"❌ Erro: {result['error']}")
        elif result['face_detected']:
            print(f"✅ Rosto detectado com sucesso!")
            print(f"📈 Compatibilidade: {result['compatibility']:.2f}%")
            print(f"📏 Distância facial: {result['distance']}")
            print(f"⏱️  Tempo de processamento: {result['processing_time']:.3f}s")
            
            if result['is_match']:
                print(f"✔️  🎯 MATCH - Provavelmente é a mesma pessoa!")
            else:
                print(f"✖️  ❌ NO MATCH - Provavelmente pessoas diferentes")
        
        print("="*70)
    
    def load_reference(self) -> bool:
        """Carrega referência salva anteriormente"""
        try:
            if os.path.exists(self.storage_path):
                with open(self.storage_path, 'rb') as f:
                    data = pickle.load(f)
                    self.reference_encoding = data['encoding']
                    self.reference_name = data['name']
                print(f"✅ Referência '{self.reference_name}' carregada!")
                return True
            else:
                print("⚠️  Nenhuma referência salva encontrada.")
                return False
        except Exception as e:
            print(f"❌ Erro ao carregar referência: {str(e)}")
            return False
    
    def visualize_jitter_samples(self, image_path: str, output_dir: str = "debug/jitter", samples: int = 10) -> bool:
        try:
            image_cv = cv2.imread(image_path)
            if image_cv is None:
                print(f"❌ Não foi possível carregar: {image_path}")
                return False
            image_cv = self._resize_image(image_cv, max_size=800)
            image = cv2.cvtColor(image_cv, cv2.COLOR_BGR2RGB)
            face_locations = face_recognition.face_locations(image, number_of_times_to_upsample=0, model="hog")
            if len(face_locations) == 0:
                print("❌ Nenhum rosto detectado para gerar jitter")
                return False
            if len(face_locations) > 1:
                face_locations = [self._get_largest_face(face_locations)]
            top, right, bottom, left = face_locations[0]
            chip = image_cv[top:bottom, left:right].copy()
            h, w = chip.shape[:2]
            os.makedirs(output_dir, exist_ok=True)
            for i in range(samples):
                angle = np.random.uniform(-3.0, 3.0)
                scale = np.random.uniform(0.98, 1.02)
                tx = np.random.uniform(-4.0, 4.0)
                ty = np.random.uniform(-4.0, 4.0)
                M = cv2.getRotationMatrix2D((w//2, h//2), angle, scale)
                M[:, 2] += [tx, ty]
                warped = cv2.warpAffine(chip, M, (w, h), flags=cv2.INTER_AREA, borderMode=cv2.BORDER_REFLECT101)
                cv2.imwrite(os.path.join(output_dir, f"jitter_{i+1:02d}.jpg"), warped)
            print(f"🖼️  Amostras de jitter salvas em: {output_dir}")
            return True
        except Exception as e:
            print(f"❌ Erro ao gerar amostras de jitter: {str(e)}")
            return False

    def generate_jitter_mosaic(self, input_dir: str = "debug/jitter", output_path: str = "debug/jitter_mosaic.jpg") -> bool:
        try:
            files = sorted([os.path.join(input_dir, f) for f in os.listdir(input_dir) if f.lower().endswith(".jpg")])[:10]
            if not files:
                print("⚠️  Nenhuma amostra de jitter encontrada")
                return False
            imgs = [cv2.imread(f) for f in files]
            imgs = [im for im in imgs if im is not None]
            if not imgs:
                print("⚠️  Falha ao carregar amostras de jitter")
                return False
            h, w = imgs[0].shape[:2]
            imgs = [cv2.resize(im, (w, h), interpolation=cv2.INTER_AREA) for im in imgs]
            cols = 5
            rows = (len(imgs) + cols - 1) // cols
            mosaic = np.full((rows * h, cols * w, 3), 255, dtype=np.uint8)
            for idx, im in enumerate(imgs):
                r = idx // cols
                c = idx % cols
                mosaic[r*h:(r+1)*h, c*w:(c+1)*w] = im
            os.makedirs(os.path.dirname(output_path), exist_ok=True)
            cv2.imwrite(output_path, mosaic)
            print(f"🖼️  Mosaico de jitter salvo em: {output_path}")
            return True
        except Exception as e:
            print(f"❌ Erro ao gerar mosaico de jitter: {str(e)}")
            return False

    def _save_reference(self):
        """Salva a referência em disco"""
        try:
            data = {
                'encoding': self.reference_encoding,
                'name': self.reference_name
            }
            with open(self.storage_path, 'wb') as f:
                pickle.dump(data, f)
            print(f"💾 Referência salva em: {self.storage_path}")
        except Exception as e:
            print(f"⚠️  Erro ao salvar referência: {str(e)}")
    
    def save_reference_json(self, json_path: str = "face_data.json"):
        try:
            data = {
                'encoding': self.reference_encoding.tolist(),
                'name': self.reference_name
            }
            tmp_path = json_path + ".tmp"
            with open(tmp_path, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False)
            os.replace(tmp_path, json_path)
            print(f"💾 Referência JSON salva em: {json_path}")
        except Exception as e:
            print(f"⚠️  Erro ao salvar referência JSON: {str(e)}")
    
    def load_reference_json(self, json_path: str = "face_data.json") -> bool:
        try:
            if os.path.exists(json_path):
                with open(json_path, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                self.reference_encoding = np.array(data['encoding'])
                self.reference_name = data['name']
                print(f"✅ Referência '{self.reference_name}' carregada (JSON)!")
                return True
            else:
                print("⚠️  JSON de referência não encontrado.")
                return False
        except Exception as e:
            print(f"❌ Erro ao carregar referência JSON: {str(e)}")
            return False
    
    def visualize_training_landmarks(self, image_path: str, output_path: str = "debug/treinamento_landmarks.jpg") -> bool:
        try:
            image_cv = cv2.imread(image_path)
            if image_cv is None:
                print(f"❌ Não foi possível carregar: {image_path}")
                return False
            image_cv = self._resize_image(image_cv, max_size=800)
            image = cv2.cvtColor(image_cv, cv2.COLOR_BGR2RGB)
            face_locations = face_recognition.face_locations(image, number_of_times_to_upsample=0, model="hog")
            if len(face_locations) == 0:
                print("❌ Nenhum rosto detectado para visualização de landmarks")
                return False
            if len(face_locations) > 1:
                face_locations = [self._get_largest_face(face_locations)]
            landmarks = face_recognition.face_landmarks(image, face_locations=face_locations, model="large")
            if not landmarks:
                print("❌ Não foi possível obter landmarks")
                return False
            pts_by_feat = landmarks[0]
            color = (180, 255, 180)
            draw_img = image_cv.copy()
            for feat, pts in pts_by_feat.items():
                for (x, y) in pts:
                    cv2.circle(draw_img, (x, y), 2, color, -1)
                closed = feat in ("left_eye", "right_eye", "top_lip", "bottom_lip")
                poly = np.array(pts, dtype=np.int32).reshape((-1, 1, 2))
                cv2.polylines(draw_img, [poly], closed, color, 1, cv2.LINE_AA)
            os.makedirs(os.path.dirname(output_path), exist_ok=True)
            cv2.imwrite(output_path, draw_img)
            print(f"🖼️  Debug de landmarks salvo em: {output_path}")
            return True
        except Exception as e:
            print(f"❌ Erro ao gerar visualização de landmarks: {str(e)}")
            return False
    
    def _densify_points(self, pts, closed, factor):
        dense = []
        n = len(pts)
        for i in range(n):
            a = pts[i]
            b = pts[(i+1) % n] if closed and i == n-1 else (pts[i+1] if i < n-1 else None)
            dense.append(a)
            if b is None:
                continue
            ax, ay = a
            bx, by = b
            for k in range(1, factor):
                t = k / factor
                x = int(round(ax + t * (bx - ax)))
                y = int(round(ay + t * (by - ay)))
                dense.append((x, y))
        return dense
    
    def visualize_training_landmarks_dense(self, image_path: str, output_path: str = "debug/treinamento_landmarks_dense.jpg", factor: int = 5) -> bool:
        try:
            image_cv = cv2.imread(image_path)
            if image_cv is None:
                print(f"❌ Não foi possível carregar: {image_path}")
                return False
            image_cv = self._resize_image(image_cv, max_size=800)
            image = cv2.cvtColor(image_cv, cv2.COLOR_BGR2RGB)
            face_locations = face_recognition.face_locations(image, number_of_times_to_upsample=0, model="hog")
            if len(face_locations) == 0:
                print("❌ Nenhum rosto detectado para visualização de landmarks")
                return False
            if len(face_locations) > 1:
                face_locations = [self._get_largest_face(face_locations)]
            landmarks = face_recognition.face_landmarks(image, face_locations=face_locations, model="large")
            if not landmarks:
                print("❌ Não foi possível obter landmarks")
                return False
            pts_by_feat = landmarks[0]
            color = (160, 255, 160)
            draw_img = image_cv.copy()
            for feat, pts in pts_by_feat.items():
                closed = feat in ("left_eye", "right_eye", "top_lip", "bottom_lip")
                dpts = self._densify_points(pts, closed, factor)
                for (x, y) in dpts:
                    cv2.circle(draw_img, (x, y), 1, color, -1)
                poly = np.array(dpts, dtype=np.int32).reshape((-1, 1, 2))
                cv2.polylines(draw_img, [poly], closed, color, 1, cv2.LINE_AA)
            os.makedirs(os.path.dirname(output_path), exist_ok=True)
            cv2.imwrite(output_path, draw_img)
            print(f"🖼️  Debug de landmarks denso salvo em: {output_path}")
            return True
        except Exception as e:
            print(f"❌ Erro ao gerar landmarks densos: {str(e)}")
            return False
    
    def quick_compare(self, image_path: str, tolerance: float = 0.45) -> float:
        """
        Comparação rápida retornando apenas a porcentagem
        
        Args:
            image_path: Caminho da imagem
            tolerance: Tolerância
            
        Returns:
            Porcentagem de compatibilidade (0-100) ou -1 se erro
        """
        result = self.compare_single_face(image_path, tolerance, verbose=False)
        return result['compatibility'] if result['face_detected'] else -1.0


# ============================================================================
# EXEMPLO DE USO - COMPARAÇÃO INDIVIDUAL
# ============================================================================

if __name__ == "__main__":
    # Inicializa o sistema
    system = FaceComparisonSystem()
    
    print("🎯 SISTEMA DE RECONHECIMENTO FACIAL - COMPARAÇÃO INDIVIDUAL")
    print("="*70)
    
    # PASSO 1: Treinar com imagem de referência
    print("\n📸 PASSO 1: Treinando com imagem de referência...")
    reference_image = "imgs/treinamento.jpg"
    
    loaded = system.load_reference_json("face_data.json")
    if not loaded:
        if os.path.exists(reference_image):
            if system.train_reference_face(reference_image, "Davi"):
                system.save_reference_json("face_data.json")
        else:
            print(f"⚠️  Arquivo '{reference_image}' não encontrado!")
            print("💡 Tentando carregar referência existente...")
            system.load_reference()
    
    # PASSO 2: Comparar UMA imagem por vez
    if system.reference_encoding is not None:
        print("\n📸 PASSO 2: Comparando imagem individual...")
        
        pass
        
        # Compara imagem alvo
        image1 = "imgs/4.jpg"
        if os.path.exists(image1):
            result1 = system.compare_single_face(image1, tolerance=0.45)
        
        # Exemplo de comparação rápida (sem verbose)
        print("\n" + "-"*70)
        print("\n🚀 MODO RÁPIDO (sem detalhes):")
        if os.path.exists(image1):
            compatibility = system.quick_compare(image1)
            print(f"   {os.path.basename(image1)}: {compatibility:.2f}%")
    
    print("\n✨ Processamento concluído!")
