# Overview — RBAC unificado por intents

## Problema

Bioenlace hoy mezcla **tres mecanismos** de autorización que el equipo administra y el código valida por separado:

| Mecanismo | Ejemplo | Dolor |
|-----------|---------|-------|
| Intents en catálogo | `turnos.crear-como-paciente`, `data-access.editar` | Coherente en **create**; fragmentado en **read/edit/list** |
| Permisos atómicos por atributo | `Persona.nombre.edit`, `ProfesionalEfectorServicio.asignacion.read` | Matriz enorme en admin; duplica lo declarado en `data-access-config` |
| Políticas de dominio + scope_checker | `condicion_laboral_staff` vs `_own`, `efector_sesion` | Mismo eje «sobre quién actúo» repetido en YAML, dominio y nombres de operación |

Los flows **create** ya siguen el modelo deseado: rol → intent → campos/pasos en YAML del intent → validación de dominio al persistir. El canal **DataAccess** (info / listar / editar disperso) y el catálogo de atributos son la excepción.

## Objetivo

Unificar **todo el producto** en una regla:

```text
Rol  →  intents permitidos (auth_item)
Intent  →  pasos, rutas API, campos y agrupaciones (YAML + UI JSON)
Dominio  →  ¿este recurso concreto es válido para ese intent?
```

Sin:

- Permisos assignables por atributo o grupo en `auth_item`
- Scopes como dimensión configurable en Roles RBAC
- Intents genéricos `data-access.info`, `data-access.listar`, `data-access.editar` como puerta con sub-permisos internos

Con:

- Admin que asigna **solo intents** a roles
- Vista informativa de campos/grupos **leídos del YAML** (no editables en catálogo)
- Variantes de contexto y de campos modeladas como **intents distintos** (como `turnos.crear-como-paciente` vs `turnos.crear-para-paciente-flow`)

## Actores

| Actor | Interés |
|-------|---------|
| Administrador institucional | Asignar capacidades por rol sin matriz atributo × operación |
| Desarrollador | Una capa RBAC predecible; metadata declarativa; 0 hardcode en orquestadores |
| Usuario staff / paciente | Asistente que pregunta «¿propio o de otro?» solo cuando tiene varios intents aplicables |
| Auditoría / seguridad | RBAC en puerta API + dominio en persistencia (no confiar en ocultar campos en UI) |

## Estado actual (punto de partida)

| Área | Referencia |
|------|------------|
| Documentación RBAC | `web/docs/arquitectura/rbac-catalogo-permisos.md` |
| Catálogo admin | `PermissionCatalogController`, `PermissionCatalogService` |
| Permisos atributo | `AttributePermissionEvaluator`, `AttributePermissionKeyMapper` |
| DataAccess config | `web/common/components/Platform/Core/DataAccess/schemas/data-access-config/` |
| Intents genéricos DataAccess | `DataAccessUiActionCatalog`, `data-access.editar.yaml` |
| Políticas dominio | `web/common/metadata/bioenlace/permission/domain-operation-policies.yaml` |
| Integridad | `CatalogIntegrityService`, `php yii catalog-integrity/check` |
| Create (modelo objetivo) | `web/common/metadata/bioenlace/assistant/intents/create/*.yaml` |

## Entregables por fase (resumen)

| Fase | Entrega |
|------|---------|
| 0 | Marco, piloto, convenciones de nombres, fuera de alcance |
| 1 | Esquema metadata intent extendido, checker de integridad actualizado (solo intents) |
| 2 | Admin: catálogo solo intents + panel lectura de campos desde YAML |
| 3 | Migración canal DataAccess → intents concretos por entidad/operación/contexto |
| 4 | Familias NL / descubrimiento asistente sin hardcode por `intent_id` |
| 5 | Piloto dominio (condición laboral, listados PES); alinear API y políticas |
| 6 | Retiro código y filas `auth_item` legacy; doc estable; migración datos RBAC |

## Fuera de alcance (este programa)

- Rediseño de **roles dinámicos por PES** (`BioenlaceDbManager` + `servicios.item_name`) — se mantiene; los intents se asignan a esos roles como hoy
- Representación paciente — sigue en dominio (`PersonRepresentationAccessService`); no sustituye intents paciente salvo donde ya existan
- Reescritura completa del motor de métricas `info_list` en una sola fase — se migra por dominios o se encapsula detrás de intents
- Cambios en autenticación JWT / `JsonHttpBearerAuth`

## Riesgos principales

| Riesgo | Mitigación |
|--------|------------|
| Proliferación de intents | Familias NL, aliases, manifests compartidos entre variantes |
| Regresión de menor privilegio | Piloto con matriz rol × intent × recurso; tests de API |
| Migración `auth_item` en producción | Script de mapeo grants atributo → intents; ventana de convivencia documentada |
| Llamadas API directas bypass UI | Revalidación dominio + whitelist de campos en servicio según contrato del intent |

## Criterios de éxito

- [ ] Admin asigna **solo intents** a roles; no hay pantalla de grants por atributo
- [ ] `php yii catalog-integrity/check` pasa sin referencias a permisos atómicos de atributo
- [ ] No quedan rutas críticas que dependan de `AttributePermissionEvaluator` para autorizar
- [ ] Piloto condición laboral: variantes propio/staff como intents; dominio en POST
- [ ] Documentación estable actualizada; carpeta `plans/rbac-intents-unificado/` eliminable
