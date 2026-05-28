# `common/components` — organización y responsabilidades

**Leer este documento antes de crear, mover o refactorizar código en `web/common/components/`.**

Código reutilizable por API v1, consola, jobs y (legacy) frontend Yii. La regla de oro: **agrupar por dominio y responsabilidad**, sin carpetas top-level sueltas ni el antiguo `Services/`.

## Árbol permitido (top-level)

| Carpeta | Contenido |
|---------|-----------|
| **`Clinical/`** | Dominio clínico: encounters, prescripción, laboratorio, guardia, internación operativa, pathways, etc. |
| **`Scheduling/`** | Turnos, agenda, quirófano (`Scheduling/Service/Quirofano/`) |
| **`Person/`** | Personas, registro (`Person/Service/`) |
| **`Organization/`** | Efectores, PES, sesión operativa (`Organization/Service/`) |
| **`Core/`** | Push, notificaciones, acciones transversales (`Core/Service/`) |
| **`Ui/`** | Pantallas JSON, plantillas UI (`UiScreenService`, …) |
| **`Assistant/`** | Stack completo del asistente (ver [Assistant/README.md](../../common/components/Assistant/README.md)) |
| **`Integrations/`** | Clientes/adaptadores a sistemas externos |
| **`Ai/`**, **`Infra/`**, **`Text/`**, **`Terminology/`**, **`Logging/`** | Utilidades técnicas transversales |

**No crear** carpetas top-level como `Emergency/`, `Inpatient/`, `Services/` ni dominios clínicos fuera de `Clinical/`.

## Patrones dentro de un dominio

### Negocio por subdominio

- Servicios: `{Dominio}/{Subdominio}/Service/*.php` → namespace `common\components\{Dominio}\{Subdominio}\Service`
- Enums: `{Dominio}/{Subdominio}/Enum/*.php` → namespace `common\components\{Dominio}\{Subdominio}\Enum`
- DTOs, mappers, support: subcarpetas dedicadas (ej. `Clinical/Dto/`, `Prescription/Mapper/`)

### Clinical — subdominios actuales

| Subdominio | Ruta | Notas |
|------------|------|-------|
| Encounter / care plans | `Clinical/Service/`, `Clinical/Workflow/` | Núcleo FHIR |
| Prescripción | `Clinical/Prescription/Service/` | Receta electrónica |
| Laboratorio | `Clinical/Laboratory/Service/` | |
| **Guardia / urgencias** | `Clinical/Emergency/Service/`, `Clinical/Emergency/Enum/` | Tablero triage, circuito, SLA |
| **Internación (operativa)** | `Clinical/Inpatient/Service/` | Mapa camas, ingreso, alta, epicrisis |
| Internación (FHIR clínico) | `Clinical/Specialty/Inpatient/` | Contexto encounter, órdenes — no confundir con `Clinical/Inpatient/` |
| Especialidades | `Clinical/Specialty/Odontology/`, `Ophthalmology/` | |
| Legacy consulta | `Clinical/Legacy/` | Puente temporal |

### Scheduling

- Turnos: `Scheduling/Service/`
- Quirófano: `Scheduling/Service/Quirofano/` (no carpeta top-level)

## Responsabilidades (capas)

| Capa | Dónde | Qué va |
|------|-------|--------|
| **Service** | `*/Service/` | Lógica de negocio reutilizable (API, consola, jobs). Sin `*HttpException`, HTML ni flash. |
| **Controller API** | `frontend/modules/api/v1/` | Delgado: permisos, JSON, traduce excepciones a HTTP. |
| **Assistant** | `Assistant/` | Intents, flows YAML, RBAC UI — no dispersar fuera. |

Ver también: [arquitectura-yii2-bioenlace.mdc](../../../.cursor/rules/arquitectura-yii2-bioenlace.mdc), [api-v1-autenticacion-y-sesion.mdc](../../../.cursor/rules/api-v1-autenticacion-y-sesion.mdc).

## Dónde ubicar código nuevo

| Necesidad | Ubicación |
|-----------|-----------|
| Guardia, triage, circuito urgencias | `Clinical/Emergency/Service/` |
| Mapa camas, ingreso/alta internación | `Clinical/Inpatient/Service/` |
| Encounter, care plan, service request | `Clinical/Service/` o subcarpeta temática |
| Turno, agenda, cancelación | `Scheduling/Service/` |
| Quirófano | `Scheduling/Service/Quirofano/` |
| Efector, PES, sesión operativa | `Organization/Service/` |
| Intent / flow asistente | `Assistant/` |
| Proveedor IA, STT | `Ai/` |
| Cliente receta nacional, LIS externo | `Integrations/` |

## Referencias

- [README en código](../../common/components/README.md)
- [README `common/`](../../common/README.md)
- [Asistente — motores](./asistente-motores.md)
- [Decisión FHIR clínico](../decisions/fhir-clinical.md)
