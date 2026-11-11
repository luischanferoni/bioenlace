# Fase 06 — Ingreso web sin costo: app, passkey del teléfono y recuperación asistida

## Objetivo

Que entrar a la web no cueste por uso y funcione en computadoras compartidas: la identidad verificada con Didit se paga **una vez en la vida de la cuenta** (el alta), no en cada ingreso.

## Problema

- La verificación de identidad como login cuesta 0,10 USD por ingreso exitoso; con computadoras compartidas y sesiones necesariamente cortas, el costo escala con **ingresos** y no hay forma de amortizarlo.
- El QR de login actual genera una URL que **no existe** en el frontend y que las apps no saben manejar: el circuito está cortado.
- Un comprador que nunca instaló la app no tiene camino de entrada.

## Diseño: gratis primero, pago sólo como excepción

| Nivel | Camino | Prueba que aporta | Costo |
|-------|--------|-------------------|-------|
| 1 | La URL del QR abre la **app**: muestra qué navegador y dispositivo piden el acceso, el usuario desbloquea con **huella local** y la app aprueba | Dispositivo enrolado + biometría local | Cero |
| 2 | **Passkey en el teléfono**: el navegador muestra un QR, el usuario confirma en su teléfono y entra; nada queda en la computadora | Credencial del dispositivo personal | Cero |
| 3 | **Recuperación asistida** si no tiene app ni passkey (teléfono nuevo, cambio de equipo) | Revisión de un superadmin + código de un solo uso + enrolamiento de passkey nueva | Cero |

- **La app no es obligatoria**: con la passkey del teléfono se entra sin instalar nada. La app sigue siendo el camino más rápido y el recomendado para quien atiende, pero no es requisito de acceso.
- **Passkey del teléfono entra en v1** (decisión cerrada): no queda como mejora posterior.
- **Didit sale del login.** Ya está pago en el alta; repetirlo en cada ingreso no aporta seguridad nueva y sí costo. Queda sólo si producto decide mantenerlo como último recurso explícito, con tope por usuario.
- **Sesiones cortas.** Como el ingreso deja de costar, no hace falta "recordar el navegador": la sesión vive lo normal para un equipo compartido y se cierra al terminar. Esto reemplaza la palanca de sesión larga de la versión anterior de esta fase.
- **El camino 2 es el que resuelve la computadora compartida**: la passkey vive en el teléfono del usuario (transporte híbrido, el navegador muestra un QR), no en el equipo de la clínica.

## Qué existe y qué falta

Casi todo el circuito está construido: esta fase es más cableado que invención.

| Pieza | Estado | Qué falta |
|-------|--------|-----------|
| Desafío y aprobación del ingreso web (`QrLoginService`, `/auth/qr-login-start`, `/auth/qr-login-status`) | Existe y funciona en el servidor | Que la URL del QR exista y que la app la maneje |
| URL del QR | **Rota**: apunta a `/qr-login/<id>`, que no está en `urlManager`, y las apps no tienen handler ni escáner de login | Ruta web, página intermedia, Universal Link / App Link, handler en la app |
| Desbloqueo con huella local en la app | Existe (`BiometricSessionLockScope`, `AuthTokenStore`) | Nada |
| Passkey: servicio y tabla | Existen (`PasskeyAuthenticationService`, `webauthn_credential`) | — |
| Passkey: ingreso en el login | **Falta**: el login no ofrece el CTA | Botón, flujo JS y enrolamiento después del primer ingreso |
| Passkey: gestión del usuario | Existe (`/auth/passkeys`) | Enlazarla desde el login y el perfil |
| Recuperación asistida | Existe (`AccountRecoveryService`) | Nada |
| Didit en el login | Existe (`/auth/login-biometrico`) | Dejar de usarlo en el canal web |

## Passkeys en computadoras compartidas

Ya está implementado (`PasskeyAuthenticationService`, tabla `webauthn_credential`, `views/login/passkeys.php`), y el modelo soporta varias credenciales:

- **Varias passkeys por persona**: `webauthn_credential` es una fila por credencial, con `name`, `last_used_at` y `revoked_at`; una persona puede tener la del teléfono y la de la notebook, y revocar una sin tocar la otra.
- **Varias personas en la misma computadora**: el pedido de autenticación va **sin lista de credenciales** (`allowCredentials` vacío), así que el autenticador muestra **todas las passkeys de ese equipo para el sitio** y el usuario elige la suya. No hay que diseñar nada: es el comportamiento estándar de WebAuthn.
- **El registro evita duplicados**: al enrolar se envían las credenciales existentes de la persona como excluidas, para no registrar dos veces el mismo autenticador.
- **La trampa de la cuenta de sistema compartida**: si toda la clínica usa la misma cuenta de Windows, la passkey de plataforma queda en ese perfil y Windows Hello pide el PIN de esa cuenta — que todos conocen. Por eso, en equipos compartidos, el camino recomendado es la **passkey del teléfono** (nada queda en el equipo) y no la del equipo.
- **Enrolamiento**: después del primer ingreso (app o recuperación) se ofrece "Agregar este teléfono como passkey". Es la única vez que hace falta otro método.

## Anti-fijación y política

- **Camino 1** (app): la aprobación es explícita y debe mostrar navegador y hora del pedido.
- **Camino 2** (passkey): la credencial está atada al sitio y al origin, así que no hay desafío que aprobar en otro equipo; no aplica el ataque de fijación.
- **Camino 3** (recuperación): ya existe el flujo con aprobación de superadmin, código de un solo uso, alerta de fraude, revocación de credenciales y sesiones, y obligación de enrolar una passkey nueva.
- **Política en servidor**: los tres caminos aplican la misma autorización de quién puede operar en la web; no se habilita por tener identidad verificada.
- **Revocación**: cerrar sesión, revocar la sesión (`auth_session`), revocar una passkey o desvincular el dispositivo (`user_device`) invalidan también el desbloqueo biométrico del teléfono.

## Aviso de pago

- Al confirmarse el pago se avisa al e-mail confirmado del titular: es el único canal que no depende de que haya instalado la app ni de que tenga una passkey.
- El copy vive en un catálogo YAML del dominio de facturación con su loader, y el disparo en la política PHP; idempotente por pago y reintentable mientras la sesión no exista.
- Si la licencia se activa y el e-mail no está confirmado, el aviso queda pendiente y sale al confirmarse.
- El estado del alta en la web muestra: en proceso de pago, habilitado con instrucciones de acceso, cancelado, vencido.

## Tareas

- [ ] Registrar la URL de ingreso como Universal Link / App Link y documentar los archivos de asociación por entorno.
- [ ] Reemplazar el payload del QR por la URL https única y añadir la ruta web que la sirve (hoy `/qr-login/<id>` no existe).
- [ ] Página intermedia en el teléfono: contexto del pedido (navegador aproximado, hora), botón "Abrir la app" y, si no la tiene, acceso al camino de recuperación asistida.
- [ ] App: manejar el enlace, mostrar el pedido con navegador y hora, exigir desbloqueo biométrico local y aprobar; conservar el comportamiento cuando no hay biometría.
- [ ] Habilitar el inicio de sesión con passkey en `/auth/login` (hoy existe el servicio y la pantalla de gestión, pero el CTA no está en el login) y el enrolamiento después del primer ingreso.
- [ ] Dejar Didit fuera del login; si producto lo mantiene como último recurso, tope por usuario/IP y mensaje que ofrezca app, passkey o recuperación.
- [ ] Validar en un equipo compartido real: dos personas, dos passkeys del teléfono, el selector elige la correcta y no queda credencial ni sesión en la computadora.
- [ ] Aviso de pago confirmado según lo descrito arriba.
- [ ] Instrumentar ingresos por canal (app, passkey, recuperación) y publicarlo con los costos; meta: cero Didit por ingreso.
- [ ] Tests: aprobación desde la app sólo con sesión válida y dispositivo enrolado; passkey de otra persona u otro origin rechazada; passkey revocada no entra; recuperación asistida exige aprobación y código vigente; desafío de otra sesión rechazado; aviso de pago único por pago.

## Criterio de salida

Entrar a la web desde una computadora compartida cuesta 0 y no deja credencial ni sesión en el equipo. Una persona con la app entra con el QR y su huella; una sin app entra con la passkey de su teléfono; una sin ninguna de las dos pide recuperación asistida y enrola una passkey nueva. Ningún ingreso normal dispara una sesión de Didit. El titular que compró un espacio recibe el aviso por e-mail y puede entrar sin depender de haber instalado la app.
