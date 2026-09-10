# Plan — Árbol espejo por dominio

| Campo | Valor |
|-------|--------|
| Slug | `arbol-espejo-dominios` |
| Estado | Fases 1–4 implementadas (falta smoke QA) — Fases 5-6 pendientes |
| Dueño | Arquitectura / API / asistente |
| Objetivo | Carga cognitiva extrínseca: que la misma palabra de dominio se pueda seguir por el árbol en **todas** las capas, sin mapas intermedios |

## Índice

- [overview.md](./overview.md) — problema, alcance, fuera de alcance
- [design.md](./design.md) — invariante de forma, conjunto de dominios, qué muere
- [phases/01-controllers-api.md](./phases/01-controllers-api.md) — Fase 1: controllers API v1 por dominio
- [phases/02-models.md](./phases/02-models.md) — Fase 2: modelos por dominio
- [phases/03-metadata-views-tests.md](./phases/03-metadata-views-tests.md) — Fase 3: metadata, `views/json` y tests con la misma forma
- [phases/04-catalogos-derivados.md](./phases/04-catalogos-derivados.md) — Fase 4: catálogos con ids del dominio y texto en YAML
- [phases/05-areas-desde-intent.md](./phases/05-areas-desde-intent.md) — Fase 5: área = carpeta del intent
- [phases/06-invariantes-y-limpieza.md](./phases/06-invariantes-y-limpieza.md) — Fase 6: invariantes de forma y limpieza

## Punto de partida (medido)

| Capa | Estado hoy |
|------|------------|
| `common/components/` | `Domain/` + `Platform/` + `Ai/` + `Core/` al mismo nivel; hay un `Domain/Platform/` |
| `common/components/Domain/` | 7 dominios: `Clinical`, `Content`, `Integrations`, `Organization`, `Person`, `Scheduling`, `Terminology` |
| `common/models/` | 143 archivos planos + 15 carpetas de ejes mezclados (`Clinical`, `Scheduling`, … junto a `busquedas`, `forms`, `fullcalendar`, `rbac`, `search`, `sumar`) — **resuelto en Fase 2**: 0 planos, 10 carpetas, todas dominios |
| `frontend/modules/api/v1/controllers/` | 19 archivos bajo `clinical/`; **41 planos** (`TurnosController`, `ServiciosController`, `PersonaController`, …) — **resuelto en Fase 1**: 46 en `clinical/`, `scheduling/`, `organization/`, `person/`, `integrations/` y 13 transversales en la raíz |
| `frontend/modules/api/v1/views/json/` | 98 archivos en 6 carpetas: `clinical`, `scheduling`, `organization`, `persona`, `core`, `common` |
| `common/metadata/bioenlace/` | 11 carpetas mezclando dominio (`clinical`, `person`, `scheduling`, …) y plataforma (`assistant`, `agents`, `ai`, `permission`, `ui`) |
| `common/tests/unit/` | 19 carpetas de ejes mezclados (`clinical` junto a `assistant`, `ai`, `api`, `infra`, `costos`) |
| `UiJsonDomainMetadata::ENTITY_DOMAINS` | 24 entradas a mano: existe solo porque la entidad no lleva su dominio |

## Síntoma que originó el plan

Seguir la palabra `clinical` por el árbol funciona (`components/Domain/Clinical`, `controllers/clinical/`, `views/json/clinical/`, `tests/unit/clinical/`).

Seguir `turnos` no: su controller era plano, su UI JSON está bajo `scheduling/`, su URL es `/api/v1/turnos/…`. El puente entre esas tres formas es un mapa PHP escrito a mano.

Después de la Fase 1 el controller ya espeja (`controllers/scheduling/TurnosController`). Queda el resto: modelos (Fase 2), `views/json` y metadata (Fase 3), y recién ahí el mapa a mano puede morir.

## Relacionado

- Reglas: [`capas-y-metadata-sin-hardcode.mdc`](../../../../.cursor/rules/capas-y-metadata-sin-hardcode.mdc), [`source-of-truth-catalogos.mdc`](../../../../.cursor/rules/source-of-truth-catalogos.mdc), [`asistente-prompts-solo-humano.mdc`](../../../../.cursor/rules/asistente-prompts-solo-humano.mdc)
- Al cerrar: volcar la invariante de forma a `arquitectura/` y borrar esta carpeta
