# `Domain/` — negocio Bioenlace (salud / HIS)

Namespace base: `common\components\Domain\…`

Todo lo **específico del rubro actual**. Para otro producto, esta carpeta se reemplaza o se empaqueta aparte; los motores en `../Platform/` permanecen.

## Subcarpetas

| Carpeta | Contenido |
|---------|-----------|
| **`Clinical/`** | Encounters, guardia, internación, prescripción, lab, texto clínico (`Text/`), legacy consulta |
| **`Scheduling/`** | Turnos, agenda, quirófano |
| **`Person/`** | Personas, registro, representación |
| **`Organization/`** | Efectores, PES, sesión operativa |
| **`Integrations/`** | SISSE, receta digital, MPI, laboratorio FHIR, identidad |
| **`Terminology/`** | SNOMED, codificación clínica (`SnomedCategoryCatalog`, `SnomedSearchProfileCatalog`) |

Metadata SNOMED: `common/metadata/bioenlace/terminology/snomed-terminology.yaml` (ECL canónicos + codificación + búsqueda).

## Cableado con motores

Los handlers que conectan dominio con motores genéricos (hydrators, scope checkers, catálogos UI, secciones del panel) se registran en **`common/config/product-registries.php`**, no dentro de `Platform/Assistant/`.

## Clinical — subdominios

| Ruta | Uso |
|------|-----|
| `Clinical/Service/` | Encounter, care plans, service requests |
| `Clinical/Emergency/` | Guardia, triage |
| `Clinical/Inpatient/` | Mapa camas, ingreso/alta |
| `Clinical/Text/` | Procesador texto clínico, SymSpell médico |
| `Clinical/Legacy/` | Puente consulta legacy |

Ver [Clinical/README.md](./Clinical/README.md).
