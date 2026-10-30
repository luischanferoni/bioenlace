# Fase 04 — Passkeys y aprobación QR

## Objetivo

Retirar contraseñas como acceso de producto y habilitar métodos resistentes al phishing en web.

## Checklist

- [ ] Passkeys/WebAuthn: enrolamiento, login, revocación, múltiples credenciales y desafíos origin-bound.
- [ ] Enrolar la primera passkey web al finalizar alta móvil de clínica/centro o consultorio.
- [ ] Aprobación QR desde app para roles habilitados en ambos canales, separada del alta KYC.
- [ ] Desafío QR de un solo uso, vida corta, vinculado a sesión/navegador; aprobación explícita en app.
- [ ] Allowlist de roles en middleware/API web y móvil; no confiar solo en UI.
- [ ] Emitir sesiones con expiración/revocación y proteger contra replay/CSRF.
- [ ] Retirar login web y API de contraseña/códigos al completar enrolamiento.

## Criterio de salida

AdminEfector entra a web con passkey; usuarios web+app también pueden usar QR; ningún flujo requiere contraseña.
