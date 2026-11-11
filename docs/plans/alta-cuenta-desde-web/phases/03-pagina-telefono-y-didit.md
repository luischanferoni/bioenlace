# Fase 03 — Página del teléfono y verificación Didit

## Objetivo

Que el teléfono valide el código, ejecute Didit hosted y deje el vínculo en `verified` con un `verification_id` confirmado por el servidor.

## Tareas

- [ ] Rutas: `GET alta/vinculo/<token>` (reclamo), `POST alta/vinculo/<id>/codigo`, `POST alta/vinculo/<id>/didit`, `GET alta/vinculo/<id>/retorno`.
- [ ] `actionVinculo()`, al abrir la URL: reclama el vínculo (una sola vez) y renderiza `frontend/views/alta/vinculo.php` (loginLayout, mobile-first) con ingreso de código. Si el token ya se usó, mensaje "vínculo ya utilizado". A partir del reclamo, la página opera con el identificador del vínculo (el token no vuelve a viajar): las acciones siguientes exigen el binding de la sesión del teléfono, no el token.
- [ ] `actionVinculoCodigo()`: valida el código contra el servicio, devuelve errores por intentos restantes y pasa a `authorized`.
- [ ] Extraer la creación de la sesión hosted a `common/components/Domain/Person/Identity/Application/UseCase/CreateHostedIdentityVerification.php` y reusarla desde `RegistroController::actionCrearSesionDiditComoStaff` y `PersonasController::actionCrearSesionDiditStaff` (hoy duplicadas).
- [ ] `actionVinculoDidit()`: exige `authorized` y binding de sesión del teléfono, crea la sesión con `vendor_data` = identificador del vínculo y `callback` a la ruta de retorno; guarda `didit_session_id`; devuelve la URL para que el teléfono navegue.
- [ ] `actionVinculoRetorno()`: exige binding de sesión, ignora el `status` de la URL, consulta la decisión en Didit con `DiditIdentityService::fetchApprovedVerification`, reintenta ante `pending` y deja el vínculo en `verified` con el `verification_id` o en `failed` con el motivo.
- [ ] La página del teléfono muestra "volvé a la computadora" al terminar y ofrece reintentar la verificación sin rehacer el vínculo mientras el TTL lo permita.
- [ ] No se acepta `verification_id` enviado por el cliente en ninguna acción: el identificador en `verified` es siempre el que confirmó el servidor.
- [ ] Registrar en el log de la categoría del alta el resultado de la verificación (aprobada, pendiente, rechazada) sin datos personales.

## Criterio de salida

Un teléfono real completa el código y Didit deja el vínculo en `verified`; con `pending` reintenta sin declarar rechazo; una sesión de teléfono distinta no puede retomar el vínculo; una verificación rechazada no crea cuenta y permite reintentar.
