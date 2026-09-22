# DDD norte — Org / Person / Scheduling (post-empaquetado)

**Estado:** cerrado (fases 01–09). Commit del usuario; redeploy cuando corresponda.  
**Precondición:** empaquetado módulo-primero ya hecho ([`ddd-empaquetado-transversal`](../ddd-empaquetado-transversal/) archivado en decisions).  
**Norte:** [`ddd-norte-modelo-rico.md`](../../decisions/ddd-norte-modelo-rico.md), [`ddd-un-eje-por-nivel`](../../../.cursor/rules/ddd-un-eje-por-nivel.mdc).

## Objetivo

Pasar de “carpetas bien” a **modelo rico**: UseCases verb phrase, Domain con invariantes, Infra `External/<Sistema>/`. Sin inventar carpetas de capacidad bajo `Application/`.

## Fases

| Fase | Contenido | Estado |
|------|-----------|--------|
| **01** | Org UseCases (PES, signup) | ✅ |
| **02** | Person UseCases (registro, identidad, front-desk) | ✅ |
| **03** | Agenda Infra Port + `External/NisFhir/` | ✅ |
| **04** | Docs Identity / Integrations / FHIR paths | ✅ |
| **05** | Más Org/Person + CuilPolicy | ✅ |
| **06** | Representation + horario + CareCohort Generate/Enqueue | ✅ |
| **07** | Encounter/CarePlan/Emergency/Inpatient lifecycle | ✅ |
| **08** | Journey/docs/Rx/Lab/Legal/History/CareRequest | ✅ |
| **09** | Residuos (followup, reconcile, sync, coding, RDI) | ✅ |

## Fuera de alcance (ok en Service)

Catalogs, query/listados, UI flows, seeds, depdrops, resolvers de fase/ventana, `CarePackConfig` (params Yii).

## Criterio de naming UseCase

`[<Contexto>]VerbObject` sin sufijo `Service`.
