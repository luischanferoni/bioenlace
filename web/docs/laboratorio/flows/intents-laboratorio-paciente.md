# Intents del asistente — Laboratorio (paciente)

## Objetivo

Índice de `intent_id` para ver y actualizar resultados de laboratorio, alineados con API v1 y RBAC.

## Actores

- Paciente autenticado.
- Motor de sub-intents (`SubIntentEngine`).

## Anclas

| intent_id | RBAC | `open_ui` / cierre |
|-----------|------|-------------------|
| `laboratorio.ver-resultados-como-paciente` | `/api/clinical/laboratory-results/mis-resultados-como-paciente` | lista → `ver-informe-como-paciente` + PDF |
| `laboratorio.sincronizar-resultados-como-paciente` | `/api/clinical/laboratory-results/sincronizar-como-paciente` | `clinical.laboratory-results.sincronizar-como-paciente` + `flow_submit` |

---

## YAML

| Archivo | Flujo |
|---------|--------|
| `schemas/intents/laboratorio.ver-resultados-como-paciente.yaml` | Ver listado |
| `schemas/intents/laboratorio.sincronizar-resultados-como-paciente.yaml` | Confirmar → sync |

Registro en atajos: `CommonActionsService` categoría `laboratorio`.

Catálogo UI: `ClinicalUiActionCatalog` (`clinical.laboratory-results.*`).

## Desambiguación

| Usuario dice | Intent |
|--------------|--------|
| ver / mis resultados / mis análisis | `laboratorio.ver-resultados-como-paciente` |
| actualizar / traer / pedir resultados | `laboratorio.sincronizar-resultados-como-paciente` |

## Relacionado

- [consultar-resultados-paciente.md](./consultar-resultados-paciente.md)
- [solicitar-resultados-paciente.md](./solicitar-resultados-paciente.md)
- [Turnos: intents paciente](../../Turnos/flows/intents-turnos.md)
