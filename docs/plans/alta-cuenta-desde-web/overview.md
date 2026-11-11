# Overview — Alta de cuenta desde la web con el teléfono

## Objetivo

Permitir que una persona cree su cuenta profesional desde el navegador de la computadora sin cargar documentos en la computadora: la web muestra un QR, el teléfono abre una URL temporal, el usuario transcribe en el teléfono el código que aparece en la computadora y recién entonces se ejecuta Didit en el teléfono. Al aprobarse la verificación, la computadora crea la cuenta con la misma lógica que usa la app.

## Punto de partida

- Hoy el alta sólo existe en la app **Personal de Salud** (`StaffSignupService`: Didit SDK nativo → `POST /api/v1/licencia/crear-cuenta`), y la web pública (`institucional/alta.html`) sólo deriva a la descarga de la app.
- La web de la aplicación ya tiene el patrón inverso: el login muestra un QR que la app autenticada aprueba (`QrLoginService`, `/auth/qr-login-start` y `/auth/qr-login-status`).
- El alta en la web se retiró porque la verificación requería cámara y documento en el escritorio. Este plan levanta esa restricción usando el teléfono como sensor y la computadora como pantalla.

## Recorridos

| Recorrido | Quién | Qué pasa |
|-----------|-------|----------|
| Cuenta nueva | Persona sin cuenta ni Persona en Bioenlace | Entra a `/alta`, elige el centro, ve el QR, lo escanea, escribe el código en el teléfono, completa Didit en el teléfono, vuelve a mirar la computadora y ve la cuenta creada con la solicitud de incorporación registrada |
| Identidad ya existente | Persona con Persona y cuenta creadas | Igual, pero el alta reutiliza su Persona y su User; se registra la solicitud al centro sin duplicar cuenta |
| Espacio de atención | Persona que quiere crear un consultorio o una clínica | Igual hasta la identidad; después completa el alta del espacio en la web con borrador en el servidor, e-mail confirmado, checkout de Mercado Pago y aviso por e-mail cuando el pago se confirma. Al volver, entra con el mismo QR: la app si la tiene, o verificación con el teléfono |

## Alcance

- Página `/alta` en el frontend Yii (loginLayout) con selector de centro, QR y estados de progreso.
- Página temporal `/alta/vinculo/<token>` para el teléfono: ingreso del código y lanzamiento de Didit hosted.
- Servicio de vínculo navegador ↔ teléfono con token, código, TTL, intentos y estados persistidos.
- Verificación de la sesión Didit en el servidor antes de crear la cuenta.
- Alta de identidad + solicitud de incorporación al centro con los casos de uso existentes.
- Alta paga desde la web: borrador en el servidor, autoguardado por paso, reanudación y e-mail confirmado antes del checkout.
- Aviso por e-mail al confirmarse el pago e ingreso web sin costo por uso: app con huella, passkey del teléfono (apta para computadoras compartidas) y recuperación asistida. Didit se paga una sola vez, en el alta.
- Enlace desde `institucional/alta.html` y desde el login web hacia el nuevo alta.
- Pruebas unitarias del vínculo y checklist de QA por recorrido.

## Fuera de alcance

- Crear sesión web al finalizar el alta de cuenta personal (el acceso web sigue siendo por aprobación desde la app).
- Deep link a la app móvil para el mismo vínculo: la página del teléfono es web y no requiere cambios en las apps.
- Alta asistida de pacientes por personal (lector DNI / Didit hosted en el admin), que sigue como está.
- Recuperación de acceso y passkeys: **no** se rediseñan. Se reutilizan como están (`AccountRecoveryService`) y sólo se agrega el ingreso con passkey en el login; el mecanismo de recuperación no se toca.
- Cambiar el motor de precios, IVA o la lógica de suscripción de Mercado Pago: el alta web reutiliza lo que ya existe.

## Fuera de alcance por decisión de producto (revisable)

- Habilitar a un recién creado a operar en la web antes de tener una asignación activa. La cuenta nace con perfil Paciente; sin asignación no hay operaciones clínicas, igual que en la app.

## Métrica de éxito

- Una persona completa el alta desde la web en un solo intento, sin soporte, en menos de tres minutos desde que escanea el QR.
- Cero altas duplicadas (misma persona con dos Persona o dos User) desde la web en el período de piloto.
- Cero cuentas creadas a partir de un `verification_id` que el servidor no confirmó contra Didit.
- Cero altas pagas que terminen sin aviso al titular al confirmarse el pago.
