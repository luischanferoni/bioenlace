# Laboratorio externo FHIR (plan activo)

Integración **pull** con LIS externos vía HTTP/FHIR. Sin módulo LIS propio; sin tablas NBU/equivalencias.

| Documento | Contenido |
|-----------|-----------|
| [overview.md](./overview.md) | Alcance y actores |
| [design.md](./design.md) | Arquitectura y decisiones |
| [phases/](./phases/) | Ejecución por fase |

## Fases

| # | Archivo | Estado |
|---|---------|--------|
| 0 | [phases/00-governance.md](./phases/00-governance.md) | hecho |
| 1 | [phases/01-foundation-db.md](./phases/01-foundation-db.md) | hecho |
| 2 | [phases/02-integrations-connectors.md](./phases/02-integrations-connectors.md) | hecho |
| 3 | [phases/03-ingest-mapper.md](./phases/03-ingest-mapper.md) | hecho |
| 4 | [phases/04-api-read-sync.md](./phases/04-api-read-sync.md) | hecho |
| 5 | [phases/05-retire-legacy-lis.md](./phases/05-retire-legacy-lis.md) | pendiente |

## Dominio operativo (al cerrar plan)

Mover documentación estable a `web/docs/laboratorio/` y borrar esta carpeta según [../design.md](../design.md).
