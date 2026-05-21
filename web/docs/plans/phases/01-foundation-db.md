# Fase 1 — Fundación de base de datos

**Programa:** [PROGRAM.md](../PROGRAM.md)  
**Depende de:** [Fase 0](./00-governance.md)  
**Estado:** hecho (migraciones 2026-05-20; aplicar en dev con `yii migrate`)

## Objetivo

Crear el esquema FHIR-native en MySQL/MariaDB y **retirar tablas clínicas legacy** del modelo objetivo (drop o rename en migración única según entorno).

## Tablas nuevas (mínimo viable)

### Núcleo

| Tabla | Notas |
|-------|--------|
| `encounter` | Ex `consultas`: `subject_persona_id`, `appointment_id`, `encounter_class`, `status`, `period`, `service_id`, `efector_id`, `parent` polimórfico si aplica |
| `encounter_definition` | Ex `consultas_configuracion`: `pasos_json` → `workflow_json` |
| `episode_of_care` | Admisión / programa; vínculo a internación o programa |
| `care_plan` | `subject_persona_id`, `status`, `intent`, `category`, `period_start/end`, `encounter_id` nullable, `episode_of_care_id` nullable |
| `care_plan_activity` | `care_plan_id`, `resource_type`, `resource_id`, `sort_order`, `status` espejo |
| `goal` | Metas terapéuticas |
| `clinical_condition` | Ex `diagnostico_consultas` (recurso FHIR Condition) |

### Órdenes

| Tabla | Notas |
|-------|--------|
| `medication_request` | Dosis, frecuencia, duración, `status` FHIR |
| `service_request` | Código SNOMED, categoría (lab, imagen, kinesio, referral), `status` |
| `procedure` | Estados Procedure FHIR; vínculo `service_request_id` opcional |
| `device_request` | Prótesis, órtesis, lentes (si no tabla aparte) |
| `nutrition_order` | Ex régimen |
| `medication_administration` | Suministro / administración |
| `observation` | Signos, balance, medidas |
| `clinical_impression` | Evolución narrativa estructurada |
| `allergy_intolerance` | Ex `alergias` o migrar tabla |

### Extensiones (según especialidad)

| Tabla | Notas |
|-------|--------|
| `procedure_odontology_ext` | Pieza, caras, tiempo PASADA/PRESENTE/FUTURA |
| `vision_prescription` | Receta lentes |

## Tablas a eliminar (post-migración schema)

`consultas`, `diagnostico_consultas`, `consultas_medicamentos`, `consultas_practicas`, `consultas_derivaciones`, `consultas_regimen`, `consultas_suministro_medicamento`, `consultas_motivos`, `consultas_sintomas`, `consultas_evolucion`, `consultas_obstetricia`, `consultas_balancehidrico`, `consultas_odontologia_*`, `consulta_practicas_oftalmologia*`, `consultas_receta_lentes`, `seg_nivel_internacion_medicamento`, `seg_nivel_internacion_practica`, … (lista completa en [MIGRATION_STATUS.md](../MIGRATION_STATUS.md)).

**`personas`, `turnos`:** se mantienen; columnas adicionales Appointment si faltan (`fhir_status`, etc.).

## Migraciones Yii

- [x] Migraciones: `m260520_100000` … `m260520_100003` en `web/common/migrations/`.
- [x] Índices: `(subject_persona_id, status)` en `care_plan`; `(encounter_id)` en órdenes.
- [x] FKs y `ON DELETE` definidos (restricción suave en clínico).

## Datos existentes

Decidir explícitamente:

- [x] **Greenfield** en dev (sin ETL en estas migraciones).
- [ ] Scripts ETL producción (sub-plan aparte si aplica).

## Fuera de alcance

- Classes PHP AR.
- API.

## Definition of Done

- Migraciones aplican en entorno dev limpio.
- Diagrama ER en PROGRAM.md coincide con tablas creadas.
- `MIGRATION_STATUS.md` columnas “Tabla objetivo” en `hecho` para núcleo fase 1.

## Siguiente fase

[Fase 2 — common/Clinical](./02-common-clinical.md)
