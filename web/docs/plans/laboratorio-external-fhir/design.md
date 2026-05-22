# Design — Laboratorio externo FHIR

## Arquitectura

| Capa | Ubicación |
|------|-----------|
| HTTP + auth FHIR | `common/components/Integrations/Laboratory/` |
| Mapper + ingesta | `common/components/Clinical/Laboratory/` |
| Persistencia | `diagnostic_report`, `observation` (`category=exam`) |
| API | `clinical/LaboratoryResultController` |

## Decisiones

| Tema | Decisión |
|------|----------|
| Proveedores | Registry global; credenciales por clave en `params` |
| Sincronización | Solo pull |
| Persistencia | Normalizado + `payload_json` del recurso FHIR |
| Encounter | Obligatorio intentar enlace; nullable si no hay match |
| Terminología | Códigos del LIS (LOINC/SNOMED en FHIR); sin `laboratorio_nbu` |
| Legacy | Retirar MVC `Laboratorio*` en fase 5 |

## Anclas

- Conector ejemplo: `SianlabsFhirConnector`
- Ingesta: `LaboratoryIngestService`
- Consola: `LaboratorySyncController` (futuro)
