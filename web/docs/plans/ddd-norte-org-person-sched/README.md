# DDD norte — Org / Person / Scheduling (post-empaquetado)

**Estado:** fases 01–05 hechas (pendiente commit deploy + opcional Representation)  
**Precondición:** empaquetado módulo-primero ya hecho ([`ddd-empaquetado-transversal`](../ddd-empaquetado-transversal/) archivado en decisions).  
**Norte:** [`ddd-norte-modelo-rico.md`](../../decisions/ddd-norte-modelo-rico.md), [`ddd-un-eje-por-nivel`](../../../.cursor/rules/ddd-un-eje-por-nivel.mdc).

## Objetivo

Pasar de “carpetas bien” a **modelo rico**: UseCases verb phrase, Domain con invariantes, Infra `External/<Sistema>/`. Sin inventar carpetas de capacidad bajo `Application/`.

## Veredicto de partida

| BC | Empaquetado | Hueco principal |
|----|-------------|-----------------|
| Organization | OK | 0 UseCases; Domain fino |
| Person | OK (`Identity`/`FrontDesk`) | 0 UseCases; docs `Identidad` stale |
| Scheduling/Agenda | Cerca | Port roto; External por rol ACL |
| Geo/Content/Terminology | Forma OK | Fuera de alcance salvo docs |

## Fases

| Fase | Contenido | Done when |
|------|-----------|-----------|
| **01** | Org UseCases (alta/baja PES, signup institucional/ministerio) | Clases verb phrase en `*/UseCase/`; callers actualizados |
| **02** | Person UseCases (registro, update identidad básica, staff alta paciente, front-desk session) | Idem |
| **03** | Agenda Infra: Port `use` + stubs fuera de raíz; External → `NisFhir/` | Sin PHP suelto en `Infrastructure/`; FQCN params OK |
| **04** | Docs Identity vs Identidad; Integrations/fhir-scheduling paths | Sin menciones stale a `Identidad/` ni `Scheduling/Infrastructure/` |
| **05** | Más UseCases (AdminEfector, billing switch, sesión operativa, identidad pendiente/resolver) + CuilPolicy wiring | Callers OK; `PersonCuilService` → `CuilPolicy` |

## No hacer en este plan

- Mover frontera Agenda-PES (ownership explícito → ADR aparte).
- Partir Representation (`PatientDelegation` / `VerifiedGuardianship`) en UseCases por método — ciclo aparte.
- Programs / Integrations código nuevo.

## Criterio de naming UseCase

`[<Contexto>]VerbObject` sin sufijo `Service` — p. ej. `EnsurePesAssignment`, `RegisterPerson`, `UpdateBasicIdentity`.
