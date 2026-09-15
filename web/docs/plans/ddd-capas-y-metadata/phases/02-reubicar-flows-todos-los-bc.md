# Fase 02 — Reubicar flows YAML de todos los BC + platform intents

## Objetivo

Completar el traslado de **todos** los intents/flows a `Application/Flows`.

## Lotes (PRs separados recomendados)

| Lote | Origen | Destino |
|------|--------|---------|
| Clinical | `metadata/bioenlace/clinical/intents/` | `Domain/Clinical/Application/Flows/intents/` |
| Person | `…/person/intents/` | `Domain/Person/Application/Flows/intents/` |
| Organization | `…/organization/intents/` | `Domain/Organization/Application/Flows/intents/` |
| Platform intents | `…/platform/intents/` | `Platform/Assistant/Application/Flows/intents/` |

Cada lote: mover + discovery + test paridad `intent_id` + borrar origen.

## Criterio de done

- No queda `metadata/bioenlace/*/intents/` (salvo stub README deprecado borrado al final de fase).
- `IntentSchemaPaths::discoverYamlFiles()` solo ve raíces nuevas.

## Riesgo

Diffs grandes en git: preferir `git mv` por dominio.