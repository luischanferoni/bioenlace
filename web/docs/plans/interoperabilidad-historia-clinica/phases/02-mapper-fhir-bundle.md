# Fase 2 — Mapper FHIR Bundle

## Objetivo

Completar `FhirClinicalHistoryBundleMapper::buildForEncounter()` para producir un **Bundle tipo `document`** válido contra el perfil nacional acordado en Fase 0.

## Entrada

- `Encounter` finalizado (`status = finished`)
- Relaciones: conditions, medication/service requests, allergy subset, diagnostic reports, electronic prescriptions (opcional)

## Salida

```json
{
  "resourceType": "Bundle",
  "type": "document",
  "timestamp": "…",
  "identifier": { "system": "…", "value": "bioenlace-encounter-{id}" },
  "entry": [ … ]
}
```

## Reglas

- `Composition.subject` → Patient
- `Composition.encounter` → Encounter
- `Composition.author` → PractitionerRole / Organization (desde PES + efector)
- Texto clínico principal: `encounter.note` o nota estructurada post-IA
- Codificación diagnósticos: ICD-10 en `Condition.code`

## Validación

- Golden file JSON en `common/tests/_data/fhir/encounter-document-v1.json`
- Test: mapper genera Bundle con `entry` mínimo Patient + Encounter + Composition

## Fuera de Fase 2

- Firmado digital del Bundle
- Compresión / MTOM
