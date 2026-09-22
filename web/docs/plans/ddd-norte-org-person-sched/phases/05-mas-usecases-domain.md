# Fase 05 — Más UseCases + Domain thin

## UseCases adicionales

| Antes | Después |
|-------|---------|
| `AdminEfectorAsignacionService` | `EnsureAdminEfectorAssignment` ✅ |
| `BillingMembershipSwitchService` | `SwitchBillingMembership` ✅ |
| `SesionOperativaService` | `EstablishOperativeSession` ✅ |
| `PersonaIdentidadPendienteService` | `CreatePendingIdentity` ✅ |
| `PersonaIdentidadResolverService` | `ResolvePersonIdentity` ✅ |

## Domain thin

- [x] `PersonCuilService` delega validación a `Domain/Policy/CuilPolicy` (+ `use` explícito)
- [ ] Representation (`PatientDelegation` / `VerifiedGuardianship`) — dejar Service; UseCase fino solo si se parte por intención
- [ ] Catálogos Agenda `*CatalogService` → reducir ruido (opcional, ciclo aparte)

## Docs producto

- [x] `registro-paciente.md`, `urgencias-guardia.md`, plan admisión → nombres UseCase actuales
