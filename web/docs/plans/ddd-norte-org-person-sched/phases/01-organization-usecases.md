# Fase 01 — Organization UseCases

## Objetivo

Extraer intenciones claras de Pes/Efector a `Application/UseCase/` con verb phrase.

## Checklist

- [x] `EnsurePesAssignment` ← `ProfesionalEfectorServicioAltaService`
- [x] `DeactivatePesAssignment` ← `ProfesionalEfectorServicioBajaService`
- [x] `SignUpInstitutionalEfector` ← `InstitutionalEfectorSignupService`
- [x] `RequestMinistrySignup` ← `MinistrySignupRequestService`
- [x] Actualizar callers (Assistant hydrator, SesionOperativa, AdminEfector, seeds, API)
- [x] `php -l` / smoke autoload registries

## Notas

Dejar en `Service/` los UI flows, depdrops, listados, schedule versioning (no son una intención de dominio única).
