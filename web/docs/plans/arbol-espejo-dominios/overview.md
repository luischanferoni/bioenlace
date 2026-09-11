# Overview

## Problema

La palabra de dominio está **repetida en muchas capas pero con forma distinta en cada una**, y en algunas está aplicada a medias. El costo no es intrínseco al negocio: es carga extrínseca, la que aparece cuando para ubicar algo hay que recordar convenciones en lugar de leer el árbol.

Tres manifestaciones concretas:

1. **Eje inconsistente por capa.** `controllers/clinical/` existe, pero `TurnosController` está plano. `models/Clinical/` existe, pero hay 143 modelos planos. `metadata/bioenlace/` pone `clinical/` (dominio) al lado de `assistant/` (plataforma).
2. **Mapas que sustituían la forma faltante.** ~~`UiJsonDomainMetadata::ENTITY_DOMAINS`~~ — resuelto en Fase 3: el dominio se lee del árbol (`UiJsonDomainIndex` / `IntentSchemaPaths::domainForIntentId`).
3. **Catálogos desconectados.** ~~`preprocess-extraction-categories.yaml`~~ — resuelto en Fase 4. ~~`context-his-areas.yaml`~~ — resuelto en Fase 5: área = carpeta del intent (o solo-contexto).

## Objetivo

Que valga una sola frase, sin excepciones:

> En cada capa, el segmento que sigue a la capa es el **dominio**; el siguiente es la **entidad**.

Y que los catálogos dejen de ser listas huérfanas: los **ids** los aporta el dominio, el **texto** vive en YAML, y un test cierra el círculo.

## Alcance

- Controllers API v1, modelos, `views/json`, metadata y tests bajo la misma forma.
- `platform` ocupa la misma posición que un dominio en todas las capas.
- Catálogos del preprocess derivados de lo que declaran los dominios.
- Invariantes en test que fallan cuando el espejo se rompe.

## Fuera de alcance

- **Cambiar URLs públicas.** El árbol se arregla sin tocar `/api/v1/...`; poner el dominio en la URL es una decisión aparte (implicaría release en lockstep con Flutter).
- **Reseed de permisos RBAC.** No hace falta: el filtro chequea también la ruta derivada del path HTTP (ver `design.md`).
- **Mover `domains/` y `platform/` fuera de `common/`.** La ubicación no baja la carga; la forma sí. Se quedan en `common/components/`.
- **Migraciones y esquema SQL.** El nombre de tabla no se reorganiza por dominio.
- **Reescribir prompts.** Los YAML de prompt los edita solo el humano; el plan entrega sugerencias en chat.
- **Vertical slices** (un solo directorio por dominio con todas sus capas adentro). Descartado: churn alto sin reducir palabras.
