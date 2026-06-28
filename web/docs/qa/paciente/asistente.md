# Asistente (app paciente)

[← Paciente](./README.md)

El asistente entiende **frases en castellano** o elegís un **Atajo** visible. Te guía paso a paso y al final hace lo que pediste o te muestra una pantalla.

Si **no tenés permiso**, te lo dice. Si **sí**, te guía con mensajes y botones.

---

## Cómo probar cualquier flujo

1. **Vos** escribís algo parecido a los ejemplos (no hace falta la frase exacta).
2. **El sistema** empieza el asistente paso a paso.
3. **Vos** respondés cada pregunta.
4. **El sistema** confirma al final o explica qué falta.

---

## Turnos

Detalle: [turnos.md](./turnos.md).

| Qué querés | Intent / atajo | Ejemplos |
|------------|----------------|----------|
| Sacar turno | `atencion.necesito-atencion`, `turnos.crear-como-paciente` | Atajo **Atención**; *«quiero un turno»* |
| Cancelar | `turnos.cancelar-como-paciente-flow` | Atajo **Turnos**; *«cancelar turno»* |
| Cambiar | `turnos.modificar-como-paciente-flow` | *«reprogramar»*, *«cambiar mi turno»* |
| Confirmar asistencia | `turnos.confirmar-asistencia-flow` | *«confirmo que voy»* |
| Política de cancelación | `turnos.consultar-politica-autogestion-flow` | *«cuánto antes puedo cancelar»* |
| Ministerio de salud | `paciente-contexto.recurso-provincial-como-paciente-flow` | *«ministerio de salud de mi provincia»* |

Requisito de contexto para turnos: [contexto-registro.md](./contexto-registro.md).

---

## Laboratorio y recetas

| Qué querés | Ejemplos | Qué deberías ver |
|------------|----------|------------------|
| Análisis | *«mis laboratorios»*, *«resultados de sangre»* | Lista de estudios |
| Recetas | *«mis recetas»* | Lista de recetas |
| Mis atenciones | Atajo **Mis atenciones** | Listado de visitas |

Detalle: [laboratorio-receta-planes.md](./laboratorio-receta-planes.md).

---

## Si no te entiende

1. Probá una frase más concreta (*«cancelar turno del martes»*).
2. El sistema puede mostrar **botones** con acciones para elegir con un clic.
3. Si tu rol no puede hacer eso, **te dice** que no tenés permiso.
