# RBAC y catálogo de permisos (greenfield)

## Principios

- **BioenlaceDbManager** se conserva: PES → rol vía `servicios.item_name`.
- **Permisos assignables** en admin:
  - **Intents** — operaciones / flows (`create/`, `read/`, `update/`, `delete/`).
  - **Atributos** — `Entidad.atributo.read|info|edit` declarados en `data-access-config`.
- **Grupos** en data-access-config: solo presentación (chips/bloques); el grant es por **atributo**.
- **Edit complejo**: solo intent (BD → AR → YAML intent); no duplicar en `edit` disperso.
- **open_ui intermedio**: hereda permiso del intent padre (`FlowStepAccessService` + header opcional `X-Flow-Intent-Id`).

## Fuentes de verdad

| Canal | Cadena |
|-------|--------|
| Create / update / delete complejo | BD → AR → `schemas/intents/{create,update,delete}/` |
| Read / info por atributo | BD → AR → `data-access-config` |
| Edit escalar | BD → AR → `data-access-config` (`edit.attributes`) |
| Pasos UI dentro de flow | Derivados del intent (sin grant propio) |

## Herramientas

- **Admin**: `/admin/permission-catalog/index`, `/admin/permission-catalog/integrity`
- **CLI**: `php yii catalog-integrity/check`

## Migración gradual

1. Campo opcional `permission:` en intents (nombre lógico, p. ej. `ProfesionalEfectorServicio.create`).
2. Mover YAML a subcarpetas CRUD (warnings de integridad mientras queden en raíz).
3. Bloque `attributes:` por entidad en data-access-config (grupos opcionales).
4. Reemplazar grants `data_access_role_grant` por permisos en `auth_item` (fase posterior).

## Fase implementada (actual)

- `IntentSchemaPaths` — carga recursiva `create|read|update|delete/`.
- Intents migrados a subcarpetas CRUD (2025-06).
- `permission:` explícito en intents piloto; inferencia vía `IntentPermissionResolver`.
- `Turno.yaml` + `attributes` en `Persona.yaml`.
- `CatalogIntegrityService`, admin `/permission-catalog/*`, herencia open_ui en API.
