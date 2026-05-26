# Paso 7 — Drop tabla `consultas` (auditoría)

## Hecho en código (Paso 7a)

- `EncounterLifecycleService::ensureFromTurno()` — altas de turno ya no insertan en `consultas`.
- `TurnoPersistService` + `TurnosController` (sobreturno): delegan en `ensureFromTurno`.
- `Consulta::createFromTurno()` deprecado → `Encounter`.
- Nomenclador / estadísticas: búsquedas sobre `encounter` + FHIR (`condition`, `medication_request`, `service_request`).
- `ReferenciasBusquedas`, derivaciones (`ConsultaDerivaciones`), odontología CPO, `PacienteController::obtenerConfiguracion`, vista referencias.

## Migración BD (aplicar en orden)

1. `m260520_100001_clinical_fhir_prepare_external_refs`
2. `m260526_100002_personas_antecedentes_encounter_id`
3. **`m260520_100002_clinical_fhir_drop_legacy`** — drop `consultas` + tablas hijas listadas en la migración

Solo ejecutar (3) en entorno con backup y smoke tests.

## Bloqueadores restantes (post-drop romperían si no se migran antes)

| Área | Archivos / notas |
|------|------------------|
| Modelo AR `Consulta` | Sigue mapeando tabla `consultas`; retirar o stub tras drop |
| `ConsultaBusqueda` | Sin callers activos en controllers; eliminar con el AR |
| `ConsultasConfiguracion` / workflow legacy | Internación MVC, layouts, `PersonasController` |
| `ConsultaProcesamientoService::analizar()` | Análisis IA aún referencia modelo legacy |
| Odontología | Tablas `consultas_odontologia_*` incluidas en drop — usar `OdontologyEncounterService` |
| Derivaciones | Tabla `consultas_derivaciones` en drop — migrar a ServiceRequest referral |
| Vistas internación `v2/_view_*` | Medicamentos/prácticas legacy |
| `DiagnosticoConsultaRepository` | Vista `view_consulta_diagnostico` |
| `SegNivelInternacionRepository` | Joins a `consultas` |

## Verificación sugerida antes del drop

- [ ] Crear turno API → fila en `encounter` con `appointment_id`, sin fila nueva en `consultas`
- [ ] Nomenclador (motivos, diagnósticos, medicamentos, prácticas) carga grids
- [ ] Referencias / derivaciones listan pacientes
- [ ] Planillas reporte (paso 5) siguen generando PDF
- [ ] `php yii migrate` hasta `m260526_100002` en staging
