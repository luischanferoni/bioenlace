# Fase 1 — Datos de confianza (en curso)

## Objetivos

1. **`personas.cuil`**: obligatorio al alta PES clínico; captura en flujo asistente si falta.
2. **`integration_fhir_service_code`**: catálogo código FHIR → `id_servicio`.
3. **`integration_schedule_link`**: esquema para vínculo Schedule HAPI → PES (onboarding fase 2).

## Hecho / en PR

- [x] Migración `personas.cuil` (único, 11 dígitos).
- [x] `PersonCuilService` + validación dígito verificador.
- [x] `ProfesionalEfectorServicioAltaService` exige CUIL (excepto `AdminEfector`).
- [x] Paso asistente `capture_cuil` en `profesional-efector-servicio.crear-flow`.
- [x] API `cargar-cuil-profesional` + UI JSON.
- [x] `FhirHealthcareServiceCodeCatalog` + modelo AR.
- [x] API staff `listar-codigos-servicio-fhir` / `guardar-codigo-servicio-fhir`.
- [x] `FhirSchedulePesResolver` (esqueleto fail-closed).
- [ ] UI onboarding Schedule → PES (fase 2).

## Tests

- `CuilValidatorTest`
- `FhirHealthcareServiceCodeCatalogTest`
- `FhirSchedulePesResolverTest` (fixtures)
