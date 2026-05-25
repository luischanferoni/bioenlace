# Plan — Resumen de atención (paciente)

| Campo | Valor |
|-------|--------|
| Slug | `resumen-atencion-paciente` |
| Estado | Fase 1 en curso (API + cola + push) |
| Dueño | Equipo clínico / API / app paciente |

## Objetivo

Tras una atención **ambulatoria finalizada**, el paciente recibe una notificación y puede ver un **resumen en lenguaje claro** (texto corregido/enriquecido por IA), con **vínculos** a receta, pedidos de estudios y resultados cuando existan. El **expediente legal** completo queda solo para **staff** (cola async).

## Índice

- [overview.md](./overview.md)
- [design.md](./design.md)
- [phases/00-marco.md](./phases/00-marco.md)
- [phases/01-api-resumen-publicacion.md](./phases/01-api-resumen-publicacion.md)
- [phases/02-notificaciones-push.md](./phases/02-notificaciones-push.md)
- [phases/03-ui-paciente-asistente.md](./phases/03-ui-paciente-asistente.md)
- [phases/04-grafo-vinculos-artefactos.md](./phases/04-grafo-vinculos-artefactos.md)
- [phases/05-expediente-legal-staff.md](./phases/05-expediente-legal-staff.md)

## Relacionado

- Pipeline IA actual: `EncounterDocumentationService::analizar` / `guardar` → `encounter.note` = `texto_procesado`
- Patrones paciente: laboratorio y receta `*-como-paciente`
- Push turnos: `web/docs/Turnos/flows/notificaciones-push.md`
