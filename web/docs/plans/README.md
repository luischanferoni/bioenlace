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
| Urgencias — triage + tablero | `urgencias-triage-tablero/` | Fase 1 (API dominio) en curso; fases 2–5 pendientes |
| Cohortes — asistencia + batch IA | `cohortes-asistencia-batch/` | Fases 1–5 implementadas; activación Vertex manual |
| Representación paciente (FHIR) | `representacion-paciente-fhir/` | Fases 1–5 implementadas; doc estable en [producto/representacion-paciente.md](../producto/representacion-paciente.md); E2E móvil manual pendiente |
| Limpieza legacy Yii / modelos / BD | `clean-legacy/` | Código fases 01–04 cerrado; migrate BD + smoke opcional |
| DataAccess — edición dispersa | `data-access-edicion-sparse/` | Cerrado (`/api/editar`, `data-access.editar`; agenda legacy deprecada) |

## Planes archivados (carpeta eliminada)

| Plan | Documentación estable |
|------|------------------------|
| Permisos DataAccess staff | `common/components/Core/DataAccess/README.md` + backend «Consultas staff» |

## Convenciones (solo dentro de `plans/`)

- [overview.md](./overview.md)
- [design.md](./design.md)

## Dónde documentar lo ya construido

| Necesidad | Dónde |
|-----------|--------|
| Narrativa de producto | [producto/](../producto/README.md) |
| Decisiones cerradas | [decisions/](../decisions/README.md) |
| Madurez HIS | [his-completo/](../his-completo/README.md) |
