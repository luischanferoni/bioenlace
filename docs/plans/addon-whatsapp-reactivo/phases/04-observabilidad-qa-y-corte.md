# Fase 04 — Observabilidad, QA y corte

## Objetivo

Medir el consumo real, validar los flujos críticos y migrar los vínculos existentes sin bloquear lecturas.

## Tareas

- [ ] Registrar cada mensaje saliente con categoría, `billable` y `pricing.type` del webhook de estado de Meta.
- [ ] Registrar cada step-up con método, resultado, `verification_id`, costo estimado y consumo de crédito Didit.
- [ ] Panel o consulta por efector/mes: mensajes, step-ups, costo Meta, costo Didit, tasa de fallos.
- [ ] Alertas: fallos de verificación, bloqueos, vínculos expirados, picos de costo.
- [ ] QA manual con el checklist de [asistente-whatsapp.md](../../../qa/paciente/asistente-whatsapp.md), ampliado con step-up y escrituras.
- [ ] Pruebas de abuso: número reciclado, teléfono compartido, SIM swap simulado, token reutilizado, intento de ejecutar sin step-up.
- [ ] Migración de vínculos `ACTIVO` creados con “SI”: conservar lectura y exigir step-up en la primera escritura.
- [ ] Documentar lo estable en [producto/asistente-y-chat.md](../../../producto/asistente-y-chat.md) y [costos-whatsapp.md](../../../negocio/costos/costos-whatsapp.md).
- [ ] Borrar el plan al cerrar, según la regla de `plans/`.

## Criterio de salida

El consumo real se puede atribuir por efector; los flujos de vinculación y escritura pasan QA y pruebas de abuso; los vínculos viejos no bloquean lecturas; la documentación estable queda actualizada y el plan se elimina.
