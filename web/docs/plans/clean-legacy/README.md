# Plan — clean-legacy

| Campo | Valor |
|-------|--------|
| Slug | `clean-legacy` |
| Estado | En ejecución — Fase 2 |
| Objetivo | Retirar MVC Yii, modelos, búsquedas y tablas que quedaron reemplazados por **API v1**, **UI JSON/flows** y dominio **FHIR** (`encounter`, `clinical/*`). |
| Decisión base | [fhir-clinical.md](../../decisions/fhir-clinical.md) — sin retrocompat HTTP legacy de consulta |

## Índice

- [overview.md](./overview.md) — alcance, reglas, fuera de alcance
- [PROGRESS.md](./PROGRESS.md) — **seguimiento vivo** (checklist por ítem)
- [phases/01-eliminacion-segura-y-fuerte.md](./phases/01-eliminacion-segura-y-fuerte.md)
- [phases/02-covid-y-vistas-huerfanas.md](./phases/02-covid-y-vistas-huerfanas.md) — COVID + limpieza enfermería

## Reglas del programa

1. **Un dominio por PR** cuando el cambio toque BD + PHP + JS.
2. Antes de borrar un **modelo AR**: `rg` en todo `web/` (controllers, views, console, jobs, tests).
3. **Tabla BD**: migración `dropTable` solo tras cero referencias en código y backup/entorno acordado.
4. No borrar **`Consulta::ENCOUNTER_CLASS_*`** ni constantes de modalidad hasta renombrar a un enum neutro (fase aparte).
5. **`common/models/Guardia.php`** y tabla `guardia` **se mantienen** — la API `clinical/emergency-guardia` las usa.

## Al cerrar

Volcar lo permanente a `producto/` o `decisions/` y borrar esta carpeta según [plans/README.md](../README.md).
