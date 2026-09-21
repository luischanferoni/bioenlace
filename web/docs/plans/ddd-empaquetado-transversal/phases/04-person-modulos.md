# Fase 04 — Person: Identidad + módulos existentes

**Estado:** hecho (módulo `Identidad/` en lugar de Registry; Representation/Ventanilla alineados)  

## Objetivo

Dejar de tener un `Application/` catch-all en la raíz de Person; consolidar capacidades.

## Reshape

```text
Person/
  Identidad/           # ex Application raíz (Flows, Seed, services) + MPI infra
  Representation/      # Application roles CA + Domain/Catalog
  Ventanilla/          # Application/Service + Didit infra
  Assistant/
  DataAccess/
```

## Checklist

- [x] Decidir nombre del módulo raíz → **Identidad** (producto admisión)
- [x] Mover `Person/Application/*` → `Identidad/`
- [x] Aplanar Application en Representation y Ventanilla
- [x] Sufijos (`CuilValidator`→`CuilPolicy`, `*Notifier`→`*Service`)
- [x] Shape tests + README; enlace packaging vs plan `admision-identidad-ventanilla`
