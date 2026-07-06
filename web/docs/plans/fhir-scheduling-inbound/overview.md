# Overview — Agendamiento FHIR entrante

## Objetivo

Bioenlace **consume** agendas y citas desde un servidor HAPI FHIR externo, materializa un espejo en `turnos` y actualiza estados (`Appointment.status`). No publica grilla propia por ahora.

## Ancla operativa

**PES** (`profesional_efector_servicio`) = profesional + efector + servicio.

## Identificación sin `urn:bioenlace:pes`

HAPI no incluirá identificador Bioenlace. Se coordina perfil nacional mínimo:

| Recurso FHIR | Identificador Bioenlace |
|--------------|-------------------------|
| `Location` / `Organization` | `Efector.codigo_sisa` |
| `Practitioner` | `Persona.cuil` (preferido) o DNI RENAPER + desambiguación |
| `HealthcareService` | Catálogo `integration_fhir_service_code` → `id_servicio` |
| `Schedule` | Catálogo `integration_schedule_link` → `id_profesional_efector_servicio` (verificado) |

## Fuera de alcance (fase inicial)

- Publicar `Slot` / `Schedule` propios.
- Materializar grilla local completa.
- Paciente obligatorio en turno entrante (`id_persona` opcional en espejo).

## Entregables por fase

1. **Datos de confianza**: CUIL al alta PES, catálogo códigos servicio, tabla schedule link.
2. **Resolver**: compuesto fail-closed + onboarding verificado con fingerprint.
3. **Sync**: conector HAPI, mapper `Appointment` → `turnos`, job reconciliación.
