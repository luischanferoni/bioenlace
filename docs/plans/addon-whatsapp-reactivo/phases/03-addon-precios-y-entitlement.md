# Fase 03 — Add-on, precios y entitlement

## Objetivo

Cobrar el add-on como opcional y habilitar el canal solo para los efectores que lo contrataron.

## Tareas

- [ ] Agregar el addon `whatsapp` en `PricingPesByEncounterClassCatalog`: `optional` en `AMB`, `EMER` e `IMP`, `plan_field` `whatsapp_permitido`.
- [ ] Declarar supuestos en `cogs_assumptions`: mensajes service por atención, escrituras por atención, proporción sin passkey/app, costo Didit.
- [ ] Declarar `cogs_usd_per_encounter['whatsapp']` con el cálculo de la fase y una nota de que es presupuesto, no consumo medido.
- [ ] Subir la versión del catálogo.
- [ ] Retirar `includes_patient_chat` del catálogo y de `PricingPesByEncounterClassMetadata`; actualizar `PricingPesByEncounterClassTest`.
- [ ] Revisar el efecto de retirar `includes_patient_chat` en `unitCogsPerEncounter`: el chat del asistente no debe quedar atribuido solo a ambulatorio.
- [ ] Verificar que `addonPlanFields`, `SignUpInstitutionalEfector` y `LicenciaController::actionCotizar` tomen el nuevo addon sin cambios adicionales.
- [ ] Persistir el entitlement y exponerlo al canal.
- [ ] Resolver el efector del paciente y verificar el add-on antes de responder. Si no está habilitado, ofrecer la app.
- [ ] Medición por efector y por mes: mensajes service, step-ups, costo Meta, costo Didit.
- [ ] Tests: cotización con y sin add-on, persistencia del entitlement, catálogo sin `includes_patient_chat`, canal bloqueado sin add-on.

## Criterio de salida

El add-on se cotiza, se persiste y se aplica como opcional; el precio refleja el presupuesto declarado; el canal no responde a pacientes de efectores sin add-on; el dato viejo `includes_patient_chat` ya no existe.
