# Análisis — Videollamada self-host (COGS y producto)

**Estado:** borrador de decisión — **no** actualiza aún el COGS publicado (`9,19`), ni metadata, ni calculador institucional.  
**Fecha de captura:** 2026-07-17  
**Contexto:** conversación sobre integrar telemedicina sin Daily/Deepgram; pipeline post-call; costos fijos vs variables.

**COGS publicado hoy (referencia):** [videollamadas.md](./estrategias-reduccion/videollamadas.md), [costos-api.md §6](./costos-api.md#6-videollamadas-pacientemédico) — **USD 9,19** = sala/TURN/ops **3,00** + Deepgram post-call **~6,19** (supuesto **30 %** tele).

---

## 1. Decisión de arquitectura (acordada)

Solo **self-host** para telemedicina. Sin Daily.co ni Deepgram en el modelo objetivo.

| Paso | Qué |
|------|-----|
| 1 | SFU self-host (LiveKit u equivalente) + **TURN** |
| 2 | **Persistir** grabaciones (tracks) |
| 3 | Extraer audio (FFmpeg / worker post-call) |
| 4 | **STT Whisper** (opción barata: API Groq `whisper-large-v3-turbo`, mismo stack § STT) |
| 5 | Texto → note del encounter → flujo existente de análisis (`analisis-consulta` / revisión / `guardar`) |

### Flujo clínico post-call (detalle producto)

1. Grabar **tracks separados** (paciente / profesional) — no hace falta composite (ver §3).
2. **Primero** audio del **paciente** → Whisper → alimenta el flujo actual de **motivos de consulta** (§2: batch / insights), para sugerir al abrir la HC.
3. Luego transcript completo (o pistas) como **note** del encounter → §4 análisis / codificación al guardar.
4. **No** sumar la IA de análisis otra vez dentro del add-on videollamada: ya está en COGS base / captura (§2–§4).

### Ops

- **TURN:** sí, obligatorio en producción.
- **Worker post-call:** bajo demanda / cola OK; **no** preocupa latencia del transcript.
- Escala de planificación: **5.000 profesionales** rápido.
- **Backup** de grabaciones: sí.

---

## 2. Supuestos de uso (nuevo vs publicado)

| Parámetro | Publicado hoy | Acordado en este análisis |
|-----------|---------------|---------------------------|
| % teleconsulta | **30 %** | **80 %** (agresivo) |
| Encounters / prof / mes | 400 | 400 (sin cambio) |
| Teleconsultas / mes | 120 | **320** |
| Minutos / teleconsulta | 12 | 12 |
| Minutos grabados / mes (1 pista) | 1.440 | **3.840** |
| Participantes (pax-min sala) | 2 × 12 × 120 | 2 × 12 × 320 = **7.680 pax-min** |

El **80 %** es hipótesis de planificación (no telemetría). Con telemetría real se podrá bajar el COGS; no conviene bajar el precio de lista antes.

---

## 3. Glosario (para retomar la conversación)

### Composite vs tracks

- **Composite:** un solo video “como la pantalla” (layout mezclado). Más pesado de generar; útil si el profesional debe re-ver la consulta como video.
- **Tracks (raw):** una pista por participante. Más barato; para STT basta el audio; la pista del paciente sirve para motivos **sin** diarización.

**Preferencia en este análisis:** tracks (lo más barato), no composite.

### TURN

Servidor que **retransmite** audio/video cuando falla el camino directo (NAT/firewall de clínicas, 4G, etc.). Sin TURN muchas llamadas no conectan; con TURN se paga VM + **ancho de banda**.

### Storage: qué se diluye y qué no

- **Infra compartida** (SFU, TURN, workers, ops): costo fijo ÷ N profesionales → a 5.000 se diluye.
- **Storage de grabaciones:** es **por profesional** (cada uno genera sus GB). **No** se divide entre 5.000. Error frecuente: tomar GB acumulados como si fueran USD o prorratearlos entre la flota.

---

## 4. Inventario de costos (nada se escape)

### 4.1 Semi-fijos / compartidos (prorrateables a 5.000)

| Ítem | Notas |
|------|--------|
| SFU LiveKit (o equiv.) | VM siempre arriba |
| Redis (si aplica) | Estado rooms |
| TURN (coturn) | Base + egress variable |
| TLS / dominio / LB | Bajo |
| Worker / cola post-call | On-demand OK a escala |
| Observabilidad | Logs, alertas salas |
| Ops humano | Parches, incidentes WebRTC |

Buffer orientativo SFU+TURN+ops a 5.000: **~2–3 USD / prof / mes** (mismo espíritu del **3,00** publicado).

### 4.2 Variables (escalan con 80 %)

| Ítem | Driver |
|------|--------|
| Egress SFU / TURN | Minutos × bitrate × % relay |
| Storage + backup | GB-mes × retención × ~1,5–2 backup |
| CPU FFmpeg | Minutos de media |
| STT Whisper (Groq) | Minutos de audio |
| IA motivos + análisis | **Ya en §2/§4 — no en add-on video** |

### 4.3 STT: dos lecturas

| Modo | Min/mes @ 80 % | Groq ~$0,0007/min |
|------|----------------|-------------------|
| 1 pista mezclada | 3.840 | **~$2,7** |
| **2 pistas** (paciente + profesional) — alineado a motivos | 7.680 | **~$5,4** |

Recomendación: **Groq API** (más barato que GPU Whisper propia a esta escala de producto; ya usado en captura clínica). Self-host Whisper GPU solo si hay decisión explícita de no salir datos de audio.

---

## 5. Storage multi-año — el punto crítico

Usuario pidió **video por años**. Con ~0,7 Mbps y 3.840 min/mes ≈ **~20 GB nuevos / prof / mes**.

| Política | GB acumulados / prof (orden) | Storage+backup barato (~$0,009/GB/mes × ~1,5–2) |
|----------|------------------------------|--------------------------------------------------|
| Video 30–90 días | ~20–60 | **~$0,2–0,5** |
| Audio años (~0,7 MB/min) | ~160 @ 5 años | **~$1,4** |
| **Video 5 años** | **~1.200 (≈1,2 TB)** | **~$11 / prof / mes** |

Conclusión del análisis: **video 5 años es el ítem más caro del add-on** (~$11), mayor que STT y que el buffer de sala. El transcript (texto) para siempre cuesta centavos; el note clínico sale del transcript, no del MP4.

**Pendiente de producto (bloquear actualización de COGS hasta decidir):**

| Opción | Efecto en COGS video |
|--------|----------------------|
| A — Video años + backup | Storage ~**11** → total add-on **~19–21** |
| B — Video corto (30–90 d) + audio/transcript años | Storage ~**1,5–2** → total add-on **~9–10** |

---

## 6. Escenarios de COGS add-on (propuestos, no publicados)

Supuestos: 5.000 prof, **80 %** tele, TURN sí, Groq 2 pistas, IA **fuera** del add-on, worker on-demand.

| Componente | Camino barato (B) | Video 5 años (A) |
|------------|-------------------|------------------|
| SFU + TURN + workers + ops | ~2,0–3,0 | ~2,0–3,0 |
| STT Whisper 2 pistas | ~5,4 | ~5,4 |
| Storage + backup | ~1,5–2,0 | ~11 |
| IA §2/§4 | 0 aquí | 0 aquí |
| **Total orientativo** | **~9–10** | **~19–21** |

Comparación con publicado:

- Hoy **9,19** @ **30 %** + Deepgram.
- Con **80 %** + self-host + Groq, el camino **B** queda en el mismo orden (~9–10): el salto de uso se come el ahorro de dejar Deepgram.
- El camino **A** exige **subir precio de lista** o aceptar menor margen.

Precio lista (margen 233 % sobre COGS, como matriz actual):

| COGS video | Delta lista aprox. | AMB+audio+video (orden) |
|------------|--------------------|-------------------------|
| 9,19 (hoy) | ~+30,6 | ~34,30 |
| ~9–10 (B) | ~+30–33 | similar al hoy |
| ~19–21 (A) | ~+63–70 | **sube fuerte** |

---

## 7. Checklist cerrado / abierto

| # | Tema | Estado |
|---|------|--------|
| 1 | Retención video | Usuario: **años**. Análisis: costoso (~$11); falta **confirmar A vs B** |
| 2 | Composite vs tracks | **Tracks** (más barato); motivos = pista paciente |
| 3 | TURN | **Sí** |
| 4 | STT | Lo más barato → **Groq Whisper**; 2 pistas |
| 5 | Worker | On-demand OK; escala 5.000 |
| 6 | Backup | **Sí** |
| 7 | IA en add-on video | **No sumar** (ya §2/§4) |
| 8 | % teleconsulta | **80 %** (antes 30 %) |

### Glosario de errores a no repetir

1. El **3,00** publicado nombraba “grabación” pero **no** modelaba storage multi-año ni recording cloud Daily.
2. Storage de video **no** se prorratea entre 5.000 profesionales.
3. No mezclar **GB** con **USD** sin tarifa $/GB/mes.
4. No duplicar STT de dictado §4 si el transcript de video **reemplaza** el dictado; sí modelar **minutos de video** (mucho mayores que 1 min de dictado).

---

## 8. Archivos a tocar cuando se cierre la decisión

Tras fijar retención (A vs B) y el número final de COGS:

| Área | Archivos |
|------|----------|
| Costos | `estrategias-reduccion/videollamadas.md`, `costos-api.md` §6 + totales, `stt.md`, `estrategias-api.md`, `overview.md`, `resumen-costos-bioenlace.md`, `impuestos-argentina.md` |
| Pricing | `common/metadata/bioenlace/organization/pricing-pes-by-encounter-class.yaml` |
| Institucional | `institucional/js/pricing-config.json`, `institucional/README.md` |
| Negocio | `matriz-argentina-modulos-precios.md`, `mapa-vias-ingreso-bioenlace.md`, `modelos-pricing-diferenciados.md` |

Sacar del COGS / copy: Daily pay-as-you-go y Deepgram post-call como camino principal. Dejar Daily solo como nota histórica si hace falta.

---

## 9. Próxima conversación — agenda sugerida

1. **Cerrar retención:** ¿video 5 años (A, COGS ~19–21) o video corto + audio/transcript años (B, COGS ~9–10)?
2. Fijar cifra única de planificación (ej. **9,50** o **20,00**) y supuesto de bitrate/GB.
3. Actualizar docs + YAML + `pricing-config.json` + textos institucionales.
4. (Opcional) Documento de integración técnica LiveKit: rooms, tokens, webhooks `meeting.ended`, worker, vínculo Encounter — separado de este análisis de costos.

---

## 10. Referencias

- COGS actual: [videollamadas.md](./estrategias-reduccion/videollamadas.md), [costos-api.md](./costos-api.md)
- STT captura: [stt.md](./estrategias-reduccion/stt.md)
- Matriz precios: [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md)
- Institucional: [`institucional/js/pricing-config.json`](../../../institucional/js/pricing-config.json)
