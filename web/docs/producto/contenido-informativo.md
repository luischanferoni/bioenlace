# Contenido informativo (artículos editoriales)

## De qué se trata

Cuando un paciente pregunta **cómo funciona algo del producto** («¿qué es la representación?», «¿cómo saco un turno?», «¿qué es la teleconsulta?»), el asistente responde desde un **artículo editorial** (`info_content_article`), con la **guía** (2ª IA) **anclada a esa fuente** (no inventa pasos) y, si corresponde, botones CTA a intents con RBAC.

## Jerarquía de alcance

```
efector → provincia → producto (global)
```

Overrides provincia/efector cambian el **cuerpo**; los CTA (`intent_ids`) se declaran en el artículo de alcance **producto** del mismo topic.

## Campos relevantes

| Campo | Descripción |
|-------|-------------|
| `topic` | Clave temática (`representacion`, `teleconsulta`, `turnos`, `pre_consulta`, …) |
| `title` / `body` | Título y contenido (markdown) |
| `scope` | `producto` \| `provincia` \| `efector` |
| `keywords` | Matcheo tolerante (fold + stem: representar ↔ representación); misma idea que tags de discovery |
| `intent_ids` | Lista CSV de intents CTA (solo producto) |
| `priority` / `activo` | Orden y publicación |

## Administración

CRUD en `/admin/info-content-article`. Integridad: los `intent_ids` deben existir en el catálogo de intents.

## Integración con el asistente

1. Preprocess: hint `guide` + tags (p. ej. `representacion`).
2. `DiscoveryIndex` rankea artículos por tags/keywords y texto; el cuerpo se adjunta al prompt Guide.
3. Si declara intents y el usuario no puede ninguno → el artículo no se sirve.
4. Respuesta: 2ª IA con fuente inyectada; si la IA falla → texto de respaldo / CTAs.
5. Botones a los intents permitidos.

No hay envelope de artículo “sin guía”: el camino DirectMatch se retiró.

## Artículos de producto (seed)

| Topic | CTA típicos |
|-------|-------------|
| `representacion` | vincular menor / designar representante |
| `teleconsulta` | crear turno |
| `turnos` | crear turno / solicitar atención |
| `pre_consulta` | asistencia pre-consulta (concepto; no preguntas del pack) |
| `que_es_bioenlace` | — |

## Relación

| Documento | Relación |
|-----------|----------|
| [asistente-y-chat.md](./asistente-y-chat.md) | Canales y hilos del chat |
| [representacion-paciente.md](./representacion-paciente.md) | Producto de representación |
| [qa/paciente/asistente-consultas.md](../qa/paciente/asistente-consultas.md) | Casos de prueba |
| [asistente-discovery-unificado.md](../decisions/asistente-discovery-unificado.md) | ADR discovery |
