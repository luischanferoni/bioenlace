# Fase 2 — Asistencia pre-consulta

**Estado:** implementado (API + presenter; integración asistente/móvil en Fase 4)

## API

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/v1/care-packs/assistance` | `ui_definition` con bloque `fields` o estado pending/submitted |
| POST | `/api/v1/care-packs/assistance` | Guarda respuestas → `ui_submit_result` |

Query/body: `encounter_id` o `turno_id` (alias `consulta_id`, `id_turno`).

Requiere `care_cohort.enabled = true` y JWT paciente (mismo permiso heredado de motivos).

## Respuestas

- **Pack listo:** `kind: ui_definition`, preguntas en `blocks[].fields`.
- **Pack en cola:** mensaje «preparando preguntas» (`ui_meta.care_pack.status = pending`).
- **Ya enviado:** mensaje de confirmación (`status = submitted`).
- **POST OK:** `kind: ui_submit_result`, `success: true`.

## Delta

Si las respuestas disparan `CareAssistanceDeltaEvaluator` (palabras urgentes, escala alta, texto largo), se encola job sync con `delta_context` en el perfil para preguntas adaptadas (staff / fases siguientes).

## Código

| Pieza | Ubicación |
|-------|-----------|
| Controller | `frontend/modules/api/v1/controllers/CarePacksController.php` |
| Servicio | `CareCohort/Service/CarePackAssistanceService.php` |
| Presenter | `CareCohort/Presentation/CarePackAssistancePresenter.php` |
| Respuestas BD | `care_assistance_response` |
| RBAC | `m260614_100000_care_assistance_response_rbac.php` |

## Pendiente Fase 4

- Intent asistente + deep link en app paciente
- Mostrar pack delta al staff en historia clínica
