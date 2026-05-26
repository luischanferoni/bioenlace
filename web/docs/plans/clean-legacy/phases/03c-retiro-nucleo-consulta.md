# Fase 03c — Retiro núcleo Consulta

## Paso 1 — `PersonasAntecedente` (hecho)

- Trait `EncounterIdLegacyConsultaColumnTrait`: columna BD `id_consulta` ↔ propiedad `encounter_id`.
- `PersonasAntecedente` y `PersonasAntecedenteFamiliar`: reglas y relación `getEncounter()`.
- Métodos nuevos: `getPersonasAntecedentePorEncounter`, `hardDeleteGrupoPorEncounter` (+ alias `@deprecated` con nombre consulta).
- `EncuestaParchesMamariosController`: asigna `encounter_id` en antecedentes.

**BD:** sin rename aún; migración `id_consulta` → `encounter_id` en `personas_antecedentes` queda para 03c.2.

## Paso 2 — Motivos API (hecho)

- `MotivosConsultaController` ya operaba sobre `encounter_id` + `EncounterAccessService`.
- `EncounterAppointmentReasonLookupService`: último motivo / encounter desde turno (sin `consultas`).
- `PacientesController::actionInformacionMedica` y agenda ambulatoria: `Encounter` + mensajes por `encounter_id`; alias API `consulta_id` = `encounter_id`.

## Paso 3 — Persistencia clínica sin `consultas` (hecho)

- `ConsultaProcesamientoService::guardar()` delega en `EncounterDocumentationService` (FHIR) y deja de crear filas en tabla `consultas`.

## Paso 4 — Autofacturación / referencias (hecho)

- `LegacyIdConsultaAsEncounterColumnTrait`: columna `legacy_id_consulta` (fallback `id_consulta`) → encounter id.
- `Autofacturacion`, `Referencia`: FK dinámica + relación `getEncounter()`.
- `EncounterSumarAutofacturacionContext` + `AutofacturacionEncounterBusqueda`: listados SUMAR sobre `Encounter`.
- `AutofacturacionController`: index / enviadas / no procesadas / mapear / enviar sin `Consulta::findOne`.
- `Referencia::getDatosPersona*`: joins `encounter` + `turnos` + `personas` (sin `consultas`).
- Vistas autofacturación: diagnósticos/prácticas vía `Condition` / `ServiceRequest`.

## Paso 5 — Reportes / planillas (hecho)

- `EncounterReporteBusqueda`: planillas 4, 5, 7, 9 y reporte farmacia sobre `encounter` (sin `consultas` en listados).
- `ReporteController`: usa `EncounterReporteBusqueda` en todas las acciones.
- Vistas `_planilla4`, `_planillaC7`, `_reporteFarmacia`: cargan `Encounter` + FHIR (`Condition`, `ServiceRequest`, `MedicationRequest`) con fallback tablas hijas legacy.
- `ConsultaOdontologiaEstados::getCPOHastaEncounter()`: CPO/CEO sin join a `consultas`.

## Paso 6 — `personas_antecedentes.encounter_id` (hecho)

- Migración `m260526_100002_personas_antecedentes_encounter_id`: rename `id_consulta` → `encounter_id`.
- `EncounterIdLegacyConsultaColumnTrait`: FK dinámica (`encounter_id` / fallback `id_consulta`).
- `PersonasAntecedenteBusqueda`: agregados por servicio vía `encounter` (sin `consultas`).

## Paso 7 — Preparación drop `consultas` (hecho en código)

- Sin nuevas filas en `consultas` al crear turnos (`ensureFromTurno`).
- Búsquedas nomenclador / referencias / derivaciones sin join a `consultas`.
- Auditoría y orden de migraciones: [03c-paso7-drop-consultas-audit.md](./03c-paso7-drop-consultas-audit.md).

**Pendiente operativo:** aplicar `m260520_100002_clinical_fhir_drop_legacy` tras smoke en staging y cerrar bloqueadores listados en la auditoría.

## Paso 8+ (backlog)

- Retirar AR `Consulta` y tablas hijas ya dropeadas.
- Internación MVC por pestaña, `ConsultasConfiguracion` → `EncounterDefinition` único.
