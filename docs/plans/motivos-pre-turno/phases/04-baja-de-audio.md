# Fase 04 — Baja de audio en motivos

Estado: **implementada** (2026-11-11). Requiere que la app con el cuestionario esté publicada.

## Alcance

Retirar la aceptación de audio en el flujo de motivos, una vez que la app ya no ofrece micrófono.

## Entregables

| # | Entregable | Estado |
|---|------------|--------|
| 1 | `encounter-reasons/subir` acepta sólo imágenes; audio responde `reason_media_type_invalid` | Hecho |
| 2 | `AppointmentReasonMessageService::sendMedia` sin `transcript` ni `stt_meta`; tipos de mensaje acotados | Hecho |
| 3 | `EncounterReasonMessage::TYPE_AUDIO` queda sólo como valor histórico; los mensajes viejos se resumen como audio sin transcripción | Hecho |
| 4 | Limpieza de lo que quede sin uso en `Platform/Ai/SpeechToText` para motivos (sin tocar captura clínica) | Hecho en el flujo; el motor STT sigue vivo para captura |

## Verificación

- `grep` sin `SpeechToTextManager` ni `SttConfigService` en `Domain/Clinical/Encounter`.
- Suite de motivos verde y `ApiContractTest` verde.
- Prueba manual: subir un audio responde error de contrato y no persiste.
