# Siguiente paso — clean-legacy

**Estado del programa:** Fases **01–03** (incl. **03e**) y **04** (código) **hechas**.  
**Fase activa:** mantenimiento / verificación manual opcional.

---

## Fase 03e — cerrada

Todos los pasos 0–8 completados. Migraciones pendientes de correr en BD:

```bash
cd web
php yii migrate --migrationPath=@common/migrations
```

Orden: `160002` → `150002` → `170001` (ver [MIGRATIONS.md](./MIGRATIONS.md)).

---

## Fase 04 — hecha (código)

| Ítem | Estado |
|------|--------|
| Turnos: `index2` → `index`, `actionEspera` restaurado | [x] |
| Vistas huérfanas `espera2`, `show-calendar` | [x] eliminadas |
| RBAC migración `m260526_170001` | [x] |
| Nomenclador: imports legacy limpiados | [x] |
| `ENCOUNTER_CLASS` en `EncounterDefinition` | [x] |
| `Encounter`: relaciones legacy con guard si tabla ausente | [x] |

Detalle: [phases/04-turnos-nomenclador-rbac.md](./phases/04-turnos-nomenclador-rbac.md)

---

## Fuera de alcance (mantener)

- `PacienteController::actionFormularioConsulta` (shell captura)
- `InternacionController` hub / ronda / view admin
- Backend `ConsultasConfiguracionController`
- Flutter / asistente (salvo intents ya en 03d)
