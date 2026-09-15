# Fase 03 — Reubicar YAML de Platform Ui, Permission, Ai

## Objetivo

Colocar manifests UI, auth composition y AI metadata bajo carpetas de capa en Platform.

## Movimientos

| Origen | Destino |
|--------|---------|
| `platform/ui/*` | `Platform/Ui/Presentation/` |
| `platform/permission/**` | `Platform/Core/Permission/` (subcarpeta `metadata/` o archivos en raíz del módulo Permission, documentado) |
| `platform/ai/*` | `Platform/Ai/` con subcarpeta clara (`Presentation` o `Application` según archivo) |

Actualizar todos los callers de `ProductMetadataPaths::homePanelManifestFile`, `domainOperationPoliciesFile`, etc.

## Criterio de done

- Panel home, client-context, policies y clinical-text-ia cargan desde paths nuevos.
- `metadata/bioenlace/platform/ui|permission|ai` eliminados.

## No hacer

- Reescribir políticas RBAC ni cambiar semantics de operations.
- Migrar implementaciones de policy PHP (solo path del YAML de composición).