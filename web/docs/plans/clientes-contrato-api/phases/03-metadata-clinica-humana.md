# Fase 03 — Metadata clínica humana

## Objetivo

Que secciones clínicas, tipos de episodio, triage y acciones permitidas lleguen del backend, de modo que agregar una especialidad no obligue a tocar clientes.

## Tareas

- Generalizar `EncounterDefinitionSpecialtyRegistry`: cada especialidad humana declara secciones, campos, unidades, orden y validaciones por metadata. Odontología y oftalmología son la primera prueba.
- API que entrega secciones clínicas con campos (clave, etiqueta, valor, unidad, formato).
- Tipos de episodio (turno, cirugía, guardia, internación) como datos con código, etiqueta y capacidades.
- Triage: niveles, etiquetas e intents desde el backend; se elimina la paleta fija del cliente.
- Acciones permitidas por episodio y por rol desde el backend.
- Ámbito de atención del profesional (humano o veterinario) informado en el inicio de sesión.
- Reemplazar los modelos fijos de signos vitales, evolución de internación y agenda de cirugía por el modelo genérico de secciones.

## Criterio de salida

Una especialidad nueva se agrega con metadata y un servicio, sin cambios en móvil ni web. El timeline del piloto se pinta solo con secciones genéricas.
