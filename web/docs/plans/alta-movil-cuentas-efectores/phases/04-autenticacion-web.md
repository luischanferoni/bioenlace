# Fase 04 — Passkeys y aprobación QR

## Objetivo

Retirar contraseñas como acceso de producto y habilitar métodos resistentes al phishing en web.

## Checklist

- [x] Passkeys/WebAuthn: enrolamiento, login, revocación, múltiples credenciales y desafíos origin-bound.
- [ ] Enrolar la primera passkey web al finalizar alta móvil de clínica/centro o consultorio.
- [x] Aprobación QR desde app para roles habilitados en ambos canales, separada del alta KYC.
- [x] Desafío QR de un solo uso, vida corta, vinculado a sesión/navegador; aprobación explícita en app.
- [x] Allowlist explícita de roles clínicos en login, alta de identidad y autenticación de solicitudes de Personal de Salud; AdminEfector, Administrativo y facturista quedan fuera del canal móvil.
- [ ] Allowlist equivalente de roles web y protección de rutas.
- [x] Emitir sesiones con expiración/revocación y proteger contra replay/CSRF.
- [x] Retirar login móvil de usuario/contraseña y activación por código.
- [ ] Retirar login web de contraseña/códigos al completar enrolamiento y conservar el acceso de revisión de tiendas.

## Criterio de salida

AdminEfector entra a web con passkey; usuarios web+app también pueden usar QR; ningún flujo requiere contraseña.

## Estado de implementación

- Passkeys: servicio `PasskeyAuthenticationService` (creación/autenticación con desafíos origin-bound y contador), endpoints web (`/auth/passkey-*`), pantalla de login con botón de passkey y pantalla de administración (`/auth/passkeys`) con enrolamiento y revocación. La contraseña queda como vía secundaria y se puede desactivar con `passwordLoginWebEnabled=false`.
- QR: `QrLoginService` con desafío de un solo uso de 120 s y aprobación explícita desde la app; endpoints web (`/auth/qr-login-start`, `/auth/qr-login-status`) y API móvil (`POST /api/v1/auth/qr-login-aprobar`).
- Pendiente: la pantalla de escaneo/aprobación QR en la app Personal de Salud (cliente móvil) y la primera passkey al finalizar altas pagas.
