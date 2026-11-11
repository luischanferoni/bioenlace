# Fase 01 — Servicio del vínculo navegador ↔ teléfono

## Objetivo

Una sola pieza que cree, reclame, autorice, verifique y cierre un vínculo, con transiciones condicionadas por estado y sin lógica de HTTP.

## Tareas

- [x] Crear `common/components/Domain/Organization/Efector/Application/Service/SignupPairingService.php` con constantes de estado y de resultado.
  - `start(string $webSessionId, array $context)`: genera `id`, token y código; guarda hashes, `id_efector` y el hash de la sesión de la computadora; devuelve el token **una sola vez** para armar el QR y el código para el navegador.
  - `claim(string $token, string $deviceSessionId)`: valida token y TTL, exige estado `PENDING`, consume el token, fija el hash del teléfono y pasa a `CLAIMED`.
  - `authorizeDevice(string $pairingId, string $code, string $deviceSessionId)`: normaliza el código, compara hash, incrementa intentos, falla a `FAILED` al agotar y pasa a `AUTHORIZED`.
  - `markVerificationStarted(...)` y `markVerified(...)`: guardan `didit_session_id` y `verification_id`; el segundo sólo desde `VERIFICATION_STARTED`.
  - `markAccountCreated(...)`: guarda `id_persona` e `id_user`.
  - `status(string $webSessionId, string $pairingId)`: devuelve el estado y `expires_at`; marca `EXPIRED` cuando corresponde. El código no se devuelve: se entregó una sola vez en `start` y la interfaz lo revela cuando el estado es `CLAIMED`.
  - `cancel(string $webSessionId, string $pairingId)`: idempotente.
- [x] El servicio no usa `Yii::$app->request`, ni sesión, ni HTTP: recibe hashes y devuelve datos. La conexión entra por constructor (sin Service Locator en código nuevo de dominio).
- [x] Comparaciones de código y token con `hash_equals`; nunca `==` sobre secretos.
- [x] Log de transiciones con `Yii::info` en categoría `signup.pairing` (vínculo y estados), sin token ni código.
- [x] Tests en `common/tests/unit/organization/SignupPairingServiceTest.php`: 19 casos sobre SQLite en memoria (inicio, reclamo feliz, token vencido, token ya usado, código incorrecto, intentos agotados, binding de sesión distinto, transición no permitida, expiración por TTL, cancelación, recorrido completo y coherencia ENUM/modelo).

## Estado

Implementado. `19 tests, 87 assertions` en verde (`codecept run unit organization/SignupPairingServiceTest.php`).

Nota de ejecución: el CLI de `codecept` necesita PHP ≥ 8.2 con el lock del proyecto; en esta máquina se corrió con PHP 8.3. Los tests no tocan MySQL (usan SQLite en memoria), así que no dependen de la base local.

## Criterio de salida

La suite del servicio pasa; ningún camino permite avanzar de estado sin la condición previa; el token no se puede reutilizar y el código no aparece en claro en la base.
