# Overview — Cohortes y batch

## Problema

Personalizar asistencia pre-consulta, seguimiento post y educación **sin** una inferencia por paciente cuando el perfil clínico es equivalente (~90 %). Sacrificar latencia en generación (minutos–horas) a cambio de costo y reutilización.

## Actores

- **Paciente** — responde packs (formulario/chips), notificaciones de seguimiento.
- **Profesional** — ve contexto enriquecido; no genera packs manualmente en Fase 1.
- **Sistema** — asigna cohorte, encola jobs, Vertex batch o cola sync, persiste packs versionados.

## Entregables por fase

| Fase | Entrega |
|------|---------|
| 1 | Tablas, cohort key, cola jobs, generación sync, hook encounter, consola |
| 2 | API/UI asistencia pre-turno desde pack |
| 3 | Touchpoints post-consulta + educación + push programado |
| 4 | Integración asistente (YAML/intents) y móvil |
| 5 | Vertex `batchPredictionJobs` + GCS en producción |

## Fuera de alcance inicial

- Videollamada, STT paciente en motivos.
- Reemplazar `AppointmentReasonBatchService` (sigue igual; mismo patrón de cola).
- Migrar preprocess conversacional a batch.
