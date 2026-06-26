# Asistente y chat

[← Índice](./README.md)

El asistente entiende **frases en castellano** (o elegís una acción del menú). Te hace preguntas paso a paso y al final hace lo que pediste o te muestra una pantalla.

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

| Qué querés | Ejemplos | Qué deberías ver |
|------------|----------|------------------|
| Sacar turno | “quiero un turno”, “sacar turno” | Cupos y confirmación |
| Cancelar | “cancelar turno” | Verifica anticipación |
| Cambiar | “reprogramar”, “cambiar mi turno” | Nuevos horarios |
| Confirmar asistencia | “confirmo que voy” | Confirmación registrada |
| Política de cancelación | “cuánto antes puedo cancelar” | Texto explicativo |
| Ministerio de salud | “ministerio de salud de mi provincia” | Datos de tu provincia |

Requisito: sector y provincia configurados ([08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md)).

---

## Turnos — personal

| Qué querés | Ejemplos | Qué deberías ver |
|------------|----------|------------------|
| Turno para paciente | “dar turno”, “turno para el paciente” | Busca paciente y horario |
| Cancelar de otro | “cancelar turno del paciente” | Cancelación con permiso staff |
| Sobreturno | “sobreturno” | Turno fuera de cupo |
| Agenda del día | “agenda de hoy” | Lista de turnos |
| Ocupación | “cómo está la agenda” | Resumen numérico |
| No vino | “no vino”, “ausente” | Marca inasistencia |

Detalle: [02-turnos-agenda.md](./02-turnos-agenda.md).

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
