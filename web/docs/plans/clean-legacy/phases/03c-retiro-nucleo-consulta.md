# Fase 03c — Retiro núcleo Consulta

## Paso 1 — `PersonasAntecedente` (hecho)

- Trait `EncounterIdLegacyConsultaColumnTrait`: columna BD `id_consulta` ↔ propiedad `encounter_id`.
- `PersonasAntecedente` y `PersonasAntecedenteFamiliar`: reglas y relación `getEncounter()`.
- Métodos nuevos: `getPersonasAntecedentePorEncounter`, `hardDeleteGrupoPorEncounter` (+ alias `@deprecated` con nombre consulta).
- `EncuestaParchesMamariosController`: asigna `encounter_id` en antecedentes.

**BD:** sin rename aún; migración `id_consulta` → `encounter_id` en `personas_antecedentes` queda para 03c.2.

## Paso 2 — Motivos API (pendiente)

- `PacientesController`, `MotivosConsultaController` → `Encounter` + `ConsultaMotivosMessage`.

## Paso 3+ (pendiente)

- `ConsultaProcesamientoService` sin escribir `consultas`.
- Autofacturación / referencias con `legacy_id_consulta`.
- Drop `consultas`.
