# Fase 05 — Migrar catálogos/knobs de negocio (YAML → Domain PHP o BD)

## Objetivo

Sacar de metadata (y de `Domain/*/metadata/`) las políticas y contratos de negocio.

## Lotes

### Clinical

| Origen | Destino |
|--------|---------|
| `pedido-atencion.yaml` → capacity_rules / coding de negocio | `Clinical/Domain/PedidoAtencion/…` |
| `acto_nl_aliases` | BD + seed (preferido) o catalog temporal PHP |
| `encounter_phase_windows` / overrides / eligibility | Domain/Application; copy notificación → Presentation ui-text |
| `motivos_consulta_intake.yaml` | Reclasificar (Domain vs Application) |

### Scheduling

| Origen | Destino |
|--------|---------|
| `turno-behavior-profile.yaml` | `Scheduling/Domain/BehaviorProfileContract` |
| Todos `Domain/Scheduling/metadata/*.yaml` | Domain o Application según si es regla vs orquestación UI |

### Organization

| Origen | Destino |
|--------|---------|
| `agenda-by-encounter-class.yaml` | `Organization/Domain/AgendaKindCatalog` (o Scheduling si el dueño es agenda) |
| `pricing-pes-by-encounter-class.yaml` | Domain |
| `efector-atributos.yaml` | Domain o BD si es maestro de atributos |

### Person

| Origen | Destino |
|--------|---------|
| `ventanilla-sesion.yaml` | Person Domain/Application |
| `Representation/metadata/*` | Person Domain |

### Terminology / Integrations

| Origen | Destino |
|--------|---------|
| `snomed-terminology.yaml` | `Terminology/Domain/…` y/o ADR si parte va a BD |
| `servicio-synonyms.yaml` | Organization o Terminology Domain |
| `integrations/fhir-healthcare-service-codes.yaml` | BD o Infrastructure catalog |

## Criterio de done

- No quedan YAML de tipo knob/catalog de **negocio** bajo `metadata/bioenlace/{clinical,scheduling,organization,person,integrations}`.
- No queda `components/Domain/*/metadata/` (o solo vacío borrado).
- Loaders PHP actualizados; tests de catalog/policy.

## Regla

Un PR no deja doble fuente: o YAML o PHP/BD, con test verde y borrado del origen.