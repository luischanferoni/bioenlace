# Recorrido pre y post consulta (encounter journey)

## De qué se trata

Orquestación declarativa de **motivos de consulta**, **cuestionario pre-consulta** (care pack) y **seguimiento post-consulta**, con **ventanas temporales**, **elegibilidad** (cuándo no aplica cada fase) y **notificaciones** al paciente.

El paciente opera desde la **app móvil**; staff consume los datos en captura clínica y bandejas existentes.

## Fases

| Fase | Superficie | Cuándo |
|------|------------|--------|
| `motivos_consulta` | Chat libre (`motivos-consulta/*`) | Antes del turno, dentro de la ventana |
| `asistencia_pre_consulta` | Flow / UI JSON care pack | Antes del turno, si hay pack de cohorte |
| `post_consulta` | Touchpoints del pack followup | Tras encounter finalizado |

**No confundir** con `atencion.consultas-seguimiento-flow` (seguimiento de tratamiento / care plan), que es un canal distinto.

## Principios

1. **Metadata YAML** — ventanas en `encounter_phase_windows.yaml`, elegibilidad en `encounter_phase_eligibility.yaml`.
2. **Servicio de dominio** — `EncounterJourneyService` compone ventana + elegibilidad + `enabled` por fase.
3. **Sin hardcode en listados ni Flutter** — el listado de turnos y `GET /api/v1/encounter-journey/estado` exponen `journey` y flags legacy (`motivos_input_abierto`, `asistencia_cohorte_disponible`).
4. **Motivos siguen siendo conversacionales** — la IA corre en lote al cierre de ventana (`motivos-consulta-batch`), no por pregunta del wizard.

## Ventanas (defaults producto)

| Fase | Apertura | Cierre |
|------|----------|--------|
| Motivos | 72 h antes del turno | `motivos_consulta_cierre_minutos` antes (params, default 2 min) |
| Pre-consulta | 48 h antes | Mismo cierre que motivos |
| Post-consulta | Al finalizar encounter | +30 días |

Offsets configurables en `encounter_phase_windows.yaml` (`-72h`, `-48h`, `param:motivos_consulta_cierre_minutos`, etc.).

## Elegibilidad (ejemplos)

- **Motivos:** no aplica sin encounter, async (`SOLICITUD_ASYNC`), turno cancelado/en resolución, clase ≠ AMB.
- **Pre-consulta:** además requiere cohortes habilitadas, pack assistance ligado y cuestionario no completado.
- **Post-consulta:** encounter finalizado, pack followup disponible.

Reglas en `encounter_phase_eligibility.yaml`; evaluación en `EncounterJourneyEligibilityService`.

## API

- `GET|POST /api/v1/encounter-journey/estado?turno_id=` — estado completo + flags legacy.
- El listado `turnos/listar-como-paciente` incluye `journey` en cada fila.

RBAC: hereda de `listar-como-paciente` (migración `m260703_120000_api_encounter_journey_estado_rbac`).

## Notificaciones

Al programar turno (`TurnoConfirmationService`), se encolan recordatorios declarados en metadata (`JOURNEY_MOTIVOS_RECORDATORIO`, `JOURNEY_PRECONSULTA_RECORDATORIO`, etc.). El cron `turno-notificacion/run` envía push con deep link (`id_turno`, `phase`).

## Relación con otros documentos

- [turnos.md](./turnos.md) — reserva y listado.
- [asistencia-cohortes.md](./asistencia-cohortes.md) — generación de packs.
- [consultas-seguimiento.md](./consultas-seguimiento.md) — seguimiento de tratamiento (care plan).
- [triage-reserva-turno.md](./triage-reserva-turno.md) — triage al reservar (no sustituye motivos).

## Próximos pasos (producto)

- Flow declarativo opcional de preguntas generales antes del chat de motivos.
- Overrides de ventana por efector/servicio en metadata o `EfectorTurnosConfig`.
- Hub único «Preparar tu consulta» en inicio app consumiendo solo `journey`.
