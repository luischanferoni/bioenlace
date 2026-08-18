# Asistente (app paciente)

[← Paciente](./README.md)

El asistente entiende **frases en castellano** o un **Atajo** visible. Te guía paso a paso y al final hace lo que pediste o abre una pantalla.

Si **no tenés permiso**, te lo dice.

**Catálogo de consultas** (tipos, ejemplo, cobertura y qué no confundir): [asistente-consultas.md](./asistente-consultas.md).  
WhatsApp (mismo asistente, solo si el paciente escribe primero): [asistente-whatsapp.md](./asistente-whatsapp.md).

**Resumen de historia en el asistente:** en Configuración hay un interruptor (encendido por defecto). Apagado, el chat **sigue** respondiendo y ofreciendo botones; no usa alergias/condiciones/medicación del expediente. Motivos pre-turno y lo que ve el médico **no** cambian. Detalle: [ia-datos-y-privacidad.md](../../producto/ia-datos-y-privacidad.md).

---

## Cómo probar

1. **Vos** escribís algo parecido a los ejemplos (no hace falta la frase exacta).
2. **El sistema** clasifica y abre el flujo o una charla con botón.
3. **Vos** respondés cada pregunta.
4. **El sistema** confirma o explica qué falta.

Requisito de contexto para turnos: [contexto-registro.md](./contexto-registro.md). Checklist: [checklist.md](./checklist.md) (AST).

---

## Smoke mínimo

Con contexto de provincia/sector OK:

| Frase | Debería abrir |
|-------|----------------|
| *«quiero un turno»* | Reserva (`turnos.crear-como-paciente`) |
| *«me duele la cabeza»* | Charla + **Solicitar Atención** (no agenda pura) |
| *«cancelar turno»* | Cancelación |
| *«mis análisis»* | Laboratorio |
| *«mis recetas»* | Recetas |
| *«mis atenciones»* | Historial de visitas |

Si no entiende: frase más concreta (*«cancelar turno del martes»*), o elegí un botón. Pasos de reserva: [turnos.md](./turnos.md). Lab/recetas: [laboratorio-receta-planes.md](./laboratorio-receta-planes.md).
