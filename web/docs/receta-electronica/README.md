# Receta electrónica — documentación operativa

Plan activo: [plans/receta-electronica/](../plans/receta-electronica/).

## Fase 1 (MVP modo A)

Receta **emitida** separada de `medication_request`. Sin repositorio nacional ni firma PKI.

### API staff / encounter

| Método | Ruta |
|--------|------|
| POST | `/api/v1/clinical/encounter/<id>/electronic-prescription/crear-borrador` |
| GET | `/api/v1/clinical/encounter/<id>/electronic-prescriptions` |
| GET | `/api/v1/clinical/electronic-prescription/<id>` |
| POST | `/api/v1/clinical/electronic-prescription/<id>/emitir` |
| POST | `/api/v1/clinical/electronic-prescription/<id>/anular` |

### Flujo staff

1. Médico documenta medicación → `medication_request` en el encounter.
2. `crear-borrador` copia ítems activos a `electronic_prescription` + `electronic_prescription_item`.
3. `emitir` asigna `prescription_number`, vigencia 30 días, snapshot FHIR y campos de verificación (`verification_token`, `document_hash`, `signature_provider=bioenlace-internal`).

## Fase 2 (PDF + UI paciente)

### API paciente / verificación

| Método | Ruta |
|--------|------|
| GET/POST | `/api/v1/clinical/electronic-prescription/mis-recetas-como-paciente` (ui_json) |
| GET/POST | `/api/v1/clinical/electronic-prescription/ver-receta-como-paciente` (ui_json) |
| GET | `/api/v1/clinical/electronic-prescription/descargar-pdf-como-paciente?prescription_id=` |
| GET | `/api/v1/clinical/electronic-prescription/verificar-receta?token=` |

### Asistente

Intent: `receta.ver-recetas-como-paciente` (listado → detalle → PDF).

### Flujo paciente

1. Lista solo recetas `issued` vía asistente o `mis-recetas-como-paciente`.
2. Detalle con mensaje formateado + widget `prescription_pdf_download`.
3. Farmacia/control: `verificar-receta` con el token impreso en PDF.

### Código

- `common/components/Clinical/Prescription/`
- `common/models/Clinical/ElectronicPrescription*.php`
- `clinical/ElectronicPrescriptionController`

### Migración

```bash
php yii migrate --migrationPath=@common/migrations
```
