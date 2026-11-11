# Fase 00 — Decisiones y modelo del vínculo

## Objetivo

Dejar cerrado el contrato del vínculo (estados, tiempos, nombres de acciones y rutas) y el esquema persistente, antes de escribir ninguna pantalla.

## Tareas

- [ ] Confirmar con producto las decisiones de [design.md](../design.md): orden centro → QR → código → Didit, no crear sesión web, máximo 5 intentos, TTL 180 s / 600 s.
- [ ] Confirmar las decisiones de borrador, e-mail y acceso: borrador sólo en el servidor, e-mail confirmado obligatorio antes del checkout del alta paga, aviso por e-mail al confirmarse el pago y camino de entrada del titular que no usa la app (passkey del teléfono en v1, app no obligatoria).
- [ ] Confirmar con Didit la URL de retorno del teléfono por entorno y su allowlist.
- [x] Registrar los knobs en `web/common/config/params.php` bajo `webSignupPairing`: TTL de reclamo, TTL de verificación, tope absoluto, intentos de código, intervalo de consulta.
- [ ] Fijar los nombres públicos: página `/alta`, página del teléfono `/alta/vinculo/<token>`, retorno `/alta/vinculo/<id>/retorno`.
- [ ] Fijar los nombres de las acciones del controlador del alta y su verbo (iniciar, estado, cancelar, código, didit, retorno, completar).
- [x] Definir la tabla `{{%signup_pairing}}`: `id`, `token_hash`, `code_hash`, `code_attempts`, `id_efector`, `web_session_hash`, `device_session_hash`, `state`, `didit_session_id`, `verification_id`, `id_persona`, `id_user`, `expires_at`, `claimed_at`, `authorized_at`, `verified_at`, `completed_at`, `created_at`.
- [x] Definir índices: `token_hash` (único), `state` + `expires_at`.
- [x] Estados en **ENUM MySQL** con las constantes de `common\models\Organization\SignupPairing` y `MigrationEnumColumn` (corrige la decisión inicial de usar texto: el dominio de estados es cerrado).
- [x] Escribir la migración con `safeUp` / `safeDown` simétricos, idempotente y con charset `utf8mb4`.

## Estado

Implementado el esquema y los knobs:

- `web/common/migrations/m261111_150000_signup_pairing.php`, idempotente por `getTableSchema`.
- `common/models/Organization/SignupPairing.php` con `stateValues()` y `terminalStateValues()` (fuente única del ENUM).
- `webSignupPairing` en `params.php` con `enabled`, TTLs, intentos y `statusPollSeconds`.
- Test `testEstadosDelModeloCoincidenConElEnumDeLaMigracion` verifica que el ENUM de la migración y las constantes no se desincronicen.

Pendiente: confirmación de producto y allowlist de Didit. La migración no se pudo correr localmente porque no hay MySQL en esta máquina (`php yii migrate --interactive=0` queda para el entorno con base).

## Criterio de salida

Existe la migración aplicada y revertible, los knobs están en `params`, los nombres de rutas y acciones coinciden con lo que usan las fases 02–04, y no queda ninguna decisión abierta que obligue a renombrar un estado después.
