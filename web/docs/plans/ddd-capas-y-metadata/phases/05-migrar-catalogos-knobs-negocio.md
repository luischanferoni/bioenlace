# Fase 05 — Migrar catálogos/knobs de negocio (YAML → Domain PHP)

**Estado: parcial (lote A hecho).**

## Hecho (lote A — `metadata/bioenlace`)

| Origen | Destino PHP |
|--------|-------------|
| `clinical/pedido-atencion.yaml` | `Clinical/Domain/PedidoAtencionCatalog` |
| `scheduling/turno-behavior-profile.yaml` | `Scheduling/Domain/TurnoBehaviorProfileCatalog` |
| `organization/agenda-by-encounter-class.yaml` | `Organization/Domain/AgendaByEncounterClassCatalog` |
| `organization/pricing-pes-*.yaml` | `Organization/Domain/PricingPesByEncounterClassCatalog` |
| `organization/efector-atributos.yaml` | `Organization/Domain/EfectorAtributosCatalog` |
| `person/ventanilla-sesion.yaml` | `Person/Domain/VentanillaSesionCatalog` |
| `terminology/snomed-terminology.yaml` | `Terminology/Domain/SnomedTerminologyCatalog` |
| `terminology/servicio-synonyms.yaml` | `Terminology/Domain/ServicioSynonymsCatalog` |
| `integrations/fhir-healthcare-service-codes.yaml` | Doc en Integrations/Scheduling README (BD es fuente operativa) |

Loaders (`*Metadata`, `TurnoBehaviorProfileContract`, `HintServiceSynonyms`) apuntan a los catalogs.

`metadata/bioenlace/` queda solo con README.

## Pendiente (lote B)

YAML aún bajo `Domain/*/metadata/` (encounter phases, scheduling async/teleconsulta, representation permissions).
