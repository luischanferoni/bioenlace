# Fase 03 — Retirar puertas (direct-doors / aspectos)

## Objetivo

Eliminar `direct-doors.yaml` y el uso de aspectos como puerta NL. Cobrir casos actuales con intent rico o artículo.

## Migraciones de contenido

| Hoy en direct-doors | Destino |
|---------------------|---------|
| `llegar-tarde-*` (aspect) | Intent Domain (tags + qué contexto cargar) **o** artículo BD |
| `articulo-representacion` | Solo artículo BD (keywords/tags) |
| `fuera-his-servicios-inexistentes` | Solo hint `fuera_his` (sin lista de frases en Platform) |

## Criterio de hecho

- No hay referencias a `direct-doors` / `SmartCatalogRegistry` en el hot path de routing.
- QA “llego tarde” / representación / fuera HIS siguen pasando por el modelo nuevo.
