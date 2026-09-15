# Fase 07 — Limpieza, docs estables y cierre

**Estado: parcial** (docs principales de Integrations/Domain ya alineados en oleada 06).

## Objetivo

Eliminar residuos, documentar la decisión y borrar el plan.

## Hecho (anticipado desde 06)

- `common-components.md`, `Domain/README.md`, `common/README.md`, READMEs producto/planes FHIR HC+agenda actualizados a `Infrastructure/External`
- `Domain/Integrations/README.md` = redirección

## Pendiente

1. Borrar `common/metadata/bioenlace/` si ya no tiene YAML canónicos (o dejar solo README; actualizar `productMetadataDir`).
2. Actualizar resto:
   - `arbol-espejo-dominios.md`, `metadata-yaml-uso.md`, `runtime-datos-y-metadata.md`
   - `Platform/README.md`
   - reglas Cursor (`common-components-organizacion`, etc.)
3. ADR ya existe (`ddd-bounded-contexts-capas-y-metadata.md`) — revisar que coincida con paths finales.
4. Quitar dual-root / fallbacks de paths.
5. Excluir carpeta `Integrations/` (solo README) de `ProductDomainCatalog` si aparece como BC fantasma.
6. Entrada en `plans/README.md` → archivados; **borrar** `plans/ddd-capas-y-metadata/`.

## Criterio de done

- Un solo árbol de discovery.
- Docs y reglas alineadas.
- Plan eliminado.
