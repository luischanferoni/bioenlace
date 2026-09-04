# Diseño — Demo sandbox institucional

## Objetivo

CTA **Probar demo** en el sitio institucional → código de un solo uso → login web como **médico efímero** en un efector plantilla, con seed clínico de prueba, sin crear efector comercial ni exponer password.

## Flujo

1. Institucional: captcha + `POST /api/v1/licencia/demo-acceso` (`role`, `email` opcional, honeypot).
2. API **provisiona** staff efímero (`demo_m_*` médico, `demo_e_*` enfermería, `demo_a_*` administrativo) + seed en efector DEV, persiste el código y responde `enter_url` + `username` + `id_efector`.
3. Browser abre `/site/demo-entrar?code=…` → consume (solo login del user ya creado; rechaza `medico_med_general_*`). Médico entra en **AMB**; enfermería y administrativo en **EMER**.
4. Logout o TTL de sesión → purga de filas de esa visita (todos los PES del staff, `id_user` anulado, chat asistente / WhatsApp). Hard-delete también adopta demos incompletos (`demo_*` sin soft-purge).

## Modelo de datos

| Pieza | Detalle |
|-------|---------|
| BD | Misma instancia; sin schema paralelo |
| Efector | Plantilla DEV por `efector_codigo_sisa` (default `DEV99002PRIV`); no usar un `id_efector` de producción |
| Aislamiento | Un **PES + user** por visitante (scope médico); no tableros de todo el centro |
| Tracking | `demo_sandbox_access` (código) + `demo_sandbox_session` (PES, payload seed, expires/purged) |
| Entrada | `demo-entrar` limpia contexto previo, fija encounter según rol (AMB médico / EMER enfermería y administrativo), **persiste `context_token`**, redirect con `fecha` del seed |
| Móvil staff | `POST /api/v1/licencia/demo-acceso-mobile` → JWT + contexto (AMB o EMER según `role`); **Probar demo** en login Personal de Salud elige el perfil (`demo-perfiles`) |
| Encounter | En visita demo, cambio AMB/EMER/IMP ancla efector/servicio a la sesión demo (nunca 863) |

## Seed por visita (defaults)

| Dato | Detalle |
|------|---------|
| Pacientes | 6 (documentos `37xxxxxx`; los de async tienen user para chat) |
| Turnos | 2 (día hábil, PES propio); el 1.º con encounter AMB |
| Consulta AMB | 1 encounter `in-progress` vía `EncounterLifecycleService::ensureFromTurno` (captura clínica) |
| Virtual (VR) | 2 solicitudes `SOLICITUD_ASYNC` en `planned` (bandeja «Por tomar» + mensaje inicial) |
| Guardia | 1 episodio (`GuardiaIngresoService`, best-effort) |
| Internación | 1 ingreso + piso/sala/cama **efímeros** (sin assert HTTP; lifecycle care plan best-effort) |

Pacientes distintos para turnos / async / guardia / internación.

## Config (`demo_sandbox`)

- `demo_sandbox_habilitado`
- `efector_codigo_sisa` (default `DEV99002PRIV`), `id_efector` (0 = resolver por SISA), `servicio_nombre` (default MED GENERAL)
- `ttl_seconds` (código), `session_ttl_seconds` (visita), `max_per_ip_hour`
- `seed`: `pacientes`, `turnos`, `with_agenda`, `with_consulta_amb`, `with_consulta_async`, `consultas_async`, `with_guardia`, `with_internacion`
- `profiles.staff.mode`: `ephemeral` (default) o `shared_account` (legacy)

## Seguridad

Flag off por defecto; rate limit por IP; honeypot; **captcha** (challenge en cache, `GET /licencia/demo-captcha`, sin sesión PHP); código hasheado; un solo uso; sin link mágico permanente de login.

Knobs: `require_captcha` (default true), `captcha_ttl_seconds`, `captcha_length`.

## Operación

- Purga al logout (`AuthController::actionLogout`).
- Cron: `php yii demo-sandbox/purge-expired`.
- Limpieza hard de residuos anonimizados: `php yii demo-sandbox/hard-delete-purged`.
- **Purga (soft):** turnos del seed + turnos de pacientes demo → **todos** los encounters de esos pacientes (AMB seed, async VR, los creados al Atender en guardia) → internación (cierra + borra hcama/cama/sala/piso) → guardias de pacientes demo → agenda/PES → anonimiza pacientes/staff (`DemoPurged`) + desactiva users.
- **Hard-delete:** para personas `DemoPurged`, borra guardias/encounters (aunque no estén soft-deleted), hijos clínicos sin FK cascade (`atenciones_enfermeria`, `encounter_capture*`, `practicas_personas`, `electronic_prescription`, etc.), FHIR con CASCADE, turnos, internaciones, PES y personas/users huérfanos. Objetivo: **sin filas clínicas huérfanas** de la visita demo.

## Servicios

- Platform: `DemoSandboxAccessService`, `DemoSandboxSessionService`
- Domain seed: `DemoSandboxStaffProvisionService`, `DemoSandboxClinicalSeedService`, `DemoSandboxPurgeService`
