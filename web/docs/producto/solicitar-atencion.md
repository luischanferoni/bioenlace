# Solicitar Atención (paciente)

## De qué se trata

Única puerta del asistente para que el **paciente** diga qué necesita: malestar nuevo, **control/seguimiento** o urgencia. El atajo se llama **Solicitar Atención** (`atencion.necesito-atencion`).

No es diagnóstico: el árbol fija rutas seguras (turno, teleconsulta, consulta por mensaje, derivación a urgencias) según el motivo.

## Motivos raíz

| Motivo (UI) | Código | Qué sigue |
|-------------|--------|-----------|
| **Malestar nuevo** | `malestar_nuevo` | Zona / detalle / evolución → servicio → modalidad (si aplica) → agenda |
| **Control/Seguimiento** | `seguimiento_cronico` | **Hub** de anclas (tratamiento, condición, protocolo, consulta general/previa, control general) |
| **Urgencia** | `urgencia` | Categoría de alarma → si banda A, **no** reserva en app (derivación 107 / guardia) |

Catálogo: `Scheduling/metadata/reserva_triage_catalog_v1.yaml`. Flujo: `intents/create/atencion.necesito-atencion.yaml`.

## Hub Control/Seguimiento

Tras elegir Control/Seguimiento, la UI lista anclas (API `consultas-seguimiento/hub`):

| Ancla | Origen | Siguiente paso típico |
|-------|--------|------------------------|
| Tratamiento | CarePlan activo | Necesidad (renovar, ajuste, consulta/evolución, turno, …) |
| Condición | Diagnóstico activo/crónico | Acciones del **protocolo** match (CIE) o defaults del hub |
| Control recomendado | Protocolo preventivo por **edad/sexo** | Mismas clases de acción (`prot:{id}`) |

Las mismas condiciones ACTIVE (deduplicadas) se listan en el **inicio** del paciente (`patient_conditions_active`); el detalle liviano reutiliza las acciones del hub. Las consultas por mensaje creadas con ancla de condición guardan `condition_codigo` / `condition_ref` en meta y se anidan bajo esa condición.

No incluye consulta suelta, atención previa ni “pedir turno” genérico: eso queda fuera de este paso.

Copy de recomendaciones de perfil: sugerencia según perfil, **no** indicación médica firme — «Consultá con tu equipo».

Detalle de consulta async y renovación/ajuste: [consultas-seguimiento.md](./consultas-seguimiento.md). Planes: [planes-de-tratamiento.md](./planes-de-tratamiento.md).

## Protocolos de cuidado (PlanDefinition-lite)

Catálogo en BD (`care_protocol`), no CarePacks IA ni YAML en runtime. Vacunas y preventivos los define el **superadmin** en la pantalla Yii **Datos → Protocolos de cuidado** (Nación o Provincia).

| Nombre | Uso |
|--------|-----|
| **Control recomendado** | Texto en el hub para el paciente (`hub_label`), p. ej. vacunas por edad/jurisdicción |
| **Protocolo de cuidado** | Nombre técnico (fila `care_protocol` / FHIR PlanDefinition-lite) |
| **Tratamiento** | CarePlan ya activo del paciente |

- Match por **código de condición** (`condition_match` activo/crónico) y/o **perfil** (edad, sexo), filtrado por jurisdicción del paciente.
- Acciones declarativas (`outcome` + `draft`); el motor genérico no enumera protocolos.
- CarePack / CareCohort = packs de asistencia IA; **otro dominio** ([asistencia-cohortes.md](./asistencia-cohortes.md)).

Decisión: [../decisions/care-protocols-plandefinition-lite.md](../decisions/care-protocols-plandefinition-lite.md).

## Separación de caminos

| Situación | Camino |
|-----------|--------|
| Malestar agudo, «necesito atención», alarmas | Solicitar Atención → Malestar / Urgencia |
| Renovar/ajustar medicación, control, consulta por mensaje, evolución | Solicitar Atención → **Control/Seguimiento** (hub) |
| Solo «sacar turno» sin motivo clínico | `turnos.crear-como-paciente` (sin triage de motivos) |
| Care pack pre/post consulta | Journey de encounter — [recorrido-pre-post-consulta.md](./recorrido-pre-post-consulta.md) |

La clasificación NL que habla de tratamiento, receta o seguimiento enruta a `atencion.necesito-atencion` (no hay intent aparte).

## Relación con otros documentos

- [triage-reserva-turno.md](./triage-reserva-turno.md) — alarmas, bandas, modalidad, persistencia en turno
- [teleconsulta-elegibilidad.md](./teleconsulta-elegibilidad.md)
- [consultas-seguimiento.md](./consultas-seguimiento.md) — async y acciones de tratamiento
- [turnos.md](./turnos.md)
- QA: [../qa/escenarios/seguimiento/README.md](../qa/escenarios/seguimiento/README.md)
