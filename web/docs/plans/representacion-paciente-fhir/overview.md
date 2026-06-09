# Overview — Representación de paciente

## Problema

Hoy Bioenlace asume **un usuario = una persona** (`getIdPersona()` = sujeto del turno, motivos, care-packs, recetas). No hay modelo reutilizable para:

- Padre/madre que gestiona turnos de un **menor sin cuenta**
- Adulto que **delega** a otra persona con cuenta para que opere por él

Existe parentesco **ad hoc** en programas (p. ej. diabetes) y antecedentes familiares clínicos, pero no representación operativa ni autorización transversal.

## Actores

| Actor | Rol |
|-------|-----|
| **Titular de cuenta** | Adulto con `id_user`; puede ser tutor (A) o representante (B) |
| **Sujeto (Patient)** | `Persona` sobre la que se actúa; menor sin login o paciente delegante |
| **Staff** | Verifica vínculo A, bloquea por orden legal, revoca vínculos |
| **Sistema** | Valida identidad (RENAPER/Didit), audita actos en nombre de otro |

## Dos regímenes

| | Régimen A — Tutela verificada | Régimen B — Delegación |
|---|------------------------------|------------------------|
| **Inicio** | Padre/madre/tutor + staff | Paciente designa representante |
| **Sujeto** | Menor (sin cuenta) | Paciente con cuenta |
| **Activación** | Staff verifica | Designación paciente (sin aceptación rep.) |
| **Fin** | Revoca staff o hijo (cuando tenga cuenta) | Revoca paciente o staff |
| **FHIR** | `RelatedPerson` + verificación | `RelatedPerson` + `Consent` |

## Entregables por fase

| Fase | Entrega |
|------|---------|
| 1 | Tablas FHIR-aligned, catálogo parentesco, servicios dominio, auditoría |
| 2 | API staff régimen A + alta menor + listado “mis familiares” |
| 3 | API régimen B: designar, revocar, listar representantes |
| 4 | `PersonRelationshipAccessService` en turnos, motivos, cohortes, recetas, HC |
| 5 | Flutter selector “a cargo de”, prefs notificación, intents asistente |

## Fuera de alcance inicial

- Árbol genealógico completo / API gubernamental de parentesco
- Menor con cuenta propia
- Aceptación obligatoria del representante (B)
- Corte automático del vínculo A al cumplir 18 años
- Migrar `PersonaProgramaDiabetes::PARENTESCO` (puede unificarse después)

## Punto de partida en código

| Área | Hoy |
|------|-----|
| Identidad | `RegistroService` (Didit), RENAPER vía MPI (admin) |
| Acceso encounter | `EncounterAccessService` — solo sujeto o profesional |
| Turnos paciente | `TurnosController` — `getIdPersona()` como paciente del turno |
| Parentesco suelto | `PersonaProgramaDiabetes::PARENTESCO` (no reutilizar como modelo) |
