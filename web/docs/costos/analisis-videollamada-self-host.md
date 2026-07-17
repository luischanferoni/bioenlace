# Análisis — Videollamada self-host (COGS y producto)

**Estado:** cifras de planificación **publicadas** en matriz / calculador (COGS video **5,00**; STT en §2/§4). Pendiente de producto: retención video A vs B y 1 vs 2 pistas.  
**Fecha de captura:** 2026-07-17 (actualizado arquitectura + minutos de voz + lista comercial)  
**Contexto:** self-host sin Daily/Deepgram; pipeline post-call; arquitectura mínima + autoescalado; STT alineado a voz real de consulta.

**COGS video vigente:** [videollamadas.md](./estrategias-reduccion/videollamadas.md), [costos-api.md §6](./costos-api.md#6-videollamadas-pacientemédico) — **USD 5,00** = sala/TURN/ops + storage (STT **no** incluido). Histórico Daily+Deepgram: **9,19**.

**STT base:** [costos-api.md § STT](./costos-api.md#stt) — médico **~5 min** + paciente **~4 min** por encounter → **~$2,52**/prof/mes en servidor. La videollamada **alimenta** esos minutos (no los duplica) cuando el transcript reemplaza dictado / notas de voz.

**Lista comercial:** base **0,95** + audio **1,40** + video **5,00** → AMB audio+video **~24,48**/prof/mes ([matriz](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md), [`pricing-config.json`](../../../institucional/js/pricing-config.json)).

---

## 1. Decisión de arquitectura

Solo **self-host** para telemedicina. Sin Daily.co ni Deepgram en el modelo objetivo.

| Paso | Qué |
|------|-----|
| 1 | SFU self-host (LiveKit u equivalente) + **TURN** |
| 2 | **Persistir** grabaciones (tracks separados) |
| 3 | Extraer audio + **VAD / recorte de silencios** (FFmpeg / worker post-call) |
| 4 | **STT Whisper** (Groq `whisper-large-v3-turbo`) sobre voz real (~5 + ~4 min) |
| 5 | Texto → note del encounter → flujo existente (`analisis-consulta` / revisión / `guardar`) |

### Principio de escala

**Empezar con el mínimo** (plano de control fijo) y **sumar servidores** según demanda. Autoescalado agresivo: apagar nodos en baja demanda (noche, fines de semana). El diseño es horizontal desde el día 1; el costo fijo no crece lineal con PES.

### Arquitectura objetivo (capas)

```
Clientes (paciente / médico)
        │
        ▼
┌───────────────────────────┐
│ Balanceador L4 (×2 HA)    │  señalización / TLS
└───────────┬───────────────┘
            ▼
┌───────────────────────────┐
│ Cluster SFU LiveKit       │  autoescala por llamadas concurrentes
│ + TURN (coturn)           │  Redis HA para multi-nodo
└───────────┬───────────────┘
            ▼
┌───────────────────────────┐
│ Pool Egress / grabación   │  tracks raw en vivo (liviano)
│ (autoescala / a cero)     │
└───────────┬───────────────┘
            ▼
┌───────────────────────────┐
│ Workers batch post-call   │  FFmpeg + VAD + transcode 480p/720p
│ (cola; escala a cero)     │  → STT Groq → object storage frío
└───────────────────────────┘
```

| Componente | Rol | Escala |
|------------|-----|--------|
| **Plano de control** | LB ×2, Redis, orquestador API, 1 SFU mínimo, monitoreo | Fijo (piso) |
| **Media en vivo** | Nodos SFU + TURN | Por **concurrencia** (autoescala) |
| **Grabación / egress** | Pool de workers que capturan tracks | Por salas activas (autoescala) |
| **Batch post-call** | Transcode + VAD + subida a frío | Por cola (escala a **cero** de noche) |
| **Object storage frío** | Video + backup | Por **GB acumulados** (no autoescala) |

**Proveedor:** cloud con **facturación por hora** y **banda incluida / barata** (p. ej. Hetzner Cloud, OVH Public Cloud). En hyperscalers (AWS/GCP) el egress de WebRTC (~$0,05–0,09/GB) invalida el ahorro del autoescalado.

### Flujo clínico post-call

1. Grabar **tracks separados** (paciente / profesional) — no composite.
2. **VAD / silenceremove** → solo voz real (médico ~5 min, paciente ~4 min).
3. **Primero** audio del **paciente** → Whisper → motivos de consulta (§2).
4. Transcript completo (pistas) como **note** del encounter → §4 análisis / codificación.
5. **No** sumar STT otra vez en el add-on video: ya está en §2/§4 con los minutos actualizados.
6. **No** sumar la IA de análisis otra vez: ya está en COGS base.

### Ops

- **TURN:** obligatorio en producción.
- **Worker post-call:** cola OK; no preocupa latencia del transcript.
- Escala de planificación: **5.000 profesionales**.
- **Backup** de grabaciones: sí (2.ª copia).
- Captura en vivo = tracks raw; transcode a calidad médica (480p/720p) en batch.

---

## 2. Supuestos de uso

| Parámetro | Publicado hoy (§6) | Acordado en este análisis |
|-----------|-------------------|---------------------------|
| % teleconsulta | **30 %** | **80 %** (agresivo) |
| Encounters / prof / mes | 400 | 400 |
| Teleconsultas / mes | 120 | **320** |
| Minutos de reloj / teleconsulta | 12 | 12 |
| **Voz médico (STT)** | (antes 1 min dictado) | **~5 min** |
| **Voz paciente (STT)** | (antes 1 min motivos) | **~4 min** |
| Minutos STT facturables / teleconsulta | 12 (pista cruda) | **~9** (VAD, 2 pistas) |
| Minutos STT / mes @ 80 % | 1.440 | **~2.880** (320 × 9) |
| Participantes (pax-min sala) | 2 × 12 × 120 | 2 × 12 × 320 = **7.680** |

### Desglose de 12 min de videollamada (planificación)

| Componente | % del tiempo | Minutos |
|------------|-------------:|--------:|
| Silencio / pausas / no-habla | ~25–35 % | ~3–4 |
| **Voz total (ambos)** | ~65–75 % | **~8–9** |
| — Médico | ~55–60 % de la voz | **~5** |
| — Paciente | ~40–45 % de la voz | **~4** |

Groq cobra por **duración del audio enviado**. Sin VAD, 2 pistas crudas = 24 min/llamada; con VAD ≈ 9 min. Mandar **un archivo concatenado por pista** evita el mínimo de 10 s/request.

El **80 %** es hipótesis de planificación (no telemetría).

---

## 3. Glosario

### Composite vs tracks

- **Composite:** un solo video “como la pantalla”. Más pesado.
- **Tracks (raw):** una pista por participante. Más barato; STT sin diarización.

**Preferencia:** tracks + VAD.

### TURN

Retransmite A/V cuando falla el camino directo (NAT/firewall). Obligatorio; se paga VM + ancho de banda (casi gratis si el proveedor incluye tráfico).

### Storage: qué se diluye y qué no

- **Infra compartida** (SFU, TURN, workers, ops): fijo ÷ N → a 5.000 se diluye; con autoescalado el promedio mensual es menor que el pico.
- **Storage de grabaciones:** **por profesional**, se **acumula** con la retención. No prorratear el stock entre 5.000 como si fuera un gasto único.
- **STT:** por minuto de voz; ya modelado en §2/§4.

---

## 4. Inventario de costos

### 4.1 Semi-fijos / compartidos (autoescalables en media)

| Ítem | Notas |
|------|--------|
| Plano de control (LB, Redis, 1 SFU mínimo) | Piso ~**$350–450**/mes siempre |
| SFU LiveKit + TURN | Nodos por concurrencia; duty cycle ~40 % |
| Worker egress / grabación | Autoescala; captura raw |
| Worker batch FFmpeg | On-demand / a cero |
| TLS / dominio / observabilidad | Bajo |
| Ops humano | Parches, incidentes WebRTC |

A 5.000 PES, cómputo media + batch con autoescalado: orden **~$3.000–3.500**/mes flotilla (~**$0,60–0,70**/prof). Buffer histórico sala **3,00** sigue como techo publicado.

### 4.2 Variables

| Ítem | Driver | ¿En add-on video? |
|------|--------|-------------------|
| Egress SFU / TURN | Minutos × bitrate × % relay | Sí (casi $0 con banda incluida) |
| Storage + backup | GB-mes × retención × ~2 copias | Sí |
| CPU FFmpeg | Minutos de media | Sí (batch) |
| STT Whisper (Groq) | ~9 min voz / teleconsulta | **No** — ya en §2/§4 |
| IA motivos + análisis | Tokens | **No** — ya en §2/§4 |

### 4.3 STT: lectura correcta

| Modo | Min/teleconsulta | @ 320 tele (80 %) | Groq ~$0,0007/min |
|------|----------------:|------------------:|------------------:|
| 2 pistas crudas (sin VAD) | 24 | 7.680 | **~$5,4** |
| 1 pista mezclada cruda | 12 | 3.840 | **~$2,7** |
| **2 pistas + VAD (acordado)** | **~9** (5+4) | **~2.880** | **~$2,0** |

Esos **~$2,0**/prof/mes @ 80 % tele están **parte** del STT base (~$2,52 a 400 encounters × 9 min). El add-on video **no** vuelve a sumarlos.

---

## 5. Storage multi-año — el punto crítico

Con ~0,7 Mbps y 3.840 min de video/mes (1 pista) ≈ **~20 GB nuevos / prof / mes**. Con **2 pistas de video** ≈ **~40 GB**. Preferencia de producto pendiente: 1 vs 2 pistas en archivo.

| Política | GB acumulados / prof (orden) | Frío Deep Archive (~$0,001/GB × 2 copias) | Caliente (~$0,007/GB) |
|----------|------------------------------|------------------------------------------|------------------------|
| Video 30–90 días | ~20–60 (1 pista) | **~$0,04–0,12** | **~$0,3–0,8** |
| Audio años (~0,7 MB/min) | ~160 @ 5 años | **~$0,3** | — |
| **Video 5 años (1 pista)** | **~1.200** | **~$2,4** | **~$11** |
| **Video 5 años (2 pistas)** | **~2.400** | **~$4,8** | **~$22** |

**Pendiente de producto (bloquear COGS video hasta decidir):**

| Opción | Efecto en storage del add-on |
|--------|------------------------------|
| A — Video años + backup | Frío ~**2,4–4,8**/prof → total add-on infra+storage **~4–8** |
| B — Video corto (30–90 d) + audio/transcript años | Storage **~$0,5–2** → total add-on **~2–4** |

El transcript (texto) para siempre cuesta centavos.

---

## 6. Estimación por etapa (infra video + storage; sin STT duplicado)

Supuestos: cloud por hora + banda incluida; autoescalado; tracks + VAD; STT en §2/§4; storage frío con 2 copias. Concurrencia pico ≈ 2× promedio (22 días × 10 h).

| Etapa | PES | Pico llamadas | Arquitectura | Infra + batch (mes) | Storage mes 1 | Storage estabilizado 5 años* | **Total etapa (mes 1)** | **Total estabilizado*** |
|-------|----:|--------------:|--------------|--------------------:|--------------:|-----------------------------:|------------------------:|------------------------:|
| 0 | 0 (dev) | 0 | 1 caja unificada; sin HA | **$20–30** | ~0 | ~0 | **$20–30** | **$20–30** |
| 1 | 100 | ~60 | Plano control + 1 SFU + 1 batch | **~$450–550** | **~$40–80** | **~$240–480** | **~$500–630** | **~$700–1.000** |
| 2 | 500 | ~290 | Autoescala 3–4 SFU pico → 1 noche | **~$700–900** | **~$200–400** | **~$1.200–2.400** | **~$900–1.300** | **~$1.900–3.300** |
| 3 | 1.000 | ~580 | ~7 SFU pico → 1 noche; batch pool | **~$1.000–1.400** | **~$400–800** | **~$2.400–4.800** | **~$1.400–2.200** | **~$3.400–6.200** |
| 4 | 5.000 | ~2.900 | Cluster; ~30 nodos pico; duty ~40 % | **~$3.200–4.000** | **~$2.000–4.000** | **~$12.000–24.000** | **~$5.200–8.000** | **~$15.000–28.000** |

\* Rango bajo = 1 pista video a frío; alto = 2 pistas. Sin telemetría de bitrate real.

**Por PES a 5.000 (estabilizado, frío):**

| Componente | USD / PES / mes |
|------------|----------------:|
| Infra SFU+TURN+batch+ops (autoescalado) | **~0,60–0,80** |
| Storage + backup (opción A, frío) | **~2,4–4,8** |
| STT (ya en §2/§4) | **0 aquí** |
| **Add-on video orientativo** | **~3–6** (B) / **~5–8** (A frío) |

Comparación con histórico **9,19** (@ 30 % + Deepgram): el camino self-host + STT en base + storage frío queda en **5,00** de add-on; el salto de uso al **80 %** y los minutos de voz se absorbieron al subir §2/§4.

---

## 7. Escenarios de COGS add-on (propuestos, no publicados)

Supuestos: 5.000 prof, **80 %** tele, TURN sí, VAD, STT **fuera** del add-on (en §2/§4), worker on-demand, banda incluida.

| Componente | Camino barato (B) | Video 5 años frío (A, 1 pista) | Video 5 años frío (A, 2 pistas) |
|------------|-------------------|-------------------------------|--------------------------------|
| SFU + TURN + workers + ops | ~0,6–1,0 | ~0,6–1,0 | ~0,6–1,0 |
| STT Whisper | 0 aquí | 0 aquí | 0 aquí |
| Storage + backup | ~0,5–2,0 | ~2,4 | ~4,8 |
| **Total orientativo add-on** | **~1,5–3** | **~3–4** | **~5–6** |

Cifra de planificación sugerida (hasta cerrar retención): **~4,00–6,00** USD/prof/mes (add-on video), dejando STT en el COGS base §2/§4.

Precio lista (margen 233 % sobre COGS):

| COGS video | Delta lista aprox. |
|------------|--------------------|
| 9,19 (histórico Daily+Deepgram) | ~+30,6 |
| **5,00 (vigente self-host)** | **~+16,7** |
| ~4–6 (rango A/B frío) | ~+13–20 |

---

## 8. Checklist cerrado / abierto

| # | Tema | Estado |
|---|------|--------|
| 1 | Retención video | Usuario: **años**. Análisis: frío ~$2–5; falta **confirmar A vs B** y 1 vs 2 pistas |
| 2 | Composite vs tracks | **Tracks** + VAD |
| 3 | TURN | **Sí** |
| 4 | STT | Groq; **~5 min médico + ~4 min paciente**; ya en §2/§4 |
| 5 | Arquitectura | Mínimo + **autoescalado agresivo**; cloud hora + banda barata |
| 6 | Worker | On-demand / a cero |
| 7 | Backup | **Sí** (2.ª copia) |
| 8 | IA en add-on video | **No sumar** |
| 9 | % teleconsulta | **80 %** (antes 30 %) |

### Glosario de errores a no repetir

1. El **3,00** publicado nombraba “grabación” pero **no** modelaba storage multi-año.
2. Storage de video **no** se prorratea entre 5.000; **se acumula** con la retención.
3. No mezclar **GB** con **USD** sin tarifa $/GB/mes (caliente ≠ frío).
4. No duplicar STT de §2/§4 si el transcript de video **reemplaza** dictado/motivos.
5. No modelar 12×2 = 24 min STT sin VAD: la voz real es ~9 min.
6. Autoescalar en AWS/GCP sin controlar egress destruye el ahorro de cómputo.

---

## 9. Archivos alineados (2026-07-17)

| Área | Archivos |
|------|----------|
| Costos | `videollamadas.md`, `costos-api.md` §6, `stt.md`, `resumen-costos-bioenlace.md`, `impuestos-argentina.md`, `overview.md`, `estrategias-api.md` |
| Pricing | `pricing-pes-by-encounter-class.yaml` |
| Institucional | `institucional/js/pricing-config.json`, `institucional/README.md` |
| Negocio | `matriz-argentina-modulos-precios.md`, `mapa-vias-ingreso-bioenlace.md`, `modelos-pricing-diferenciados.md` |

**Pendiente:** retención A vs B y 1 vs 2 pistas de video (puede mover el **~2,00** de storage dentro del 5,00).

## 10. Próxima conversación — agenda sugerida

1. **Cerrar retención:** ¿video 5 años (A) o corto + audio/transcript (B)? ¿1 o 2 pistas de video?
2. Ajustar el **~2,00** de storage dentro del 5,00 si hace falta.
3. (Opcional) Doc técnico LiveKit: rooms, tokens, webhooks `meeting.ended`, worker, Encounter.

---

## 11. Referencias

- COGS actual: [videollamadas.md](./estrategias-reduccion/videollamadas.md), [costos-api.md](./costos-api.md)
- STT captura: [stt.md](./estrategias-reduccion/stt.md)
- Matriz precios: [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md)
- Institucional: [`institucional/js/pricing-config.json`](../../../institucional/js/pricing-config.json)
