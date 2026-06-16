# Fase 3 — Migrar canal DataAccess

## Objetivo

Reemplazar los intents genéricos `data-access.info`, `data-access.listar`, `data-access.editar` y el endpoint `/api/editar` (y listar/info genéricos) por **intents concretos por dominio**, siguiendo el patrón create.

## Estrategia

Migración **por dominio**, no big-bang:

| Orden sugerido | Dominio | Origen actual | Notas |
|----------------|---------|---------------|-------|
| 1 | Condición laboral | Endpoints PES dedicados | Piloto fase 5 |
| 2 | Profesionales en efector | `ProfesionalEfectorServicio.yaml` info_list | Métricas → intents list/info |
| 3 | Persona identidad en contexto staff | `ProfesionalEfectorServicio.yaml` edit | Campos en YAML intent |
| 4 | Agenda PES | `ProfesionalEfectorServicioAgenda.yaml` | open_ui existente |
| 5 | Turno | `Turno.yaml` si aplica | Sin canal data-access; operaciones vía intents `turnos.*` |

Por cada dominio:

1. Crear YAML intent(s) con variantes propio/staff/paciente según aplique
2. Registrar `rbac_route` (dedicada o reutilizar existente)
3. Mapear grants atributo → intents en script migración
4. Actualizar `DataAccessUiActionCatalog` / shortcuts / classification rules
5. Deprecar entrada en `data-access-config` para esa entidad

## Tareas transversales

### 3.1 Retirar autorización por atributo en runtime

- `DataAccessEditUiService`: filtrar por intent, no por `AttributePermissionEvaluator`
- `EditSurfaceAuthorizationService`: reemplazar o eliminar
- `QueryAuthorizationService`: intents por `metric_id` o eliminar canal genérico API
- `DataAccessCatalogIntentSupport`: dejar de registrar `data-access.*` cuando haya paridad (`DataAccessGenericChannelRetirement`)

### 3.2 Endpoint `/api/editar`

- Marcar deprecado en docblock y logs
- Redirigir superficies migradas a rutas dedicadas por intent
- Eliminar cuando integridad reporte 0 usos

### 3.3 Asistente

- Quitar `data-access.editar` de shortcuts cuando existan reemplazos
- Hydrator `data_access.edit_flow`: sustituir por resolución familia → intent concreto

## Entregables

- [x] Al menos **dos dominios** migrados (piloto condición laboral + profesionales conteo/listado)
- [x] Dominios **agenda PES**, **identidad profesional** y **distribución profesionales por servicio** migrados
- [x] **Turno**: sin migración data-access (ya cubierto por intents `turnos.*`)
- [x] `data-access.*` retirados del catálogo NL del asistente (`discoverCatalogEntries` vacío con paridad)
- [ ] Tests API por intent migrado

## Estado

Completada para dominios data-access; pendiente tests API E2E y fases 4–6 (retiro código legacy).
