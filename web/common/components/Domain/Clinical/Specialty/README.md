# Clinical — Especialidades (Fase 7)

Extensiones FHIR por especialidad. Los modelos de captura específicos de cada especialidad se mapean a recursos clínicos sin escribir tablas `consultas_*` (eliminadas en migración).

## Fuente de verdad

[`Domain/Catalog/ClinicalSpecialtyCatalog.php`](Domain/Catalog/ClinicalSpecialtyCatalog.php) declara cada especialidad con su estado (`specific` / `standard` / `planned`), el copy público y —cuando tiene captura propia— sus secciones y campos. `EncounterDefinitionSpecialtyRegistry` consume ese catálogo: no guarda listas propias.

Los `key` de cada campo son las claves que la IA devuelve, tomadas de `PROMPT_FIELDS` del contrato de fila (`OdontologiaItemRowContract`, `OftalmologiaEstudioRowContract`). Un campo con `extracted: false` está declarado para la UI pero todavía no se le pide a la IA. `ClinicalSpecialtyCatalogTest` falla si el catálogo omite un campo que el prompt pide.

## Categorías adicionales

Una especialidad `standard` puede declarar `extra_sections`: categorías que se suman al
workflow estándar sin identificarla. Es el caso de pediatría, que agrega la categoría de
signos vitales y antropometría sobre el registro clínico completo. Una categoría
adicional nunca resuelve como especialidad de origen, así que un encounter de enfermería
no se reporta como pediátrico.

## Pilotos completos

| Especialidad | Carpeta | Recursos | API |
|--------------|---------|----------|-----|
| Odontología | `Odontology/` | `procedure` + `procedure_odontology_ext`, `observation` + `observation_odontology_ext`, `condition` (diagnósticos) | `GET …/encounter/<id>/odontology` |
| Oftalmología | `Ophthalmology/` | `observation` (category `ophthalmology`), `vision_prescription` | `GET …/encounter/<id>/ophthalmology` |
| Obstetricia | `Application/Service/ObstetricsEncounterService` | `observation` (category `obstetrics`) + `observation_panel_ext` | historia clínica (`control_prenatal`) |
| Salud mental / psicología | `Application/Service/MentalHealthEncounterService` | `observation` (category `mental-health`) + `observation_panel_ext` | historia clínica (`salud_mental`) |

La respuesta de odontología incluye `procedures`, `conditions`, `toothStates` (estado del encounter) y `odontogram` (estado acumulado del paciente hasta ese encuentro, agrupado por pieza con `current` e `history`). La historia clínica (`GET …/personas/<id>/historia-clinica`) publica el odontograma en `odontograma` y la última receta de lentes en `receta_lentes`.

Odontología extrae `Tipo`, `Codigo`, `Pieza`, `Caras` y `Tiempo` (PASADA/PRESENTE/FUTURA). Oftalmología extrae `Codigo`, `Ojo` (OD/OI/AO), `Prueba`, `Estado`, `Informe` y `Tipo` en prácticas, `Codigo`, `Ojo` e `Informe` en estudios, y las ocho medidas de la receta de lentes. Enfermería extrae diez signos vitales y cuatro medidas antropométricas —peso, talla, perímetro cefálico y circunferencia abdominal—, que alimentan las curvas de crecimiento. `OdontologyEncounterService` y `OphthalmologyEncounterService` resuelven esos campos con los extractores del contrato, así una clave capitalizada (`Pieza`, `OD esfera`) o en snake_case (`pieza`, `od_esfera`) persiste igual.

## Paneles clínicos

Los parámetros que se consultan o grafican por campo (control prenatal, sesión de salud
mental) se guardan como una observación por valor, agrupadas por panel en
`observation_panel_ext` (`panel_kind` + `panel_id`). El almacenamiento compartido vive
en `Shared/Infrastructure/Persistence/ObservationPanelStore`. Los tramos de severidad de
las escalas viven en `MentalHealthInstrumentCatalog` y se calculan al leer: no se guardan.

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

- Salud mental: ítems individuales de cada escala y cálculo del puntaje
- Obstetricia: derivar la edad gestacional desde la FUM y curvas fetales
- Enfermería (`procedure` / `task`) como prácticas de enfermería fuera de la captura
- `device_request` prótesis odonto
- Yii web / UI JSON por especialidad (fase 11)
