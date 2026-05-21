# Planes de migración clínica (FHIR + CarePlan)

Documentación del **programa de rediseño** del dominio clínico hacia un modelo alineado a HL7 FHIR, con **CarePlan** como eje del tratamiento activo del paciente.

| Documento | Uso |
|-----------|-----|
| [PROGRAM.md](./PROGRAM.md) | Visión, arquitectura objetivo, índice de fases, decisiones cerradas |
| [MIGRATION_STATUS.md](./MIGRATION_STATUS.md) | Tablero vivo: recurso FHIR ↔ tabla ↔ clase ↔ estado |
| [phases/](./phases/) | Un plan ejecutable por etapa (alcance, DoD, riesgos) |

**Canal de producto:** API v1 (`frontend/modules/api/v1`) + clientes (Flutter, SPA). El frontend Yii clásico (`frontend/controllers`, `frontend/views/consultas`) se trata en la fase final u opcional.

**Fuera de alcance del programa (por ahora):** interoperabilidad export (bundles receta digital), perfiles regulatorios externos.
