# Fase 1 — Identidad compartida (Didit→Persona) y workflow KYC único

## Objetivo

Extraer el paso "Didit → Persona" a un servicio reutilizable y unificar el workflow KYC entre paciente y personal de salud, sin romper el flujo paciente/médico existente.

## Entregables

- Servicio compartido de verificación de identidad que devuelva la Persona normalizada (hoy vive en `RegisterPerson`).
- `RegisterPerson` consume el servicio (sin cambio de comportamiento para paciente/médico).
- Parámetro de workflow KYC compartido `didit_kyc_workflow_id`, con fallback al nombre anterior `didit_paciente_kyc_workflow_id`.
- `config-movil` expone el nuevo key; la app lo resuelve con fallback.

## Decisiones a cerrar

- Nombre final del servicio y su ubicación (`Domain/Person/Identity/Application/Service/…`).
- Si el servicio también crea el `User` sin contraseña o solo resuelve la Persona.

## Criterios de aceptación

- Tests unit de `RegisterPerson`/`DiditClient` en verde.
- Registro de paciente y médico sin cambios observables.
- `config-movil` devuelve `didit_kyc_workflow_id` (nuevo) y la app lo consume.
