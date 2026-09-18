# Entrypoints clínicos del asistente

Orquestación delgada expuesta por la API (sin preprocess del chat).

| Clase | API |
|-------|-----|
| `Application/ClinicalEncounterEntry` | `POST /api/v1/clinical/encounter/analizar\|guardar` |

Chat pre-consulta (AppointmentReason): API `motivos-consulta/*` → `AppointmentReasonController` → `Encounter/Application/AppointmentReasonMessageService` (no Entry en Assistant).

La lógica de negocio vive en `Clinical/Encounter/Application/`, etc.
