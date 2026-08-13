# Fase 3 — Identidad pendiente (NN)

Episodio de guardia **sin** Persona definitiva cuando no hay DNI ni Didit (inconsciente, trauma). Flag de identidad pendiente; al aparecer el documento se vincula. No fusionar MPI desde admisión.

## Checklist

- [x] Columna `guardia.identidad_pendiente` + RBAC `vincular-identidad`.
- [x] Placeholder por episodio (`apellido=NN`, sin documento, `acredita_identidad=0`). No DNI inventado.
- [x] Ingreso con `identidad_pendiente: true`; varios NN en el mismo efector no chocan.
- [x] `POST …/{id}/vincular-identidad` (DNI / Didit / paciente conocido) retargetea el episodio, no fusiona padrones.
- [x] Tablero: badge + CTA Identificar (web y app).
- [x] Docs producto/QA.
