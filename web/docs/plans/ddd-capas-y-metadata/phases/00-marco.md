# Fase 00 — Marco, ADR borrador e invariantes

**Estado: hecha** (dual-root, mapa agents, tests, ADR borrador).

## Objetivo

Fijar el contrato del plan antes de mover archivos: decisión escrita, tests esqueleto, mapa agents→BC, y facade de paths con lectura dual.

## Entregables

1. ADR borrador: `web/docs/decisions/ddd-bounded-contexts-capas-y-metadata.md` (estado: en implementación).
2. `AgentBoundedContextMap` + tabla en design.md.
3. Tests: `DddMigrationPhase0Test`, `BoundedContextLayerShapeTest`.
4. Dual-root: `ProductMetadataPaths::colocatedIntentRoots()`, `IntentSchemaPaths` une legacy + colocalizado (gana colocalizado si hay duplicado).
5. Esqueleto vacío: `Domain/Scheduling/Application/Flows/intents/`, `Platform/Assistant/Application/Flows/intents/`.

## Criterio de done

- CI verde con dual-root.
- Equipo alineado con design.md.

## No hacer (cumplido)

- Mover YAML masivo.
- Migrar policies a PHP aún.

## Siguiente

[Fase 01](./01-piloto-esqueleto-y-yaml-flows.md) — mover intents Scheduling + Platform Assistant YAML.
