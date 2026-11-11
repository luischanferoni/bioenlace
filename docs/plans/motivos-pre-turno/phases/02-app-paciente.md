# Fase 02 — App paciente

Estado: **implementada** (2026-11-11). Falta prueba en dispositivo.

## Alcance

Reemplazar el chat libre de motivos por la pantalla del cuestionario en la app del paciente, sin
audio ni STT.

## Entregables

| # | Entregable | Estado |
|---|------------|--------|
| 1 | Pantalla de cuestionario sobre `encounter-reasons/cuestionario` y `responder-cuestionario`: burbujas del bot, opciones seleccionables, «Otra» con campo corto y cierre libre con contador de 320 | Hecho |
| 2 | Retiro del grabador, del dictado local y de `SttClientConfig` del flujo de motivos (`EncounterReasonsChatScreen`, `PendingMotivoMessageStore`) | Hecho |
| 3 | Punto de entrada desde el turno (`mis_turnos`, inicio) y desde el push de recordatorio de motivos: abre la fase `motivos_consulta` | Hecho |
| 4 | Estado de completado en el hub «Preparar tu consulta» y en el listado de turnos | Hecho |
| 5 | Metadata de la fase: `surface` pasa de `chat_motivos` a `cuestionario_motivos`; la app resuelve la superficie por el valor declarado, no por nombre de pantalla | Hecho |

## Reglas de UI

- Sin caja de texto libre en las preguntas: sólo opciones. «Otra» habilita un campo corto y acotado.
- El cierre libre muestra el contador y no deja enviar más de 320 caracteres.
- Si la ventana cerró, el cuestionario queda en modo lectura con el resumen del médico cuando exista.

## Verificación

- `flutter analyze` en `mobile/paciente` y `mobile/packages/shared`: sin issues nuevas (las que
  quedan son previas).
- `flutter test` en `mobile/packages/shared`: 38 tests OK. En `mobile/paciente`, los tests de
  navegación del journey y de push pasan; `test/widget_test.dart` falla por una firma previa de
  `MyApp` (`isLoggedIn`), ajena a este cambio.
- Pendiente: prueba manual en dispositivo (responder completo, reabrir, push del recordatorio).
