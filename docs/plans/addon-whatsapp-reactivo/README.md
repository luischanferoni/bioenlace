# Plan — Add-on WhatsApp reactivo

| Campo | Valor |
|-------|--------|
| Slug | `addon-whatsapp-reactivo` |
| Estado | Diseño; fases 00–04 pendientes |
| Dueño | Producto / Asistente / Identidad / Billing |
| Compatibilidad | Reemplaza el vínculo por "SI" y el alcance reactivo actual. Sin plantillas proactivas ni datos clínicos. |

## Contexto

WhatsApp ya existe como canal reactivo del asistente (`WhatsAppInboundService` → `ChatOrchestrator`), pero el vínculo teléfono↔paciente se resuelve con match de `PersonaTelefono` + respuesta "SI". Eso no prueba identidad y no alcanza para operaciones de escritura. El add-on agrega verificación fuerte antes de cualquier alta, baja o modificación, y se cobra como add-on opcional, igual que videollamada.

## Alcance del programa

- Canal **solo reactivo**: el paciente escribe primero; no se envían plantillas proactivas.
- Sin datos clínicos por WhatsApp: turnos, información y trámites similares.
- Verificación de identidad al vincular el número y **step-up antes de toda escritura**.
- Add-on opcional, con costo de Meta y de Didit incluido; passkey como alternativa más barata.
- Un número compartido de Bioenlace.

## Índice

- [overview.md](overview.md) — flujo objetivo, alcance y decisiones de producto
- [design.md](design.md) — arquitectura, identidad, step-up, gate de escrituras y costos
- [phases/00-decisiones-y-modelo.md](phases/00-decisiones-y-modelo.md) — decisiones y modelo
- [phases/01-vinculo-y-verificacion.md](phases/01-vinculo-y-verificacion.md) — reemplazo del vínculo "SI"
- [phases/02-stepup-y-gate-escrituras.md](phases/02-stepup-y-gate-escrituras.md) — assurance y operaciones mutantes
- [phases/03-addon-precios-y-entitlement.md](phases/03-addon-precios-y-entitlement.md) — catálogo, cobro y habilitación
- [phases/04-observabilidad-qa-y-corte.md](phases/04-observabilidad-qa-y-corte.md) — métricas, pruebas y migración

## Dependencias

- Passkeys web existentes: `PasskeyAuthenticationService`, `WebAuthController` y `webauthn_credential`.
- Didit: `DiditClient::createVerificationSession` y `getBiometricAuth`, `didit_paciente_biometric_workflow_id`.
- Asistente: `ChatOrchestrator::handle`, `SubIntentEngine` y el contrato de `flow_submit`.
- Precios: `PricingPesByEncounterClassCatalog`, `PricingPesByEncounterClassMetadata`, `SignUpInstitutionalEfector` y `LicenciaController::actionCotizar`.
- Costos de referencia: [costos-whatsapp.md](../../negocio/costos/costos-whatsapp.md).

## Criterio de cierre

Ningún alta, baja o modificación se ejecuta sin una verificación vigente que coincida con la Persona vinculada; el vínculo no se crea con un "SI"; no hay datos clínicos en mensajes; el add-on se cotiza y persiste como entitlement opcional; el consumo de Meta y Didit se mide por efector; y los vínculos existentes migran sin bloquear lecturas.
