# Fase 00 — Decisiones y modelo de producto

## Objetivo

Traducir las decisiones confirmadas a límites de dominio, estados y recorridos antes de sustituir los flujos existentes.

## Confirmado

- [x] Tres opciones: cuenta profesional personal sin pago; consultorio propio pago; clínica/centro pago.
- [x] Las tres altas empiezan en la app y verifican identidad con documento y selfie.
- [x] La cuenta profesional puede preceder a la asignación; queda sin acceso operativo.
- [x] AdminEfector agrega a los profesionales desde la búsqueda de personas, incluso a sí mismo.
- [x] REFEPS al asignar rol clínico y antes de checkout de consultorio; no al crear AdminEfector.
- [x] AdminEfector web-only por ahora; Administrativo y otros roles futuros web-only por defecto.
- [x] Passkeys web + aprobación QR para usuarios con app y web; recuperación asistida para web-only.
- [x] Guardar altas e intentos de pago; mostrar condiciones comerciales; sin compatibilidad legacy.

## Criterio de salida

Los límites quedan reflejados en autorización server-side, contratos API, modelo de estados y textos de producto; las cuestiones de pagos y cobertura REFEPS siguen explícitamente abiertas hasta su confirmación.
