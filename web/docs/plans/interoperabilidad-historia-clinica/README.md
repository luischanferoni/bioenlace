# Plan — Interoperabilidad historia clínica (FHIR saliente)

| Campo | Valor |
|-------|--------|
| Slug | `interoperabilidad-historia-clinica` |
| Estado | Fase 1–2 completas; Fase 3 código listo (credenciales TBD); Fase 4 reconcile (polling) |
| Dueño | Equipo clínico / integraciones |
| Norma referencia | FHIR R4; perfiles nacionales TBD con MSAL / red jurisdiccional |

## Índice

| Doc | Contenido |
|-----|-----------|
| [overview.md](./overview.md) | Alcance, actores, qué entra y qué no |
| [design.md](./design.md) | Arquitectura, capas, reintentos, idempotencia |
| [phases/00-marco.md](./phases/00-marco.md) | Marco normativo y contrato con el Estado |
| [phases/01-estructura-y-cola.md](./phases/01-estructura-y-cola.md) | Cola, cron, hook al finalizar encounter |
| [phases/02-mapper-fhir-bundle.md](./phases/02-mapper-fhir-bundle.md) | Bundle documental (Composition + recursos) |
| [phases/03-conector-nacional.md](./phases/03-conector-nacional.md) | HTTP POST definitivo (pendiente credenciales) |
| [phases/04-recepcion-y-reconciliacion.md](./phases/04-recepcion-y-reconciliacion.md) | Acuses, pull, conciliación |

## Código

| Área | Ubicación |
|------|-----------|
| Conectores + mapper | `common/components/Domain/Integrations/ClinicalHistory/` |
| Dominio (cola, retry, reconcile) | `common/components/Domain/Clinical/HistoryExchange/` |
| Modelos | `ClinicalHistoryOutboundJob`, `ClinicalHistoryOutboundAudit` |
| API staff | `frontend/modules/api/v1/controllers/clinical/HistoryExchangeController.php` |
| Consola | `console/controllers/ClinicalHistoryExchangeController.php` |
| Tests | `common/tests/unit/clinical/ClinicalHistory*`, `FhirClinicalHistoryBundleMapperTest` |
| Params | `common/config/params.php` → `clinicalHistoryExchange` |
| Migraciones | `m260618_100000_clinical_history_outbound.php`, `m260618_100001_api_clinical_history_exchange_rbac.php` |

## Relacionado

- Modelo FHIR interno: [decisions/fhir-clinical.md](../../decisions/fhir-clinical.md)
- Receta RDI (patrón conector): [plans/receta-electronica/](../receta-electronica/README.md)
- Madurez HIS: [his-completo/10-atencion-ambulatoria.md](../../his-completo/10-atencion-ambulatoria.md) (ítem interoperabilidad saliente)
