# QA — Ambulatorio (AMB)

[← Escenarios](../README.md)

Turno con fecha y hora en un servicio ambulatorio. El paciente usa la **app móvil**; el profesional atiende desde **web** o **app Personal de Salud** con `encounter_class` ambulatorio en sesión.

## Escenarios en esta carpeta

| Documento | Servicio / caso | Flujo paciente típico |
|-----------|-----------------|------------------------|
| [medicina-general.md](./medicina-general.md) | Medicina clínica / generalistas (hub) | Malestar leve o control; triage + reserva; journey pre-consulta |
| [teleconsulta.md](./teleconsulta.md) | Medicina clínica (u otro con política remota) | Mismo triage; paso Modalidad → videollamada |
| [odontologia.md](./odontologia.md) | Odontología | Dolor dental o control; suele requerir derivación del clínico o turno directo según efector |

## Qué comparten todos los casos ambulatorios

- **Encounter** vinculado al turno (`appointment_id`).
- **Ventana de motivos** — por defecto desde 72 h antes hasta ~2 min antes del turno ([recorrido-pre-post-consulta.md](../../../producto/recorrido-pre-post-consulta.md)).
- **Historia clínica staff** — se abre según `historia_clinica_apertura_medico_minutos` (típ. 1 min antes en ambulatorio).
- **Captura** — timeline + formulario encounter; el workflow muta por especialidad (`EncounterDefinition`).

## Qué no es ambulatorio en esta carpeta

- **Urgencia / guardia** → [urgencia/](../urgencia/README.md).
- **Consulta por mensaje o seguimiento de plan** sin malestar nuevo → [seguimiento/](../seguimiento/README.md).

## Referencias QA por rol

- Paciente turnos: [paciente/turnos.md](../../paciente/turnos.md)
- Médico captura: [medico/captura-clinica.md](../../medico/captura-clinica.md)
- Staff agenda: [staff/turnos-agenda.md](../../staff/turnos-agenda.md)
