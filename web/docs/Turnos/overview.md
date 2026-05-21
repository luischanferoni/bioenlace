# Turnos — Visión general

## Qué es

El dominio **turnos** cubre la **cita agendada** entre una persona (paciente) y un profesional en un efector y servicio, con estados de ciclo de vida (pendiente, cancelado, atendido, etc.), reglas de autogestión y vínculo opcional con agenda versionada por **profesional–efector–servicio (PES)**.

## Objetivo

- Ofrecer **cupos reservables** alineados a la grilla real del profesional.
- Permitir **autogestión** al paciente (reservar, cancelar, reprogramar) dentro de políticas por efector.
- Dar a **staff** herramientas operativas (alta para tercero, cancelación masiva, sobreturno, conflictos de agenda).
- Notificar recordatorios y cambios por canales configurados (push, etc.).

## Actores

| Actor | Uso típico |
|-------|------------|
| Paciente | App / asistente: reservar, ver pendientes, cancelar, reprogramar |
| Profesional / administración efector | Calendario web, operaciones sobre turnos de pacientes |
| Sistema | Cron de notificaciones, cola de conflictos tras cambio de agenda |

## Alcance

Incluye: oferta de slots, persistencia de turno, políticas de anticipación, conflictos por cambio de agenda, intents del asistente ligados a API v1.

Fuera de este dominio (documentado aparte): facturación del acto, contenido clínico del encuentro (`encounter`), derivaciones RRHH puras sin turno.

## Relacionado

- [design.md](./design.md) — decisiones de arquitectura del dominio.
- [flows/](./flows/) — procedimientos y contratos HTTP detallados.
