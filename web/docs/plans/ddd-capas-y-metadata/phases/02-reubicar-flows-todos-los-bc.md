# Fase 02 — Reubicar flows YAML de todos los BC + platform intents

**Estado: hecha.**

## Movido

| Origen | Destino | Cantidad |
|--------|---------|----------|
| `metadata/bioenlace/clinical/intents/` | `Domain/Clinical/Application/Flows/intents/` | 15 |
| `metadata/bioenlace/person/intents/` | `Domain/Person/Application/Flows/intents/` | 4 |
| `metadata/bioenlace/organization/intents/` | `Domain/Organization/Application/Flows/intents/` | 13 |
| `metadata/bioenlace/platform/intents/` | `Platform/Assistant/Application/Flows/intents/` | 4 |
| (fase 01) scheduling | `Domain/Scheduling/Application/Flows/intents/` | 19 |

**Total:** 55 intents, todos colocalizados. Cero `metadata/bioenlace/*/intents/`.

## Criterio de done

- `IntentSchemaPaths::discoverYamlFiles()` solo ve raíces nuevas para intents.
- Samples clinical / person / organization / platform / scheduling → `isColocatedPath`.

## Siguiente

[Fase 03](./03-reubicar-yaml-platform-ui-permission-ai.md) — Ui, Permission, Ai.
