# Plan — RBAC capabilities para UI nativa

| Campo | Valor |
|-------|--------|
| Slug | `rbac-capabilities-ui-nativa` |
| Estado | Planificado → **En curso** (Fases 1–5 implementadas; Fase 6 docs + migración grants) |
| Dueño | Plataforma / API / web staff / móvil Personal de Salud |
| Madurez HIS objetivo | Autorización coherente en guardia, encounter y panel EMER (sin cambiar reglas clínicas de dominio) |

## Problema

Hoy conviven **intents del asistente**, **rutas API** (`/api/...`), **capabilities en YAML** (`home-panel-manifest.yaml`) y **permisos legacy** (`front_*`, `listado_pacientes`) sin una fuente assignable única para UIs nativas (web `pacientes/listado`, Flutter `personalsalud`).

Síntomas observados en producción/staging:

- Botones visibles por manifiesto pero **403** en API (guardia ingreso, retiro administrativo, triage nativo sin `X-Flow-Intent-Id`).
- Rutas de guardia colgadas de `listado_pacientes` (solo **Medico**) mientras **Administrativo** tiene intents de tablero/triage.
- Encounter: **Administrativo** puede tener RBAC de lectura (`front_ver_historial_paciente`) pero no captura; enfermería tiene `documentar_roles` en manifiesto pero no `analisis` en RBAC.
- `/api/home/panel` en lista **auth-only** aunque devuelve datos clínicos del tablero.

## Índice

- [overview.md](./overview.md) — alcance, actores, fuera de alcance
- [design.md](./design.md) — modelo capability, capas RBAC ↔ dominio ↔ UI
- [phases/00-marco.md](./phases/00-marco.md) — principios y matriz rol → capability
- [phases/01-catalogo-capabilities.md](./phases/01-catalogo-capabilities.md) — Fase 1: metadata + registro en auth
- [phases/02-rbac-guardia-emer.md](./phases/02-rbac-guardia-emer.md) — Fase 2: guardia (ingreso, triage, retiro, tablero)
- [phases/03-rbac-encounter-clinico.md](./phases/03-rbac-encounter-clinico.md) — Fase 3: ver / capturar / documentar encounter
- [phases/04-admin-sync-integridad.md](./phases/04-admin-sync-integridad.md) — Fase 4: admin, CLI, CI
- [phases/05-manifiesto-clientes.md](./phases/05-manifiesto-clientes.md) — Fase 5: home_panel + web/móvil
- [phases/06-legacy-front-migracion.md](./phases/06-legacy-front-migracion.md) — Fase 6: `front_*` → capabilities

## Código existente (punto de partida)

| Área | Ubicación |
|------|-----------|
| RBAC API | `BioenlaceApiAccessControl`, `ApiRoutePermissionResolver`, `FlowStepAccessService` |
| Sync intents | `CatalogPermissionSyncService`, `php yii catalog-permission/sync` |
| Catálogo admin | `/admin/permission-catalog/index` |
| Capabilities UI EMER | `home-panel-manifest.yaml`, `GuardiaBoardCapabilityService` |
| Panel + tablero | `HomeController`, `EmergencyBoardSectionProvider` |
| Guardia API | `EmergencyGuardiaController`, migraciones `*emergency*rbac*` |
| Encounter API | `EncounterController`, `EncounterStaffSummaryController` |
| Doc estable RBAC | [rbac-catalogo-permisos.md](../../arquitectura/rbac-catalogo-permisos.md), [autorizacion-solo-por-intents.md](../../decisions/autorizacion-solo-por-intents.md), [autorizacion-capabilities-ui-nativa.md](../../decisions/autorizacion-capabilities-ui-nativa.md) |

## Relacionado

- Plan guardia: [urgencias-triage-tablero/](../urgencias-triage-tablero/) (producto EMER; este plan **autorización**)
- Plan admisión: [admision-identidad-ventanilla/](../admision-identidad-ventanilla/) (ingreso DNI/NN)
- Plan enfermería: [captura-actor-enfermeria/](../captura-actor-enfermeria/) (documentar sin tomar caso)
- Al cerrar: ADR en `decisions/` + actualizar [rbac-catalogo-permisos.md](../../arquitectura/rbac-catalogo-permisos.md); borrar carpeta `plans/rbac-capabilities-ui-nativa/`
