# Fase 5 — Expediente legal (staff)

## Objetivo

Generación **async** del expediente amplio para roles autorizados; **no** descarga paciente.

## Checklist

- [ ] Tabla cola `legal_record_export_request` (id_persona, solicitante, estado, path archivo, created_at, ready_at)
- [ ] Servicio generación PDF/ZIP (alcance legal a definir con jurídico/clínica)
- [ ] Rol RBAC dedicado (generar / descargar)
- [ ] Job consola o worker: procesar cola, notificar staff (email o `persona_notificacion` interna)
- [ ] Endpoint staff: solicitar, listar mis solicitudes, descargar cuando `ready`
- [ ] Auditoría: quién solicitó y quién descargó

## Fuera de alcance paciente

- Ningún intent `historia.exportar` en app paciente en esta fase.
- No reutilizar endpoint `historia-clinica` staff sin filtro y sin cola.

## Criterio de cierre

Staff con permiso solicita expediente; recibe aviso; descarga archivo generado.
