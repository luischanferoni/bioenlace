# Captura clínica conversacional

Audio y texto libre → interpretación → persistencia clínica o formulario guiado; corrección de texto médico; resúmenes IA del historial con sensibilidad SNOMED.

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Qué es, actores, alcance |
| [design.md](./design.md) | Niveles 1/2, unmapped, sensibilidad |

## Flujos

| Flujo | Archivo |
|-------|---------|
| Niveles de carga y `ai_unmapped_data` | [flows/niveles-carga-audio-texto.md](./flows/niveles-carga-audio-texto.md) |
| Corrección/expansión texto médico (SymSpell + IA) | [flows/correccion-texto-medico.md](./flows/correccion-texto-medico.md) |
| Resumen timeline paciente con IA | [flows/timeline-paciente-ia.md](./flows/timeline-paciente-ia.md) |

## Relacionado

- [asistente](../asistente/README.md) — Nivel 2 (intents, chat)
- [plans](../plans/README.md) — encounter FHIR / documentación clínica
- Código: `common/components/Assistant/EntryPoints/ClinicalEncounter/`
