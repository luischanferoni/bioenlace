# Autorización solo por intents (RBAC unificado)

## Contexto

Bioenlace usó dos modelos en paralelo:

1. **Intents** (`intent_id` en YAML) para flujos create/read/update del asistente.
2. **Permisos atómicos** `Entidad.atributo.read|info|edit` derivados de `data-access-config` para métricas staff y edición dispersa (`data-access.info|listar|editar`).

Eso duplicaba reglas (mismo eje «sobre quién actúo» en YAML, dominio y grants), complicaba el admin y violaba la regla de proyecto «0 hardcode en orquestadores».

## Decisión

- **Permiso assignable en admin y RBAC runtime:** solo `intent_id` (y rutas API `/api/...` como enlaces técnicos).
- **Dominios migrados** (condición laboral, profesionales métricas, agenda PES, identidad staff): intents concretos con `domain_operation`, `fields`, `subject_resolution` y whitelist en servicios de dominio.
- **Canal genérico `data-access.*`:** retirado del catálogo NL del asistente cuando todas las métricas/superficies tienen intent enlazado; los endpoints `/api/info|listar|editar` permanecen como transporte HTTP para `open_ui` de intents concretos hasta su retiro final.
- **Grants legacy `Entidad.atributo.*`:** migrar con `catalog-permission/migrate-grants` y eliminar de `auth_item` con `catalog-permission/prune-attributes` (tras backup).

## Alternativas descartadas

- **Mantener atributos assignables en admin:** duplica UX y fuente de verdad; rechazado.
- **Big-bang borrar `/api/editar` sin intents:** rompe paridad; se migró dominio por dominio.
- **Hardcodear `intent_id` en orquestadores:** sustituido por familias NL (`intent-families.yaml`) y `IntentFamilyClassificationService`.

## Consecuencias

- Admin catálogo: solo intents + vista de manifiesto de campos (solo lectura).
- Integridad: grants atributo en `auth_item` son **error** (`catalog-integrity/check`).
- Código legacy (`AttributePermissionEvaluator`, etc.) queda `@deprecated` hasta eliminar referencias runtime restantes.
- Nuevos dominios staff deben declarar YAML intent antes de exponer API/asistente.

## Referencias

- [rbac-catalogo-permisos.md](../arquitectura/rbac-catalogo-permisos.md)
- `common/metadata/bioenlace/permission/intent-grant-migration-map.yaml`
- CLI: `php yii catalog-permission/sync`, `migrate-grants`, `prune-attributes`
