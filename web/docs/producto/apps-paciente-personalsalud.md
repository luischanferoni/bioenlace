# Experiencia paciente y personal de salud

## De qué se trata

**Una misma plataforma Bioenlace** con la misma API: el **paciente** gestiona turnos, resultados, resúmenes y conversación; el **personal de salud** trabaja agenda, captura clínica y operación en el efector (web clínica y **app móvil Personal de Salud**).

## Registro e identidad

Detalle de flujos, MPI reducido y alta staff: **[registro-paciente.md](./registro-paciente.md)**.

```mermaid
flowchart TB
  subgraph paciente [Paciente — app]
    PU[Usuario nuevo]
    PAPP[App móvil paciente]
    PAPI[API registro]
  end
  subgraph staff [Personal — web y app]
    SU[Personal de salud]
    AS[Asistente / atajo]
    WEB[Pantalla embebida alta]
    MOB[App Personal de Salud]
    SAPI[API registrar-como-staff]
  end
  REN[Gateway RENAPER]
  LOC[(Persona en Bioenlace)]
  PU --> PAPP --> PAPI --> REN
  PAPI --> LOC
  SU --> AS --> WEB --> SAPI --> REN
  SU --> MOB
  SAPI --> LOC
```

- **Paciente:** autoregistro en app; contexto provincial/sector y domicilio RENAPER en segundo plano.
- **Staff:** alta por lector DNI o Didit desde el **asistente**; sin flujo MPI de candidatos; sin redirigir a ficha clínica ni fijar el paciente en la sesión del staff.
- **App Personal de Salud:** login con usuario del centro; registro REFEPS solo para **médicos nuevos**; `appClient` `personalsalud-flutter`.
- **MPI:** solo RENAPER, coberturas y domicilio; empadronar/candidatos retirados.
- Tras login, **token** y **sesión operativa** del staff (efector, servicio, rol) definen su contexto; el paciente en un flujo clínico se pasa **explícitamente** (asistente, API, captura).

## Capacidades transversales

| Capacidad | Idea |
|-----------|------|
| Conversación y acciones guiadas | [asistente-y-chat.md](./asistente-y-chat.md) |
| Notificaciones push | Turnos, guardia, resumen de atención listo, etc. |
| Medios | Intercambio de audio, imagen o video según flujo clínico o soporte |
| Videollamada | Cuando el producto habilita teleconsulta |

## Paciente en el día a día

- Inicio: próximos turnos, tratamientos activos, mis atenciones.
- **Representación:** chip «A cargo de» en inicio (yo u otro paciente con tutela o delegación activa); gestión en Configuración → Representación. Detalle: [representacion-paciente.md](./representacion-paciente.md).
- Resolver turnos en conflicto o pedir acciones desde la conversación o desde accesos directos en inicio.
- Configuración: alertas, recordatorios de planes de tratamiento, preferencia de aviso cuando un representante actúa (N9).

## Personal de salud en el día a día

- Sesión con **efector y servicio**; la **página de inicio muta** según `encounter_class` y rol (tablero EMER, mapa IMP, agenda AMB, etc.) — ver [superficies-ui.md](./superficies-ui.md). Misma lógica en **web** y **app Personal de Salud** (`GET /home/panel`).
- **Captura clínica** unificada: timeline del paciente + formulario encounter (texto/audio → API `clinical/encounter/*`); muta por encounter, rol y especialidad — [captura-clinica.md](./captura-clinica.md).
- Operaciones puntuales (alta internación, triage, etc.) vía **flows del asistente** cuando aplica — [asistente-y-chat.md](./asistente-y-chat.md).
- Con **encounterClass = EMER** (guardia): tablero operativo, triage, atender, derivar y egreso — [urgencias-guardia.md](./urgencias-guardia.md).
- **Internación (IMP):** mapa de camas en inicio; atención en piso vía timeline con `parent=INTERNACION` — [internacion.md](./internacion.md).

## Relación con otros documentos

- [registro-paciente.md](./registro-paciente.md) — alta paciente, MPI reducido, contexto y domicilio RENAPER
- [representacion-paciente.md](./representacion-paciente.md) — tutela de menor y delegación
- [superficies-ui.md](./superficies-ui.md) — inicio vs captura vs flows (web = app Personal de Salud)
- [urgencias-guardia.md](./urgencias-guardia.md), [internacion.md](./internacion.md)
