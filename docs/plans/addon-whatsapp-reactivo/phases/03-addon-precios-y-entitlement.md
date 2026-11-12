# Fase 03 — Add-on, precios y entitlement

## Objetivo

Cobrar el add-on como opcional y habilitar el canal solo para los efectores que lo contrataron.

## Estado de la entrega

Implementado:

- Addon `whatsapp` en `PricingPesByEncounterClassCatalog`: `optional` en `AMB`, `EMER` e `IMP`, `plan_field` `whatsapp_permitido`.
- Supuestos y COGS declarados: 1 mensaje service por atención + vinculación Didit amortizada (1 cada 100 atenciones) = USD 0,0293 por atención.
- Versión del catálogo a 15.
- Test del addon en `PricingPesByEncounterClassTest`.

Pendiente:

- Retirar `includes_patient_chat`: requiere decidir a qué clases se atribuye el COGS de chat del asistente. No se toca en esta entrega para no mover precios sin decisión de producto.
- Entitlement por efector y bloqueo del canal sin add-on.
- Medición por efector/mes.

## Tareas

- [x] Agregar el addon `whatsapp` en `PricingPesByEncounterClassCatalog`: `optional` en `AMB`, `EMER` e `IMP`, `plan_field` `whatsapp_permitido`.
- [x] Declarar supuestos en `cogs_assumptions`: mensajes service por atención, vinculación Didit amortizada.
- [x] Declarar `cogs_usd_per_encounter['whatsapp']` con el cálculo de la fase y una nota de que es presupuesto, no consumo medido.
- [x] Subir la versión del catálogo.
- [ ] Retirar `includes_patient_chat` del catálogo y de `PricingPesByEncounterClassMetadata`; actualizar `PricingPesByEncounterClassTest`. Requiere decidir la atribución del COGS de chat.
- [ ] Verificar que `addonPlanFields`, `SignUpInstitutionalEfector` y `LicenciaController::actionCotizar` tomen el nuevo addon sin cambios adicionales.
- [ ] Persistir el entitlement y exponerlo al canal.
- [ ] Resolver el efector del paciente y verificar el add-on antes de responder. Si no está habilitado, ofrecer la app.
- [ ] Medición por efector y por mes: mensajes service, vinculaciones, costo Meta, costo Didit.
- [ ] Tests: cotización con y sin add-on, persistencia del entitlement, canal bloqueado sin add-on.

## Criterio de salida

El add-on se cotiza, se persiste y se aplica como opcional; el precio refleja el presupuesto declarado; el canal no responde a pacientes de efectores sin add-on. `includes_patient_chat` se retira cuando se decida la atribución del COGS de chat.