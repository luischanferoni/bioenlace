# Fase 0 — Marco normativo y contrato

## Pendiente con el proveedor estatal / red

Antes del POST definitivo, cerrar con el organismo receptor:

| Tema | Pregunta abierta |
|------|------------------|
| Perfil Bundle | ¿`Composition` + `Encounter`? ¿IPS argentina? |
| Identificador paciente | CUIL, MPI nacional, `Patient.identifier` system URI |
| Identificador prestador | REFES / efector / CUIT institución |
| Autenticación | OAuth2 client credentials vs mTLS |
| Endpoint | URL base `$transaction` o `$submit` documental |
| Acuse | ¿202 + `Location`? ¿OperationOutcome? ¿Webhook? |
| Anulación | ¿DELETE lógico del documento? |

## Referencias internas Bioenlace

- Receta digital MSAL: perfil `recetaDigitalRegistroRecetaAR` (solo receta, no HC completa).
- Laboratorio Sianlabs: pull FHIR entrante (patrón conector distinto).

## Entregable Fase 0

Documento de contrato adjunto al ticket de integración + actualizar `HttpNationalClinicalHistoryConnector` con paths reales.
