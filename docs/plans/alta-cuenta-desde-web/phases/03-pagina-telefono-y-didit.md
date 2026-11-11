# Fase 03 — Página del teléfono y verificación Didit

## Objetivo

Que el teléfono valide el código, ejecute Didit hosted y deje el vínculo en `verified` con un `verification_id` confirmado por el servidor.

## Tareas

- [x] Rutas: `GET alta/vinculo/<token>` (reclamo), `GET alta/telefono/<id>` (página del teléfono), `POST alta/telefono/<id>/codigo`, `POST alta/telefono/<id>/didit`, `GET alta/telefono/<id>/retorno`. La página del dispositivo usa un prefijo propio (`/alta/telefono`) porque el token y el `id` comparten forma y no se pueden distinguir en la misma URL.
- [x] `actionVinculo()`, al abrir la URL: reclama el vínculo (una sola vez) y redirige a `/alta/telefono/<id>` (loginLayout, mobile-first) con ingreso de código. Si el token ya se usó, página de error. A partir del reclamo, la página opera con el identificador del vínculo (el token no vuelve a viajar): las acciones siguientes exigen el binding de la sesión del teléfono, no el token.
- [x] `actionTelefonoCodigo()`: valida el código contra el servicio, devuelve errores por intentos restantes y pasa a `AUTHORIZED`.
- [x] Extraer la creación de la sesión hosted a `common/components/Domain/Person/Identity/Application/UseCase/CreateHostedIdentityVerification.php` y reusarla desde `RegistroController::actionCrearSesionDiditComoStaff` y `PersonasController::actionCrearSesionDiditStaff` (hoy duplicadas).
- [x] `actionTelefonoDidit()`: exige `AUTHORIZED` y binding de sesión del teléfono, crea la sesión con `vendor_data` = identificador del vínculo y `callback` a la ruta de retorno; guarda `didit_session_id`; redirige al teléfono a la URL de Didit (form POST, funciona sin JS).
- [x] `actionTelefonoRetorno()`: exige binding de sesión, ignora el `status` de la URL, usa **la sesión guardada** (no el `verificationSessionId` de la URL, que sólo se compara), consulta la decisión en Didit con `DiditIdentityService::fetchApprovedVerification`, reintenta ante `pending` y deja el vínculo en `VERIFIED` con el `verification_id` o en `FAILED` si Didit rechaza.
- [x] La página del teléfono muestra "volvé a la computadora" al terminar y ofrece reintentar el resultado sin rehacer el vínculo mientras el TTL lo permita (auto-reintento de 6 × 5 s y botón manual).
- [x] No se acepta `verification_id` enviado por el cliente en ninguna acción: el identificador en `VERIFIED` es siempre el que confirmó el servidor.
- [x] Registrar en el log de la categoría del alta el resultado de la verificación (aprobada, pendiente, rechazada) sin datos personales.

## Criterio de salida

Un teléfono real completa el código y Didit deja el vínculo en `VERIFIED`; con `pending` reintenta sin declarar rechazo; una sesión de teléfono distinta no puede retomar el vínculo; una verificación rechazada no crea cuenta y permite reintentar.

## Estado

Implementado:

- `AltaController`: `actionVinculo` (reclamo + redirección), `actionTelefono`, `actionTelefonoCodigo` (JSON), `actionTelefonoDidit` (form POST → redirect a Didit), `actionTelefonoRetorno`.
- `SignupPairingService`: `deviceStatus()` y `markFailed()` (con tests: 23 casos en verde).
- `CreateHostedIdentityVerification` + reuso en `RegistroController` y `PersonasController` (se eliminó la duplicación del cliente).
- Vistas `alta/vinculo.php` y `alta/vinculo-error.php`, `AltaVinculoMovilAsset`, `alta-vinculo-movil.js`.
- Rutas verificadas con `parseRequest` para las cinco URLs.

Precauciones aplicadas: el `callback` de Didit se compara con la sesión guardada (no se confía en el `verificationSessionId` de la URL), el `status` de la URL se ignora, y todas las acciones del teléfono exigen binding de sesión.

Pendiente de prueba real: el circuito completo contra Didit necesita credenciales y un dispositivo. No se pudo verificar con base local por la ausencia de MySQL en esta máquina.
