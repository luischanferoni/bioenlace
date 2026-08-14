# Fase 3 — RBAC encounter clínico

## Objetivo

Separar permisos **ver**, **capturar** y **documentar nota**; desacoplar captura de `analisis` / `listado_pacientes` donde corresponda.

## Tareas

### 3.1 Capabilities encounter

Migración + sync:

| capability_id | Origen legacy | Rutas |
|---------------|---------------|-------|
| `encounter.ver_como_staff` | `front_ver_historial_paciente` | `ver-consulta-como-staff`, ghost staff-summary, alternos en `ApiRoutePermissionResolver` |
| `encounter.capturar` | `analisis` | `captura-*`, `guardar`, `analizar` |
| `encounter.documentar_nota` | nuevo | Subconjunto TBD en `ClinicalEncounterEntry` / policy |

### 3.2 Grants por rol

- [ ] Medico → `encounter.capturar`, `encounter.ver_como_staff`
- [ ] enfermeria → `encounter.documentar_nota`, `encounter.ver_como_staff` (sin `encounter.capturar` completo)
- [ ] Administrativo → solo `encounter.ver_como_staff` (si producto confirma lectura HC acotada)

### 3.3 Dominio vs RBAC

- [ ] Documentar en controller docblocks: RBAC = operación; `EncounterAccessService` = recurso.
- [ ] Revisar si admisión necesita policy `encounter.ver_como_staff` más permisiva en guardia (solo episodio activo del efector) — **decisión dominio**, no RBAC.

### 3.4 `listado_pacientes`

- [ ] Inventariar rutas que siguen colgando de `listado_pacientes` (internación, chat, etc.).
- [ ] Plan incremental: mover rutas guardia a capabilities (Fase 2); dejar resto para Fase 6.
- [ ] Medico mantiene `listado_pacientes` hasta migración completa.

## QA

| Caso | Rol | Esperado |
|------|-----|----------|
| Ver consulta atendida | Administrativo | 200 si dominio OK; 403 dominio si no participante |
| Captura EMER | Medico | 200 |
| Captura EMER | Administrativo | 403 RBAC |
| Nota enfermería sin tomar caso | enfermeria | 200 (Fase 3 + plan captura-actor-enfermeria) |
| Captura completa | enfermeria | 403 RBAC |

## Criterios de aceptación

- [ ] Grants `analisis` replicados en `encounter.capturar` sin perder acceso Medico.
- [ ] enfermeria no depende de `analisis` para documentar nota acordada.
- [ ] Tests API o integración mínimos en endpoints captura.

## PR sugerido

`feat(rbac): encounter capabilities ver capturar documentar`

## Dependencias

- Coordinar con [captura-actor-enfermeria](../captura-actor-enfermeria/) para `encounter.documentar_nota`.
