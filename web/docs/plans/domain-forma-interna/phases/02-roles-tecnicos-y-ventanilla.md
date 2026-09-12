# Fase 2 — Roles técnicos + Ventanilla

## Inventario (al ejecutar)

| Desvío | Acción |
|--------|--------|
| `Person/Ventanilla/*.php` (plano) | → `Person/Ventanilla/Service/` |
| `Person/Util/CuilValidator.php` | → `Person/Service/CuilValidator.php` (sin `Util/`) |

## Checklist

- [x] Inventario de desvíos (tabla arriba)
- [x] Moves Ventanilla + CuilValidator; `use` / config / tests
- [x] Actualizar `Domain/README.md` (Person / Ventanilla)
- [x] Sin alias en rutas viejas

## Criterio de hecho

Ningún PHP de negocio nuevo fuera de `…/Service/` (salvo Integrations y roles técnicos documentados). `Person/Util/` eliminado.
