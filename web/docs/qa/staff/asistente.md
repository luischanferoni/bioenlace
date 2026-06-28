# Asistente (staff web)

[← Staff](./README.md)

El asistente entiende **frases en castellano** o elegís un **Atajo** visible. Te guía paso a paso.

**Web (personal):** abrís el asistente y escribís.  
Frases paciente (app): [paciente/asistente.md](../paciente/asistente.md).

---

## Cómo probar cualquier flujo

1. **Vos** escribís algo parecido a los ejemplos.
2. **El sistema** empieza el asistente paso a paso.
3. **Vos** respondés cada pregunta.
4. **El sistema** confirma al final o explica qué falta.

---

## Urgencias y guardia

| Qué querés | Ejemplos | Qué deberías ver |
|------------|----------|------------------|
| Ver tablero | *«tablero de guardia»*, *«ver urgencias»* | Cola de guardia |
| Hacer triage | *«triage»*, *«clasificar paciente»* | Preguntas de nivel y motivo |

Detalle: [urgencias-guardia.md](./urgencias-guardia.md).

---

## Internación

| Qué querés | Ejemplos | Qué deberías ver |
|------------|----------|------------------|
| Mapa de camas | *«mapa de camas»* | Mapa con camas |
| Ingresar | *«internar»*, *«ingreso de internación»* | Asistente pide paciente y cama |
| Cambiar cama | *«cambio de cama»* | Lista de camas libres |
| Alta | *«alta de internación»*, *«epicrisis»* | Pasos del alta |

Detalle: [internacion.md](./internacion.md).

---

## Turnos — personal

Detalle: [turnos-agenda.md](./turnos-agenda.md).

- **Turno para paciente** — `turnos.crear-para-paciente-flow`
- **Cancelar de otro** — `turnos.cancelar-para-paciente-flow`
- **Sobreturno** — `turnos.crear-sobreturno-flow`
- **Agenda del día** — `turnos.ver-agenda-dia-profesional-flow`
- **Indicadores / ocupación** — `turnos.indicadores-agenda-flow`, `turnos.consultar-ocupacion-dia-flow`
- **No vino** — `turnos.no-se-presento-flow`
- **Conflictos de agenda** — `profesional-agenda.resolver-conflictos-flow`
- **Condición laboral / agenda PES** — atajos **Profesional, agenda y condición laboral**

---

## Profesionales y métricas (staff)

Atajo **Profesional, agenda y condición laboral** → **Para el personal**:

- `profesionales.conteo-efector`
- `profesionales.listado-efector`
- `profesionales.distribucion-servicio-efector`
- `condicion-laboral.editar-staff`, `profesional-agenda.configurar-staff`, etc.

---

## Si no te entiende

1. Probá una frase más concreta (*«cancelar turno del paciente»*, *«mapa de camas»*).
2. El sistema puede mostrar **botones** con acciones.
3. Si tu rol no puede hacer eso, **te dice** que no tenés permiso.
