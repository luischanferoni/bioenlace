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
3. Farmacia/control: `verificar-receta` con el token impreso en PDF o escaneando el QR del PDF.

### QR en PDF

En `params-local.php`:

```php
'recetaDigitalRepository' => [
    'verificationPublicBaseUrl' => 'https://TU-HOST/api/v1',
],
```

Sin esa URL el PDF muestra el código alfanumérico pero no genera imagen QR.

### Repositorio nacional (conexión, sin envío real aún)

Ver [plans/receta-electronica/phases/03-repositorio-nacional.md](../plans/receta-electronica/phases/03-repositorio-nacional.md). Por defecto el conector `null` no llama a MSAL; al emitir se registra evento `repository_sync` con estado `skipped`.

### Seed de desarrollo

```bash
php yii clinical-seed/prescription-demo --persona=<id_persona>
php yii clinical-seed/prescription-demo-info --persona=<id_persona>
php yii clinical-seed/prescription-demo-remove --persona=<id_persona>
```

Crea receta `issued` número `DEV-RX-{id_persona}` con dos medicamentos demo. Reutiliza el encounter del care plan demo si existe.

### Código

- `common/components/Clinical/Prescription/`
- `common/models/Clinical/ElectronicPrescription*.php`
- `clinical/ElectronicPrescriptionController`

### Migración

```bash
php yii migrate --migrationPath=@common/migrations
```
