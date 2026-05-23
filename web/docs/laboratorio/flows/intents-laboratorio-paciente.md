# Intents del asistente — Laboratorio (paciente)

## Objetivo

Índice de `intent_id` para **ver** resultados de laboratorio ya en Bioenlace.

## Actores

- Paciente autenticado.
- Motor de sub-intents (`SubIntentEngine`).

## Anclas

| intent_id | RBAC | `open_ui` |
|-----------|------|-----------|
| `laboratorio.ver-resultados-como-paciente` | `/api/clinical/laboratory-result/mis-resultados-como-paciente` | lista → `ver-informe-como-paciente` + PDF |

---

## YAML

| Archivo | Flujo |
|---------|--------|
| `schemas/intents/laboratorio.ver-resultados-como-paciente.yaml` | Listado → detalle |

Registro en atajos: `CommonActionsService` categoría `laboratorio`.

Catálogo UI: `ClinicalUiActionCatalog` (`clinical.laboratory-result.*`).

## Desambiguación

| Usuario dice | Intent |
|--------------|--------|
| ver / mis resultados / mis análisis / laboratorio | `laboratorio.ver-resultados-como-paciente` |

La ingesta desde el LIS **no** se dispara desde el asistente; ver [ingesta-cron.md](./ingesta-cron.md).

## Relacionado

- [consultar-resultados-paciente.md](./consultar-resultados-paciente.md)
- [ingesta-cron.md](./ingesta-cron.md)
