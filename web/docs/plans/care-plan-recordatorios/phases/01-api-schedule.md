# Fase 1 — API agenda de recordatorios

## Objetivo

El paciente autenticado puede obtener la lista de ítems recordables con horarios estructurados desde sus care plans activos.

## Checklist backend

- [x] Documentar convención `dosage_json.timing` en `design.md` (hecho) + snippet para staff en doc dominio al cerrar fase
- [x] `MedicationDosageTimingParser` — valida y normaliza `timeOfDay[]`
- [x] `CarePlanReminderScheduleBuilder` — recorre planes `active` del paciente → actividades `medication-request` → ítems
- [x] `CarePlanReminderItemDto` + `toArray()` para JSON
- [x] `CarePlanController::actionRecordatoriosComoPaciente()`
- [x] Ruta `main.php`: `GET api/.../clinical/care-plans/recordatorios-como-paciente`
- [x] Migración RBAC: `/api/clinical/care-plans/recordatorios-como-paciente`
- [ ] Entrada en `ClinicalUiActionCatalog` solo si hace falta asistente (Fase 4); no obligatorio en Fase 1

## Checklist datos / seed

- [x] `php yii clinical-seed/care-plan-reminder-demo --persona=<id>`
- [ ] Probar con `GET recordatorios-como-paciente` y JWT paciente

## Criterios de aceptación

- Paciente con plan activo y `timing` válido recibe ≥1 ítem con `schedule.timeOfDay` no vacío.
- Paciente sin planes activos: `items: []`.
- Staff de otro paciente: 403 en endpoint (mismo patrón que `actionActive`).
- `requiresPatientSetup: true` cuando solo hay `dosage_text` sin `timing`.

## Prueba manual

```http
GET /api/v1/clinical/care-plans/recordatorios-como-paciente
Authorization: Bearer <paciente>
```

## Fuera de esta fase

- Flutter
- Preferencias servidor
- `service-request`
