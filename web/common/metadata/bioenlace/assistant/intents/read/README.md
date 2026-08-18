# Intents — lectura (`read/`)

Motor de **consulta genérica**: un `intent_id` (RBAC) + parámetros hidratados + query DataAccess (`info` / `listar`). No es un cajón de pantallas.

## Qué va aquí (raíz)

- Intents con `metric_id` y `open_ui` hacia `data-access.info` o `data-access.listar`.
- Stubs `data-access.info` / `data-access.listar`: transporte HTTP, **no** catálogo NL (ADR `autorizacion-solo-por-intents`).

El permiso assignable es el **intent concreto** (`profesionales.conteo-efector`, …), no el canal genérico.

## Qué no va aquí

Pantallas, wizards, tableros o listados de producto que no son una métrica parametrizable → [`flows/`](./flows/README.md).

## Cómo agregar una lectura

1. ¿Es “cuántos / listar / último X” con filtros hidratables (efector, oferta, alcance, límite)? → métrica en `data-access-config` + YAML en esta carpeta.
2. Si ya existe la métrica, **no** crear otro intent one-off: reutilizar `metric_id` y params de draft.
3. Si hace falta wizard o UI de producto → `read/flows/`.

Producto: `web/docs/producto/asistente-y-chat.md`. Arquitectura: `web/docs/arquitectura/asistente-lectura-data-access.md`.
