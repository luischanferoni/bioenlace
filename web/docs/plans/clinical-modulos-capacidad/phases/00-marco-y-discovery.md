# Fase 00 — Marco + discovery multi-módulo

**Estado: hecha.**

## Objetivo

Fijar el plan y permitir que el runtime descubra intents en `Domain/<BC>/<Modulo>/Application/Flows/intents` **sin romper** los que aún viven en `Domain/<BC>/Application/Flows/intents`.

## Hecho

- [x] Crear `plans/clinical-modulos-capacidad/` + fila en `plans/README.md`
- [x] Extender `ProductMetadataPaths::colocatedIntentRoots()` (BC + hijos módulo)
- [x] Ajustar `IntentSchemaPaths::domainFromColocatedPath` (BC plano o `BC/Modulo/…`)
- [x] Test `testClinicalModuleIntentRootsYDomain` en `DddMigrationPhase0Test`
