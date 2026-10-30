# Fase 2 — Cuenta de identidad (alta = identidad)

## Objetivo

Que el alta de la app cree únicamente Persona + User verificados (sin efector, sin contraseña, sin pago).

## Entregables

- El alta de personal de salud reutiliza el paso de identidad (Didit) y crea User sin contraseña con rol base sin efector.
- Se elimina del contrato de alta el `password` y la creación de efector/plan/pago (quedan en fase 3).
- Login biométrico local + re-verificación Didit en dispositivo nuevo (reusar `actionLoginBiometrico`).

## Decisiones a cerrar

- Rol base del usuario sin efector (reusar roles `_sin_efector_` existentes o definir uno).
- Qué hace el usuario recién verificado sin efector (pantalla de selección de efector).

## Criterios de aceptación

- Un usuario recién creado no tiene efector ni entitlements y no se le exige pago.
- El login biométrico local funciona tras el alta; en dispositivo nuevo pide re-verificación Didit.
