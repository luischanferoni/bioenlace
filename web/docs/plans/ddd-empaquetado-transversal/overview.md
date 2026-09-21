# Overview — Empaquetado DDD/CA transversal

## Problema

`Clinical/Capture` ya sigue el norte (módulo → capas → roles CA → dominio en el nombre de clase + sufijo transversal). El resto del árbol `Domain/` aún mezcla:

| Síntoma | Dónde se ve |
|---------|-------------|
| Carpetas de **capacidad** bajo `Application/` | `Organization/Application/{Efectores,Servicios,Billing,…}`, `Scheduling/Application/BehaviorProfile`, `Clinical/Encounter/Application/{Documentation,AiContext,EncounterJourney,…}` |
| **Layer-first** en la raíz del BC (sin módulos) | `Organization/`, `Scheduling/` (parcial), `Person/` (parcial), `Geo/`, `Content/`, `Terminology/` |
| Sufijos / clases opacos | `*Normalizer`, `*Orchestrator`, `*Processor`, `*Formatter`, `Dto/` como carpeta Application |
| README Domain desactualizado | Aún menciona `Application/*.php` sueltos |

Sin un plan único, cada PR “arregla un módulo” y reintroduce ejes duales.

## Objetivo

Aplicar el **mismo eje** que Capture a **todos** los BCs bajo `Domain/`:

1. Decidir **módulo de capacidad** (o confirmar BC compacto layer-first).
2. Bajo cada módulo: solo `Application/` / `Domain/` / `Infrastructure/`.
3. Bajo `Application/`: solo roles CA + naming `*[SufijoTransversal]`.
4. Actualizar guardrails (tests de forma, rules, ADRs).

## Fuera de alcance

- Refactors de producto / features nuevas.
- Reescribir Platform (salvo imports rotos por moves).
- Partir Clinical en varios BCs (ya descartado en ADR).
- Cambiar contratos HTTP públicos salvo renames internos transparentes.

## Criterio de éxito

- Ninguna carpeta de capacidad hermana de `UseCase/` / `Presentation/` / `Service/` bajo `Application/`.
- Sufijos de clase ∈ catálogo transversal (o verb phrase en UseCase).
- `BoundedContextLayerShapeTest` (o equivalente) verde para todos los BCs adoptados.
- Docs estables alineadas; esta carpeta `plans/` eliminable.
