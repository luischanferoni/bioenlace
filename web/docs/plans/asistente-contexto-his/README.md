# Plan — Contexto HIS para el asistente (áreas + aspectos)

| Campo | Valor |
|-------|-------|
| Slug | `asistente-contexto-his` |
| Estado | **En diseño** |
| Dominio | Platform/Assistant + Scheduling/Clinical/Person (según área) |
| Objetivo | Volcar contexto del HIS a la 2ª IA con vocabulario clínico-operativo, sin duplicar catálogos YAML ni nombres de tablas en prompts |

## Principio

- **Área HIS** (`AssistantContextHISArea`): top-level que el **preprocess** conoce (prompt + `context_areas` en JSON).
- **Aspecto** (`AssistantContextHISAreaAspect`): unidad de carga y volcado para la **2ª IA**; PHP mapea a entidades Yii y queries.
- El preprocess **no** elige aspectos ni filtros SQL; PHP resuelve aspectos, anclas y adjunta JSON al prompt de la 2ª IA.

## Índice

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Problema, alcance, fuera de alcance, fases |
| [design.md](./design.md) | Catálogo, registries, resolución área→aspecto, loaders, ensamblaje prompt |
| [Fase 0](./phases/00-marco-terminologia.md) | Glosario, áreas v1, relación FHIR |
| [Fase 1](./phases/01-preprocess-context-areas.md) | Catálogo en prompt preprocess + `context_areas` |
| [Fase 2](./phases/02-aspectos-y-loaders.md) | Aspectos, registry, AnchorResolver, AreaAspectResolver |
| [Fase 3](./phases/03-ensamblaje-segunda-ia.md) | `AssistantContextAssemblyService`, canales, debug |
| [Fase 4](./phases/04-qa-y-cierre.md) | QA, docs estables, eliminación del plan |

## Dependencias actuales

- `ChatPreprocessService` + `preprocess.yaml`
- `ChatPreprocessContext` (sesión)
- `ChatRouter` / canales `clinical`, `informational`
- `ConversationalChannel`, `InfoContentAssistantService`
- `TurnoPacienteListadoService`, `PersonRepresentationSubjectService`
- Data Access: entidades YAML (`Turno.yaml`, …), `ScopeCheckerRegistry`
- `PatientAiContextBuilder` (perfil conversacional — área `clinical_record` futura)

## Orden de ejecución

Fase 0 → 1 (preprocess) → 2 (aspectos/loaders) → 3 (2ª IA) → 4 (QA/cierre). Fase 2 puede empezar en paralelo con stubs de loaders una vez cerrado el enum de aspectos en Fase 0.

## Cierre del plan

1. Narrativa en `producto/asistente-y-chat.md` y ADR en `decisions/` si hace falta.
2. Arquitectura en `arquitectura/asistente-motores.md` (sección contexto HIS).
3. Casos QA en `qa/paciente/asistente-consultas.md`.
4. Eliminar `plans/asistente-contexto-his/`.
