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

## Paso 5+ (pendiente)

- Drop `consultas`.
- `ReporteController` + planillas (backlog facturación).
- Migración BD `personas_antecedentes.id_consulta` → `encounter_id` (03c.2).
