# Fase 06 — Representation + CareCohort + Org horario

## Checklist

- [x] `DesignatePatientDelegation` ← `PatientDelegationService`
- [x] `EstablishVerifiedGuardianship` ← `VerifiedGuardianshipService`
- [x] `ActivateProfesionalHorario` ← `ProfesionalHorarioActivaService`
- [x] `ResolveOperativeProfessionalEligibility` ← `SesionOperativaProfesionalHabilitacionService`
- [x] `GenerateCarePack` ← `CarePackGenerationService`
- [x] `EnqueueCarePackJob` ← `CarePackJobEnqueueService`
- [x] `use` cruzados tras cambio de namespace (Preference/Mpi/Prompt/Repository)

## Notas

- Capture `Application/` ya solo tiene roles CA (verificado en disco).
- Clinical modules: carpetas no-CA bajo Application ya limpias en filesystem.
- Siguiente oleada opcional: más CareCohort/Encounter Services → UseCase; `CarePackConfig` → Domain/Catalog.
