# Producto

Cada archivo cuenta **una historia de punta a punta**: qué problema resuelve, qué actores intervienen y cómo se enlazan procesos que viven en sitios distintos del sistema (cron, servicio externo, base de datos, API, IA, interfaz de Bioenlace).

No es un índice de archivos del repositorio ni un manual de un solo endpoint.

| Documento | Tema |
|-----------|------|
| [apps-paciente-personalsalud.md](./apps-paciente-personalsalud.md) | Experiencia paciente y personal de salud, registro, medios |
| [sesion-paciente-app.md](./sesion-paciente-app.md) | Sesión, bloqueo local y reingreso Didit tras cerrar sesión (app paciente) |
| [registro-paciente.md](./registro-paciente.md) | Alta paciente (app y staff), MPI reducido, contexto y RENAPER |
| [representacion-paciente.md](./representacion-paciente.md) | Tutela de menor y delegación (operar por otro paciente) |
| [turnos.md](./turnos.md) | Agenda, reserva, cancelación, notificaciones |
| [triage-reserva-turno.md](./triage-reserva-turno.md) | Motivo y alarmas antes de reservar (catálogo + bandas A–D) |
| [atencion-remota-async.md](./atencion-remota-async.md) | Atención remota y consulta async (adopción gradual) |
| [consultas-seguimiento.md](./consultas-seguimiento.md) | Consulta general y seguimiento de tratamiento (app paciente) |
| [recorrido-pre-post-consulta.md](./recorrido-pre-post-consulta.md) | Motivos, pre-consulta y post-consulta (ventanas y journey) |
| [catalogo-usos-ia.md](./catalogo-usos-ia.md) | Catálogo de contextos y usos de IA (referencia rápida) |
| [agentes-autonomos.md](./agentes-autonomos.md) | Agentes proactivos (decisión autónoma + auditoría) |
| [asistente-y-chat.md](./asistente-y-chat.md) | Conversación y acciones en lenguaje natural |
| [captura-clinica.md](./captura-clinica.md) | Audio/texto, corrección, resumen con IA |
| [laboratorio.md](./laboratorio.md) | Resultados externos, ingestas, consulta paciente |
| [resumen-atencion-paciente.md](./resumen-atencion-paciente.md) | Resumen post-consulta y expediente staff |
| [planes-de-tratamiento.md](./planes-de-tratamiento.md) | Care plans y recordatorios |
| [receta-electronica.md](./receta-electronica.md) | Receta emitida, PDF, paciente |
| [interoperabilidad-historia-clinica.md](./interoperabilidad-historia-clinica.md) | Export FHIR de atención finalizada hacia red / Estado |
| [urgencias-guardia.md](./urgencias-guardia.md) | Triage, tablero operativo, circuito EMER |
| [internacion.md](./internacion.md) | Mapa de camas, alta estructurada, plantillas de epicrisis (ABM) |
| [superficies-ui.md](./superficies-ui.md) | Inicio vs captura encounter vs flows (web = móvil) |

## Ideas a futuro

Visión y extensiones **no comprometidas** en el roadmap actual: [ideas-a-futuro/](./ideas-a-futuro/README.md).

## Otros mapas

- [Flujos paso a paso (usuario y sistema)](../qa/README.md)
- [Arquitectura del asistente](../arquitectura/asistente-motores.md)
- [Madurez HIS](../his-completo/README.md)
- [Costos IA/infra](../costos/README.md)
