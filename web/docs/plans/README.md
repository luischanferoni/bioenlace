# Planes en ejecución (uso interno)

Espacio **temporal** para programas de trabajo grandes (varias fases, varios PR). Solo existe **mientras se construye**.

## Reglas

1. Al **terminar** la construcción: borrar `plans/<slug>/` por completo.
2. Antes de borrar: dejar lo que siga vigente en `producto/<tema>.md` o `decisions/`.
3. **Ningún** otro archivo en `web/docs/` debe enlazar a rutas bajo `plans/` (ni `README` global, ni `producto/`, ni `his-completo/`). Los planes son para quien ejecuta el programa, no para lectores de documentación estable.

## Planes activos

| Plan | Carpeta | Notas |
|------|---------|--------|
| Receta electrónica (AR) | `receta-electronica/` | Fases 1–2 en producción; repositorio nacional pendiente |
| Interoperabilidad HC FHIR | `interoperabilidad-historia-clinica/` | Fases 1–2 + reconcile; homologación nacional pendiente |
| Urgencias — triage + tablero | `urgencias-triage-tablero/` | Fase 1 (API dominio) en curso; fases 2–5 pendientes |
| Agendamiento FHIR entrante | `fhir-scheduling-inbound/` | Fase 1 (CUIL, catálogo servicio, schedule link) en curso |

## Planes archivados (carpeta eliminada)

| Plan | Documentación estable |
|------|------------------------|
| Atención remota y async | [producto/atencion-remota-async.md](../producto/atencion-remota-async.md) |
| Cohortes — asistencia + batch IA | [producto/asistencia-cohortes.md](../producto/asistencia-cohortes.md) |
| Representación paciente (FHIR) | [producto/representacion-paciente.md](../producto/representacion-paciente.md) |
| DataAccess — edición dispersa | `common/components/Platform/Core/DataAccess/README.md` + admin «Consultas staff» |
| Permisos DataAccess staff | `common/components/Platform/Core/DataAccess/README.md` + admin «Consultas staff» |
| RBAC sin webvimark | [arquitectura/rbac-catalogo-permisos.md](../arquitectura/rbac-catalogo-permisos.md) |
| RBAC unificado por intents | [decisions/autorizacion-solo-por-intents.md](../decisions/autorizacion-solo-por-intents.md) + [arquitectura/rbac-catalogo-permisos.md](../arquitectura/rbac-catalogo-permisos.md) |
| Limpieza legacy Yii / modelos / BD | Migraciones y código en repo; sin plan activo |

## Convenciones (solo dentro de `plans/`)

- [overview.md](./overview.md)
- [design.md](./design.md)

## Dónde documentar lo ya construido

| Necesidad | Dónde |
|-----------|--------|
| Narrativa de producto | [producto/](../producto/README.md) |
| Decisiones cerradas | [decisions/](../decisions/README.md) |
| Madurez HIS | [his-completo/](../his-completo/README.md) |
