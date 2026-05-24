# Design — Receta electrónica

## Decisiones

| Tema | Decisión |
|------|----------|
| Orden vs receta | `medication_request` = intención en encounter; `electronic_prescription` = documento emitido (snapshot legal) |
| Emisión | Acto explícito (`emitir`); no auto-emitir al guardar medicación |
| Identificador | `prescription_number` único al emitir (`RX-YYYYMMDD-…`) |
| Perfil FHIR | Mapper a `recetaDigitalRegistroRecetaAR` en Fase 1 como JSON snapshot (`fhir_bundle_json`), sin envío externo |
| API | Prefijo `/api/v1/clinical/electronic-prescription/` y rutas por encounter |
| Paciente | Solo lectura de propias recetas `issued` |

## Estados legales (`PrescriptionLegalStatus`)

| Estado | Significado |
|--------|-------------|
| `draft` | Borrador editable |
| `issued` | Emitida (vigente para consulta paciente) |
| `cancelled` | Anulada por prescriptor |
| `expired` | Reservado (job futuro por `valid_until`) |

## Separación de programas

Este plan **reabre** interoperabilidad de export receta fuera del programa FHIR clínico base ([fhir-clinical.md](../decisions/fhir-clinical.md)): la receta es subdominio `Clinical/Prescription/`.

## PRs sugeridos

1. Esquema + modelos + servicios + API Fase 1.
2. Firma + PDF/QR (Fase 2).
3. Conector repositorio (Fase 3).
