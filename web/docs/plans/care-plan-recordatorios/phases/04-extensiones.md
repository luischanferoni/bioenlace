# Fase 4 — Extensiones

## Objetivo

Ampliar recordatorios más allá de medicación con horarios fijos, multi-dispositivo y descubrimiento en asistente.

## Checklist opcional (priorizar con producto)

### service-request

- [x] Extender builder para actividades `service-request` con `reminder_json` / timing
- [x] Textos de notificación distintos (`notificationLabel`: "Recordatorio de estudio")

### Preferencias en servidor

- [x] Tabla `persona_care_plan_reminder_pref`
- [x] `GET/PUT /api/v1/clinical/care-plans/preferencias-recordatorios-como-paciente`
- [x] Flutter: merge servidor → local al sincronizar (`CarePlanReminderPrefSync`)

### Asistente

- [x] Intent `tratamiento.recordatorios-como-paciente`
- [x] `ClinicalUiActionCatalog` + categoría Tratamiento en `CommonActionsService`
- [x] Pantalla nativa `care_plan_reminders_settings` → Configuración (paciente)

### Staff — carga de horarios

- [x] API `POST medication-requests`: `dosage_json` o `time_of_day` → validación en `MedicationRequestService`
- [x] API `POST service-requests`: `reminder_json` o `time_of_day` → validación en `ServiceRequestService`
- [ ] UI web picker de horarios (solo API v1; UI staff pendiente de producto)

### Adherencia (programa futuro)

- [ ] Acción en notificación “Marcar tomada” → evento opcional en API (nuevo plan)

## Criterios de cierre del programa

- [x] Doc operativo actualizado
- [ ] Eliminar `web/docs/plans/care-plan-recordatorios/` cuando producto cierre el programa
- [ ] Actualizar `plans/README.md` al archivar
