# Fase 03 — Consulta: desacople guardia + huérfanos

## Objetivo

Avanzar hacia retiro de `common\models\Consulta` y tabla `consultas` sin romper flujos activos. La migración greenfield `m260520_100002_clinical_fhir_drop_legacy` ya define el drop de `consultas` y tablas hijas en entornos que la aplicaron.

## Ejecutado en esta fase

### A. Guardia / EMER → `Encounter`

- `GuardiaOperacionService`: resuelve encounter vía `GuardiaEncounterResolver`; respuesta `encounter_id` (ya no `id_consulta`).
- `GuardiaClinicalSummaryService`, `GuardiaQueueService`, `Guardia`: constantes `Encounter::PARENT_GUARDIA` / `ENCOUNTER_CLASS_EMER`.
- `PatientHistoriaUrl`: documentación alineada a `Encounter::PARENT_*`.

### B. Modelos / búsquedas huérfanas

| Archivo | Motivo |
|---------|--------|
| `ConsultaIA.php` (`ConsultaIa`) | Tabla `consultas_ia` en drop legacy; relación y escritura quitadas de `Consulta` / `ConsultaProcesamientoService` |
| `ConsultarValidaciones.php` | Form legacy sin uso |
| `ConsultaPracticasOftalmologiaBusqueda.php` | Sin instanciación |
| `ConsultasRecetaLentesBusqueda.php` | Sin instanciación |
| `ConsultaSuministroMedicamentoBusqueda.php` | Sin instanciación |

## Bloqueado — requiere migración de dominio (Fase 03b / 04)

| Área | Dependencia de `Consulta` |
|------|---------------------------|
| `ConsultaProcesamientoService` / `EncounterDocumentationService` | IA + guardado (bridge) |
| `ConsultaAccessService` / `PacientesController` | Autorización API |
| `ConsultaDerivaciones`, turnos, referencias | Derivaciones activas |
| `AutofacturacionController`, `ReporteController` | SUMAR / planillas |
| `EncuestaParchesMamariosController` | Crea fila `consultas` + `atenciones_enfermeria` |
| `ConsultaAtencionesEnfermeria`, `DiagnosticoConsulta`, especialidades | Datos + internación v2 |
| `NomencladorController` | ABM legacy motivos/meds/prácticas |
| Modelo `Consulta` + `ConsultaBusqueda` | Núcleo |

## No ejecutar aún

- `dropTable consultas` en producción sin ETL.
- Borrar `Consulta.php` ni hijos con relación activa en `EncounterDocumentationService`.
