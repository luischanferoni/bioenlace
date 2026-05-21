# Fase 12 — Frontend Yii web (controllers y vistas)

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** Fases 2–8 completas para el dominio tocado  
**Estado:** pendiente

## Objetivo

Renombrar o retirar el frontend Yii clásico que aún referencia `Consulta*`, alineado al dominio Clinical — **solo si el canal sigue en uso**.

Si el canal está **deshabilitado en producción**, esta fase puede reducirse a:

- [ ] Marcar controllers como deprecated y respuesta 410, o
- [ ] Eliminar carpetas `frontend/controllers/Consulta*` y `frontend/views/consultas/`.

## Alcance completo (si Yii web sigue activo)

| Área | Acción |
|------|--------|
| `frontend/controllers/ConsultasController.php` | `clinical/EncounterController` web o thin wrapper a API |
| `frontend/controllers/Consulta*Controller.php` (~25) | Consolidar en documentación por encounter o eliminar |
| `frontend/views/consultas/` | `frontend/views/clinical/encounter/` |
| `SisseConsultaFilter` | `EncounterFilter` |
| Reports / PDF farmacia | Actualizar queries a tablas nuevas |

## Estrategia recomendada

1. Inventariar URLs Yii aún accedidas (logs).
2. Priorizar vistas que duplican API → **eliminar** y enlazar SPA/Flutter.
3. El resto: wrapper mínimo que llame `Clinical/*Service`.

## Fuera de alcance

- Rediseño visual completo del backoffice.

## Definition of Done

- Grep en `frontend/` sin `ConsultaMedicamentos`, `id_consulta` en código activo (salvo comentarios/docs).
- O documento explícito “Yii web clínico retirado” firmado por producto.

## Cierre del programa

- [ ] `MIGRATION_STATUS.md` todo `hecho` o `n/a`.
- [ ] Entrada en CHANGELOG / release notes interno.
