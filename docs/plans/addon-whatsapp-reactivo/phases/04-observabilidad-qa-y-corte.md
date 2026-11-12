# Fase 04 — Observabilidad, QA y corte

## Objetivo

Medir el consumo real, validar los flujos críticos y migrar los vínculos existentes sin bloquear lecturas.

## Estado de la entrega

Implementado:

- Webhook de `statuses`: normaliza y persiste `wamid`, estado, `pricing_category`, `pricing_type`, `billable` y conversación en `asistente_whatsapp_mensaje_estado` (migración `m261112_140000`).
- Log estructurado `whatsapp.cost` por cada estado.
- Auditoría de vinculaciones en `asistente_whatsapp_verificacion`.
- Tests de normalización de estados.

Pendiente / opcional:

- **QA: a cargo del usuario.**
- Consulta o panel por efector/mes: **no es requisito de esta entrega**. La tabla de estados ya permite hacer la consulta cuando haga falta.
- Alertas de costo: **descartadas por ahora**; el log estructurado alcanza para monitorear.
- Documentación estable y cierre del plan.

## Tareas

- [x] Registrar cada mensaje saliente con categoría, `billable` y `pricing.type` del webhook de estado de Meta.
- [x] Registrar cada vinculación con método, resultado y `verification_id`.
- [ ] Panel o consulta por efector/mes: opcional, se hace sobre la tabla cuando producto lo pida.
- [ ] Alertas: descartadas por ahora.
- [ ] QA manual: **a cargo del usuario**.
- [x] Migración de vínculos `ACTIVO` creados con “SI”: se conservan y las escrituras se derivan a la app.
- [ ] Documentar lo estable en [producto/asistente-y-chat.md](../../../producto/asistente-y-chat.md) y [costos-whatsapp.md](../../../negocio/costos/costos-whatsapp.md).
- [ ] Borrar el plan al cerrar, según la regla de `plans/`.

## Criterio de salida

El consumo real se puede atribuir por efector; los flujos de vinculación y escritura pasan QA y pruebas de abuso; los vínculos viejos no bloquean lecturas; la documentación estable queda actualizada y el plan se elimina.
