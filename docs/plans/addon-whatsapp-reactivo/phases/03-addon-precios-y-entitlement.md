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
- `WhatsAppAddonAccessService` queda listo para resolver el efector del paciente y verificar el add-on.

Pendiente:

- **Bloqueo de efectores sin add-on**: diferido por decisión de producto. El canal no se bloquea todavía; cuando se active, alcanza con llamar a `WhatsAppAddonAccessService::allowsPersona()` en `WhatsAppInboundService`.
- Medición por efector/mes.
- Tests de integración con base de datos.

Resuelto:

- **`includes_patient_chat` se mantiene solo en AMB (opción C).** El paciente no reserva internación ni interactúa con el asistente antes de guardia: `urgencias.triage-paciente-guardia` y `urgencias.ver-tablero-guardia` son flujos de staff, e `internacion.ingreso-flow` es staff y está oculto. Por eso el COGS de chat se atribuye solo a la atención ambulatoria; no se retira el campo.

## Tareas

- [x] Agregar el addon `whatsapp` en `PricingPesByEncounterClassCatalog`: `optional` en `AMB`, `EMER` e `IMP`, `plan_field` `whatsapp_permitido`.
- [x] Declarar supuestos en `cogs_assumptions`: mensajes service por atención, vinculación Didit amortizada.
- [x] Declarar `cogs_usd_per_encounter['whatsapp']` con el cálculo de la fase y una nota de que es presupuesto, no consumo medido.
- [x] Subir la versión del catálogo.
- [x] Mantener `includes_patient_chat` solo en `AMB` (opción C): el paciente no opera el asistente en `EMER`/`IMP`; triage, tablero e ingreso son staff.
- [x] Verificar que `addonPlanFields`, `SignUpInstitutionalEfector` y `LicenciaController::actionCotizar` tomen el nuevo addon: el plan normalizado y la cotización ya iteran `addonPlanFields()`.
- [x] Persistir el entitlement y exponerlo al canal: columna `whatsapp_permitido` en `billing_account_encounter_entitlement`, migración `m261112_130000_billing_entitlement_whatsapp`.
- [ ] **Diferido:** resolver el efector del paciente y bloquear el canal si no tiene el add-on. El servicio `WhatsAppAddonAccessService` ya está implementado; falta activar la llamada en `WhatsAppInboundService`.
- [ ] Medición por efector y por mes: mensajes service, vinculaciones, costo Meta, costo Didit.
- [ ] Tests con base de datos: cotización con add-on, persistencia del entitlement, canal bloqueado sin add-on.

## Criterio de salida

El add-on se cotiza, se persiste y se aplica como opcional; el precio refleja el presupuesto declarado; el canal no responde a pacientes de efectores sin add-on (cuando se active el bloqueo). `includes_patient_chat` queda solo en AMB: el paciente no interactúa con el asistente en EMER/IMP.