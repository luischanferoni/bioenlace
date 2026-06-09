# Experiencia paciente y médico

## De qué se trata

**Una misma plataforma Bioenlace** con la misma API: el **paciente** gestiona turnos, resultados, resúmenes y conversación; el **médico** trabaja agenda, captura clínica y operación en el efector.

## Registro e identidad

```mermaid
flowchart LR
  U[Usuario nuevo]
  UI[Interfaz Bioenlace]
  API[API registro / Verifik]
  MPI[Identidad en Bioenlace]
  U --> UI --> API
  API --> MPI
  MPI --> UI
```

- Alta con validación de documento y, para profesionales, referencias externas cuando corresponde.
- Tras login, **token** y **sesión operativa** (efector, servicio, rol) fijan qué puede hacer cada pantalla.

## Capacidades transversales

| Capacidad | Idea |
|-----------|------|
| Conversación y acciones guiadas | [asistente-y-chat.md](./asistente-y-chat.md) |
| Notificaciones push | Turnos, resumen de atención listo, etc. |
| Medios | Intercambio de audio, imagen o video según flujo clínico o soporte |
| Videollamada | Cuando el producto habilita teleconsulta |

## Paciente en el día a día

- Inicio: próximos turnos, tratamientos activos, mis atenciones.
- **Representación:** chip «A cargo de» en inicio (yo u otro paciente con tutela o delegación activa); gestión en Configuración → Representación. Detalle: [representacion-paciente.md](./representacion-paciente.md).
- Resolver turnos en conflicto o pedir acciones desde la conversación o desde accesos directos en inicio.
- Configuración: alertas, recordatorios de planes de tratamiento, preferencia de aviso cuando un representante actúa (N9).

## Médico en el día a día

- Sesión con **efector y servicio**; la **página de inicio muta** según `encounter_class` y rol (tablero EMER, mapa IMP, agenda AMB, etc.) — ver [superficies-ui.md](./superficies-ui.md).
- **Captura clínica** unificada: timeline del paciente + formulario encounter (texto/audio → API `clinical/encounter/*`); muta por encounter, rol y especialidad — [captura-clinica.md](./captura-clinica.md).
- Operaciones puntuales (alta internación, triage, etc.) vía **flows del asistente** cuando aplica — [asistente-y-chat.md](./asistente-y-chat.md).
- Con **encounterClass = EMER** (guardia): tablero operativo, triage, atender, derivar y egreso — [urgencias-guardia.md](./urgencias-guardia.md).
- **Internación (IMP):** mapa de camas en inicio; atención en piso vía timeline con `parent=INTERNACION` — [internacion.md](./internacion.md).

## Relación con otros documentos

- [representacion-paciente.md](./representacion-paciente.md) — tutela de menor y delegación
- [superficies-ui.md](./superficies-ui.md) — inicio vs captura vs flows (web = móvil)
- [urgencias-guardia.md](./urgencias-guardia.md), [internacion.md](./internacion.md)
