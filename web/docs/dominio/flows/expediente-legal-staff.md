# Expediente legal (staff)

## Resumen

El **paciente** consume resúmenes narrativos publicados (Fases 1–4). El **expediente legal** amplio (PDF con datos estructurados + atenciones) es solo para **staff** con permiso, vía **cola async**.

## API

| Método | Ruta | Uso |
|--------|------|-----|
| POST | `/api/v1/clinical/legal-record-export/solicitar` | Body: `id_persona`, `id_efector?` |
| GET | `/api/v1/clinical/legal-record-export/listar-mis-solicitudes` | Solicitudes del usuario autenticado |
| GET | `/api/v1/clinical/legal-record-export/ver-estado?request_id=` | Estado de una solicitud |
| GET | `/api/v1/clinical/legal-record-export/descargar?request_id=` | PDF cuando `estado=LISTO` |

RBAC: rutas `/api/clinical/legal-record-export/*` y permiso de negocio `ExpedienteLegalGenerar`.

## Cola

```bash
php yii migrate --migrationPath=@common/migrations
php yii legal-record-export/run              # cron ~cada minuto
php yii legal-record-export/process 42       # una solicitud (prueba)
```

Archivos en `@runtime/legal-record-exports/`.

## Push / bandeja

Tipo: `LEGAL_RECORD_EXPORT_READY` — payload `{ request_id, subject_persona_id }` al **persona_id del solicitante**.

## Reglas de acceso

- Requiere sesión staff (no autogestión paciente sobre su propio `id_persona`).
- Efector en sesión operativa o `id_efector` en body.
- Al menos una atención AMB `finished` del paciente en ese efector y acceso vía `EncounterAccessService`.

## Código

- `common/components/Clinical/LegalRecord/`
- `frontend/modules/api/v1/controllers/clinical/LegalRecordExportController.php`
- `console/controllers/LegalRecordExportController.php`
