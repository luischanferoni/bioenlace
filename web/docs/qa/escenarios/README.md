# QA por escenario clínico

[← Índice QA](../README.md)

Guías orientadas a **necesidades del paciente** y **tipo de encounter**, no al rol del probador. Cada documento recorre el caso de punta a punta: qué hace el paciente, qué hace el equipo y un **guion de consulta de ejemplo**.

Complementan (no reemplazan) las carpetas por rol: [paciente/](../paciente/README.md), [medico/](../medico/README.md), [staff/](../staff/README.md), [app-personalsalud/](../app-personalsalud/README.md).

## Estructura

| Carpeta | Encounter / necesidad | Documentos |
|---------|----------------------|------------|
| [ambulatorio/](./ambulatorio/README.md) | Turno AMB (presencial o remoto) | [Medicina general](./ambulatorio/medicina-general.md) · [Odontología](./ambulatorio/odontologia.md) · [Teleconsulta](./ambulatorio/teleconsulta.md) |
| [urgencia/](./urgencia/README.md) | Guardia / EMER, triage Manchester | [Guardia y derivación](./urgencia/README.md) |
| [seguimiento/](./seguimiento/README.md) | Consulta async, care plan | [Consultas y seguimiento](./seguimiento/README.md) |
| [internacion/](./internacion/README.md) | Piso IMP — ingreso, evolución, alta | [Internación](./internacion/README.md) |

## Plantilla de cada guía

Cada escenario sigue el mismo esquema:

1. **De qué se trata** — problema del paciente y flujo Bioenlace que aplica.
2. **Prerrequisitos** — datos de staging, roles, flags del efector.
3. **Consulta de ejemplo** — frases típicas del paciente (app/chat) y del médico (captura o consultorio).
4. **Paciente** — pasos numerados (vos / el sistema).
5. **Personal de salud** — pasos numerados (web y/o app Personal de Salud).
6. **Notificaciones** — qué push o aviso esperar y **cuándo** (orden aproximado).
7. **Qué validar** — checklist corto para marcar en la prueba.
8. **Referencias** — producto y QA por rol.

## Convenciones

- **Intent** cuando el flujo pasa por el asistente.
- Tiempos de notificación dependen del cron `turno-notificacion/run` en staging (preguntar intervalo al responsable del entorno).
- Si un paso no aplica en tu efector (p. ej. cohortes deshabilitadas), marcá **N/A** y seguí con el resto.

## Contexto de producto

- [recorrido-pre-post-consulta.md](../../producto/recorrido-pre-post-consulta.md) — journey pre/post turno ambulatorio.
- [consultas-seguimiento.md](../../producto/consultas-seguimiento.md) — canal distinto de «necesito atención».
- [urgencias-guardia.md](../../producto/urgencias-guardia.md) — circuito EMER.
- [internacion.md](../../producto/internacion.md) — mapa de camas y alta IMP.
- [teleconsulta-elegibilidad.md](../../producto/teleconsulta-elegibilidad.md) — modalidad remota en reserva.
