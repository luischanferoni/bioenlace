# Agenda tipada por encounter_class

**Tipo:** producto · organización / scheduling  
**Última actualización:** 2026-07-10

## Principio

La **clase de encuentro** (`AMB` | `EMER` | `IMP`) define el **modelo de capacidad**. El **servicio** filtra ámbito; no tipa la agenda.

| Clase | Almacenamiento | Capacidad | Paciente reserva turnos |
|-------|----------------|-----------|-------------------------|
| **AMB** | `profesional_efector_servicio_agenda` (+ versiones) | Cupos / grilla semanal | **Sí** |
| **EMER** | `profesional_cobertura` | Roster entrada–salida | No |
| **IMP** | `profesional_cobertura` | Roster entrada–salida | No |

Metadata: [`agenda-by-encounter-class.yaml`](../../common/metadata/bioenlace/organization/agenda-by-encounter-class.yaml).

## AMB (sin cambio de idea)

- Configuración: intents `profesional-agenda.configurar-*`, API `/api/v1/profesional-agenda/*`.
- Reserva paciente: `TurnoSlotFinder` + `turnos.*-como-paciente` solo sobre agendas `encounter_class = AMB`.
- Encounter desde turno: sigue siendo AMB.

## EMER / IMP — cobertura

- Tabla `profesional_cobertura`: persona, efector, clase, `inicio`/`fin`, servicio opcional, PES opcional, rol/notas.
- Conflictos: solape de intervalos misma persona + mismo efector (configurable en metadata).
- API: `/api/v1/profesional-cobertura/*` (propio vs `*-para-recurso`).
- UI JSON: `profesional-cobertura/gestionar`.
- Intents: `profesional-cobertura.gestionar-propio` | `gestionar-staff`.

No crea filas en `turnos` ni slots públicos.

## Frontera paciente

1. Servicios con `acepta_turnos = SI` (catálogo).
2. Agendas PES con `encounter_class = AMB`.
3. Cobertura EMER/IMP **nunca** entra al funnel de reserva.

## Migraciones

1. `m260710_100000_agenda_tipada_por_encounter_class`
2. `m260710_100001_api_profesional_cobertura_rbac`

## Relacionado

- [turnos.md](./turnos.md)
- [urgencias-guardia.md](./urgencias-guardia.md)
- [internacion.md](./internacion.md)
- [his-completo/11-agenda-turnos.md](../his-completo/11-agenda-turnos.md)
