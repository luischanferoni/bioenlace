# Overview — RBAC capabilities para UI nativa

## Objetivo

Unificar autorización de **UIs nativas** (web shell + app móvil) y **endpoints API que no son flujos del asistente**, sin abandonar el modelo intent-first del asistente conversacional.

Resultado: cada operación de producto assignable en admin tiene una **clave estable** (`intent_id` o `capability_id`) enlazada a rutas `/api/...`, alineada con `home_panel_manifest.yaml` y verificable en CI.

## Principio de capas (no negociable)

```
RBAC (¿tiene la operación?) → Dominio (¿sobre ESTE recurso?) → Servicio
```

- **RBAC** responde «¿este rol puede invocar esta operación?».
- **Dominio** (`EncounterAccessService`, `EfectorAccessService`, …) responde «¿sobre este encounter/efector/PES?».
- **Manifiesto UI** (`home_panel_manifest`, `client_open`) responde «¿mostramos el CTA en este cliente?» — **no sustituye RBAC**.

## Alcance

| Incluido | Detalle |
|----------|---------|
| Tipo **capability** en catálogo RBAC | Permisos assignables para UI nativa sin NL |
| Guardia EMER | ingreso, triage, tablero, retiro admin, operaciones staff excl. captura médica |
| Encounter | ver consulta staff, captura completa, documentar nota (enfermería) |
| Panel `/api/home/panel` | Dejar de tratarlo como auth-only para secciones clínicas |
| Admin + CLI | Catálogo, sync, integridad |
| Web + móvil | Consumir flags/capabilities del panel; headers de contexto donde aplique |
| Migración grants | Desacoplar guardia de `listado_pacientes`; mapear `front_*` relevantes |

## Fuera de alcance inicial

- Reescribir todos los `front_*` legacy del hospital (solo los que bloquean EMER/encounter en fases 2–3 y el resto en fase 6 incremental).
- RBAC de rutas **solo paciente** (`*-como-paciente`) salvo impacto directo en panel staff.
- Cambiar reglas clínicas de dominio (p. ej. quién es participante del encounter) — solo documentar interacción con RBAC nuevo.
- Permisos por **efector** o **servicio** en RBAC (siguen en sesión operativa + dominio).
- Asistente NL: los intents existentes **siguen**; capabilities complementan, no reemplazan.

## Actores y capabilities objetivo (MVP)

| Rol | Capabilities MVP |
|-----|------------------|
| **Administrativo** | `guardia.view_board`, `guardia.ingreso`, `guardia.triage`, `guardia.retiro_administrativo` |
| **AdminEfector** | Igual admisión + tablero |
| **enfermeria** | `guardia.view_board`, `guardia.triage`, `encounter.ver_como_staff`, `encounter.documentar_nota` |
| **Medico** | Bundle clínico: `listado_pacientes` / `encounter.capturar`, `guardia.atender`, `guardia.egreso_clinico`, … |
| **paciente** | Sin cambios (endpoints `*-como-paciente`) |

## Fases

Ver [phases/](./phases/). Orden: **0 → 1 → 2** (parche urgente guardia) → **3** en paralelo parcial → **4 → 5 → 6**.

## Criterios de cierre del programa

- [ ] Matriz rol → capability → rutas API documentada y aplicada en staging/prod vía migraciones.
- [ ] `catalog-integrity/check` sin rutas huérfanas ni capabilities sin rutas.
- [ ] QA: Administrativo ingresa/triage/retira sin 403; Médico atiende/captura; Enfermería documenta nota sin `analisis`.
- [ ] ADR `decisions/autorizacion-capabilities-ui-nativa.md` + [rbac-catalogo-permisos.md](../../arquitectura/rbac-catalogo-permisos.md) actualizado.
- [ ] Carpeta `plans/rbac-capabilities-ui-nativa/` eliminada ([plans/README.md](../README.md)).

## Riesgos

| Riesgo | Mitigación |
|--------|------------|
| Regresión 403 masiva post-migración | Migraciones idempotentes + dry-run grants + staging con checklist QA guardia |
| Duplicar reglas manifiesto vs RBAC | Fase 5: manifiesto referencia `required_capability`; una sola lista de roles por capability en YAML de permisos |
| Admin solo muestra intents | Fase 4: extender Permission Catalog con pestaña capabilities |
| FlowStep como parche permanente | Rutas nativas con grant directo; FlowStep solo para pasos de asistente |
