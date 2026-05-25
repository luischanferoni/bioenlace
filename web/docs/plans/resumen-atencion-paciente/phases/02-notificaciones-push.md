# Fase 2 — Notificaciones push

## Objetivo

Avisar al paciente cuando el resumen está publicado (minutos después de finalizar la atención).

## Checklist

- [x] `PushNotificationTypes::ENCOUNTER_SUMMARY_READY`
- [x] Integrado en `PatientEncounterSummaryPublishService::publishEncounter`
- [x] Fila en `persona_notificacion` vía `PushNotificationSender`
- [ ] Deep link documentado para Flutter (`encounter_id`)
- [ ] (Opcional) Segundo tipo `LAB_RESULT_READY` con `report_id` + `encounter_id` si no existe push paciente lab

## Copy sugerido (producto)

- Título: “Tu resumen de atención está listo”
- Cuerpo: “Consultá qué indicó el profesional y tus próximos pasos.”

## Criterio de cierre

Dispositivo con token FCM recibe push tras cierre demo; tap abre flujo definido en Fase 3.
