# Receta electrónica — documentación operativa

Plan activo: [plans/receta-electronica/](../plans/receta-electronica/).

## Fase 1 (MVP modo A)

Receta **emitida** separada de `medication_request`. Sin repositorio nacional ni firma.

### API

| Método | Ruta |
|--------|------|
| POST | `/api/v1/clinical/encounter/<id>/electronic-prescription/crear-borrador` |
| GET | `/api/v1/clinical/encounter/<id>/electronic-prescriptions` |
| GET | `/api/v1/clinical/electronic-prescription/<id>` |
| POST | `/api/v1/clinical/electronic-prescription/<id>/emitir` |
| POST | `/api/v1/clinical/electronic-prescription/<id>/anular` |
| GET | `/api/v1/clinical/electronic-prescription/mis-recetas-como-paciente` |

### Flujo

1. Médico documenta medicación → `medication_request` en el encounter.
2. `crear-borrador` copia ítems activos a `electronic_prescription` + `electronic_prescription_item`.
3. `emitir` asigna `prescription_number`, vigencia 30 días y snapshot FHIR en `fhir_bundle_json`.
4. Paciente lista con `mis-recetas-como-paciente` (solo `issued`).

### Código

- `common/components/Clinical/Prescription/`
- `common/models/Clinical/ElectronicPrescription*.php`
- `clinical/ElectronicPrescriptionController`

### Migración

```bash
php yii migrate --migrationPath=@common/migrations
```
