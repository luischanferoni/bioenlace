# Notificaciones y avisos automáticos

[← Staff](./README.md) · Checklist: [checklist.md](./checklist.md)

El sistema puede enviar **avisos al celular** (push) o mostrar mensajes según lo que pase en turnos, guardia, internación o laboratorio. En staging, pedí al responsable que confirme qué avisos están activos.

**No probás código:** probás que, ante una situación concreta, llegue el aviso correcto (o el mensaje en pantalla).

---

## Turnos

| ID | Situación | Qué deberías ver |
|----|-----------|------------------|
| AGT-03 | Cancelás un turno y hay lista de espera | El siguiente paciente en lista recibe aviso de cupo |
| AGT-09 | Turno con alto riesgo de inasistencia (según reglas del efector) | Paciente recibe push pidiendo confirmar asistencia |
| AGT-10 | Paciente no responde a oferta de reprogramar (varios días) | Cierre del trámite o aviso a coordinación (según configuración) |
| — | Te reprograman o cancelan un turno | Push en la app (si notificaciones activas) |

---

## Reserva de turno sin cupo (después del triage)

| ID | Situación | Qué deberías ver |
|----|-----------|------------------|
| AGT-05a | Urgencia muy alta (banda A) sin cupo | Mensaje de derivación a guardia / emergencia; **no** ofrece turno ambulatorio |
| AGT-05b | Control crónico sin cupo y consulta asíncrona disponible | Oferta de consulta asíncrona o canal alternativo (mensaje o push) |
| AGT-05c | Mismo caso repetido | No spam de avisos duplicados |

---

## Internación

| ID | Situación | Qué deberías ver |
|----|-----------|------------------|
| AGT-08 | Iniciás ingreso y hay camas libres | Pantalla sugiere camas candidatas (podés elegir otra) |
| AGT-06 | Das de alta a un internado | Paciente puede recibir seguimiento post-alta (mensaje o encuesta según programa) |

---

## Recetas

| ID | Situación | Qué deberías ver |
|----|-----------|------------------|
| AGT-07a | Emitís receta completa y válida | Emisión normal, PDF disponible |
| AGT-07b | Faltan datos obligatorios de la receta | Mensaje claro **antes** de emitir; no queda receta inválida publicada |

---

## Laboratorio y seguimiento

| ID | Situación | Qué deberías ver |
|----|-----------|------------------|
| AGT-02 | Resultado con valor crítico | Notificación al paciente o médico (según reglas del efector) |
| AGT-01 | Respondés encuesta de seguimiento de un programa | El flujo continúa o cierra según tu respuesta |

---

## Qué no incluye esta guía

- Mensajes redactados por inteligencia artificial (aún no para QA).
- Clasificación libre del chat por IA — ver [asistente.md](./asistente.md).

Si un aviso no llega, anotá: usuario, hora, acción hecha y si las notificaciones del celular están permitidas para la app.
