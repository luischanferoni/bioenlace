# Fase 2 — RBAC guardia EMER (parche prioritario)

## Objetivo

Corregir desalineación **Administrativo / enfermería ↔ API guardia** detectada en dump y producción. Entregable usable sin esperar Fase 5.

## Problema actual (baseline)

- Rutas `emergency-guardia/*` en `auth_item_child` cuelgan de **`listado_pacientes`** (solo Medico).
- Intents `urgencias.*` / `GuardiaEpisode.*` enlazan a `/api/home/panel` pero **no** a ingreso/triage/egreso.
- `urgencias.egreso-estructurado-flow` solo asignado a Medico; retiro administrativo necesita grant propio.
- Propagación `m260618_100000_api_home_panel_rbac` incompleta en BD real.

## Tareas

### 2.1 Migración RBAC guardia

Nueva migración `m*_guardia_capabilities_rbac.php`:

- [ ] Registrar capabilities en `auth_item` (si Fase 1 no corrió, incluir inserts mínimos).
- [ ] `auth_item_child`: capability → rutas (ver design.md).
- [ ] `auth_assignment` / propagación rol → capability:
  - Administrativo, AdminEfector → `guardia.ingreso`, `guardia.triage`, `guardia.view_board`, `guardia.retiro_administrativo`
  - enfermeria → `guardia.view_board`, `guardia.triage`
  - Medico → todas las guardia clínicas + mantener `listado_pacientes` temporalmente
- [ ] Re-propagar ghost: padres con `/api/home/panel` → rutas hijas guardia (reutilizar patrón `inheritFrom` de `m260618`).
- [ ] Grant explícito retiro: roles admin → `egreso-formulario` vía `guardia.retiro_administrativo`.

### 2.2 Intents YAML (opcional en este PR)

- [ ] Ajustar `urgencias.triage-paciente-guardia.yaml`: `rbac_route` puede quedar `/api/home/panel` pero documentar capability `guardia.triage`.
- [ ] Crear intent técnico `guardia.ingreso-flow` (sin NL) **o** solo capability — decisión: **capability first**; intent NL en iteración posterior.

### 2.3 Verificación

- [ ] Script/consulta SQL post-migración: Administrativo tiene path a `ingresar`, `registrar-triage-formulario`, `egreso-formulario`.
- [ ] Medico conserva acceso a `iniciar-atencion`.
- [ ] `catalog-integrity/check` pasa.

## QA

| Caso | Rol | Endpoint | Esperado |
|------|-----|----------|----------|
| Ingreso NN | Administrativo | POST ingresar | 200/201 |
| Triage nativo móvil | Administrativo | POST registrar-triage-formulario | 200 |
| Retiro | Administrativo | GET/POST egreso-formulario | 200 |
| Atender | Administrativo | POST iniciar-atencion | 403 |
| Atender | Medico | POST iniciar-atencion | 200 |

## Criterios de aceptación

- [ ] Sin 403 en flujos admisión/triage/retiro admin en staging.
- [ ] No regresión tablero Medico.
- [ ] Migración idempotente + `safeDown` documentado (rollback grants).

## PR sugerido

`fix(rbac): guardia capabilities grants for admin and triage staff`

## Dependencias

- Fase 1 recomendada; mínimo viable: migración autocontenida con nombres de capability acordados en Fase 0.
