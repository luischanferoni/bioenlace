# Diseño — Demo sandbox institucional

## Objetivo

CTA **Probar demo** en el sitio institucional → código de un solo uso → login web en cuenta seed (datos ficticios), sin crear efector ni exponer password.

## Flujo

1. Institucional: `POST /api/v1/licencia/demo-acceso` (`role`, `email` opcional, honeypot).
2. API emite `enter_url` → `/site/demo-entrar?code=…` (TTL ~15 min).
3. App: consume código, `Yii::$app->user->login`, JWT vía `afterLogin`, redirect a sesión operativa.

## Config

- `demo_sandbox_habilitado` (params)
- `demo_sandbox.accounts.staff.username` (default `medico_med_general_863`)
- Seed: `php yii clinical-seed/efector-demo-contexto` / `medico-med-general`

## Tablas

`demo_sandbox_access`: hash del código, rol, user, IP, expires/used.

## Seguridad

Flag off por defecto; rate limit por IP; honeypot; código hasheado; un solo uso.
