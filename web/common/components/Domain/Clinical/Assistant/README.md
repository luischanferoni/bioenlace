# Entrypoints clínicos del asistente

Orquestación delgada expuesta por la API (sin preprocess del chat).

| Clase | API |
|-------|-----|
| `ClinicalEncounterEntry` | `POST /api/v1/clinical/encounter/analizar\|guardar` |
| `AppointmentReasonEntry` | `POST /api/v1/motivos-consulta/enviar` |

La lógica de negocio vive en `Clinical/Workflow/`, `Clinical/Service/`, etc.
