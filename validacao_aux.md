# API de Verificação Visual Auxiliar (Compatibilidade)

> **Objetivo**: Adicionar uma camada **auxiliar e não decisória** de verificação visual ao sistema de frequência por QR Code **sem caracterizar reconhecimento facial biométrico**, preservando desempenho, legalidade (LGPD) e a operação atual do sistema.

---

## 1. Princípios do Projeto (Não Quebrar o que Já Funciona)

* ❌ **Não** realizar identificação automática de pessoas
* ❌ **Não** criar ou armazenar templates/embeddings biométricos persistentes
* ❌ **Não** bloquear ou negar frequência automaticamente
* ❌ **Não** substituir decisão humana
* ✅ **Sim** gerar um **índice de compatibilidade visual** como apoio
* ✅ **Sim** manter o fluxo atual de leitura de QR intacto
* ✅ **Sim** priorizar **baixa latência** (< 1s)

> **Nota Legal**: A API realiza **análise pontual e efêmera** de imagens para fins informativos, sem biometria persistente.

---

## 2. Arquitetura Geral

```
[Totem Web (PHP/HTTPS)]
        |
        | (POST: QR lido + frame capturado)
        v
[API Local Python - Verificador Visual]
        |
        | (GET temporário da foto oficial)
        v
[Servidor Web (PHP + MySQL)]
```

### Componentes

* **Totem**: Interface web atual (PHP)
* **API Local**: Serviço Python rodando localmente no PC do totem
* **Servidor Web**: Hospedagem (ex.: Hostinger) com PHP + MySQL

---

## 3. Fluxo de Funcionamento

1. Aluno apresenta o QR Code
2. Totem lê o QR (fluxo atual **inalterado**)
3. Totem captura **1 frame** da câmera
4. Totem envia para API Local:

   * `student_id`
   * `frame_atual`
5. API Local:

   * Busca **foto oficial** do aluno no servidor web (URL assinada ou token)
   * Executa **comparação visual efêmera**
   * Gera **percentual de compatibilidade (0–100)**
   * Descarta vetores/intermediários da memória
6. API retorna:

   * `compatibilidade: 72`
   * `status: ok`
7. Totem:

   * Registra a frequência (como já faz)
   * Exibe mensagem:

     > "Frequência registrada. Compatibilidade visual estimada: 72%"
   * Envia o percentual para armazenamento junto à frequência

---

## 4. Requisitos de Desempenho

* ⏱️ Tempo total alvo: **300–700 ms**
* 🧠 Processamento: CPU (sem GPU)
* 📸 Resolução do frame: 320x240 ou 480x360
* 🔄 Timeout da API Local: 1s

> Se a API Local estiver offline, o sistema **continua funcionando**, exibindo: "Verificação visual indisponível".

---

## 5. Implementação da API Local (Python)

### Stack Sugerida

* Python 3.10+
* FastAPI ou Flask
* OpenCV (detecção básica)
* Biblioteca de similaridade **sem persistência**

### Regras Técnicas Obrigatórias

* Comparação **somente em memória (RAM)**
* Nenhum embedding salvo em disco
* Nenhum banco biométrico
* Nenhum ID retornado (apenas %)

### Endpoint Principal

```
POST /verificar-compatibilidade
```

**Payload**:

```json
{
  "student_id": 123,
  "frame_base64": "..."
}
```

**Resposta**:

```json
{
  "compatibilidade": 68,
  "status": "ok"
}
```

---

## 6. Integração com PHP (Totem)

### Passos

1. Ler QR (já existente)
2. Capturar frame via JS
3. Enviar POST para API Local
4. Receber percentual
5. Registrar frequência normalmente
6. Salvar percentual junto ao registro

### Campo no Banco (Exemplo)

```sql
ALTER TABLE frequencia ADD compatibilidade INT NULL;
```

---

## 7. Relatórios e Auditoria

### Objetivo

Identificar **padrões suspeitos**, não punir automaticamente.

### Exemplos de Métricas

* Frequências com compatibilidade < 45%
* Alunos com recorrência baixa
* Turmas com padrões anômalos

> **Importante**: Relatórios são **analíticos**, não punitivos.

---

## 8. Linguagem Permitida (Interface e Relatórios)

### ✅ Usar

* "Compatibilidade visual"
* "Verificação auxiliar"
* "Índice estimado"
* "Apoio à conferência"

### ❌ Não usar

* Reconhecimento facial
* Identidade confirmada
* Biometria
* Validação automática

---

## 9. LGPD – Salvaguardas

* Foto do frame pode ser:

  * descartada imediatamente
  * armazenada por curto período (opcional)
* Percentual **não é dado biométrico** isoladamente
* Documentar que:

  * não há identificação automática
  * não há decisão automatizada

---

## 10. Roadmap Seguro

* Fase 1: Compatibilidade visual (atual)
* Fase 2: Relatórios e alertas
* Fase 3: Auditoria pedagógica

❌ Não escalar para reconhecimento facial pleno

---

## 11. Declaração Técnica (Recomendada)

> "O sistema não realiza reconhecimento facial nem identificação biométrica. O índice de compatibilidade visual é apenas informativo e não gera decisões automáticas, sendo utilizado como apoio à conferência humana."

---

**Fim do Documento**
