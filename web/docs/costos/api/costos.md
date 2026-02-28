# Costos – Uso de APIs

Este documento refleja el **costo real** cuando se usan **APIs externas** (IA generativa, STT, Vision, videollamadas), **sin aplicar** estrategias de reducción. Las reducciones posibles se documentan en [api/estrategias.md](estrategias.md).

Incluye: precios de referencia de Google Cloud y otros; coste por capacidad cuando la IA o los servicios corren vía API; y la **comunicación previa al turno** (pre-turno), que no estaba contemplada antes.

## Supuestos base

- **Consultas por médico**: 20/día = 400/mes (20×20, mismo que en [infra/costos.md](../infra/costos.md)).
- Todos los valores son **costo real** (uso completo sin caché ni optimizaciones).

---

## Precios de referencia (Google Cloud y otros, 2025)

| Servicio | Precio (USD) | Fuente / Notas |
|----------|--------------|-----------------|
| **Speech-to-Text V2** (estándar) | $0.016/min (0–500k min/mes) | [cloud.google.com/speech-to-text/pricing](https://cloud.google.com/speech-to-text/pricing) |
| **Speech-to-Text V1** (estándar) | 60 min/mes gratis; luego $0.016/min (con data logging) o $0.024/min (sin) | Misma fuente |
| **Vision API** (Label, Text, Face, etc.) | Primeras 1.000 unidades/mes gratis; luego $1.50 por 1.000 unidades | [cloud.google.com/vision/pricing](https://cloud.google.com/vision/pricing) |
| **Cloud Storage Standard** (almacenamiento) | ~$0.020/GB/mes (us-central1) | [cloud.google.com/storage/pricing](https://cloud.google.com/storage/pricing) |
| **Cloud Storage** (egress a internet) | $0.12/GB (primer tramo, destinos mundial) | Misma fuente |

Para **Vertex AI / Gemini** y **videollamadas** (Twilio, Daily.co) conviene revisar el [Calculador de precios de Google Cloud](https://cloud.google.com/products/calculator) y las páginas de precios de cada proveedor. Ejemplo orientativo: Gemini Flash muy por debajo de Pro por token; facturación típica por millón de tokens (input/output).

---

## Coste de IA vía API (referencia)

Cuando la IA **no** corre en nuestra GPU sino vía **Vertex/Gemini, OpenAI, etc.**, el coste depende del proveedor y del modelo (p. ej. Gemini Flash vs Pro). Se asume que el coste por “llamada IA” equivalente es del orden de **$0.005–0.02** según modelo y longitud (referencia típica 2025). Para **modelos pequeños vía API** (p. ej. OpenAI GPT-4o mini), ver el desglose por token más abajo. Para cifras exactas, usar calculadoras del proveedor y [api/estrategias.md](estrategias.md) para reducir uso (modelo más barato, caché, tokens).

### Desglose por token: OpenAI (GPT-4o mini)

Precios típicos por millón de tokens (referencia 2025):

| Tipo   | USD por millón de tokens |
|--------|---------------------------|
| Input  | $0.15                     |
| Output | $0.60                     |

**Consulta de 1.500 tokens totales (input + output)** — según reparto ejemplo:

| Reparto (input / output) | Cálculo | USD por consulta   |
|--------------------------|---------|--------------------|
| 1.000 in + 500 out       | 0,15×0,001 + 0,60×0,0005 | **≈ $0.00045** |
| 750 + 750                | 0,15×0,00075 + 0,60×0,00075 | **≈ $0.00056** |
| 500 + 1.000 (respuesta larga) | 0,15×0,0005 + 0,60×0,001 | **≈ $0.00068** |

**Rango orientativo para consulta ~1.500 tokens**: **$0.0005–0.0007** por consulta (~0,05–0,07 céntimos USD).

**A 400 consultas/mes (20×20)**: 400 × $0.0006 ≈ **~$0.24/médico/mes** solo por IA de consultas (modelo mini).

Los modelos más caros (p. ej. GPT-4o estándar) o consultas más largas siguen en el rango **$0.005–0.02 por llamada**; usar calculadoras del proveedor para cifras exactas.

---

## Capacidades que consumen API

### 1. Comunicación previa al turno (pre-turno)

El chat/bot guía al paciente **antes** de sacar el turno. Conversación que puede terminar en turno o no.

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Contactos pre-turno | ~1.000/médico/mes; 4 mensajes × 40% con IA ⇒ **~1.600 llamadas IA** | — |
| **IA vía API** (ej. $0.01/llamada media) | 1.600 × $0.01 | **~$16/médico/mes** (orden de magnitud) |
| **IA vía API** (ej. Flash, ~$0.005/llamada) | 1.600 × $0.005 | **~$8/médico/mes** |
| **IA vía API** (OpenAI GPT-4o mini, ~1.500 tokens/llamada) | 1.600 × $0.0006 | **~$0.96/médico/mes** |

*Rango orientativo*: **~$1–16/médico/mes** (modelo mini a modelo estándar) según modelo y longitud de respuestas. Ver [api/estrategias.md](estrategias.md) para reducción (caché, reglas, menos % con IA).

---

### 2. Conversación pre-consulta (chat para despejar dudas)

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA | 400 × 5 × 50% = **1.000 llamadas IA** | — |
| **IA vía API** (ej. $0.01/llamada) | 1.000 × $0.01 | **~$10/médico/mes** |
| **IA vía API** (Flash, ~$0.005) | 1.000 × $0.005 | **~$5/médico/mes** |
| **IA vía API** (OpenAI GPT-4o mini, ~1.500 tokens/llamada) | 1.000 × $0.0006 | **~$0.60/médico/mes** |

*Rango orientativo*: **~$0.60–10/médico/mes** (modelo mini a estándar).

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA/médico/mes | ~400 | — |
| **IA vía API** (ej. $0.01/llamada) | 400 × $0.01 | **~$4/médico/mes** |
| **IA vía API** (Flash, ~$0.005) | 400 × $0.005 | **~$2/médico/mes** |
| **IA vía API** (OpenAI GPT-4o mini, ~1.500 tokens/llamada) | 400 × $0.0006 | **~$0.24/médico/mes** |

*Rango orientativo*: **~$0.24–4/médico/mes** (modelo mini a estándar).

---

### 4. Intercambio de audios, fotos y videos (STT + Vision)

**Modelo de uso**: Los medios no se almacenan en cloud; solo hay costo cuando se envía a la nube para analizar (STT, Vision). Ver [CAPACIDADES_PACIENTE_MEDICO.md](../../CAPACIDADES_PACIENTE_MEDICO.md).

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Almacenamiento / egress | No se usa cloud storage | **$0** |
| **STT** (transcribir todo el audio) | 1 min/consulta × 400 = 400 min; $0.016/min (V2) | **$6.40** (V1: 60 min gratis → **$5.44**) |
| **Vision** (analizar todas las fotos) | 400 × 2 = 800 imágenes; 1.000 gratis | **$0** |
| **Total medios (STT + Vision)** | — | **~$5.44–6.40/médico/mes** |

---

### 5. Videollamadas paciente–médico

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Consultas por videollamada/mes | 30% de 400 = 120 | 120 |
| Duración media | 12 min | 12 min |
| Minutos totales/mes | 120 × 12 = 1.440 min | **1.440 min** |
| **Twilio Video** (2 participantes, $0.004/min c/u) | 1.440 × 2 × $0.004 | **~$11.52/médico/mes** |
| **Plan por asiento** (ej. Daily.co repartido 10 médicos) | — | **~$10/médico/mes** (orden de magnitud) |

---

## Resumen: costo real por API (por médico/mes)

| Capacidad | Costo real (USD/médico/mes) |
|-----------|-----------------------------|
| Comunicación previa al turno (IA vía API) | ~$1–16 (mini a estándar) |
| Conversación pre-consulta (IA vía API) | ~$0.60–10 (mini a estándar) |
| Agente onboarding (IA vía API) | ~$0.24–4 (mini a estándar) |
| Audios, fotos, videos (STT + Vision, uso máximo) | ~$5.44–6.40 |
| Videollamadas (CPaaS) | ~$10–11.52 |
| **Total adicional (API)** | **~$17–48/médico/mes** (modelo mini a estándar; según proveedor video) |

**Nota**: Si la IA de pre-turno, pre-consulta y onboarding corre en **nuestra infra**, esos ítems figuran en [infra/costos.md](../infra/costos.md) y no se duplican aquí; aquí solo se cuentan cuando se usan APIs de IA.

---

## Referencias

- [api/estrategias.md](estrategias.md) – Cómo reducir coste vía API.
- [infra/costos.md](../infra/costos.md) – Costes cuando la IA corre en nuestra GPU.
- [CAPACIDADES_PACIENTE_MEDICO.md](../../CAPACIDADES_PACIENTE_MEDICO.md) – Descripción de las capacidades.
