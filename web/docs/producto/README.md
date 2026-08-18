# Producto

Cada archivo cuenta **una historia de punta a punta**: qué problema resuelve, quién interviene y cómo se enlazan procesos (API, base, IA, jobs, interfaz).

No es un índice del repositorio ni el manual de un endpoint. Para probar: [qa/](../qa/README.md).

## Paciente y cuenta

| Documento | Tema |
|-----------|------|
| [apps-paciente-personalsalud.md](./apps-paciente-personalsalud.md) | Experiencia paciente y personal de salud |
| [sesion-paciente-app.md](./sesion-paciente-app.md) | Sesión, bloqueo local y reingreso Didit |
| [registro-paciente.md](./registro-paciente.md) | Alta (app y staff), MPI reducido, contexto, RENAPER |
| [representacion-paciente.md](./representacion-paciente.md) | Tutela de menor y delegación |

## Pedir y seguir atención

| Documento | Tema |
|-----------|------|
| [solicitar-atencion.md](./solicitar-atencion.md) | Puerta del paciente: malestar, estudio, control, urgencia |
| [triage-reserva-turno.md](./triage-reserva-turno.md) | Árbol de alarmas y bandas al reservar |
| [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md) | Cuándo se ofrece videollamada |
| [medicina-clinica-hub-reserva.md](./medicina-clinica-hub-reserva.md) | Reserva por medicina clínica y derivación a especialista |
| [consultas-seguimiento.md](./consultas-seguimiento.md) | Tras el hub: renovar, ajustar, consulta por mensaje |
| [atencion-remota-async.md](./atencion-remota-async.md) | Videollamada, mensaje y bandeja staff (adopción) |
| [recorrido-pre-post-consulta.md](./recorrido-pre-post-consulta.md) | Motivos, intake y packs pre/post consulta |
| [asistencia-cohortes.md](./asistencia-cohortes.md) | Packs de asistencia, seguimiento y educación por cohorte |

## Agenda

| Documento | Tema |
|-----------|------|
| [turnos.md](./turnos.md) | Reserva AMB, cancelación, reubicación, avisos |
| [glosario-servicio-pes-acto.md](./glosario-servicio-pes-acto.md) | Servicio del centro vs PES vs acto SNOMED |
| [agenda-por-encounter-class.md](./agenda-por-encounter-class.md) | AMB cupos vs EMER/IMP cobertura |
| [interoperabilidad-agendamiento-fhir.md](./interoperabilidad-agendamiento-fhir.md) | Espejo de citas NIS HAPI ↔ turnos |

## Consulta, resultados y tratamiento

| Documento | Tema |
|-----------|------|
| [captura-clinica.md](./captura-clinica.md) | Audio/texto, análisis y guardado del encounter |
| [resumen-atencion-paciente.md](./resumen-atencion-paciente.md) | Resumen post-consulta y expediente staff |
| [laboratorio.md](./laboratorio.md) | Resultados externos, ingestas, consulta |
| [receta-electronica.md](./receta-electronica.md) | Receta emitida, PDF, paciente |
| [planes-de-tratamiento.md](./planes-de-tratamiento.md) | Care plans y recordatorios |
| [interoperabilidad-historia-clinica.md](./interoperabilidad-historia-clinica.md) | Export FHIR de atención finalizada |

## Guardia e internación

| Documento | Tema |
|-----------|------|
| [urgencias-guardia.md](./urgencias-guardia.md) | Triage, tablero, circuito EMER |
| [hcd-episodio-emergencia-internacion.md](./hcd-episodio-emergencia-internacion.md) | Cockpit HCD de episodio |
| [internacion.md](./internacion.md) | Mapa de camas, alta, plantillas de epicrisis |

## Conversación, IA y agentes

| Documento | Tema |
|-----------|------|
| [asistente-y-chat.md](./asistente-y-chat.md) | Conversación y acciones en lenguaje natural |
| [catalogo-usos-ia.md](./catalogo-usos-ia.md) | Contextos de modelo (telemetría y costos) |
| [ia-datos-y-privacidad.md](./ia-datos-y-privacidad.md) | Vertex como encargado, extracto de HC y consentimiento del paciente |
| [agentes-autonomos.md](./agentes-autonomos.md) | Agentes proactivos en producción |

Frases que un paciente podría decir (para probar enrutado): [qa/paciente/asistente-consultas.md](../qa/paciente/asistente-consultas.md).

## Plataforma

| Documento | Tema |
|-----------|------|
| [superficies-ui.md](./superficies-ui.md) | Inicio vs captura vs flows |
| [alta-cuenta-licencia.md](./alta-cuenta-licencia.md) | Self-service clínica/efector, solicitud ministerio |

## Ideas a futuro

Extensiones **no comprometidas** (sin mezclar con lo que ya corre): [ideas-a-futuro/](./ideas-a-futuro/README.md).

## Otros mapas

- [Arquitectura del asistente](../arquitectura/asistente-motores.md)
- [Lecturas del asistente](../arquitectura/asistente-lectura-data-access.md)
- [Madurez HIS](../his-completo/README.md)
- [Costos IA/infra](../costos/README.md)
