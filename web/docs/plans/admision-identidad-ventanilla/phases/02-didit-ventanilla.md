# Fase 2 — Didit en ventanilla

Cuando no hay PDF417 o el DNI es ilegible: mismo KYC que la app (documento + selfie + liveness) vía `RegistroStaffPacienteService` modo `didit`. Un solo POST `ingresar` con `verification_id` (no alta + ingreso en dos cerebros).

Opcional (no en este corte): re-identificar a alguien ya enrolado con workflow biométrico (selfie 1:1), no KYC completo.

## Checklist

- [x] `GuardiaIngresoService` acepta `verification_id` → `registrar(modo=didit)`.
- [x] Modal web: **Foto del DNI (Didit)** → `crear-sesion-didit-como-staff` + callback al tablero; al volver, confirmar ingreso.
- [x] App Personal de Salud: SDK nativo (`DiditSdk` + mismo workflow KYC que la app paciente).
- [x] RBAC: preview RENAPER y sesión Didit heredan de `emergency-guardia/ingresar`.
- [x] Docs producto/QA.
- [ ] Face match 1:1 con `didit_paciente_biometric_workflow_id` (opcional, fuera de este corte).
