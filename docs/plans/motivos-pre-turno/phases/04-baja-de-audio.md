# Fase 04 — Baja de audio en motivos

Estado: pendiente. Depende de la fase 02 publicada.

## Alcance

Retirar la aceptación de audio en el flujo de motivos, una vez que la app ya no ofrece micrófono.

## Entregables

| # | Entregable |
|---|------------|
| 1 | `encounter-reasons/subir` acepta sólo imágenes; audio responde `reason_media_type_invalid` |
| 2 | `AppointmentReasonMessageService::sendMedia` sin `transcript` ni `stt_meta`; tipos de mensaje acotados |
| 3 | `EncounterReasonMessage::TYPE_AUDIO` queda sólo como valor histórico; los mensajes viejos se resumen como audio sin transcripción |
| 4 | Limpieza de lo que quede sin uso en `Platform/Ai/SpeechToText` para motivos (sin tocar captura clínica) |

## Verificación

- `grep` sin `SpeechToTextManager` ni `SttConfigService` en `Domain/Clinical/Encounter`.
- Suite de motivos verde y `ApiContractTest` verde.
- Prueba manual: subir un audio responde error de contrato y no persiste.
