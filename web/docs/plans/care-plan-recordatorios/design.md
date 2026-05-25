# Design — Recordatorios care plan

## Decisiones

| Tema | Decisión |
|------|----------|
| Alcance clínico | Todos los **care plans activos** del paciente (`status = active`; `on_hold` excluido en v1 salvo cambio de producto) |
| Fuente de ítems | `care_plan_activity` → `medication-request` (v1); `service-request` en Fase 4 |
| Horarios | Estructura en `medication_request.dosage_json` (`timing.repeat.timeOfDay[]`); texto libre no genera alarmas automáticas en v1 |
| Canal de aviso | **Notificaciones locales** Flutter (`flutter_local_notifications` + `timezone`) |
| Push FCM | No usar para tomas; mantener FCM para turnos (`PushNotificationTypes::TURNO_*`) |
| Preferencias on/off | **Local-first** (`SharedPreferences`); servidor opcional en Fase 4 |
| API | Una agenda derivada; el cliente programa alarmas; sin cola `*_notificacion_programada` de medicación |
| IDs de notificación | Estable por `activityId` + fecha/hora (evitar duplicados al re-sync) |
| Re-sync | Al login, refresh home, cambio de switches, y cada N días (ventana 7–14 días programada) |

## Contrato `dosage_json` (timing v1)

```json
{
  "timing": {
    "repeat": {
      "period": 1,
      "periodUnit": "d",
      "timeOfDay": ["08:00", "20:00"]
    }
  }
}
```

- `timeOfDay`: `HH:mm` 24 h, zona interpretada en **dispositivo** (no enviar UTC por toma en v1).
- `periodUnit`: `d` en v1; `wk` reservado.
- Sin `timing` válido → ítem con `requiresPatientSetup: true` (Fase 3).

## Payload API — `CarePlanReminderItem`

| Campo | Tipo | Notas |
|-------|------|-------|
| `carePlanId` | int | |
| `activityId` | int | `care_plan_activity.id` |
| `kind` | string | `medication-request` |
| `resourceId` | int | `medication_request.id` |
| `title` | string | `medication_display` |
| `subtitle` | string | `dosage_text` |
| `schedule` | object \| null | `timeOfDay[]`, `period`, `periodUnit`, `validFrom`, `validUntil` |
| `requiresPatientSetup` | bool | true si no hay horarios estructurados |
| `planStatus` | string | `active` |

Respuesta envuelta: `{ generatedAt, version: 1, items: [] }`.

## Rutas API (ghost RBAC)

| Método | Ruta permiso |
|--------|----------------|
| GET | `/api/clinical/care-plans/recordatorios-como-paciente` |
| GET | `/api/clinical/care-plans/recordatorios-por-plan` (query `care_plan_id`, opcional Fase 1) |

Controller: extender `CarePlanController` o `CarePlanReminderController` delgado en `clinical/`.

## Código (ubicación sugerida)

| Capa | Ruta |
|------|------|
| Builder | `common/components/Clinical/CarePlan/Reminder/CarePlanReminderScheduleBuilder.php` |
| DTO | `common/components/Clinical/CarePlan/Reminder/CarePlanReminderItemDto.php` |
| Timing parse | `common/components/Clinical/CarePlan/Reminder/MedicationDosageTimingParser.php` |
| Flutter sync | `mobile/packages/shared/lib/clinical/care_plan_local_reminder_service.dart` |
| Flutter prefs | `mobile/packages/shared/lib/clinical/care_plan_reminder_preferences.dart` |
| UI | `configuracion_screen.dart`, `care_plan_detail_screen.dart` |

## Flutter — dependencias

Añadir en `mobile/paciente/pubspec.yaml` (o `shared` si se comparte):

- `flutter_local_notifications`
- `timezone`

Permisos: reutilizar `permission_handler` (ya en paciente).

## Reglas al cambiar tratamiento

- Plan `completed` / `revoked` → próximo sync cancela alarmas de ese `carePlanId`.
- Cambio de `dosage_json` → nuevo `generatedAt` / hash → re-sync completo de ítems afectados.
- Master switch off → cancelar todas las alarmas de care plan sin borrar prefs por ítem.

## PRs y orden

Implementar **en orden de fases**; no mezclar Fase 4 en el mismo PR que Fase 1.
