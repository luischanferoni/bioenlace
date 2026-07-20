# Fase 5 — Cierre producto y retiro del plan

## Objetivo

Dejar documentación estable y eliminar esta carpeta `plans/`.

## Volcar a docs estables

| Destino | Contenido |
|---------|-----------|
| `producto/solicitar-atencion.md` (nuevo) o actualizar `triage-reserva-turno.md` | Motivos Malestar / Control-Seguimiento / Urgencia; hub |
| Actualizar o archivar narrativa de `consultas-seguimiento.md` | “Entrada absorbida en Solicitar Atención”; async sigue existiendo |
| `planes-de-tratamiento.md` | Acciones desde hub + detalle |
| `decisions/` (opcional) | ADR corto: PlanDefinition-lite en metadata; CarePack fuera de scope |

## Limpieza código

- [ ] Intent viejo eliminado del disco si ya no es motor interno (o reducido a stub que falla en tests).
- [ ] Classification / shortcuts / QA scenarios actualizados.
- [ ] Sin referencias huérfanas en Flutter al intent id retirado.

## Cierre del plan

1. Actualizar checklist HIS si aplica (turnos / planes).
2. Quitar fila de `docs/plans/README.md`.
3. **Borrar** `docs/plans/control-seguimiento-protocolos/`.
