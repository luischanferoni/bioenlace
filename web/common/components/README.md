# Organización de `common/components`

Código reutilizable por web, API, consola y jobs.

**Antes de tocar este árbol**, leer: [common-components.md](../../docs/arquitectura/common-components.md).

## Reglas rápidas

- **Dominios de negocio** (`Clinical/`, `Scheduling/`, `Person/`, `Organization/`, `Core/`, `Ui/`): lógica en `*/Service/` (o subcarpeta `Service/` del subdominio). **No** usar `Services/` (eliminado).
- **No** carpetas top-level sueltas (`Emergency/`, `Inpatient/`, etc.): van bajo el dominio (`Clinical/Emergency/`, `Clinical/Inpatient/`).
- **`Assistant/`**: todo el stack del asistente. Ver [Assistant/README.md](./Assistant/README.md).
- **`Integrations/`**: clientes/adaptadores externos.
- **`Ai/`**, **`Infra/`**, **`Text/`**, **`Terminology/`**, **`Logging/`**: utilidades transversales.

## Dominios (resumen)

| Carpeta | Contenido principal |
|---------|---------------------|
| `Clinical/` | FHIR, prescripción, lab, **guardia** (`Emergency/`), **internación operativa** (`Inpatient/`), especialidades |
| `Scheduling/` | Turnos, agenda, `Service/Quirofano/` |
| `Person/` | `Service/` personas y registro |
| `Organization/` | Efectores, PES, sesión operativa |
| `Core/` | Push, notificaciones |
| `Ui/` | UI JSON y pantallas |
| `Assistant/` | IntentEngine, SubIntentEngine, flows |
| `Integrations/` | Receta nacional, externos |
| `Ai/`, `Infra/`, … | Transversal |

## Clinical — subdominios

| Ruta | Uso |
|------|-----|
| `Clinical/Service/` | Encounter, care plans, service requests |
| `Clinical/Emergency/` | Guardia, triage, circuito ([README](./Clinical/Emergency/README.md)) |
| `Clinical/Inpatient/` | Mapa camas, ingreso/alta ([README](./Clinical/Inpatient/README.md)) |
| `Clinical/Specialty/Inpatient/` | Contexto FHIR de internación (distinto de `Inpatient/`) |
| `Clinical/Prescription/`, `Laboratory/` | Receta y lab |
| `Clinical/Legacy/` | Puente consulta legacy |

## Quirófano

`Scheduling/Service/Quirofano/` — no crear carpeta top-level.

## Documentación

- [common-components.md](../../docs/arquitectura/common-components.md) — **fuente de verdad** de organización
- [common/README.md](../README.md) — vista `common/` completa
- [asistente-motores.md](../../docs/arquitectura/asistente-motores.md)
- Reglas Cursor: `.cursor/rules/common-components-organizacion.mdc`, `arquitectura-yii2-bioenlace.mdc`
