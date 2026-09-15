# Plan — DDD: capas en BC + metadata reclasificada

Acercar `common/components` y la metadata del producto a un modelo **por bounded context** con capas Application / Domain / Infrastructure / Presentation; dejar YAML solo donde aporta (flows, composición de motores, prompts, ui-text, manifests, auth de motor); migrar knobs y catálogos de negocio a PHP o BD.

| Doc | Uso |
|-----|-----|
| [overview.md](./overview.md) | Problema, objetivos, alcance |
| [design.md](./design.md) | Árbol destino, reglas YAML vs PHP/BD, inventario |
| [phases/](./phases/) | Ejecución por fases |

Al cerrar: volcar decisiones a `web/docs/decisions/` y arquitectura a `web/docs/arquitectura/`; borrar esta carpeta.