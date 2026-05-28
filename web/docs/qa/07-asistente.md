# Asistente y chat

[← Índice](./README.md) · Cómo funciona por dentro: [asistente-motores.md](../arquitectura/asistente-motores.md) · Producto: [asistente-y-chat.md](../producto/asistente-y-chat.md)

El asistente entiende **frases en castellano** (o elegís una acción del menú). Te hace preguntas paso a paso y al final hace lo que pediste o te muestra una pantalla.

**En la web (personal):** entrás, abrís el asistente y escribís.  
**En la app (paciente):** igual, desde el chat.

Si **no tenés permiso** para algo, el asistente te lo dice y no avanza. Si **sí** tenés permiso, te guía con mensajes y botones.

---

## Cómo probar cualquier flujo del asistente

1. **Vos** escribís algo parecido a lo de abajo (no hace falta la frase exacta).
2. **El sistema** te reconoce la intención y empieza el asistente paso a paso.
3. **Vos** respondés cada pregunta (fecha, paciente, horario…).
4. **El sistema** al final confirma (“listo”, “turno creado”, “cama asignada”) o te explica qué falta.

---

## Urgencias y guardia

| Qué querés | Ejemplos de lo que podés decir | Qué hace el sistema |
|------------|-------------------------------|---------------------|
| Ver tablero | “tablero de guardia”, “ver urgencias” | Abre la cola de guardia del efector |
| Hacer triage | “triage”, “clasificar paciente en guardia” | Te pide nivel Manchester, motivo, etc. |

Detalle del circuito: [03-urgencias-guardia.md](./03-urgencias-guardia.md).

---

## Internación

| Qué querés | Ejemplos | Qué hace el sistema |
|------------|----------|---------------------|
| Mapa de camas | “mapa de camas”, “ver camas” | Muestra el mapa |
| Ingresar paciente | “ingreso de internación”, “internar” | Wizard: paciente, cama, datos de ingreso |
| Cambiar cama | “cambio de cama”, “mover de cama” | Elegís cama nueva y confirma |
| Alta | “alta de internación”, “epicrisis” | Guía el alta estructurada |

Detalle: [04-internacion.md](./04-internacion.md).

---

## Turnos — paciente (app)

| Qué querés | Ejemplos | Qué hace el sistema |
|------------|----------|---------------------|
| Sacar turno | “quiero un turno”, “sacar turno” | Cupos, profesional, confirmación |
| Cancelar | “cancelar turno” | Verifica anticipación y cancela |
| Cambiar turno | “cambiar mi turno”, “reprogramar” | Nuevos horarios |
| Confirmar que vas | “confirmo que voy” | Marca asistencia |
| Ver política | “cuánto antes puedo cancelar” | Te explica reglas del efector |

---

## Turnos — personal (secretaría / médico)

| Qué querés | Ejemplos | Qué hace el sistema |
|------------|----------|---------------------|
| Turno para un paciente | “turno para el paciente”, “dar turno” | Busca paciente, horario, confirma |
| Cancelar turno de otro | “cancelar turno del paciente” | Mismo con permisos staff |
| Sobreturno | “sobreturno” | Carga fuera de cupo |
| Agenda del día | “agenda de hoy”, “turnos del Dr. X” | Lista del profesional |
| Ocupación del día | “cómo está la agenda”, “ocupación” | Resumen numérico |
| No se presentó | “no vino”, “ausente” | Marca inasistencia |
| Conflicto de agenda | “resolver conflictos”, “cancelar varios turnos” | Flujo de coordinación |

Detalle: [02-turnos-agenda.md](./02-turnos-agenda.md).

---

## Agenda del profesional (alta / edición)

| Qué querés | Ejemplos | Qué hace el sistema |
|------------|----------|---------------------|
| Dar de alta profesional con agenda | “alta de profesional”, “crear agenda” | Datos, PES, bloques horarios |
| Editar agenda | “editar agenda”, “cambiar horarios” | Modifica bloques existentes |
| Resolver conflictos masivos | “conflictos de agenda” | Coordinación de cambios |

---

## Laboratorio y recetas (paciente)

| Qué querés | Ejemplos | Qué hace el sistema |
|------------|----------|---------------------|
| Ver análisis | “mis laboratorios”, “resultados de sangre” | Lista estudios |
| Ver recetas | “mis recetas”, “receta electrónica” | Lista recetas emitidas |

Detalle: [05-laboratorio-receta-planes.md](./05-laboratorio-receta-planes.md).

---

## Tratamientos y atenciones (paciente / staff)

| Qué querés | Quién | Qué hace el sistema |
|------------|-------|---------------------|
| Recordatorios del plan | Paciente | Qué tomar / hacer |
| Adherencia | Personal | Resumen de cumplimiento |
| Mis atenciones | Paciente | Historial de visitas |
| Última atención | Paciente | Resumen de la última consulta |

---

## Si no te entiende

1. **Vos** probás otra frase más concreta (“cancelar turno del martes”, “mapa de camas piso 2”).
2. **El sistema** puede ofrecerte acciones del catálogo en pantalla para elegir con un clic.
3. Si tu rol no puede hacer eso, **te dice** que no tenés permiso — no es un error de la frase.

Los flujos exactos viven en archivos YAML del asistente (equipo técnico); acá importa **qué pedís** y **qué debería pasar**, no el nombre interno de cada uno.
