# Fase 02 — App paciente

Estado: pendiente.

## Alcance

Reemplazar el chat libre de motivos por la pantalla del cuestionario en la app del paciente, sin
audio ni STT.

## Entregables

| # | Entregable |
|---|------------|
| 1 | Pantalla de cuestionario sobre `encounter-reasons/cuestionario` y `responder-cuestionario`: burbujas del bot, opciones seleccionables, «Otra» con campo corto y cierre libre con contador de 320 |
| 2 | Retiro del grabador, del dictado local y de `SttClientConfig` del flujo de motivos (`EncounterReasonsChatScreen`, `PendingMotivoMessageStore`) |
| 3 | Punto de entrada desde el turno (`mis_turnos`, inicio) y desde el push de recordatorio de motivos: abre la fase `motivos_consulta` |
| 4 | Estado de completado en el hub «Preparar tu consulta» y en el listado de turnos |
| 5 | Metadata de la fase: `surface` pasa de `chat_motivos` a `cuestionario_motivos`; la app resuelve la superficie por el valor declarado, no por nombre de pantalla |

## Reglas de UI

- Sin caja de texto libre en las preguntas: sólo opciones. «Otra» habilita un campo corto y acotado.
- El cierre libre muestra el contador y no deja enviar más de 320 caracteres.
- Si la ventana cerró, el cuestionario queda en modo lectura con el resumen del médico cuando exista.

## Verificación

- `flutter analyze` en `mobile/paciente` y `mobile/packages/shared`.
- Prueba manual: responder completo, cerrar y reabrir; ver el estado de completado en el hub.
- Prueba manual de push: el recordatorio abre el cuestionario del turno correcto.
