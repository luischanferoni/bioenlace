# Fase 01 — Dedupe y rutas

## Objetivo

Un solo árbol por dominio ya reestructurado; sin copias en raíz del dominio ni carpetas `api/`/`infra/` huérfanas en costos.

## Tareas

- [ ] Borrar `.md` duplicados en raíz de `Turnos/` (mantener solo `README`, `overview`, `design`, `flows/`)
- [ ] Borrar `.md` duplicados en raíz de `asistente/` (mantener `README`, `overview`, `design`, `flows/`)
- [ ] Borrar `.md` duplicados en raíz de `dominio/`
- [ ] Borrar `costos/api/`, `costos/infra/`, `costos/pruebas_costos_ia.md` sueltos si ya están en `flows/`
- [ ] Alinear `web/docs/README.md` con ruta real `turnos/` (carpeta `Turnos` en disco Windows)
- [ ] Actualizar `common/components/README.md` → `Turnos/`

## DoD

- Cada dominio tiene exactamente un índice y una carpeta `flows/`.
- `grep` sin referencias a rutas de archivos borrados.
