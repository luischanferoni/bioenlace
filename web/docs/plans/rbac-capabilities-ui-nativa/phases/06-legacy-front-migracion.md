# Fase 6 — Migración legacy `front_*` (histórico / cerrado)

Migración one-shot **ya aplicada**. Retirados: `legacy-permission-aliases.yaml`, `intent-grant-migration-map.yaml`, `IntentGrantMigrationService`, sección admin deprecados. `migrate-grants` es no-op; limpieza restante de atributos atómicos: `catalog-permission/prune-attributes` tras backup.

Ver ADR: `web/docs/decisions/autorizacion-capabilities-ui-nativa.md`.
