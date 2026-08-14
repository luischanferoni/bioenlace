# Agenda tipada por encounter_class

**Tipo:** producto · organización / scheduling  
**Última actualización:** 2026-08-11

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

- Tabla `profesional_cobertura`: intervalos absolutos (entrada/salida) materializados desde plantilla.
- Plantilla `profesional_cobertura_plantilla`: patrón semanal (`lunes_2`…`domingo_2`, mismo CSV de horas que AMB) + `vigente_desde` + `semanas`.
- Al guardar: reemplaza coberturas generadas (`notas` `plantilla:*`) en la ventana y crea intervalos contiguos por día.
- Conflictos: solape de intervalos misma persona + mismo efector; y solape con la **grilla semanal** AMB (`cobertura_vs_amb_slots`), leída del patrón `lunes_2`… (no de slots generados). Una agenda AMB en `SIN_ATENCION` igual ocupa esas horas. La UI pinta las celdas ocupadas en gris; el guardado rechaza la intersección. Cobertura de noche puede coexistir con ambulatorio de día.
- API: `/api/v1/profesional-cobertura/*`; `elegir-encounter-class`; `gestionar` (UI plantilla).
- Intent unificado (atajo): `profesional-horarios.gestionar-propio` (servicio → AMB|EMER|IMP → agenda o plantel).
- Intents legado (no atajo): `profesional-agenda.configurar-*`, `profesional-cobertura.gestionar-*`.
- Panel inicio: `staff_cobertura_activa` (`session.tiene_cobertura`, `session.mensaje_sin_cobertura`).
- **Tomar/asignar caso EMER** exige cobertura vigente (`operational.emer_assign_requires_cobertura`).

No crea filas en `turnos` ni slots públicos.

## Frontera paciente

1. Servicios con `acepta_turnos = SI` (catálogo).
2. Agendas PES con `encounter_class = AMB`.
3. Cobertura EMER/IMP **nunca** entra al funnel de reserva.

## Migraciones

1. `m260710_100000_agenda_tipada_por_encounter_class`
2. `m260710_100001_api_profesional_cobertura_rbac`
3. `m260710_120000_api_profesional_cobertura_listar_activas_rbac`
4. `m260710_130000_api_profesional_cobertura_elegir_pes_rbac`

## Relacionado

- Comercial / entitlements por clase: [matriz-argentina-modulos-precios.md](../modelo-de-negocio/business-plan/matriz-argentina-modulos-precios.md) (`billing_account` pool, `max_pes`, downgrade diferido)
- [turnos.md](./turnos.md)
- [urgencias-guardia.md](./urgencias-guardia.md)
- [internacion.md](./internacion.md)
- [his-completo/11-agenda-turnos.md](../his-completo/11-agenda-turnos.md)
