# Fase 06 — QA y cierre

## Objetivo

Validar producto y archivar plan.

## Checklist conversacional (mínimo)

| Mensaje | Catalogación esperada | Entrega |
|---------|----------------------|---------|
| Me duele la panza | clara | flow `atencion.necesito-atencion` |
| Quiero un turno con el cardiólogo | clara | flow turnos crear o atención |
| ¿Cuáles son mis turnos? | clara | flow ver turnos o list |
| Llego 10 min tarde, ¿hay problema? | incompletas | texto + políticas centro |
| ¿Qué opinas de este médico vs el otro? | incompletas | texto con datos + límites |
| Listar profesionales del centro | clara | flow/list |
| Hola | dudosa o clara vacía | preguntas o saludo sin botones |
| ¿Sale el sol hoy? | fuera_de_his | texto límite |

## Tareas

- [ ] Tests unitarios fases 01–05 en verde.
- [ ] Actualizar `producto/asistente-y-chat.md` (sin enlazar a `plans/`).
- [ ] Actualizar `arquitectura/asistente-motores.md` si cambia diagrama.
- [ ] QA manual web + nota WhatsApp si envelope cambia.
- [ ] Borrar `plans/asistente-catalogacion-unificada/`.
- [ ] Quitar fila de README planes activos.

## Criterio de salida

Programa cerrado; documentación estable publicada.
