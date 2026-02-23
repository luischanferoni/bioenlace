# Costos – Uso de APIs

Este documento refleja el **costo real** cuando se usan **APIs externas** (IA generativa, STT, Vision, videollamadas), **sin aplicar** estrategias de reducción. Las reducciones posibles se documentan en [api/estrategias.md](estrategias.md).

Incluye: precios de referencia de Google Cloud y otros; coste por capacidad cuando la IA o los servicios corren vía API; y la **comunicación previa al turno** (pre-turno), que no estaba contemplada antes.

## Supuestos base

- **Consultas por médico**: 20/día = 600/mes (mismo que en [infra/costos.md](../infra/costos.md)).
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

Cuando la IA **no** corre en nuestra GPU sino vía **Vertex/Gemini, OpenAI, etc.**, el coste depende del proveedor y del modelo (p. ej. Gemini Flash vs Pro). No se incluye aquí un desglose por token; se asume que el coste por “llamada IA” equivalente es del orden de **$0.005–0.02** según modelo y longitud (referencia típica 2025). Para cifras exactas, usar calculadoras del proveedor y [api/estrategias.md](estrategias.md) para reducir uso (modelo más barato, caché, tokens).

---

## Capacidades que consumen API

### 1. Comunicación previa al turno (pre-turno)

El chat/bot guía al paciente **antes** de sacar el turno. Conversación que puede terminar en turno o no.

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Contactos pre-turno | ~1.000/médico/mes; 4 mensajes × 40% con IA ⇒ **~1.600 llamadas IA** | — |
| **IA vía API** (ej. $0.01/llamada media) | 1.600 × $0.01 | **~$16/médico/mes** (orden de magnitud) |
| **IA vía API** (ej. Flash, ~$0.005/llamada) | 1.600 × $0.005 | **~$8/médico/mes** |

*Rango orientativo*: **~$8–16/médico/mes** según modelo y longitud de respuestas. Ver [api/estrategias.md](estrategias.md) para reducción (caché, reglas, menos % con IA).

---

### 2. Conversación pre-consulta (chat para despejar dudas)

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA | 600 × 5 × 50% = **1.500 llamadas IA** | — |
| **IA vía API** (ej. $0.01/llamada) | 1.500 × $0.01 | **~$15/médico/mes** |
| **IA vía API** (Flash, ~$0.005) | 1.500 × $0.005 | **~$7.50/médico/mes** |

*Rango orientativo*: **~$7.50–15/médico/mes**.

---

### 3. Agente de IA para onboarding y tareas del día a día

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Llamadas IA/médico/mes | ~400 | — |
| **IA vía API** (ej. $0.01/llamada) | 400 × $0.01 | **~$4/médico/mes** |
| **IA vía API** (Flash, ~$0.005) | 400 × $0.005 | **~$2/médico/mes** |

*Rango orientativo*: **~$2–4/médico/mes**.

---

### 4. Intercambio de audios, fotos y videos (STT + Vision)

**Modelo de uso**: Los medios no se almacenan en cloud; solo hay costo cuando se envía a la nube para analizar (STT, Vision). Ver [CAPACIDADES_PACIENTE_MEDICO.md](../../CAPACIDADES_PACIENTE_MEDICO.md).

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Almacenamiento / egress | No se usa cloud storage | **$0** |
| **STT** (transcribir todo el audio) | 1 min/consulta × 600 = 600 min; $0.016/min (V2) | **$9.60** (V1: 60 min gratis → **$8.64**) |
| **Vision** (analizar todas las fotos) | 600 × 2 = 1.200 imágenes; 1.000 gratis + 200 × $1.50/1.000 | **$0.30** |
| **Total medios (STT + Vision)** | — | **~$8.95–9.60/médico/mes** |

---

### 5. Videollamadas paciente–médico

| Concepto | Supuesto | Costo real mensual (por médico) |
|----------|----------|----------------------------------|
| Consultas por videollamada/mes | 30% de 600 = 180 | 180 |
| Duración media | 12 min | 12 min |
| Minutos totales/mes | 180 × 12 = 2.160 min | **2.160 min** |
| **Twilio Video** (2 participantes, $0.004/min c/u) | 2.160 × 2 × $0.004 | **~$17.30/médico/mes** |
| **Plan por asiento** (ej. Daily.co repartido 10 médicos) | — | **~$10/médico/mes** (orden de magnitud) |

---

## Resumen: costo real por API (por médico/mes)

| Capacidad | Costo real (USD/médico/mes) |
|-----------|-----------------------------|
| Comunicación previa al turno (IA vía API) | ~$8–16 |
| Conversación pre-consulta (IA vía API) | ~$7.50–15 |
| Agente onboarding (IA vía API) | ~$2–4 |
| Audios, fotos, videos (STT + Vision, uso máximo) | ~$8.95–9.60 |
| Videollamadas (CPaaS) | ~$10–17.30 |
| **Total adicional (API)** | **~$37–62/médico/mes** (según modelo IA y proveedor video) |

**Nota**: Si la IA de pre-turno, pre-consulta y onboarding corre en **nuestra infra**, esos ítems figuran en [infra/costos.md](../infra/costos.md) y no se duplican aquí; aquí solo se cuentan cuando se usan APIs de IA.

---

## Referencias

- [api/estrategias.md](estrategias.md) – Cómo reducir coste vía API.
- [infra/costos.md](../infra/costos.md) – Costes cuando la IA corre en nuestra GPU.
- [CAPACIDADES_PACIENTE_MEDICO.md](../../CAPACIDADES_PACIENTE_MEDICO.md) – Descripción de las capacidades.
