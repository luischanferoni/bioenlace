# Recordatorios de care plan (paciente)

> Programa implementado (fases 1–4). El plan de trabajo en `plans/care-plan-recordatorios/` fue archivado; esta es la documentación operativa.

## Resumen

Agenda derivada en API; el dispositivo programa **notificaciones locales** (no FCM por toma). El paciente activa/desactiva en Configuración y por plan/actividad. Las preferencias pueden sincronizarse en servidor.

## API

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/api/v1/clinical/care-plans/recordatorios-como-paciente` | Agenda (medicación + estudios). Query opcional: `care_plan_id` |
| GET | `/api/v1/clinical/care-plans/preferencias-recordatorios-como-paciente` | Preferencias del paciente |
| PUT | `/api/v1/clinical/care-plans/preferencias-recordatorios-como-paciente` | Guardar preferencias (parcial) |

Permisos RBAC (ruta HTTP plural en webvimark; ghost `care-plan` en ApiGhost).

## Horarios en medicación

Al indicar medicación, cargar `medication_request.dosage_json`:

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

Sin `timing`, el ítem sale con `requiresPatientSetup: true` (el paciente define horas en la app).

Al crear vía API (`POST .../medication-requests`), se acepta `dosage_json` o `time_of_day` / `timing`; el servicio valida el formato.

## Estudios / prácticas (`service_request`)

Campo `reminder_json` con la misma forma que `dosage_json.timing`. En notificaciones locales el texto por defecto es **Recordatorio de estudio**.

## Asistente

Intent `tratamiento.recordatorios-como-paciente` → abre Configuración en móvil (`screen_id`: `care_plan_reminders_settings`).

## Seed desarrollo

```bash
php yii clinical-seed/care-plan-reminder-demo --persona=<id_persona>
```

Requiere care plan demo (`m260521_100009`).

## Flutter

- Configuración → switch “Recordatorios de tratamiento”
- Detalle de tratamiento → panel por plan/actividad
- Tap en notificación → detalle del care plan
- Sincronización de preferencias: `CarePlanReminderPrefSync` (servidor gana al descargar)
- Paquete: `mobile/packages/shared/lib/clinical/care_plan_*`

## Código backend

`common/components/Clinical/CarePlan/Reminder/`
