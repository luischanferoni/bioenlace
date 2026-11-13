# Clinical — Especialidades (Fase 7)

Extensiones FHIR por especialidad. Los modelos de captura específicos de cada especialidad se mapean a recursos clínicos sin escribir tablas `consultas_*` (eliminadas en migración).

## Fuente de verdad

[`Domain/Catalog/ClinicalSpecialtyCatalog.php`](Domain/Catalog/ClinicalSpecialtyCatalog.php) declara cada especialidad con su estado (`specific` / `standard` / `planned`), el copy público y —cuando tiene captura propia— sus secciones y campos. `EncounterDefinitionSpecialtyRegistry` consume ese catálogo: no guarda listas propias.

Los `key` de cada campo son las claves que la IA devuelve, tomadas de `PROMPT_FIELDS` del contrato de fila (`OdontologiaItemRowContract`, `OftalmologiaEstudioRowContract`). Un campo con `extracted: false` está declarado para la UI pero todavía no se le pide a la IA. `ClinicalSpecialtyCatalogTest` falla si el catálogo omite un campo que el prompt pide.

## Pilotos completos

| Especialidad | Carpeta | Recursos | API |
|--------------|---------|----------|-----|
| Odontología | `Odontology/` | `procedure` + `procedure_odontology_ext`, `observation` + `observation_odontology_ext`, `condition` (diagnósticos) | `GET …/encounter/<id>/odontology` |
| Oftalmología | `Ophthalmology/` | `observation` (category `ophthalmology`), `vision_prescription` | `GET …/encounter/<id>/ophthalmology` |

La respuesta de odontología incluye `procedures`, `conditions`, `toothStates` (estado del encounter) y `odontogram` (estado acumulado del paciente hasta ese encuentro, agrupado por pieza con `current` e `history`). La historia clínica (`GET …/personas/<id>/historia-clinica`) publica el mismo odontograma en la clave `odontograma`.

Odontología extrae `Tipo`, `Codigo`, `Pieza`, `Caras` y `Tiempo` (PASADA/PRESENTE/FUTURA). `OdontologyEncounterService` resuelve esos campos con los extractores del contrato, así una clave capitalizada (`Pieza`) o en minúscula (`pieza`) persiste igual.

## Persistencia IA / guardar encounter

`EncounterDocumentationService::guardar` enruta por `workflow_json` → `relacion`:

- `EncounterDentalPractice` → `OdontologyEncounterService::persistPractices` (+ care plan `odontology`)
- `EncounterDentalDiagnosis` → `persistDiagnostics` (Condition con metadatos en `note`)
- `EncounterDentalStatus` → `persistToothStates` (`observation` categoría `odontology` + `observation_odontology_ext`)
- `OphthalmologyPractice` / `OphthalmologyStudy` → `OphthalmologyEncounterService::persistPractices`
- `OphthalmologyLensPrescription` → `persistLensPrescription`

El **estado de una pieza** se lee de `observation` unida a su extensión (`tooth_number`, `surfaces`, `time_qualifier`), no de la nota de una condición. La migración `m261113_130000` crea la extensión y copia los estados legacy que vivían como nota `odontology_state:`.

Solo se persisten modelos listados en la definición del encounter (`EncounterDefinitionSpecialtyRegistry::isModelAllowed`).

## Pendiente (documentado en MIGRATION_STATUS)

- Salud mental (`mental-health` care plan + sesiones)
- Obstetricia (`observation` panel)
- Enfermería (`procedure` / `task`) y completar `requeridosPrompt` de `EncounterNursingCare`
- `device_request` prótesis odonto
- Pedir a la IA los campos de `OphthalmologyPractice` y `OphthalmologyLensPrescription` (hoy `extracted: false`)
- Yii web / UI JSON por especialidad (fase 11)
