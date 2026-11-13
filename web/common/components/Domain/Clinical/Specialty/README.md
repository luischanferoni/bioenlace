# Clinical — Especialidades (Fase 7)

Extensiones FHIR por especialidad. Los modelos de captura específicos de cada especialidad se mapean a recursos clínicos sin escribir tablas `consultas_*` (eliminadas en migración).

## Fuente de verdad

[`Domain/Catalog/ClinicalSpecialtyCatalog.php`](Domain/Catalog/ClinicalSpecialtyCatalog.php) declara cada especialidad con su estado (`specific` / `standard` / `planned`), el copy público y —cuando tiene captura propia— sus secciones y campos. `EncounterDefinitionSpecialtyRegistry` consume ese catálogo: no guarda listas propias.

Los `key` de cada campo son las claves que la IA devuelve, tomadas de `PROMPT_FIELDS` del contrato de fila (`OdontologiaItemRowContract`, `OftalmologiaEstudioRowContract`). Un campo con `extracted: false` está declarado para la UI pero todavía no se le pide a la IA. `ClinicalSpecialtyCatalogTest` falla si el catálogo omite un campo que el prompt pide.

## Pilotos completos

| Especialidad | Carpeta | Recursos | API |
|--------------|---------|----------|-----|
| Odontología | `Odontology/` | `procedure` + `procedure_odontology_ext`, `condition` (meta en `note`) | `GET …/encounter/<id>/odontology` |
| Oftalmología | `Ophthalmology/` | `observation` (category `ophthalmology`), `vision_prescription` | `GET …/encounter/<id>/ophthalmology` |

Odontología extrae `Tipo`, `Codigo`, `Pieza`, `Caras` y `Tiempo` (PASADA/PRESENTE/FUTURA). `OdontologyEncounterService` resuelve esos campos con los extractores del contrato, así una clave capitalizada (`Pieza`) o en minúscula (`pieza`) persiste igual.

## Persistencia IA / guardar encounter

`EncounterDocumentationService::guardar` enruta por `workflow_json` → `relacion`:

- `EncounterDentalPractice` → `OdontologyEncounterService::persistPractices` (+ care plan `odontology`)
- `EncounterDentalDiagnosis` → `persistDiagnostics`
- `OphthalmologyPractice` / `OphthalmologyStudy` → `OphthalmologyEncounterService::persistPractices`
- `OphthalmologyLensPrescription` → `persistLensPrescription`

Solo se persisten modelos listados en la definición del encounter (`EncounterDefinitionSpecialtyRegistry::isModelAllowed`).

## Pendiente (documentado en MIGRATION_STATUS)

- Salud mental (`mental-health` care plan + sesiones)
- Obstetricia (`observation` panel)
- Enfermería (`procedure` / `task`) y completar `requeridosPrompt` de `EncounterNursingCare`
- `device_request` prótesis odonto
- Pedir a la IA los campos de `OphthalmologyPractice` y `OphthalmologyLensPrescription` (hoy `extracted: false`)
- Yii web / UI JSON por especialidad (fase 11)
