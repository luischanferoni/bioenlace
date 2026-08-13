# Design

## Identidad (quién es)

Un solo núcleo: `RegistroStaffPacienteService` + `RegistroService`.

| Camino | Entrada | Resultado |
|--------|---------|-----------|
| Paciente conocido | Búsqueda local → `id_persona` | Ingreso sin alta |
| DNI lector | PDF417 (`codigo_barras`) o documento + sexo → RENAPER | Alta/reuse con `acredita_identidad = 1` |
| Didit (fase 2) | `verification_id` KYC (doc + selfie + liveness) | Igual que app paciente |
| NN / sin documento (fase 3) | Episodio con identidad pendiente | **No** inventar `Persona` con DNI trucho |

El administrativo **no** completa nombre/apellido/fecha: los trae el DNI o RENAPER. Teléfono de contacto del ingreso (acompañante) sí es dato operativo del episodio.

Domicilio: igual que la app (cron / gateway), no edición en ventanilla.

## Ingreso a guardia

`GuardiaIngresoService::ingresar`:

1. `id_persona` conocido, **o**
2. Identidad DNI → `RegistroStaffPacienteService::registrar(modo=dni_lector)` → `id_persona`, **o**
3. (fase 2) Didit.
4. (fase 3) `identidad_pendiente` → placeholder por episodio (sin documento); luego `vincularIdentidad`.

Ya no: `PersonaAltaOperativaService` (teclado libre).

La UI web ofrece paciente **conocido** o **NN** (leyenda: si no está en el sistema, app Personal de Salud para escanear DNI). DNI/Didit solo en app (`ingreso_dni_clients: mobile`): consulta `preview-renaper-como-staff` o Didit, confirmación de solo lectura, y al confirmar `ingresar` con documento+sexo, código de barras o `verification_id` (el dominio vuelve a validar; no confía en campos editables de nombre).

## Sesión de ventanilla (fase 4)

Tras DNI/Didit: fila `ventanilla_sesion` (staff, sujeto, efector, método, `expires_at`) + knob YAML `ttl_minutes`. El JWT sigue siendo del staff; el sujeto se resuelve en `PersonRepresentationSubjectService` **solo** para `scheduling.turno`. No usa `person_related`, ni `subjectPersonaPaciente`, ni cambia la sesión operativa (efector / encounter).

UI web de ventanilla (botón / modal / banner) **retirada**. Los intents `turnos.crear-como-paciente` / `ver-mis-turnos-como-paciente` se re-muestran en el catálogo staff **solo** con ventanilla activa vía API (`unhide_paciente_intent_ids` en el YAML).

## Selfie

| Uso | Fase |
|-----|------|
| KYC Didit (selfie + DNI) cuando no hay lector | 2 |
| Face match 1:1 si ya hay `didit_reference_id` y olvidó el DNI | 2 (opcional) |
| Selfie sola de desconocido | No |
