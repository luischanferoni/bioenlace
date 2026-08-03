# Diseño — Demo sandbox institucional

## Objetivo

CTA **Probar demo** en el sitio institucional → código de un solo uso → login web como **médico efímero** en un efector plantilla, con seed clínico de prueba, sin crear efector comercial ni exponer password.

## Flujo

1. Institucional: `POST /api/v1/licencia/demo-acceso` (`role`, `email` opcional, honeypot).
2. API emite `enter_url` → `/site/demo-entrar?code=…` (TTL del código ~15 min).
3. App: consume código → **provision** (Persona+User+PES+agenda) + **seed** (pacientes/turnos/guardia/internación) → `login` → sesión operativa (un solo PES).
4. Logout o TTL de sesión → purga de filas de esa visita.

## Modelo de datos

| Pieza | Detalle |
|-------|---------|
| BD | Misma instancia; sin schema paralelo |
| Efector | Plantilla fija (`demo_sandbox.id_efector`, default 863) |
| Aislamiento | Un **PES + user** por visitante (scope médico); no tableros de todo el centro |
| Tracking | `demo_sandbox_access` (código) + `demo_sandbox_session` (PES, payload seed, expires/purged) |

## Seed por visita (defaults)

| Dato | Detalle |
|------|---------|
| Pacientes | 4 (documentos `37xxxxxx`) |
| Turnos | 2 (día hábil, PES propio) |
| Guardia | 1 episodio (`GuardiaIngresoService`, best-effort) |
| Internación | 1 ingreso + piso/sala/cama **efímeros** (sin assert HTTP; lifecycle care plan best-effort) |

Pacientes distintos para turnos / guardia / internación.

## Config (`demo_sandbox`)

- `demo_sandbox_habilitado`
- `id_efector`, `servicio_nombre` (default MED GENERAL)
- `ttl_seconds` (código), `session_ttl_seconds` (visita), `max_per_ip_hour`
- `seed`: `pacientes`, `turnos`, `with_agenda`, `with_guardia`, `with_internacion`
- `profiles.staff.mode`: `ephemeral` (default) o `shared_account` (legacy)

## Seguridad

Flag off por defecto; rate limit por IP; honeypot; código hasheado; un solo uso; sin link mágico permanente de login. Captcha: pendiente si hay abuso.

## Operación

- Purga al logout (`AuthController::actionLogout`).
- Cron: `php yii demo-sandbox/purge-expired`.
- Purga: turnos → internación (cierra + borra hcama/cama/sala/piso) → guardia → agenda/PES → pacientes/user.

## Servicios

- Platform: `DemoSandboxAccessService`, `DemoSandboxSessionService`
- Domain seed: `DemoSandboxStaffProvisionService`, `DemoSandboxClinicalSeedService`, `DemoSandboxPurgeService`
