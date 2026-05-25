# Fase 3 — Preferencias por plan/ítem y setup de horarios

## Objetivo

Granularidad de activación y soporte cuando el médico no cargó `dosage_json.timing`.

## Checklist preferencias locales

- [x] `care_plan_reminders_plan_{id}` — bool, default = global
- [x] `care_plan_reminders_item_{activityId}` — bool, default = plan
- [x] `care_plan_reminders_custom_times_{activityId}` — JSON si `requiresPatientSetup`
- [x] `syncFromApi()` respeta jerarquía: global → plan → ítem

## Checklist UI

- [x] `CarePlanReminderPlanPanel` en detalle de tratamiento
- [x] Diálogo “Elegí horarios” para ítems `requiresPatientSetup`
- [ ] (Opcional) minutos de anticipación global en Configuración

## Checklist API (mínimo)

- [ ] Sin cambios obligatorios si horarios custom son 100 % locales
- [ ] (Opcional) incluir en payload `itemKey` estable para prefs

## Criterios de aceptación

- Desactivar un plan cancela solo alarmas de ese `carePlanId`.
- Ítem con setup manual programa alarmas con horas elegidas por paciente.
- Cambiar switch ítem no afecta otros planes.

## Fuera de esta fase

- Sync de preferencias al servidor
- service-request
