# Documentación Bioenlace (web)

Mapa de documentación por **dominio de negocio**. Cada dominio sigue la misma forma:

| Archivo / carpeta | Contenido |
|-------------------|-----------|
| `README.md` | Índice del dominio |
| `overview.md` | Qué es, objetivo, actores, alcance |
| `design.md` | Por qué está armado así; alternativas consideradas |
| `flows/` | Cómo funciona (diagramas, pasos, anclas con nombres de métodos/rutas) |
| `decisions/` (global) | Decisiones cerradas con impacto en varios dominios |

Sin fragmentos de código en los cuerpos: solo nombres de métodos, rutas y servicios como anclas al repositorio.

## Dominios

| Dominio | Descripción |
|---------|-------------|
| [turnos](./Turnos/README.md) | Agenda, reserva, cancelación, autogestión paciente |
| [asistente](./asistente/README.md) | Chat, intents YAML, UI JSON embebible |
| [plans](./plans/README.md) | Planes largos en ejecución |
| [dominio](./dominio/README.md) | Conceptos transversales (PES, relaciones AR) |
| [costos](./costos/README.md) | Costos IA e infraestructura estimada |
| [producto](./producto/README.md) | Apps paciente/médico, registro, capacidades |
| [captura-clinica](./captura-clinica/README.md) | Audio/texto, niveles de carga, corrección, resumen IA |
| [plataforma](./plataforma/README.md) | GCP, anotaciones API, plan interno |
| [legacy](./legacy/README.md) | Análisis HIS histórico (archive) |

## Convenciones (Cursor rules)

Al editar documentación, aplicar las rules `.cursor/rules/documentacion-*.mdc` (estructura, `flows/`, estilo).

Migración histórica: [restructure-phases/](./restructure-phases/README.md) (fases 01–10 completadas).

## Decisiones globales

Ver [decisions/README.md](./decisions/README.md).
