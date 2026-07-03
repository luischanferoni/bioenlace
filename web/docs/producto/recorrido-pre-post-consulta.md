# Recorrido pre y post consulta (encounter journey)

## De qué se trata

Orquestación declarativa de **motivos de consulta**, **cuestionario pre-consulta** (care pack) y **seguimiento post-consulta**, con **ventanas temporales**, **elegibilidad** (cuándo no aplica cada fase) y **notificaciones** al paciente.

El paciente opera desde la **app móvil**; staff consume los datos en captura clínica y bandejas existentes.

## Fases

| Fase | Superficie | Cuándo |
|------|------------|--------|
| `motivos_intake` | Formulario UI JSON (preguntas declarativas) | Antes del chat de motivos, misma ventana |
| `motivos_consulta` | Chat libre (`motivos-consulta/*`) | Antes del turno, dentro de la ventana |
| `asistencia_pre_consulta` | Flow / UI JSON care pack | Antes del turno, si hay pack de cohorte |
| `post_consulta` | Touchpoints del pack followup | Tras encounter finalizado |

**No confundir** con `atencion.consultas-seguimiento-flow` (seguimiento de tratamiento / care plan), que es un canal distinto.

## Principios

1. **Metadata YAML** — ventanas en `encounter_phase_windows.yaml`, overrides en `encounter_phase_window_overrides.yaml`, elegibilidad en `encounter_phase_eligibility.yaml`, preguntas previas en `motivos_consulta_intake.yaml`.
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

**Overrides por efector/servicio** (`encounter_phase_window_overrides.yaml`): reglas con `match.id_efector` y/o `match.id_servicio`; gana la regla más específica (más claves en `match`).

## Preguntas previas al chat de motivos

Catálogo `motivos_consulta_intake.yaml` (`enabled: true/false`). API `GET|POST /api/v1/encounter-journey/motivos-intake`. Respuestas en `encounter.motivos_intake_json`. El chat de motivos queda bloqueado hasta completar el intake si está habilitado.

## Elegibilidad (ejemplos)

- **Motivos:** no aplica sin encounter, async (`SOLICITUD_ASYNC`), turno cancelado/en resolución, clase ≠ AMB.
- **Pre-consulta:** además requiere cohortes habilitadas, pack assistance ligado y cuestionario no completado.
- **Post-consulta:** encounter finalizado, pack followup disponible.

Reglas en `encounter_phase_eligibility.yaml`; evaluación en `EncounterJourneyEligibilityService`.

## API

- `GET|POST /api/v1/encounter-journey/estado?turno_id=` — estado completo + flags legacy.
- `GET|POST /api/v1/encounter-journey/motivos-intake?turno_id=` — formulario previo al chat.
- El listado `turnos/listar-como-paciente` incluye `journey` en cada fila.

RBAC: hereda de `listar-como-paciente` (migración `m260703_120000_api_encounter_journey_estado_rbac`).

## Notificaciones

Al programar turno (`TurnoConfirmationService`), se encolan recordatorios de fases con anchor `turno_start`. Al finalizar encounter (`EncounterLifecycleService`), se encolan los de `post_consulta` (anchor `encounter_finished`). El cron `turno-notificacion/run` envía push con deep link (`id_turno`, `phase`, opcional `touchpoint_id`).

Los touchpoints del pack followup (`care-pack process-followups`) incluyen `id_turno` y `phase=post_consulta` para abrir el hub o el formulario directamente en la app.

## Relación con otros documentos

- [turnos.md](./turnos.md) — reserva y listado.
- [asistencia-cohortes.md](./asistencia-cohortes.md) — generación de packs.
- [consultas-seguimiento.md](./consultas-seguimiento.md) — seguimiento de tratamiento (care plan).
- [triage-reserva-turno.md](./triage-reserva-turno.md) — triage al reservar (no sustituye motivos).

## Próximos pasos (producto)

- Activar `motivos_consulta_intake.yaml` (`enabled: true`) cuando el equipo quiera preguntas previas en producción.
