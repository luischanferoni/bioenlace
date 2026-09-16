# Overview — Clinical módulos de capacidad

## Problema

Tras colocar `Application/` / `Domain/` / `Infrastructure/` a nivel BC, los **flows** quedaron en `Clinical/Application/Flows/intents/{crud}/…` ordenados por verbo del asistente. No se puede encontrar el flow de laboratorio mirando `Laboratory/`.

Además conviven:

- carpetas de capacidad (`Emergency/`, `Inpatient/`, `Laboratory/`),
- cajones técnicos (`Enum/`, `Service/`, `Dto/`),
- capas DDD a nivel BC (`Application/`, `Domain/`).

Tres taxonomías → localidad rota.

## Objetivo

1. **Módulo primero** (capacidad con dueño).
2. Capas **dentro** del módulo (`Application` / `Domain` / `Infrastructure`).
3. **Sin `Shared/`**: lo transversal tiene dueño (`Encounter/`, `CarePlan/`, …); dependencias hacia el núcleo.
4. Discovery de intents que encuentre `Domain/<BC>/<Modulo>/Application/Flows/intents`.

## No-goals

- No partir Clinical en varios BCs.
- No reescribir MVC Yii (controllers/views/models).
- No migrar todos los `Service/` en la fase 0–1 (solo piloto + discovery).
- No inventar `Shared/` / `Kernel/` / `Common/`.

## Criterio de done del plan

- Todo flow Clinical vive bajo un módulo de capacidad.
- `Enum/` y `Service/` catch-all del BC vacíos o eliminados.
- Agents Clinical bajo el módulo dueño.
- Docs + ADR alineados; discovery y tests verdes.
