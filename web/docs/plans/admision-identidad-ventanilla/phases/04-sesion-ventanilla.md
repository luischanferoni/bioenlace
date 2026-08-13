# Fase 4 — Sesión de ventanilla

Tras identificar (DNI/Didit): contexto temporal para turnos de autogestión, TTL, auditoría. No domicilio MPI ni fusión. No reutiliza tutela (`person_related` / `subjectPersonaPaciente`).

## Checklist

- [x] Tabla `ventanilla_sesion` + knob YAML `ttl_minutes` (default 15).
- [x] `PersonaIdentidadResolverService` compartido con ingreso de guardia (sin NN).
- [x] `VentanillaSesionService` iniciar / estado / cerrar; allowlist `scheduling.turno`.
- [x] Enganche en `PersonRepresentationSubjectService` (no en sesión operativa).
- [x] API `POST/GET …/ventanilla-sesion/{iniciar,estado,cerrar,buscar-persona}`.
- [x] RBAC: rutas de ventanilla heredan de `/personas/buscar-persona`; `crear/listar-como-paciente` al rol `Administrativo`.
- [x] API + dominio. UI web (banner / modal) retirada: no se usa en producto.
- [x] Docs producto/QA.

## Fuera

- Selfie 1:1 Didit si olvidó el DNI.
- Cancelar/reprogramar como-paciente (se puede ampliar el YAML `unhide_paciente_intent_ids`).
- App Personal de Salud.
