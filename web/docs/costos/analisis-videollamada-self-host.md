# Análisis — Videollamada self-host (COGS y producto)

**Estado:** cifras de planificación **publicadas** (COGS video **1,75** @ **40 %** tele; STT en §2/§4, una sola vez en calculador). Retención: **14 d caliente → Deep Archive** (años; mín. 180 d); **sin 2.ª copia**. Grabación: **Track Egress (muxing)** autoescala 1/4/12. Pendiente: 1 vs 2 pistas de video.  
**Fecha de captura:** 2026-07-17 (actualizado arquitectura + minutos de voz); **2026-07-23:** share tele **40 %** → COGS **1,75** / **0,0044** por atención.  
**Contexto:** self-host sin Daily/Deepgram; pipeline post-call; arquitectura mínima + autoescalado; STT alineado a voz real de consulta.

**COGS video vigente:** [videollamadas.md](./estrategias-reduccion/videollamadas.md), [costos-api.md §6](./costos-api.md#6-videollamadas-pacientemédico) — **USD 1,75** @ **40 %** tele = sala/TURN/Track Egress/ops (~0,75) + storage (~1,00); STT **no** duplicado (mismo que dictado). Histórico techo @ 80 %: **3,50**. Daily+Deepgram: **9,19**. Techo intermedio: **5,00**.

**STT base:** [costos-api.md § STT](./costos-api.md#stt) — médico **~5 min** + paciente **~4 min** por encounter → bruto **~$2,52**/prof/mes en servidor; planificación **−30 % on-device → ~$1,76**. La videollamada **alimenta** esos minutos (no los duplica) cuando el transcript reemplaza dictado / notas de voz.

**Lista comercial:** base **0,95** + audio **0,98** (−30 % on-device) + video **1,75** → AMB con videollamada (STT incluido) **~12,25**/prof/mes @ 400 encounters ([matriz](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md), [`pricing-config.json`](../../../institucional/js/pricing-config.json)).

---

## 1. Decisión de arquitectura

Solo **self-host** para telemedicina. Sin Daily.co ni Deepgram en el modelo objetivo.

| Paso | Qué |
|------|-----|
| 1 | SFU self-host (LiveKit) + **TURN** |
| 2 | **Track Egress** (muxing de pistas; sin re-encode en vivo) |
| 3 | Extraer audio + **VAD / recorte de silencios** (worker post-call) |
| 4 | **STT Whisper** (Groq) sobre voz real (~5 + ~4 min) |
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
│ Pool Track Egress         │  muxing (sin re-encode); autoescala 1/4/12
│ (grabación por pistas)    │
└───────────┬───────────────┘
            ▼
┌───────────────────────────┐
│ Workers batch post-call   │  VAD + STT Groq + lifecycle
│ (cola; escala a cero)     │  → object storage
└───────────────────────────┘
            │
            ▼
   14 días caliente (R2/B2) → Deep Archive (años)
```

| Componente | Rol | Escala |
|------------|-----|--------|
| **Plano de control** | LB ×2, Redis, orquestador API, 1 SFU mínimo, monitoreo | Fijo (piso) |
| **Media en vivo** | Nodos SFU + TURN | Por **concurrencia** (autoescala) |
| **Grabación / Track Egress** | Muxing de pistas (CPU baja) | min 1 / base 4 / max 12; CPU+RAM >75 % |
| **Batch post-call** | VAD + STT + subida a caliente | Por cola (escala a **cero** de noche) |
| **Object storage** | 14 d caliente → Deep Archive (1 copia) | Por **GB acumulados** (no autoescala) |

**Proveedor cómputo/SFU:** cloud con **facturación por hora** y **banda incluida / barata** (DigitalOcean, Linode/Akamai, Hetzner, OVH). En hyperscalers (AWS/GCP) el egress de WebRTC (~$0,05–0,09/GB) invalida el ahorro del autoescalado.  
**Proveedor storage:** caliente R2/B2; frío **solo** AWS S3 Glacier **Deep Archive** (~$0,001/GB-mes; permanencia mínima **180 d** OK).

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
- **Backup / 2.ª copia:** **no** (una sola copia: caliente → Deep Archive).
- **Grabación en vivo:** **Track Egress** (muxing; sin re-encode). Clientes publican ya a 480p/720p @ 15 fps.
- **Batch post-call:** VAD + STT + lifecycle a frío; **no** compositing de pantalla salvo que producto lo pida.
- **Lifecycle:** 14 días en caliente (reclamos inmediatos) → Deep Archive (años; mín. 180 d).

### Grabación: Track Egress + muxing (acordado)

No usar Room Composite / Jibri (Chrome por sala) en vivo: eso satura CPU. Con LiveKit **Track Egress**:

| Regla | Detalle |
|-------|---------|
| Qué hace el servidor | Toma paquetes ya listos (Opus + VP8/H.264) y los **muxea** a WebM/MP4 **sin alterar píxeles** |
| Qué no hace | Transcodificar, renderizar layout, PiP en vivo |
| Origen de calidad | El **cliente** publica a la resolución de archivo (480p/720p @ 15 fps) |
| Unidad de capacidad | **Pistas**, no llamadas (1 tele ≈ hasta 4 pistas: 2 video + 2 audio) |
| Post-call | VAD + STT por pista; unir audio+video del **mismo** participante = remux barato. Componer médico+paciente en un solo video = re-encode batch (**evitar** si nadie re-ve la pantalla) |

**Autoescalado del pool de grabación** (referencia a 1.000 PES; escala lineal):

| Parámetro | Valor |
|-----------|--------|
| Mínimo | **1** instancia (madrugada) |
| Base hora pico | **4** instancias (~8 vCPU c/u) |
| Máximo | **12** instancias |
| Disparo | CPU **o** memoria del clúster > **75 %** → clonar (< 60 s) |
| Admisión | Calibrar `track_cpu_cost` con load test; el default de LiveKit es conservador |
| Pico antes de clonado | Retry / cola a nivel app si Egress responde 503 |

Con muxing puro, 4 instancias base son el **punto de partida** (no un techo fijo). El techo 12 cubre picos. Validar ratio pistas/vCPU y RAM por sesión antes de fijar capacidad en producción.

**Códec / contenedor:** WebRTC suele usar VP8 → grabar **WebM** o publicar **H.264** si el entregable debe ser MP4 sin re-encode. Si hace falta MP4 desde VP8, el remux a MP4 va en **batch**, no en vivo.

---

## 2. Supuestos de uso

| Parámetro | Histórico §6 | Techo análisis @ 80 % | **Vigente lista @ 40 %** |
|-----------|-------------|----------------------|-------------------------|
| % teleconsulta | **30 %** | **80 %** | **40 %** |
| Encounters / prof / mes | 400 | 400 | 400 |
| Teleconsultas / mes | 120 | **320** | **160** |
| Minutos de reloj / teleconsulta | 12 | 12 | 12 |
| **Voz médico (STT)** | (antes 1 min dictado) | **~5 min** | **~5 min** |
| **Voz paciente (STT)** | (antes 1 min motivos) | **~4 min** | **~4 min** |
| Minutos STT facturables / teleconsulta | 12 (pista cruda) | **~9** (VAD, 2 pistas) | **~9** |
| Minutos STT / mes (tele) | 1.440 | **~2.880** (320 × 9) | **~1.440** (160 × 9) |
| Participantes (pax-min sala) | 2 × 12 × 120 | 2 × 12 × 320 = **7.680** | 2 × 12 × 160 = **3.840** |

### Desglose de 12 min de videollamada (planificación)

| Componente | % del tiempo | Minutos |
|------------|-------------:|--------:|
| Silencio / pausas / no-habla | ~25–35 % | ~3–4 |
| **Voz total (ambos)** | ~65–75 % | **~8–9** |
| — Médico | ~55–60 % de la voz | **~5** |
| — Paciente | ~40–45 % de la voz | **~4** |

Groq cobra por **duración del audio enviado**. Sin VAD, 2 pistas crudas = 24 min/llamada; con VAD ≈ 9 min. Mandar **un archivo concatenado por pista** evita el mínimo de 10 s/request.

El **40 %** es el supuesto de lista comercial (no telemetría). El **80 %** queda como techo de dimensionamiento de infra.

---

## 3. Glosario

### Composite vs tracks vs muxing

- **Composite (Room Composite / Jibri):** un solo video “como la pantalla”. Requiere Chrome/GStreamer por sala; **CPU alta**; no usar en vivo a escala.
- **Tracks + Track Egress (muxing):** una pista por participante; el servidor **solo empaqueta** paquetes RTP ya codificados. CPU baja.
- **Batch post-call:** VAD / STT; compositing opcional y caro — solo si producto lo exige.

**Preferencia:** tracks + Track Egress (muxing) + VAD. Sin compositing en el camino feliz.

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
| Worker egress / grabación (**Track Egress**) | Autoescala min 1 / base 4 / max 12; muxing (CPU baja) |
| Worker batch post-call | VAD + STT + lifecycle; a cero de noche |
| TLS / dominio / observabilidad | Bajo |
| Ops humano | Parches, incidentes WebRTC |

A 5.000 PES, cómputo media + grabación + batch con autoescalado: orden **~$2.500–3.500**/mes flotilla (~**$0,50–0,70**/prof). El Track Egress **abarata** el tier de grabación vs composite; el buffer infra **~0,75** del COGS **1,75** (@ 40 %) deja margen operativo.

### 4.2 Variables

| Ítem | Driver | ¿En add-on video? |
|------|--------|-------------------|
| Egress SFU / TURN | Minutos × bitrate × % relay | Sí (casi $0 con banda incluida) |
| Storage (1 copia) | 14 d caliente + Deep Archive acumulado | Sí |
| CPU Track Egress (muxing) | Pistas concurrentes (muy bajo vs composite) | Sí (dentro de infra) |
| CPU batch (VAD / STT prep) | Post-call; a cero de noche | Sí (dentro de infra) |
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

## 5. Storage — lifecycle acordado (caliente + Deep Archive, 1 copia)

**Decisión cerrada:**

| Regla | Valor |
|-------|--------|
| Caliente (R2/B2) | **14 días** (reclamos inmediatos) |
| Frío | **S3 Glacier Deep Archive** (~$0,00099/GB-mes) |
| Permanencia mínima Deep Archive | **180 días** — aceptada |
| Copias | **1** (sin 2.ª copia / backup) |
| Retención total video | **Años** (acumula en frío) |

Con ~0,7 Mbps y 3.840 min de video/mes (1 pista) ≈ **~20 GB nuevos / prof / mes**. Con **2 pistas** ≈ **~40 GB**. Pendiente: 1 vs 2 pistas en archivo.

Stock en régimen (por profesional):

| Capa | 1 pista (~20 GB/mes nuevos) | 2 pistas (~40 GB/mes) |
|------|----------------------------:|----------------------:|
| Caliente estable (~14/30 del mes) | ~9–10 GB → **~$0,07**/mes | ~18–20 GB → **~$0,14**/mes |
| Frío @ 1 año | ~220–240 GB → **~$0,22–0,24** | ~440–480 GB → **~$0,44–0,48** |
| Frío @ 5 años | ~1.200 GB → **~$1,20** | ~2.400 GB → **~$2,40** |
| **Total storage @ 5 años** | **~$1,3**/prof/mes | **~$2,5**/prof/mes |

El frío **sí se acumula** (no es estable): mes 1 ≈ solo caliente + poco frío; a 5 años el frío domina. Sin 2.ª copia, el buffer **~1,00** del COGS video **1,75** (@ 40 %) cubre 1 pista a 5 años y deja margen corto para 2 pistas.

El transcript (texto) para siempre cuesta centavos; la note clínica sale del transcript, no del MP4.

---

## 6. Estimación por etapa (infra video + storage; sin STT duplicado)

Supuestos: cloud por hora + banda incluida; autoescalado; tracks + VAD; STT en §2/§4; **14 d caliente → Deep Archive; 1 copia**. Concurrencia pico ≈ 2× promedio (22 días × 10 h).

| Etapa | PES | Pico llamadas | Arquitectura | Infra + batch (mes) | Storage mes 1* | Storage @ 5 años* | **Total etapa (mes 1)** | **Total @ 5 años*** |
|-------|----:|--------------:|--------------|--------------------:|---------------:|------------------:|------------------------:|--------------------:|
| 0 | 0 (dev) | 0 | 1 caja unificada; sin HA | **$20–30** | ~0 | ~0 | **$20–30** | **$20–30** |
| 1 | 100 | ~60 | Plano control + 1 SFU + 1 batch | **~$450–550** | **~$15–30** | **~$130–250** | **~$465–580** | **~$580–800** |
| 2 | 500 | ~290 | Autoescala 3–4 SFU pico → 1 noche | **~$700–900** | **~$70–140** | **~$650–1.250** | **~$770–1.040** | **~$1.350–2.150** |
| 3 | 1.000 | ~580 | ~7 SFU pico → 1 noche; batch pool | **~$1.000–1.400** | **~$140–280** | **~$1.300–2.500** | **~$1.140–1.680** | **~$2.300–3.900** |
| 4 | 5.000 | ~2.900 | Cluster; ~30 nodos pico; duty ~40 % | **~$3.200–4.000** | **~$700–1.400** | **~$6.500–12.500** | **~$3.900–5.400** | **~$9.700–16.500** |

\* Rango bajo = 1 pista; alto = 2 pistas. Mes 1 ≈ casi todo caliente + poco frío; a 5 años domina Deep Archive.

**Por PES a 5.000 (@ 5 años):**

| Componente | USD / PES / mes |
|------------|----------------:|
| Infra SFU+TURN+batch+ops (autoescalado) | **~0,60–0,80** |
| Storage 14 d caliente + Deep Archive (1 copia) | **~1,3–2,5** |
| STT (ya en §2/§4) | **0 aquí** |
| **Add-on video orientativo** | **~2–3,5** |

El COGS publicado **1,75** (@ **40 %** tele) = infra buffer **~0,75** + storage buffer **~1,00**. Con Track Egress el gasto real de cómputo baja (~0,5–0,8); el **1,75** deja margen vs real escalado a ~40 %. Comparación con histórico **9,19** (@ 30 % + Deepgram), techo intermedio **5,00** y techo @ 80 % **3,50**: self-host + STT en base + lifecycle queda por debajo.

---

## 7. Escenarios de COGS add-on (alineados a decisión)

Supuestos: 5.000 prof, **80 %** tele, TURN sí, **Track Egress (muxing)**, VAD, STT **fuera** del add-on (una vez en calculador), banda incluida, **14 d caliente → Deep Archive, 1 copia**.

| Componente | 1 pista @ 5 años | 2 pistas @ 5 años |
|------------|-----------------:|------------------:|
| SFU + TURN + Track Egress + ops | ~0,5–0,8 | ~0,5–0,8 |
| STT Whisper | 0 aquí | 0 aquí |
| Storage (caliente + Deep Archive, 1 copia) | **~1,3** | **~2,5** |
| **Total orientativo add-on (real)** | **~1,8–2,1** | **~3,0–3,3** |

### ¿Afecta el COGS video publicado (sin STT duplicado)?

| Pregunta | Respuesta |
|----------|-----------|
| ¿Baja el gasto **real** de cómputo? | **Sí.** Muxing + autoescalado 1/4/12 deja el tier de grabación en centavos–dólares por prof. |
| ¿Cambia storage? | **No** (sigue 14 d → Deep Archive; 1 vs 2 pistas). |
| ¿Cambia STT del add-on? | **No** (sigue en §2/§4; calculador: una sola vez con videollamada). |
| ¿Bajamos el **5,00** de lista / metadata? | **Sí → 1,75** @ **40 %** tele. Recomendado: infra ~0,75 + storage ~1,00. Techos históricos: **5,00** intermedio; **3,50** @ 80 %. |

**COGS lista vigente:** **1,75** USD/prof/mes @ **40 %** tele = infra buffer **~0,75** + storage buffer **~1,00**.

Precio lista (margen 233 % sobre COGS):

| COGS video | Delta lista aprox. |
|------------|--------------------|
| 9,19 (histórico Daily+Deepgram) | ~+30,6 |
| 5,00 (techo intermedio self-host) | ~+16,7 |
| **1,75 (vigente @ 40 %)** | **~+5,8** |
| 3,50 (techo @ 80 %) | ~+11,7 |

---

## 8. Checklist cerrado / abierto

| # | Tema | Estado |
|---|------|--------|
| 1 | Lifecycle storage | **Cerrado:** 14 d caliente → **Deep Archive** (años; mín. 180 d OK) |
| 2 | Composite vs tracks | **Tracks + Track Egress (muxing)**; sin Room Composite en vivo |
| 3 | TURN | **Sí** |
| 4 | STT | Groq; **~5 min médico + ~4 min paciente**; ya en §2/§4 |
| 5 | Arquitectura | Mínimo + **autoescalado agresivo**; cloud hora + banda barata |
| 6 | Grabación | Track Egress; autoescala **min 1 / base 4 / max 12**; disparo CPU+RAM >75 % |
| 7 | Backup / 2.ª copia | **No** (1 copia) |
| 8 | IA en add-on video | **No sumar** |
| 9 | % teleconsulta | **40 %** vigente lista (techo histórico análisis **80 %**; antes 30 %) |
| 10 | 1 vs 2 pistas de video en archivo | **Abierto** (mueve storage ~1,3 vs ~2,5/prof @ 5 años) |
| 11 | COGS video lista | **1,75** vigente @ 40 % (**0,0044**/atención); techo @ 80 % era **3,50** |

### Glosario de errores a no repetir

1. El **3,00** publicado nombraba “grabación” pero **no** modelaba storage multi-año.
2. Storage de video **no** se prorratea entre 5.000; **se acumula** en Deep Archive.
3. No mezclar **GB** con **USD** sin tarifa $/GB/mes (caliente ≠ Deep Archive).
4. No duplicar STT de §2/§4 si el transcript de video **reemplaza** dictado/motivos (calculador: una sola vez).
5. No modelar 12×2 = 24 min STT sin VAD: la voz real es ~9 min.
6. Autoescalar en AWS/GCP sin controlar egress destruye el ahorro de cómputo.
7. No contar storage como “estable el primer año”: el frío crece mes a mes.
8. No dimensionar grabación como “llamadas”: son **pistas**; no usar Room Composite a escala.
9. El techo **5,00** fue intermedio; techo @ 80 % **3,50**; vigente @ **40 %** **1,75** (= ~0,75 infra + ~1,00 storage).

---

## 9. Archivos alineados (2026-07-17)

| Área | Archivos |
|------|----------|
| Costos | `videollamadas.md`, `costos-api.md` §6, `stt.md`, `resumen-costos-bioenlace.md`, `impuestos-argentina.md`, `overview.md`, `estrategias-api.md` |
| Pricing | `pricing-pes-by-encounter-class.yaml` |
| Institucional | `institucional/js/pricing-config.json`, `institucional/README.md` |
| Negocio | `matriz-argentina-modulos-precios.md`, `mapa-vias-ingreso-bioenlace.md`, `modelos-pricing-diferenciados.md` |

**Cerrado en storage:** caliente 14 d + Deep Archive + sin 2.ª copia.  
**Cerrado en grabación:** Track Egress (muxing) + autoescala 1/4/12.  
**Cerrado en lista:** COGS video **1,75** @ **40 %** tele; calculador no duplica STT con dictado+videollamada. Techo @ 80 % era **3,50**.  
**Pendiente:** 1 vs 2 pistas de video en archivo.

## 10. Próxima conversación — agenda sugerida

1. **Cerrar 1 vs 2 pistas** de video en archivo (storage @ 5 años ~1,3 vs ~2,5/prof).
2. Load test: calibrar `track_cpu_cost`, RAM por sesión y ratio pistas/vCPU.
3. (Opcional) Doc técnico LiveKit: rooms, tokens, webhooks `meeting.ended`, Track Egress, worker.

---

## 11. Referencias

- COGS actual: [videollamadas.md](./estrategias-reduccion/videollamadas.md), [costos-api.md](./costos-api.md)
- STT captura: [stt.md](./estrategias-reduccion/stt.md)
- Matriz precios: [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md)
- Institucional: [`institucional/js/pricing-config.json`](../../../institucional/js/pricing-config.json)
