# Fase 03 — Add-on, precios y entitlement

## Objetivo

Cobrar el add-on como opcional y habilitar el canal solo para los efectores que lo contrataron.

## Estado de la entrega

Implementado:

- Addon `whatsapp` en `PricingPesByEncounterClassCatalog`: `optional` en `AMB`, `EMER` e `IMP`, `plan_field` `whatsapp_permitido`.
- Supuestos y COGS declarados: 1 mensaje service por atención + vinculación Didit amortizada (1 cada 100 atenciones) = USD 0,0293 por atención.
- Versión del catálogo a 15.
- Entitlement persistido: columna `whatsapp_permitido` en `billing_account_encounter_entitlement` + migración `m261112_130000`.
- Alta, cambio de membresía, admin y resumen de contrato leen/escriben el nuevo campo.
- El canal verifica el add-on por efector del paciente antes de responder (`WhatsAppAddonAccessService`).
- Test del addon en `PricingPesByEncounterClassTest`.

Pendiente:

- Retirar `includes_patient_chat`: requiere decidir a qué clases se atribuye el COGS de chat del asistente. No se toca en esta entrega para no mover precios sin decisión de producto.
- Medición por efector/mes.
- Tests de integración con base de datos.

## Tareas

- [x] Agregar el addon `whatsapp` en `PricingPesByEncounterClassCatalog`: `optional` en `AMB`, `EMER` e `IMP`, `plan_field` `whatsapp_permitido`.
- [x] Declarar supuestos en `cogs_assumptions`: mensajes service por atención, vinculación Didit amortizada.
- [x] Declarar `cogs_usd_per_encounter['whatsapp']` con el cálculo de la fase y una nota de que es presupuesto, no consumo medido.
- [x] Subir la versión del catálogo.
- [ ] Retirar `includes_patient_chat` del catálogo y de `PricingPesByEncounterClassMetadata`; actualizar `PricingPesByEncounterClassTest`. Requiere decidir la atribución del COGS de chat.
- [x] Verificar que `addonPlanFields`, `SignUpInstitutionalEfector` y `LicenciaController::actionCotizar` tomen el nuevo addon: el plan normalizado y la cotización ya iteran `addonPlanFields()`.
- [x] Persistir el entitlement y exponerlo al canal: columna `whatsapp_permitido` en `billing_account_encounter_entitlement`, migración `m261112_130000_billing_entitlement_whatsapp`.
- [x] Resolver el efector del paciente y verificar el add-on antes de responder: `WhatsAppAddonAccessService` + `EfectorEncounterEntitlementService::isWhatsappAllowedForEfector`.
- [ ] Medición por efector y por mes: mensajes service, vinculaciones, costo Meta, costo Didit.
- [ ] Tests con base de datos: cotización con add-on, persistencia del entitlement, canal bloqueado sin add-on.

## Criterio de salida

El add-on se cotiza, se persiste y se aplica como opcional; el precio refleja el presupuesto declarado; el canal no responde a pacientes de efectores sin add-on. `includes_patient_chat` se retira cuando se decida la atribución del COGS de chat.