# Fase 1 — Quitar alias

- Eliminar `ConsultasConfiguracion`, busqueda, controller y vistas `consultas-configuracion`.
- Admin: `EncounterDefinitionController` + vistas `encounter-definition` (`service_id`, `workflow_json`).
- En `EncounterDefinition`: sin `pasos_json`, `id_servicio`, `getUrlPorServicioYEncounterClass`, `pasos_legacy`.
- `EncounterDefinitionQuery`: sin mapeo `id_servicio` → `service_id`.
- Migración: drop `pasos_legacy`.
