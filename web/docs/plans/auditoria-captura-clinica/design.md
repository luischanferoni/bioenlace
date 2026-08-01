# Diseño — Auditoría de captura clínica

## Objetivo

Trail durable del pipeline de captura (audio/texto → STT → análisis IA → confirmación humana → guardar) consultable solo por **superadmin** en la app admin.

## Decisiones

- Escritura del trail desde dominio (`EncounterCaptureAuditService`), no desde controllers API.
- Lectura solo en `admin/CapturaClinicaAuditController` con gate `isSuperadmin`.
- Sin API v1 clínica para esta auditoría.
- PHI permitido en detalle (transcript, extracción, staged). Audio completed sigue borrándose.

## Eventos (`encounter_capture_audit.event_type`)

| Evento | Origen |
|--------|--------|
| `UPLOADED` | `crearOSubir` exitoso |
| `STT_OK` / `STT_FAILED` | `transcribir` |
| `ANALYZED` / `ANALYSIS_FAILED` | `analizar` |
| `RESOLUTIONS_APPLIED` | `aplicarResoluciones` |
| `SAVED` / `SAVE_FAILED` | `guardar` |
| `DISCARDED` | `descartar` |

## Meta en `SAVED` (aceptación IA)

Comparar `default_staged_item_ids` del `capture_review` vs `staged_item_ids` finales:

- `ai_accepted_ids` / `ai_rejected_ids`
- `clinical_deselected_ids`
- `counts_by_category` (aceptados/rechazados IA y desmarcados clinical)
- `resolutions` si hubo

## Pantallas admin (v1)

| Ruta | Uso |
|------|-----|
| `/captura-clinica-audit/index` | Listado filtrable de drafts |
| `/captura-clinica-audit/fallos` | Stages fallidos |
| `/captura-clinica-audit/view` | Draft + timeline + resumen aceptación |

## Fuera de Fase 1

Tablero KPIs, motivos pre-consulta, invocaciones IA durables, coding audit, diff FHIR.
