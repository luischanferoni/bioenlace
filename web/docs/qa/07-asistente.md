# Asistente y chat

[← Índice](./README.md)

El asistente entiende **frases en castellano** (o elegís un **Atajo** visible). Te hace preguntas paso a paso y al final hace lo que pediste o te muestra una pantalla.

**Web (personal):** abrís el asistente y escribís.  
**App (paciente):** igual, desde el chat.

Si **no tenés permiso**, el asistente te lo dice. Si **sí**, te guía con mensajes y botones.

---

## Cómo probar cualquier flujo

1. **Vos** escribís algo parecido a los ejemplos (no hace falta la frase exacta).
2. **El sistema** empieza el asistente paso a paso.
3. **Vos** respondés cada pregunta.
4. **El sistema** confirma al final (“turno creado”, “cama asignada”) o explica qué falta.

---

## Urgencias y guardia

| Qué querés | Ejemplos | Qué deberías ver |
|------------|----------|------------------|
| Ver tablero | “tablero de guardia”, “ver urgencias” | Cola de guardia |
| Hacer triage | “triage”, “clasificar paciente” | Preguntas de nivel y motivo |

Detalle: [03-urgencias-guardia.md](./03-urgencias-guardia.md).

---

## Internación

| Qué querés | Ejemplos | Qué deberías ver |
|------------|----------|------------------|
| Mapa de camas | “mapa de camas” | Mapa con camas |
| Ingresar | “internar”, “ingreso de internación” | Asistente pide paciente y cama |
| Cambiar cama | “cambio de cama” | Lista de camas libres |
| Alta | “alta de internación”, “epicrisis” | Pasos del alta |

Detalle: [04-internacion.md](./04-internacion.md).

---

## Turnos — paciente (app)

Detalle paso a paso: [02-turnos-agenda.md](./02-turnos-agenda.md).

- **Sacar turno** — `atencion.necesito-atencion`, `turnos.crear-como-paciente`: Atajo **Atención**; “quiero un turno”, “sacar turno”
- **Cancelar** — `turnos.cancelar-como-paciente-flow`: Atajo **Turnos**; “cancelar turno”
- **Cambiar** — `turnos.modificar-como-paciente-flow`: “reprogramar”, “cambiar mi turno”
- **Confirmar asistencia** — `turnos.confirmar-asistencia-flow`: “confirmo que voy”
- **Política de cancelación** — `turnos.consultar-politica-autogestion-flow`: “cuánto antes puedo cancelar”
- **Ministerio de salud** — `paciente-contexto.recurso-provincial-como-paciente-flow`: “ministerio de salud de mi provincia”

Requisito: sector y provincia configurados ([08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md)).

---

## Turnos — personal (web)

Detalle paso a paso: [02-turnos-agenda.md](./02-turnos-agenda.md).

- **Turno para paciente** — `turnos.crear-para-paciente-flow`: “dar turno”, “turno para el paciente”
- **Cancelar de otro** — `turnos.cancelar-para-paciente-flow`: “cancelar turno del paciente”
- **Sobreturno** — `turnos.crear-sobreturno-flow`: “sobreturno”
- **Agenda del día** — `turnos.ver-agenda-dia-profesional-flow`: “agenda del día”, “turnos de hoy”
- **Indicadores / ocupación** — `turnos.indicadores-agenda-flow`, `turnos.consultar-ocupacion-dia-flow`: Atajo **Profesional, agenda…**; “cómo está la agenda”
- **No vino** — `turnos.no-se-presento-flow`: “no vino”, “ausente”
- **Conflictos de agenda** — `profesional-agenda.resolver-conflictos-flow`: “conflictos agenda”

Panel **Pacientes** (ambulatorio): turnos del día sin pasar por el asistente — ver [02-turnos-agenda.md](./02-turnos-agenda.md).

---

## Laboratorio y recetas (paciente)

| Qué querés | Ejemplos | Qué deberías ver |
|------------|----------|------------------|
| Análisis | “mis laboratorios”, “resultados de sangre” | Lista de estudios |
| Recetas | “mis recetas” | Lista de recetas |

Detalle: [05-laboratorio-receta-planes.md](./05-laboratorio-receta-planes.md).

---

## Si no te entiende

1. Probá una frase más concreta (“cancelar turno del martes”, “mapa de camas”).
2. El sistema puede mostrar **botones** con acciones para elegir con un clic.
3. Si tu rol no puede hacer eso, **te dice** que no tenés permiso.
