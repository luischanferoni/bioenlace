# Fase 04 — Migrar agents knobs YAML → Application Policy PHP

**Estado: hecha** (15/15).

## Hecho

- `*AgentPolicy` en `Domain/Scheduling|Clinical/Application/Agent/`
- `AgentPolicyRegistry` + `AutonomousAgentMetadata` solo lee PHP
- Eliminado `metadata/bioenlace/platform/agents/`
- `AgentRunAuditQueryService` lista ids desde el registry

## Siguiente

Fase 05 (catálogos) / 06 (capas PHP Integrations).
