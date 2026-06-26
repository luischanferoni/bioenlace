# Turnos y agenda

[← Índice](./README.md) · Más detalle: [turnos.md](../producto/turnos.md) · Frases del asistente: [07-asistente.md](./07-asistente.md)

Antes conviene tener [sesión operativa](./00-transversal.md#elegir-efector-servicio-y-tipo-de-atención) en **ambulatorio** (personal web).

**Cómo leer cada flujo**

| Columna | Significado |
|---------|-------------|
| **Dónde** | Web (menú), app paciente o asistente (web o app) |
| **Cómo** | Menú, atajo visible, o texto a escribir en el chat |
| **Resultado esperado** | Qué debería mostrarse en pantalla |

---

## Ver la agenda del consultorio (personal)

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **Web** | Menú **Turnos** | Pantalla de agenda del efector (servicios, feriados; derivaciones pendientes si hay paciente seleccionado) |
| 2 | **Web** | Elegir día y profesional | Lista de turnos de ese día |
| — | **Asistente (web)** | Escribir *«agenda de hoy»* | Lista de turnos del día (alternativa al menú) |

---

## Lista de espera del día

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **Web** | Menú **Turnos** → **Lista de espera**, o desde la agenda | Quién tiene turno hoy, con datos del paciente |
| 2 | **Web** | Cambiar fecha (ayer / mañana) | La lista se actualiza |

---

## Sacar un turno (secretaría / personal)

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **Web** | Menú **Turnos** → alta / calendario (según pantalla del efector) | Formulario o grilla de cupos |
| 2 | **Web** | Elegir paciente, profesional, fecha y horario libre | Turno creado; aparece en agenda y lista de espera |
| — | **Asistente (web)** | Escribir *«dar turno»*, *«turno para el paciente»* | Flujo paso a paso hasta confirmar |
| Error | — | Mismo horario ya ocupado | Mensaje de rechazo; no se duplica el turno |

---

## Sacar turno (paciente)

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **App** | Pestaña **Asistente** → atajo **Atención**, o escribir *«quiero un turno»*, *«sacar turno»* | Flujo de servicio, centro, profesional y horario |
| 2 | **App** | Confirmar horario | Turno en **Inicio** → próximos turnos |
| Sin cupo | **App / asistente** | Seguir el flujo | Otros horarios o mensaje claro |
| Filtro | **App** | Con sector y provincia en Configuración | Solo centros y profesionales que correspondan ([08-registro-contexto-paciente.md](./08-registro-contexto-paciente.md)) |

---

## Cancelar turno (paciente)

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **App** | Asistente → atajo **Turnos** (cancelar), o escribir *«cancelar turno»* | Flujo de cancelación |
| 2 | — | Con anticipación suficiente (regla del efector) | Turno cancelado; cupo liberado |
| Error | — | Cancelación muy tarde | Rechazo con motivo |

---

## Cambiar fecha u hora del turno

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| Paciente | **App** | Asistente → atajo **Turnos** (modificar), o *«reprogramar»*, *«cambiar mi turno»* | Nuevos cupos y confirmación |
| Personal | **Web** o **asistente** | Reprogramar desde agenda o *«reprogramar turno del paciente»* | Turno actualizado; cupo anterior libre |

---

## Ver derivaciones pendientes

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **Web** | Menú **Referencias** (o listado equivalente del efector) | Derivaciones de consulta sin turno asignado |
| 2 | **Web** | Elegir una y programar turno | Derivación marcada como con turno |

---

## Sobreturno (personal)

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **Web** o **asistente** | Alta de turno fuera de cupo, o escribir *«sobreturno»* | Turno guardado como sobreturno en la agenda del día |

---

## Marcar «no se presentó»

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **Web** o **asistente** | Marcar inasistencia en agenda, o escribir *«no vino»*, *«ausente»* | Estado del turno actualizado (ausencia) |

---

## Calendario del profesional

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **Web** | Menú **Turnos** → vista calendario (día o semana) | Turnos, bloqueos y feriados visibles |

---

## Indicadores de agenda (ocupación, no-show, etc.)

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **Asistente (web)** | Atajo **Profesional, agenda…** → indicadores, o escribir *«cómo está la agenda»*, *«cómo va la agenda hoy»* | Resumen numérico o pantalla de indicadores |

---

## Conflicto de agenda o cancelación masiva (coordinación)

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **Web** o **asistente** | Flujo de resolver conflictos o cancelar varios turnos | Pregunta qué turnos afectar y pide confirmación |
| 2 | — | Confirmar | Cambios aplicados; pacientes pueden recibir aviso si está activo |

---

## Confirmar asistencia (paciente)

| Paso | Dónde | Cómo | Resultado esperado |
|------|-------|------|-------------------|
| 1 | **App** | Asistente → escribir *«confirmo que voy»* | Confirmación registrada para el personal |

---

## Aviso en el celular cuando cambia un turno

| Condición | Resultado esperado |
|-----------|-------------------|
| App con notificaciones permitidas; efector con envío activo | Push al reprogramar o cancelar un turno |
