# Contenido informativo (artículos editoriales)

## De qué se trata

Cuando un paciente pregunta **cómo funciona algo del sistema** ("¿qué es la representación?", "¿cómo saco un turno?", "¿qué es la teleconsulta?"), el asistente responde con un **artículo editorial de la base de datos**, no con charla improvisada por IA.

Cada artículo tiene un **topic** (clave temática), **keywords** para matcheo, y un **alcance jerárquico** que permite personalizar el contenido por centro o provincia.

## Jerarquía de alcance (fallback)

```
efector → provincia → producto (global)
```

Si el paciente está en el contexto de un efector que tiene un artículo sobre el topic, ve ese. Si no, cae al de la provincia. Si tampoco, al global (producto).

## Tabla `info_content_article`

| Campo | Descripción |
|-------|-------------|
| `topic` | Clave temática (ej. `representacion`, `teleconsulta`, `turnos`) |
| `title` | Título del artículo |
| `body` | Contenido en texto plano o markdown |
| `scope` | `producto` (global), `provincia`, `efector` |
| `id_provincia` | Solo si scope = provincia |
| `id_efector` | Solo si scope = efector |
| `keywords` | Palabras clave separadas por coma para matcheo contra el mensaje del usuario |
| `priority` | Mayor = más prioritario dentro del mismo scope |
| `activo` | Si está activo |

## Administración

CRUD en `/admin/info-content-article`. Cualquier usuario admin puede crear, editar y eliminar artículos.

Para **personalizar un artículo por centro**: crear un artículo con el mismo `topic`, scope `efector` y seleccionar el centro. Ese artículo tiene prioridad sobre el global.

## Integración con el asistente

El contenido informativo se resuelve en dos canales:

1. **InformationalChannel**: cuando el preprocess clasifica como `informational` (consultas tipo "¿qué es X?", "¿cómo funciona X?").
2. **ConversationalChannel**: antes de llamar a la IA conversacional, busca si hay un artículo relevante.

En ambos casos, si hay un artículo que matchea por keywords, se devuelve directamente como `AssistantEnvelope::message()`. Si no hay match, sigue el flujo normal (IA conversacional o menú de capacidades).

## Matcheo

El `InfoContentResolverService` usa un scoring simple:
- +10 si el topic aparece en el texto del usuario
- +8 si el título aparece en el texto
- +5 por cada keyword que aparece en el texto

El artículo con mayor score se resuelve; luego se aplica el fallback jerárquico para obtener la versión más específica del mismo topic.

## Artículos iniciales (seed)

| Topic | Título | Keywords |
|-------|--------|----------|
| `representacion` | Representación y tutela de menores | representacion, tutela, menor, hijo, vincular, representante, delegar |
| `teleconsulta` | ¿Qué es la teleconsulta? | teleconsulta, videollamada, remoto, virtual, consulta online |
| `turnos` | ¿Cómo saco un turno? | turno, cita, sacar turno, cancelar turno, reprogramar |
| `que_es_bioenlace` | ¿Qué es Bioenlace? | que es bioenlace, como funciona, para que sirve, ayuda |

## Relación con otros docs

| Documento | Relación |
|-----------|----------|
| [asistente-y-chat.md](./asistente-y-chat.md) | Cómo conversa Bioenlace (este módulo es un canal más) |
| [representacion-paciente.md](./representacion-paciente.md) | Producto de representación (el artículo explica, no reemplaza) |
| [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md) | Reglas técnicas de elegibilidad (el artículo explica al paciente) |
| [asistente-motores.md](../arquitectura/asistente-motores.md) | Arquitectura de motores del asistente |
| [qa/paciente/asistente-consultas.md](../qa/paciente/asistente-consultas.md) | Catálogo de consultas para testing |
