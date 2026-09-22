# Fase 03 — Agenda Infra Port + NisFhir

## Checklist

- [x] `use` explícito de `Domain\Port\EfectorDirectionsProvider` en stub + `TurnoReminderContentService`
- [x] Mover `NullEfectorDirectionsProvider` → `Infrastructure/Persistence/`
- [x] Mover `TurnoOutboundChannel` → `Infrastructure/External/Outbound/`
- [x] Reagrupar `External/{Connector,Contract,Mapper,Registry,Sync,Exception}` → `External/NisFhir/`
- [x] Actualizar `params.php` + callers FQCN
