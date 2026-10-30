# Fase 0 — Marco de producto y modelo

## Objetivo

Cerrar el modelo identidad-primero y las decisiones de producto antes de tocar código de alta.

## Entregables

- Glosario: cuenta de identidad, efector, pool, `AdminEfector`, `AdminMinisterio`, afiliado, efector propio.
- Estados de cuenta y efector (ver `design.md`).
- Matriz de entrada por perfil (clínica/centro, consultorio, profesional de organización, ministerio).
- Decisiones de producto cerradas: sin contraseña, login biométrico local, re-verificación Didit en dispositivo nuevo, KYC compartido, consultorio crear-y-pagar, ministerio asistido, unión por invitación, geo AR/UY, REFEPS/SISA en consultorio.

## Decisiones a cerrar

- Contenido exacto de la pantalla "seleccionar efector" en estado vacío (mensaje de contactar al administrador + botón "crear mi consultorio").
- Si el botón del estado vacío aplica también a "crear mi clínica/centro" o solo consultorio.
- Alcance del push al agregar profesional (canal FCM existente).

## Criterios de aceptación

- Producto aprueba el modelo y los mensajes de la pantalla de efector.
- Ningún cambio de alta se habilita antes de cerrar esta fase.
- El plan queda registrado en `web/docs/plans/README.md`.
