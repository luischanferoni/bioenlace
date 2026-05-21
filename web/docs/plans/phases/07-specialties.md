# Fase 7 — Especialidades (odonto, oftalmo, psico, obstetricia, …)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 6](./06-orders-medication-practice.md)  
**Estado:** hecho — pilotos odonto + oftalmo (2026-05-21); resto documentado pendiente

## Objetivo

Migrar modelos especializados hoy colgados de `consultas_*` hacia **extensiones + mismos recursos FHIR**.

## Sub-entregas (pueden ser PRs separados)

### Odontología

| Hoy | Objetivo |
|-----|----------|
| `consultas_odontologia_practicas` | `procedure` + `procedure_odontology_ext` |
| `consultas_odontologia_diagnosticos` | `condition` + extensión pieza |
| `consultas_odontologia_estados` | metadata en procedure / care_plan category `odontology` |
| Prótesis fija/removible | `device_request` |

`components/Clinical/Specialty/Odontology/`

### Oftalmología

| Hoy | Objetivo |
|-----|----------|
| `consulta_practicas_oftalmologia` | `service_request` + `observation` |
| `consultas_receta_lentes` | `vision_prescription` |

### Psicología / salud mental

- `CarePlan.category = mental-health` o `program`
- `ServiceRequest` por sesión; `Goal` obligatorio en plan

### Obstetricia

- `observation` panel + extensiones encounter; no tabla `consultas_obstetricia` suelta

### Enfermería

- `atenciones_enfermeria` → `procedure` / `task` + `observation`

## API / UI

- [x] `GET /api/v1/clinical/encounter/<id>/odontology` y `…/ophthalmology`.
- [x] Validación por `encounter_definition` vía `EncounterDefinitionSpecialtyRegistry::isModelAllowed` en `guardar`.

## Definition of Done

- [x] Pilotos **odontología** y **oftalmología**: AR, servicios en `components/Clinical/Specialty/`, persistencia en `EncounterDocumentationService`, API lectura.
- [x] Psico, obstetricia, enfermería → pendientes en [MIGRATION_STATUS.md](../MIGRATION_STATUS.md).

## Siguiente fase

[Fase 8 — Internación](./08-inpatient-episode.md)
