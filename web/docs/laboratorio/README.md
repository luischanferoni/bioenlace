# Laboratorio (LIS externos FHIR)

Resultados de laboratorio por **pull** desde sistemas externos (FHIR R4). Sin módulo LIS propio en Bioenlace.

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Alcance y actores |
| [design.md](./design.md) | Conectores, persistencia, encounter |
| [flows/consultar-resultados-paciente.md](./flows/consultar-resultados-paciente.md) | Ver informes (paciente) |
| [flows/intents-laboratorio-paciente.md](./flows/intents-laboratorio-paciente.md) | Intents asistente (índice) |
| [flows/ingesta-cron.md](./flows/ingesta-cron.md) | Ingesta programada (cron / lote) |
| [flows/ingesta-pull.md](./flows/ingesta-pull.md) | Ingesta técnica, consola |
| [flows/solicitar-resultados-paciente.md](./flows/solicitar-resultados-paciente.md) | Ingesta operaciones (redirect a cron) |

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
