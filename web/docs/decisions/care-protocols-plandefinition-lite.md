# Decisiones — protocolos de cuidado (PlanDefinition-lite)

Fecha de registro: 2026-07-20.

## Contexto

El paciente necesita controles periódicos y acciones por diagnóstico o perfil (edad/sexo) **sin** abrir un intent aparte ni confundir eso con CarePacks de asistencia IA.

## Decisión

| Tema | Decisión |
|------|----------|
| Entrada paciente | Solo **Solicitar Atención** → motivo **Control/Seguimiento** (hub). |
| Plantilla de protocolo | FHIR **PlanDefinition** en forma **lite** (YAML v1: `Clinical/metadata/care_protocols.yaml`). |
| Match | Por `condition_codes` (CIE, prefijo) **y/o** `age_years` / `sex`. |
| Instancia | En v1 el flow opera con `protocol_id` + draft; **no** materializa CarePlan preventivo automático. |
| Acciones | Declarativas (`outcome`, `draft`); servicios de dominio resuelven; orquestadores sin `if protocol_id`. |
| CarePack / CareCohort | **Fuera de scope** de este hub; packs IA pre/post consulta siguen en su dominio. |
| Vacunas | Orientación / turno / mensaje; **no** ImmunizationRecommendation HIS completo. |

## Alternativas descartadas

| Alternativa | Por qué no |
|-------------|------------|
| Intent propio «Consultas y seguimiento» | Fragmentaba UX; absorbido en Control/Seguimiento. |
| Reusar CarePack como protocolo de control | Dominio distinto (contenido IA por cohorte). |
| Hardcode de protocolos en orquestador | Viola metadata / capas del proyecto. |

## Consecuencias

- Catálogo y matcher: `CareProtocolCatalogService`, `CareProtocolMatcherService`.
- Hub: `ControlSeguimientoHubService` (anclas `cp:`, `diag:`, `prot:`).
- Producto: [solicitar-atencion.md](../producto/solicitar-atencion.md), [consultas-seguimiento.md](../producto/consultas-seguimiento.md).
