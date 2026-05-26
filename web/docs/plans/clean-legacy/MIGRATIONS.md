# Migraciones — clean-legacy (orden de ejecución)

Ejecutar desde `web/` (o ruta del proyecto Yii) con backup previo:

```bash
php yii migrate --migrationPath=@common/migrations
```

## Estado

Migraciones base **aplicadas** (incluye drop tabla padre `consultas`).

**Pendiente de código antes de ejecutar:** `m260526_150002` (drop tablas hijas).

**Puente derivaciones (si 150002 ya corrió):** `m260526_160001_recreate_consultas_derivaciones` — hasta cerrar 03e-1.

---

## Orden histórico (staging / producción con datos)

| # | Migración | Qué hace |
|---|-----------|----------|
| 1 | `m260520_100000_clinical_fhir_create_schema` | Tablas FHIR (`encounter`, `clinical_condition`, …) |
| 2 | `m260520_100001_clinical_fhir_prepare_external_refs` | Renombra FK externas (`referencia`, chat, motivos, enfermería) |
| 3 | `m260526_100002_personas_antecedentes_encounter_id` | `personas_antecedentes.id_consulta` → `encounter_id` |
| 4 | `m260526_130001_view_encounter_diagnostico` | Vista dual legacy/FHIR para diagnósticos |
| 5 | `m260526_120001_api_internacion_cambio_cama_rbac` | Permisos API cambio de cama |
| 6 | `m260526_140001_api_internacion_ingreso_rbac` | Permisos API ingreso internación |
| 7 | `m260520_100002_clinical_fhir_drop_legacy` | **Drop** `consultas`, `consultas_ia`, `consultas_configuracion` |
| 8 | `m260520_100003_turnos_appointment_fhir_columns` | Columnas appointment en turnos (si aplica) |

Opcionales / catálogo (sin dependencia del drop):

- `m260526_100001_api_laboratory_singular_rbac_canonical`
- `m260521_100008_encounter_definition_sanitize_legacy_workflow_urls`

## NO ejecutar aún

| Migración | Motivo |
|-----------|--------|
| `m260526_150002_clinical_fhir_drop_legacy_child_tables` | Elimina `consultas_motivos`, `consultas_derivaciones`, `diagnostico_consultas`, etc. El código aún lee esas tablas (columna `id_consulta` = `encounter.id`). Ejecutar solo cuando captura y reportes usen 100 % FHIR. |

## Greenfield

Si nunca existió `diagnostico_consultas`, `m260526_130001` crea la vista desde `clinical_condition`.

Si `m260526_130001` falló antes del fix dual, re-ejecutar tras actualizar el archivo de migración.

## Post-migración (smoke)

- Login staff + `site/pacientes` (EMER / IMP).
- `POST .../clinical/emergency-guardia/iniciar-atencion` → `encounter_id`.
- Ingreso internación: mapa → `/internacion/ingreso` → API → episodio.
- Referencias: listado paciente sin error.
- Planillas reporte 4/5/7/9/farmacia con `Encounter`.
