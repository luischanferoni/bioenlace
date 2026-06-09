# Fase 1 — Dominio FHIR y catálogo

**Estado:** implementada

## Objetivo

Tablas, modelos AR, catálogo de parentesco y servicio de autorización base (sin UI).

## Checklist

- [x] Migración `m260616_100000_person_representation_fhir.php`
- [x] Seed catálogo: padre, madre, tutor_legal + conyuge, hijo, hermano, otro (B)
- [x] `PersonRepresentationAccessService::canAct()` + `evaluateAccess()`
- [x] `representation_permissions_v1.yaml`
- [x] Tests unitarios en `common/tests/unit/person/PersonRepresentationAccessServiceTest.php`
- [x] Sin cambios en `TurnosController` (Fase 4)

## Criterios de aceptación

- Dado vínculo A `active` verificado, `canAct(padre, hijo, scheduling.turno)` = true
- Dado `blocked`, `canAct` = false aunque status histórico sea active
- Dado B sin `Consent` active, `canAct` = false
