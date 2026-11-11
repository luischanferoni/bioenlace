# Ingreso a la web: app, passkey y recuperación asistida

## Contexto

La web sólo permitía ingresar aprobando un desafío desde la app: el QR apuntaba a una ruta que no existía en el frontend y las apps no manejaban el enlace, así que el circuito estaba cortado. Además, usar la verificación de identidad como login tiene costo por uso y, en computadoras compartidas, las sesiones tienen que ser cortas: el gasto escalaría con la cantidad de ingresos y no habría forma de amortizarlo. "Recordar este dispositivo" tampoco es aceptable cuando varias personas usan el mismo equipo.

## Decisión

Tres caminos, ninguno con costo por uso:

1. La URL del QR abre la app; el teléfono muestra qué navegador pide el acceso y se desbloquea con la biometría local del equipo ya enrolado.
2. Passkey del teléfono: el navegador muestra un selector, el usuario confirma en su equipo y no queda credencial ni sesión en la computadora.
3. Recuperación asistida con aprobación humana, código de un solo uso y enrolamiento de una passkey nueva.

La verificación de identidad deja de usarse en el ingreso web: se paga una sola vez, en el alta. Las sesiones son cortas y no se implementa sesión recordada por navegador.

El QR de login apunta a `GET /ingresar/<challenge>`, una URL real. Esa página muestra el navegador y la hora del pedido antes de aprobar y ofrece los caminos disponibles.

## Alternativas consideradas

- **Verificación de identidad en cada ingreso** (0,10 USD por sesión exitosa): descartada porque el costo escala con los ingresos y en equipos compartidos no hay forma de reducirlo.
- **Sesión recordada por navegador**: descartada por ser un equipo compartido.
- **TOTP con app autenticadora**: funciona y no cuesta, pero suma soporte de enrolamiento y códigos de recuperación sin resolver nada que la passkey no resuelva mejor.
- **Magic link por e-mail**: contradice la decisión de que el e-mail no es un factor de autenticación.
- **Código por WhatsApp**: costo variable y riesgo de SIM swap.
- **Usuario y contraseña**: es el mecanismo que se retiró por soporte y phishing.
- **Universal Link / App Link**: es la evolución deseada para evitar el diálogo del navegador; hoy se usa esquema propio, que no requiere publicar archivos de asociación.

## Consecuencias

- El QR se arma con la URL de la aplicación, no con un dominio aparte; la página intermedia vive en `AuthController::actionIngresar` y `views/login/ingresar.php`.
- La app interpreta `bioenlace-personalsalud://qr-login/<challenge>`, pide confirmación y llama a `POST /api/v1/auth/qr-login-aprobar`. La aprobación queda detrás del bloqueo biométrico existente.
- La biometría local no aporta identidad: libera un token de un dispositivo ya enrolado y debe invalidarse al revocar la sesión o desvincular el dispositivo.
- El ingreso con passkey se ofrece en `/auth/login`; la gestión de credenciales sigue en `/auth/passkeys`.
- Costos: cero por ingreso; el gasto de identidad queda en el alta ([costos-didit.md](../negocio/costos/costos-didit.md)).
