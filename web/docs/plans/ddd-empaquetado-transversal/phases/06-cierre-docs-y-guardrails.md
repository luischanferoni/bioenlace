# Fase 06 — Cierre: docs y guardrails

**Estado:** pendiente  
**Depende de:** fases 01–05 (o del subconjunto adoptado)

## Objetivo

Dejar el norte enforceable y borrar este plan.

## Checklist

- [ ] Actualizar `Domain/README.md` (quitar `Application/*.php` sueltos; documentar módulo-primero vs BC compacto)
- [ ] Actualizar READMEs por BC (`Clinical`, `Organization`, `Scheduling`, `Person`, …)
- [ ] ADR corto o enmienda a [clinical-modulos-capacidad.md](../../decisions/clinical-modulos-capacidad.md): “módulo-primero aplica a Organization/Scheduling/Person; BCs compactos excepción”
- [ ] Extender / generalizar `BoundedContextLayerShapeTest` (o equivalente) a BCs adoptados
- [ ] Rule `ddd-un-eje-por-nivel.mdc` ya apunta al catálogo — verificar ejemplos no-Capture
- [ ] Volcar decisiones a `decisions/` + `arquitectura/common-components.md`
- [ ] Quitar fila de este plan en `plans/README.md` y **borrar** `plans/ddd-empaquetado-transversal/`

## Definition of done

Ningún BC activo con carpetas de capacidad bajo `Application/`; sufijos ∉ catálogo = deuda explícita ticketizada o cero.
