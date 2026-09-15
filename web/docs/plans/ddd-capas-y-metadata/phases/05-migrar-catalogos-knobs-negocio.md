# Fase 05 — Migrar catálogos/knobs de negocio (YAML → Domain PHP)

**Estado: hecha.**

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

## Hecho (lote B — `Domain/*/metadata`)

| Origen | Destino PHP |
|--------|-------------|
| `Clinical/metadata/encounter_phase_*.yaml` | `Clinical/Domain/EncounterPhase*Catalog` |
| `Clinical/metadata/motivos_consulta_intake.yaml` | `Clinical/Domain/MotivosConsultaIntakeCatalog` |
| `Scheduling/metadata/consulta_async_*.yaml` | `Scheduling/Domain/ConsultaAsync*Catalog` |
| `Scheduling/metadata/reserva_*.yaml` | `Scheduling/Domain/Reserva*Catalog` |
| `Scheduling/metadata/agenda_atencion_remota.yaml` | `Scheduling/Domain/AgendaAtencionRemotaCatalog` |
| `Scheduling/metadata/staff_modalidad_insight.yaml` | `Scheduling/Domain/StaffModalidadInsightCatalog` |
| `Scheduling/metadata/servicio_teleconsulta_politica.yaml` | `Scheduling/Domain/ServicioTeleconsultaPoliticaCatalog` |
| `Scheduling/metadata/control_seguimiento_hub.yaml` | `Scheduling/Domain/ControlSeguimientoHubCatalog` |
| `Scheduling/metadata/consultas_seguimiento_intake.yaml` | `Scheduling/Domain/ConsultasSeguimientoIntakeCatalog` |
| `Scheduling/metadata/turno_slot_offer_ui.yaml` | `Scheduling/Domain/TurnoSlotOfferUiCatalog` |
| `Person/Representation/metadata/representation_permissions_v1.yaml` | `Person/Domain/RepresentationPermissionsV1Catalog` |

`*CatalogService` / hub services leen `*Catalog::config()`. Carpetas `Domain/*/metadata/` eliminadas.

## Siguiente

Fase 06 (Integrations → `Infrastructure/External`) / 07 (docs).
