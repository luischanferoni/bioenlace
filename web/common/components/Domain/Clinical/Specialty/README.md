# Clinical — Especialidades (Fase 7)

Extensiones FHIR por especialidad. Los modelos legacy (`ConsultaOdontologia*`, `ConsultaPracticasOftalmologia`, `ConsultasRecetaLentes`) se mapean a recursos clínicos sin escribir tablas `consultas_*` (eliminadas en migración).

## Pilotos completos

| Especialidad | Carpeta | Recursos | API |
|--------------|---------|----------|-----|
| Odontología | `Odontology/` | `procedure` + `procedure_odontology_ext`, `condition` (meta en `note`) | `GET …/encounter/<id>/odontology` |
| Oftalmología | `Ophthalmology/` | `observation` (category `ophthalmology`), `vision_prescription` | `GET …/encounter/<id>/ophthalmology` |

## Persistencia IA / guardar encounter

`EncounterDocumentationService::guardar` enruta por `workflow_json` → `relacion`:

- `ConsultaOdontologiaPracticas` → `OdontologyEncounterService::persistPractices` (+ care plan `odontology`)
- `ConsultaOdontologiaDiagnosticos` → `persistDiagnostics`
- `ConsultaPracticasOftalmologia` / `Estudios` → `OphthalmologyEncounterService::persistPractices`
- `ConsultasRecetaLentes` → `persistLensPrescription`

Solo se persisten modelos listados en la definición del encounter (`EncounterDefinitionSpecialtyRegistry::isModelAllowed`).

## Pendiente (documentado en MIGRATION_STATUS)

- Salud mental (`mental-health` care plan + sesiones)
- Obstetricia (`observation` panel)
- Enfermería (`procedure` / `task`)
- `device_request` prótesis odonto
- Yii web / UI JSON por especialidad (fase 11)
