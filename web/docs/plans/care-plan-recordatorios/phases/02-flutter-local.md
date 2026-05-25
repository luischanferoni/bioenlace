# Fase 2 — Flutter notificaciones locales

## Objetivo

Programar alarmas en el dispositivo a partir del endpoint de Fase 1, con interruptor global en Configuración.

## Checklist dependencias

- [x] `flutter_local_notifications` + `timezone` en `packages/shared`
- [x] `bootstrapCarePlanReminders()` en `main.dart`
- [x] Permiso notificaciones vía `permission_handler`

## Checklist código (`packages/shared`)

- [x] `CarePlanReminderPreferences`
- [x] `CarePlanReminderApi`
- [x] `CarePlanLocalReminderService` (init, sync, schedule 14 días, cancelAll)
- [x] Canal Android `care_plan_reminders`

## Checklist UI

- [x] `CarePlanReminderGlobalSwitch` en Configuración
- [x] Hook en `home_screen` tras cargar care plans
- [ ] Tap notificación → navegar a `CarePlanDetailScreen` (payload log; navegación host pendiente)

## Criterios de aceptación

- Con global on y API con ítems, dispara notificación local a la hora configurada (probar con hora 1–2 min en futuro en seed).
- Con global off, no quedan alarmas pendientes de care plan.
- Reabrir app no duplica notificaciones (mismo id).

## Fuera de esta fase

- Switch por plan / por medicamento
- Horarios editados por paciente
- Asistente
