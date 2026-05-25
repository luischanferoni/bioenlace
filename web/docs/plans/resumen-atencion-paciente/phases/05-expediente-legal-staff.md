# Fase 5 — Expediente legal (staff)

## Objetivo

Generación **async** del expediente amplio para roles autorizados; **no** descarga paciente.

## Checklist

- [x] Tabla cola `legal_record_export_request` + `legal_record_export_audit`
- [x] Servicio generación PDF (`LegalRecordExportPdfService` + `LegalRecordExportDataCollector`)
- [x] Permiso RBAC `ExpedienteLegalGenerar` + rutas ApiGhost
- [x] Consola `php yii legal-record-export/run` | `process <id>`
- [x] Endpoints staff: solicitar, listar mis solicitudes, ver estado, descargar
- [x] Auditoría: SOLICITADO, GENERADO, DESCARGADO, FALLIDO
- [x] Notificación staff `LEGAL_RECORD_EXPORT_READY` (push + bandeja)

## Fuera de alcance paciente

- Ningún intent `historia.exportar` en app paciente en esta fase.
- No reutilizar endpoint `historia-clinica` staff sin filtro y sin cola.

## Criterio de cierre

Staff con permiso solicita expediente; recibe aviso; descarga archivo generado.
