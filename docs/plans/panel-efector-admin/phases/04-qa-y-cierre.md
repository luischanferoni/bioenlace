# Fase 04 — Verificación y cierre

## Objetivo

Verificar el panel con datos reales y cerrar el plan volcando lo estable a la documentación permanente.

## Verificación

- **QA: a cargo del usuario.**
- Casos mínimos:
  - Efector con contrato y add-ons: secciones completas.
  - Efector sin contrato: contrato vacío, sin error.
  - Efector sin datos en el período: estados vacíos.
  - Usuario sin alcance: 403.
  - Período y clase: los totales cambian de forma consistente.
  - Sin datos personales en el HTML ni en la API.
- Comparar una sección de costos contra la factura del período.

## Documentación de cierre

| Contenido | Destino |
|-----------|---------|
| Secciones y definiciones del panel | `docs/producto/` (documento del panel de efector) |
| Fuentes y reglas de atribución de costos | `docs/negocio/costos/` |
| Decisión de atribución de IA y WhatsApp por efector | `docs/decisions/` si afecta varios módulos |
| Checklist de QA | `docs/qa/` |

## Tareas

- [ ] QA del usuario.
- [ ] Volcar lo estable a `producto/`, `negocio/costos/` y `qa/`.
- [ ] Borrar `docs/plans/panel-efector-admin/` al cerrar.

## Criterio de salida

El panel queda verificado, documentado en las carpetas permanentes y el plan temporal se elimina.
