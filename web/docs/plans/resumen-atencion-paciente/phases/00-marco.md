# Fase 0 — Marco

## Objetivo

Alinear producto, contenido del resumen y límites de seguridad antes de API.

## Checklist

- [x] Plan en `plans/resumen-atencion-paciente/`
- [x] Resumen narrativo = texto IA (`texto_procesado` → `encounter.note`), no texto crudo ni SNOMED como cuerpo
- [x] Publicación automática T+Δ tras cierre, sin autorización médico
- [x] Solo ambulatorio (`AMB`); paciente multi-efector por `id_persona`
- [x] Expediente legal solo staff (Fase 5, cola async)
- [ ] Validar con clínica: Δ en minutos (3 vs 5) y mensaje push
- [ ] Validar: encounter sin `note` (solo artefactos) — copy UX
- [x] Rol staff para expediente legal (`ExpedienteLegalGenerar` + rutas API)

## Criterio de cierre

Decisiones en [design.md](../design.md) aceptadas; Fase 1 puede abrir PR.
