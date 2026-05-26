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

## Paso 3+ (pendiente)

- `ConsultaProcesamientoService` sin escribir `consultas`.
- Autofacturación / referencias con `legacy_id_consulta`.
- Drop `consultas`.
