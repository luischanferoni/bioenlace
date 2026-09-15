# Fase 03 — Reubicar YAML de Platform Ui, Permission, Ai

**Estado: hecha.**

## Movido

| Origen | Destino |
|--------|---------|
| `metadata/bioenlace/platform/ui/*` | `Platform/Ui/Presentation/` |
| `metadata/bioenlace/platform/permission/**` | `Platform/Core/Permission/metadata/` |
| `metadata/bioenlace/platform/ai/*` | `Platform/Ai/Application/` |

`ProductMetadataPaths` actualizado (`uiPresentationDir`, `permissionDir` → metadata, `aiApplicationDir`).

Regla prompts: glob de AI apunta a `Platform/Ai/Application/`.

## Residual en `metadata/bioenlace/platform/`

Solo `agents/` (fase 04 → PHP).

## Siguiente

[Fase 04](./04-migrar-agents-a-php.md) — agents knobs → Application Policy PHP.
