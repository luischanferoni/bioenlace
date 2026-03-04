# Costos – Uso de APIs

Este documento refleja el **costo real** cuando se usan **APIs externas** (IA generativa, STT, Vision, videollamadas), **sin aplicar** estrategias de reducción. Las reducciones posibles se documentan en [api/estrategias.md](estrategias.md).

Se consideran **APIs de Google** (Vertex AI/Gemini, Speech-to-Text, Vision) y **Together AI** (Llama 3.1 8B) para IA generativa. Incluye: precios de referencia, coste por capacidad y la **comunicación previa al turno** (pre-turno).

## Supuestos base

- **Consultas por médico**: 20/día = 400/mes (20×20, mismo que en [infra/costos.md](../infra/costos.md)).
- **Proveedor de API (IA generativa)**: se consideran **Google (Vertex AI / Gemini)** y **Together AI (Llama 3.1 8B)**. Transcripción y análisis de imágenes: **Google** (Speech-to-Text, Vision API).
- Todos los valores son **costo real** (uso completo sin caché ni optimizaciones).

---

## Precios de referencia (Google Cloud, 2025)

| Servicio | Precio (USD) | Fuente / Notas |
|----------|--------------|-----------------|
| **Speech-to-Text V2** (estándar) | $0.016/min (0–500k min/mes) | [cloud.google.com/speech-to-text/pricing](https://cloud.google.com/speech-to-text/pricing) |
| **Speech-to-Text V1** (estándar) | 60 min/mes gratis; luego $0.016/min (con data logging) o $0.024/min (sin) | Misma fuente |
| **Vision API** (Label, Text, Face, etc.) | Primeras 1.000 unidades/mes gratis; luego $1.50 por 1.000 unidades | [cloud.google.com/vision/pricing](https://cloud.google.com/vision/pricing) |
| **Cloud Storage Standard** (almacenamiento) | ~$0.020/GB/mes (us-central1) | [cloud.google.com/storage/pricing](https://cloud.google.com/storage/pricing) |
| **Cloud Storage** (egress a internet) | $0.12/GB (primer tramo, destinos mundial) | Misma fuente |

Para **Vertex AI / Gemini** y **videollamadas** (Twilio, Daily.co) conviene revisar el [Calculador de precios de Google Cloud](https://cloud.google.com/products/calculator) y las páginas de precios de cada proveedor. Ejemplo orientativo: Gemini Flash muy por debajo de Pro por token; facturación típica por millón de tokens (input/output).

---

## Coste de IA vía API – Google y Together AI

### Google (Vertex AI / Gemini)

Para un modelo tipo **Gemini Flash** y consulta típica de 1.500 tokens (input + output), el coste por llamada es del orden de **$0.0005–0.001** por consulta (referencia 2025). A 400 consultas/mes: **aprox. $0.20–0.40/médico/mes** solo por IA de consultas. [Calculador de Google](https://cloud.google.com/vertex-ai/generative-ai/pricing).

### Together AI (Llama 3.1 8B)

Precio **$0.18 por millón de tokens** (input y output; referencia [Together AI](https://docs.together.ai/docs/serverless-models)). Para consulta de 1.500 tokens: 1.500 × $0.18/1.000.000 ≈ **$0.00027 por consulta**. A 400 consultas/mes: **aprox. $0.11/médico/mes** solo por IA de consultas. Más barato que Gemini Flash por consulta; útil como alternativa para reducir coste de IA generativa.

Para cifras exactas, usar [api/estrategias.md](estrategias.md) y las páginas de precios de cada proveedor.

---

## Capacidades que consumen API

### 1. Comunicación previa al turno (pre-turno)

El chat/bot guía al paciente **antes** de sacar el turno. Conversación que puede terminar en turno o no.

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Contactos pre-turno | 1.000/médico/mes; 4 mensajes × 40% con IA ⇒ **1.600 llamadas IA** | — |
| **Google (Gemini Flash)** 1.600 × $0.0006/llamada | — | **aprox. $0.96/médico/mes** |
| **Together AI (Llama 3.1 8B)** 1.600 × $0.00027/llamada | — | **aprox. $0.43/médico/mes** |

---

### 2. Conversación pre-consulta (chat para despejar dudas)

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA | 400 × 5 × 50% = **1.000 llamadas IA** | — |
| **Google (Gemini Flash)** 1.000 × $0.0006/llamada | — | **aprox. $0.60/médico/mes** |
| **Together AI (Llama 3.1 8B)** 1.000 × $0.00027/llamada | — | **aprox. $0.27/médico/mes** |

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA/médico/mes | 400 | — |
| **Google (Gemini Flash)** 400 × $0.0006/llamada | — | **aprox. $0.24/médico/mes** |
| **Together AI (Llama 3.1 8B)** 400 × $0.00027/llamada | — | **aprox. $0.11/médico/mes** |

---

### 4. Consulta (IA de las 400 consultas/mes)

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| **Google (Gemini Flash)** 400 × $0.0006/consulta | — | **aprox. $0.24/médico/mes** |
| **Together AI (Llama 3.1 8B)** 400 × $0.00027/consulta | — | **aprox. $0.11/médico/mes** |

---

### 5. Intercambio de audios, fotos y videos (STT + Vision)

**Modelo de uso**: Los medios no se almacenan en cloud; solo hay costo cuando se envía a la nube para analizar (STT, Vision). Ver [CAPACIDADES_PACIENTE_MEDICO.md](../../CAPACIDADES_PACIENTE_MEDICO.md).

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Almacenamiento / egress | No se usa cloud storage | **$0** |
| **STT** (transcribir todo el audio) | 1 min/consulta × 400 = 400 min; $0.016/min (V2) | **$6.40** (V1: 60 min gratis → **$5.44**) |
| **Vision** (analizar todas las fotos) | 400 × 2 = 800 imágenes; 1.000 gratis | **$0** |
| **Total medios (STT + Vision)** | — | **~$5.44–6.40/médico/mes** |

---

### 6. Videollamadas paciente–médico

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Consultas por videollamada/mes | 30% de 400 = 120 | 120 |
| Duración media | 12 min | 12 min |
| Minutos totales/mes | 120 × 12 = 1.440 min | **1.440 min** |
| **Twilio Video** (2 participantes, $0.004/min c/u) | 1.440 × 2 × $0.004 | **~$11.52/médico/mes** |
| **Plan por asiento** (ej. Daily.co repartido 10 médicos) | — | **~$10/médico/mes** (orden de magnitud) |

---

## Resumen: costo real por API (por médico/mes)

**Apartado 1** usa **Google (Gemini Flash)** o **Together AI (Llama 3.1 8B)**; **Apartado 2** usa **Google** (STT, Vision). Cifras orientativas: Gemini Flash aprox. $0.0006/llamada (1.500 tokens), Together AI Llama 3.1 8B aprox. $0.00027/llamada.

### Apartado 1 – IA generativa (chat y consulta)

| Concepto | Google (Gemini Flash) | Together AI (Llama 3.1 8B) |
|----------|------------------------|-----------------------------|
| Comunicación previa al turno | aprox. $0.96 | aprox. $0.43 |
| Conversación pre-consulta | aprox. $0.60 | aprox. $0.27 |
| Agente onboarding | aprox. $0.24 | aprox. $0.11 |
| Consulta (400 consultas/mes) | aprox. $0.24 | aprox. $0.11 |
| **Total (Apartado 1)** | **aprox. $2.04** | **aprox. $0.92** |

### Apartado 2 – Medios (transcripción y análisis de imágenes)

Solo Google (Speech-to-Text, Vision API).

| Concepto | Costo real (USD/médico/mes) |
|----------|-----------------------------|
| Transcripción de audios (STT, Google) | $5.44 (V1, 60 min gratis) – $6.40 (V2) |
| Análisis de fotos (Vision API, Google) | $0 (800 imágenes; 1.000 gratis/mes) |
| **Total (Apartado 2)** | **aprox. $5.44–6.40** |

### Total general

| Proveedor IA (Apartado 1) | Total Apartado 1 | Total Apartado 2 | **Total general** |
|---------------------------|------------------|------------------|-------------------|
| Google (Gemini Flash) | aprox. $2.04 | aprox. $5.44–6.40 | **aprox. $7.50–8.44** |
| Together AI (Llama 3.1 8B) | aprox. $0.92 | aprox. $5.44–6.40 | **aprox. $6.36–7.32** |

**Nota**: Si la IA de pre-turno, pre-consulta, onboarding y consulta corre en **nuestra infra**, esos ítems figuran en [infra/costos.md](../infra/costos.md) y no se duplican aquí. Videollamadas (Twilio, Daily.co, etc.) no están incluidas en este resumen; ver sección correspondiente si aplica.

---

## Referencias

- [api/estrategias.md](estrategias.md) – Cómo reducir coste vía API.
- [infra/costos.md](../infra/costos.md) – Costes cuando la IA corre en nuestra GPU.
- [Together AI – Serverless Models / Pricing](https://docs.together.ai/docs/serverless-models) – Llama 3.1 8B y otros modelos.
- [CAPACIDADES_PACIENTE_MEDICO.md](../../CAPACIDADES_PACIENTE_MEDICO.md) – Descripción de las capacidades.
