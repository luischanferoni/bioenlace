# Plan — Auditoría de captura clínica

| Campo | Valor |
|-------|--------|
| Slug | `auditoria-captura-clinica` |
| Estado | En ejecución — Fase 1 (trail + admin) |
| Dueño | Equipo clínico / plataforma |
| Superficie | Admin Yii, solo superadmin |

## Índice

- [design.md](./design.md) — eventos, meta_json, pantallas admin, fases

## Código existente (punto de partida)

| Área | Ubicación |
|------|-----------|
| Pipeline sync | `EncounterCapturePipelineService` |
| Draft | tabla / AR `encounter_capture` |
| Review / source clinical\|ai | `EncounterCaptureReviewPresenter` |
| Trail de referencia | `ClinicalHistoryOutboundAudit` |
| Admin superadmin | `CostosController`, `QuejaPacienteController` |

## Al cerrar

Volcar narrativa a `producto/captura-clinica.md` (o ADR en `decisions/`) y borrar esta carpeta.
