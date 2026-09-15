# Fase 07 — Limpieza, docs estables y cierre

## Objetivo

Eliminar residuos, documentar la decisión y borrar el plan.

## Tareas

1. Borrar `common/metadata/bioenlace/` si ya no tiene YAML canónicos (o dejar solo README que redirija a components — preferible borrar y actualizar `productMetadataDir`).
2. Actualizar:
   - `web/docs/arquitectura/common-components.md`
   - `web/docs/arquitectura/arbol-espejo-dominios.md`
   - `web/docs/arquitectura/metadata-yaml-uso.md`
   - `web/docs/arquitectura/runtime-datos-y-metadata.md` (si aplica)
   - `common/components/Domain/README.md`, `Platform/README.md`, `common/metadata` si queda algo
   - reglas Cursor `common-components-organizacion`, `metadata-yaml-uso`, `capas-y-metadata-sin-hardcode` (paths)
3. Publicar ADR en `web/docs/decisions/` (BC + capas + qué es YAML).
4. Narrativa breve en `producto/` solo si cambia comportamiento visible (probable: no); si no, solo arquitectura/decisions.
5. Quitar dual-root / fallbacks de paths.
6. Entrada en `plans/README.md` → archivados; **borrar** `plans/ddd-capas-y-metadata/`.

## Criterio de done

- Un solo árbol de discovery.
- Docs y reglas alineadas.
- Plan eliminado.