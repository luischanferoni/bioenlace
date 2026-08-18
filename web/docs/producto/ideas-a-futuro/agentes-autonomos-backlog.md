# Ideas a futuro — agentes autónomos

Lo que **ya corre** (trigger, política, efecto, flag): [agentes-autonomos.md](../agentes-autonomos.md).

Este archivo solo guarda **lo que aún no está en producto**: agentes IA diferidos, procesos que no son agente, y el corte D4 (nunca autónomo).

Definición corta: un **agente** decide y actúa (reglas + datos del HIS); un **agente IA** usa el modelo en el paso que cambia el desenlace. Recordatorio fijo, FAQ o grilla donde elige el paciente **no** son agente.

---

## Pendiente (agentes IA)

| ID | Idea | Ejemplo |
|----|------|---------|
| **C03** | Clasificar la puerta de entrada desde texto libre (banda + flujo) | *«Me duele el pecho»* → urgencia, no turno ambulatorio |
| **D02** | Borrador de resumen al paciente al cerrar el encounter (humano publica) | Nota del médico → texto claro para la app |
| — | Redacción IA de pushes **después** de una regla (LOINC, touchpoint) | La rama ya la fijó el YAML; el modelo solo escribe el texto |

Hoy el clasificador del chat y el resumen publicado cubren una parte; C03/D02 serían **compromiso** (banda persistida / borrador como acto de agente), no solo charla.

---

## Ideas que no son agente (proceso o asistencia)

Útiles; no mezclarlas con el catálogo de `agent_run`.

| ID | Idea | Por qué no es agente | Ver |
|----|------|----------------------|-----|
| B04 | Aviso de refill crónico (T−7) | Recordatorio + link; no renueva solo | [receta-electronica.md](../receta-electronica.md) |
| B05 | Educación post-consulta en el tiempo | Secuencia de contenido, no rama | [seguimiento-post-consulta-educacion.md](./seguimiento-post-consulta-educacion.md) |
| C01 | Completar MPI / teléfono antes de video | Formulario obligatorio | [registro-paciente.md](../registro-paciente.md) |
| C02 | Briefing pre-turno al staff | Resume; el médico decide | [recorrido-pre-post-consulta.md](../recorrido-pre-post-consulta.md) |
| D01 | Huecos en la nota de captura | Checklist; completa el profesional | [captura-clinica.md](../captura-clinica.md) |
| G01 | Listas poblacionales (diabéticos sin HbA1c) | Query; la campaña la arma dirección | [asistencia-cohortes.md](../asistencia-cohortes.md) |
| G02 | Informe narrativo mensual | KPI agregados, sin acto sobre un paciente | — |
| H02 | FAQ del efector para staff | Cita metadata; no decide clínica | [asistente-y-chat.md](../asistente-y-chat.md) |
| I01 | Onboarding post-Didit | Calendario fijo de mensajes | [sesion-paciente-app.md](../sesion-paciente-app.md) |
| I02 | «Qué canal usar» | Recomienda; el paciente elige | [solicitar-atencion.md](../solicitar-atencion.md) |

Preguntas dinámicas según HCE y zona: [contexto-asistencia-dinamica.md](./contexto-asistencia-dinamica.md).

---

## Fuera de alcance (D4)

Diagnóstico o prescripción sin médico, derivar a guardia solo porque «la IA sintió urgencia», cambiar un tratamiento activo, decidir cobertura de obra social, comunicar mal pronóstico. Las alarmas de reserva usan **bandas del catálogo**, no un modelo suelto.

---

## Relacionado

- [agentes-autonomos.md](../agentes-autonomos.md) · [catalogo-usos-ia.md](../catalogo-usos-ia.md)
- [seguimiento-post-consulta-educacion.md](./seguimiento-post-consulta-educacion.md) · [contexto-asistencia-dinamica.md](./contexto-asistencia-dinamica.md)
