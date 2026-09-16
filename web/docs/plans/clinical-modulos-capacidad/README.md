# Plan — Clinical: módulos de capacidad (sin Shared)

Reorganizar `Domain/Clinical` para que el **primer eje** sea la capacidad de negocio (Encounter, Emergency, Laboratory, …) y las capas DDD vivan **dentro** del módulo. Sin carpeta `Shared/` / `Common/` / `Enum/` basurero.

| Doc | Uso |
|-----|-----|
| [overview.md](./overview.md) | Problema, objetivos, no-goals |
| [design.md](./design.md) | Módulos canónicos, dependencias, discovery |
| [phases/](./phases/) | Ejecución |

Al cerrar: ADR + `Clinical/README.md` + reglas Cursor; borrar esta carpeta.
