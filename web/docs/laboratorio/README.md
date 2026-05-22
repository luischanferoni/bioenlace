# Laboratorio (LIS externos FHIR)

Resultados de laboratorio por **pull** desde sistemas externos (FHIR R4). Sin módulo LIS propio en Bioenlace.

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Alcance y actores |
| [design.md](./design.md) | Conectores, persistencia, encounter |
| [flows/ingesta-pull.md](./flows/ingesta-pull.md) | Sync, API, consola |

## Anclas código

| Área | Ubicación |
|------|-----------|
| Conectores HTTP | `common/components/Integrations/Laboratory/` |
| Ingesta | `common/components/Clinical/Laboratory/` |
| API | `clinical/LaboratoryResultController` |
| Config | `params['laboratoryConnectors']` (credenciales en params-local) |

## Relacionado

- [decisions/fhir-clinical.md](../decisions/fhir-clinical.md) — API clínica general
- [captura-clinica](../captura-clinica/README.md) — consulta / encounter
