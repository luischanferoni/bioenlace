# Design — ddd-norte-org-person-sched

## Mapa UseCase fase 01–02

| Antes (`Application/Service`) | Después (`Application/UseCase`) |
|-------------------------------|---------------------------------|
| `ProfesionalEfectorServicioAltaService` | `EnsurePesAssignment` ✅ |
| `ProfesionalEfectorServicioBajaService` | `DeactivatePesAssignment` ✅ |
| `InstitutionalEfectorSignupService` | `SignUpInstitutionalEfector` ✅ |
| `MinistrySignupRequestService` | `RequestMinistrySignup` ✅ |
| `RegistroService` | `RegisterPerson` ✅ |
| `RegistroStaffPacienteService` | `RegisterPatientByStaff` ✅ |
| `PersonaIdentidadBasicaUpdateService` | `UpdateBasicIdentity` ✅ |
| `FrontDeskSessionService` | `EstablishFrontDeskSession` ✅ |
| `AdminEfectorAsignacionService` | `EnsureAdminEfectorAssignment` ✅ |
| `BillingMembershipSwitchService` | `SwitchBillingMembership` ✅ |
| `SesionOperativaService` | `EstablishOperativeSession` ✅ |
| `PersonaIdentidadPendienteService` | `CreatePendingIdentity` ✅ |
| `PersonaIdentidadResolverService` | `ResolvePersonIdentity` ✅ |

Métodos públicos se mantienen cuando es posible (`ensurePersonaServicioEnEfector`, `registrar`, `update`, …) para no reescribir el cuerpo; solo cambia FQCN + carpeta.

## Agenda Infra (fase 03)

```text
Agenda/Infrastructure/
  Persistence/          # NullEfectorDirectionsProvider (stub local)
  External/
    Outbound/           # TurnoOutboundChannel (email/SMS stub)
    NisFhir/            # Connector, Contract, Mapper, Registry, Sync, Exception
```

Port: `Domain/Port/EfectorDirectionsProvider` — toda implementacion y consumer con `use` explícito.

## Riesgos

- Autoload / params `fhirSchedulingInbound.class` → actualizar path tras mv NisFhir.
- Controllers API + product-registries + hydrators: grep exhaustivo al renombrar.
- Índice Cursor puede mostrar paths fantasma (`Scheduling/Infrastructure/`); source of truth = disco.
