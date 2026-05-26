# QA — Catálogo de casos de prueba Bioenlace

Documentación **exhaustiva** para pruebas manuales y smoke de regresión. Cada caso tiene ID único, prioridad, actor, superficie, pasos y resultado esperado.

**No sustituye** tests automatizados; complementa QA exploratorio y validación por release.

---

## Cómo usar

1. Elegir **prioridad** (P0 primero en cada release).
2. Abrir el archivo del módulo o buscar el **ID** en la tabla maestra.
3. Completar la tabla **Registro de ejecución** al final de cada caso (o en hoja externa vinculando el ID).
4. Cruzar con [matriz actores × módulos](./matriz-actores-modulos.md) para cubrir huecos.

**Plantilla:** [_plantilla-caso.md](./_plantilla-caso.md)

**Producto (contexto):** [docs/producto/](../producto/README.md) · **Madurez HIS:** [his-completo/](../his-completo/README.md)

---

## Prioridades

| Nivel | Criterio |
|-------|----------|
| **P0** | Bloquea operación diaria o regresión crítica post-deploy |
| **P1** | Funcional importante; debe cubrirse en sprint / release minor |
| **P2** | Edge, admin, módulos parciales, entornos sin feature flag |

---

## Archivos por dominio

| Archivo | Contenido |
|---------|-----------|
| [00-transversal.md](./00-transversal.md) | Auth, sesión, personas, RBAC, migraciones |
| [01-captura-clinica.md](./01-captura-clinica.md) | Encounter, IA, workflow, especialidades |
| [02-turnos-agenda.md](./02-turnos-agenda.md) | Reserva, lista espera, referencias, agenda staff |
| [03-urgencias-guardia.md](./03-urgencias-guardia.md) | Triage, tablero, derivación, egreso |
| [04-internacion.md](./04-internacion.md) | Mapa, ingreso, piso, alta, plantillas |
| [05-laboratorio-receta-planes.md](./05-laboratorio-receta-planes.md) | LIS, receta, care plans, adherencia |
| [06-reportes-nomenclador.md](./06-reportes-nomenclador.md) | Planillas, grids FHIR, export legal |
| [07-asistente-intents.md](./07-asistente-intents.md) | Los 29 intents YAML |
| [08-post-clean-legacy-fhir.md](./08-post-clean-legacy-fhir.md) | Validación migración FHIR (03e–04) |

---

## Tabla maestra de casos (índice)

### 00 — Transversal

| ID | Título | P | Archivo |
|----|--------|---|---------|
| CU-TR-001 | Login staff web | P0 | [00](./00-transversal.md#cu-tr-001) |
| CU-TR-002 | Sesión operativa (`set-session`) | P0 | [00](./00-transversal.md#cu-tr-002) |
| CU-TR-003 | Menú principal sin rutas 404 | P0 | [00](./00-transversal.md#cu-tr-003) |
| CU-TR-004 | Login paciente app (JWT) | P0 | [00](./00-transversal.md#cu-tr-004) |
| CU-TR-005 | Buscar persona (staff) | P0 | [00](./00-transversal.md#cu-tr-005) |
| CU-TR-006 | Alta / registro paciente | P1 | [00](./00-transversal.md#cu-tr-006) |
| CU-TR-007 | Migraciones clean-legacy (160002→150002→170001) | P0 | [00](./00-transversal.md#cu-tr-007) |
| CU-TR-008 | Rutas MVC retiradas devuelven 410 o sin permiso | P1 | [00](./00-transversal.md#cu-tr-008) |

### 01 — Captura clínica

| ID | Título | P | Archivo |
|----|--------|---|---------|
| CU-CAP-001 | Timeline paciente carga historial | P0 | [01](./01-captura-clinica.md#cu-cap-001) |
| CU-CAP-002 | Analizar texto libre → borrador FHIR | P0 | [01](./01-captura-clinica.md#cu-cap-002) |
| CU-CAP-003 | Guardar encounter (dx + meds + prácticas) | P0 | [01](./01-captura-clinica.md#cu-cap-003) |
| CU-CAP-004 | Captura con audio / transcripción | P1 | [01](./01-captura-clinica.md#cu-cap-004) |
| CU-CAP-005 | Permiso atención (`validarPermisoAtencion`) turno | P0 | [01](./01-captura-clinica.md#cu-cap-005) |
| CU-CAP-006 | Captura parent INTERNACION | P0 | [01](./01-captura-clinica.md#cu-cap-006) |
| CU-CAP-007 | Captura parent GUARDIA / EMER | P0 | [01](./01-captura-clinica.md#cu-cap-007) |
| CU-CAP-008 | Workflow odontología (prácticas + estados CPO) | P1 | [01](./01-captura-clinica.md#cu-cap-008) |
| CU-CAP-009 | Workflow oftalmología + receta lentes | P1 | [01](./01-captura-clinica.md#cu-cap-009) |
| CU-CAP-010 | Derivación en captura → service_request referral | P0 | [01](./01-captura-clinica.md#cu-cap-010) |
| CU-CAP-011 | Balance hídrico / régimen / suministro (IMP) | P1 | [01](./01-captura-clinica.md#cu-cap-011) |
| CU-CAP-012 | API encounter 401 sin token | P1 | [01](./01-captura-clinica.md#cu-cap-012) |

### 02 — Turnos y agenda

| ID | Título | P | Archivo |
|----|--------|---|---------|
| CU-TUR-001 | Agenda staff `/turnos/index` | P0 | [02](./02-turnos-agenda.md#cu-tur-001) |
| CU-TUR-002 | Lista de espera `/turnos/espera` | P0 | [02](./02-turnos-agenda.md#cu-tur-002) |
| CU-TUR-003 | Crear turno staff (API) | P0 | [02](./02-turnos-agenda.md#cu-tur-003) |
| CU-TUR-004 | Paciente crea turno (app / API) | P0 | [02](./02-turnos-agenda.md#cu-tur-004) |
| CU-TUR-005 | Cancelar turno paciente (política anticipación) | P0 | [02](./02-turnos-agenda.md#cu-tur-005) |
| CU-TUR-006 | Reprogramar / reubicar turno | P1 | [02](./02-turnos-agenda.md#cu-tur-006) |
| CU-TUR-007 | Referencias listado `/referencias` | P0 | [02](./02-turnos-agenda.md#cu-tur-007) |
| CU-TUR-008 | Derivación pendiente → turno CON_TURNO | P0 | [02](./02-turnos-agenda.md#cu-tur-008) |
| CU-TUR-009 | Sobreturno staff | P1 | [02](./02-turnos-agenda.md#cu-tur-009) |
| CU-TUR-010 | No se presentó | P1 | [02](./02-turnos-agenda.md#cu-tur-010) |
| CU-TUR-011 | Calendario / eventos profesional | P1 | [02](./02-turnos-agenda.md#cu-tur-011) |
| CU-TUR-012 | Indicadores agenda (API / asistente) | P1 | [02](./02-turnos-agenda.md#cu-tur-012) |
| CU-TUR-013 | Conflicto agenda / cancelación masiva | P1 | [02](./02-turnos-agenda.md#cu-tur-013) |
| CU-TUR-014 | Confirmar asistencia | P2 | [02](./02-turnos-agenda.md#cu-tur-014) |
| CU-TUR-015 | Push notificación cambio turno | P2 | [02](./02-turnos-agenda.md#cu-tur-015) |

### 03 — Urgencias / guardia

| ID | Título | P | Archivo |
|----|--------|---|---------|
| CU-EMER-001 | Tablero guardia (inicio EMER) | P0 | [03](./03-urgencias-guardia.md#cu-emer-001) |
| CU-EMER-002 | Registrar triage Manchester | P0 | [03](./03-urgencias-guardia.md#cu-emer-002) |
| CU-EMER-003 | Tomar caso / asignar médico | P0 | [03](./03-urgencias-guardia.md#cu-emer-003) |
| CU-EMER-004 | Iniciar atención → captura | P0 | [03](./03-urgencias-guardia.md#cu-emer-004) |
| CU-EMER-005 | Derivar a otro efector | P1 | [03](./03-urgencias-guardia.md#cu-emer-005) |
| CU-EMER-006 | Finalizar / egreso guardia | P0 | [03](./03-urgencias-guardia.md#cu-emer-006) |
| CU-EMER-007 | Indicadores resumen día | P1 | [03](./03-urgencias-guardia.md#cu-emer-007) |
| CU-EMER-008 | Solicitar internación desde guardia | P0 | [03](./03-urgencias-guardia.md#cu-emer-008) |
| CU-EMER-009 | Push triage crítico / asignación | P2 | [03](./03-urgencias-guardia.md#cu-emer-009) |
| CU-EMER-010 | Re-triage | P2 | [03](./03-urgencias-guardia.md#cu-emer-010) |

### 04 — Internación

| ID | Título | P | Archivo |
|----|--------|---|---------|
| CU-IMP-001 | Mapa de camas | P0 | [04](./04-internacion.md#cu-imp-001) |
| CU-IMP-002 | Marcar cama libre / bloqueada / aislamiento | P1 | [04](./04-internacion.md#cu-imp-002) |
| CU-IMP-003 | Ingreso internación (web / flow) | P0 | [04](./04-internacion.md#cu-imp-003) |
| CU-IMP-004 | Atender desde mapa → timeline IMP | P0 | [04](./04-internacion.md#cu-imp-004) |
| CU-IMP-005 | Cambio de cama (flow) | P1 | [04](./04-internacion.md#cu-imp-005) |
| CU-IMP-006 | Alta estructurada + epicrisis | P0 | [04](./04-internacion.md#cu-imp-006) |
| CU-IMP-007 | ABM plantillas epicrisis | P1 | [04](./04-internacion.md#cu-imp-007) |
| CU-IMP-008 | Ficha `/internacion/view` administrativa | P1 | [04](./04-internacion.md#cu-imp-008) |
| CU-IMP-009 | Bundle clínico API (meds, prácticas, balance) | P1 | [04](./04-internacion.md#cu-imp-009) |
| CU-IMP-010 | Controllers MVC clínico retirados (410) | P1 | [04](./04-internacion.md#cu-imp-010) |

### 05 — Laboratorio, receta, planes

| ID | Título | P | Archivo |
|----|--------|---|---------|
| CU-LAB-001 | Paciente ve resultados laboratorio | P0 | [05](./05-laboratorio-receta-planes.md#cu-lab-001) |
| CU-LAB-002 | Sincronizar resultados (paciente) | P1 | [05](./05-laboratorio-receta-planes.md#cu-lab-002) |
| CU-REC-001 | Emitir receta electrónica + PDF | P0 | [05](./05-laboratorio-receta-planes.md#cu-rec-001) |
| CU-REC-002 | Paciente ve recetas | P1 | [05](./05-laboratorio-receta-planes.md#cu-rec-002) |
| CU-PLAN-001 | Care plan agudo en captura | P1 | [05](./05-laboratorio-receta-planes.md#cu-plan-001) |
| CU-PLAN-002 | Recordatorios paciente | P1 | [05](./05-laboratorio-receta-planes.md#cu-plan-002) |
| CU-PLAN-003 | Adherencia resumen staff | P1 | [05](./05-laboratorio-receta-planes.md#cu-plan-003) |
| CU-ATN-001 | Resumen post-atención paciente | P1 | [05](./05-laboratorio-receta-planes.md#cu-atn-001) |
| CU-ATN-002 | Mis atenciones / última atención paciente | P1 | [05](./05-laboratorio-receta-planes.md#cu-atn-002) |

### 06 — Reportes y nomenclador

| ID | Título | P | Archivo |
|----|--------|---|---------|
| CU-NOM-001 | Nomenclador motivos (reason_text) | P1 | [06](./06-reportes-nomenclador.md#cu-nom-001) |
| CU-NOM-002 | Nomenclador diagnósticos (clinical_condition) | P1 | [06](./06-reportes-nomenclador.md#cu-nom-002) |
| CU-NOM-003 | Nomenclador medicamentos / prácticas | P1 | [06](./06-reportes-nomenclador.md#cu-nom-003) |
| CU-NOM-004 | Nomenclador alergias | P1 | [06](./06-reportes-nomenclador.md#cu-nom-004) |
| CU-REP-001 | Planilla reporte 4 (ambulatorio) | P1 | [06](./06-reportes-nomenclador.md#cu-rep-001) |
| CU-REP-002 | Planilla C7 odontología | P1 | [06](./06-reportes-nomenclador.md#cu-rep-002) |
| CU-REP-003 | Reporte farmacia | P2 | [06](./06-reportes-nomenclador.md#cu-rep-003) |
| CU-REP-004 | Expediente legal staff | P2 | [06](./06-reportes-nomenclador.md#cu-rep-004) |
| CU-REP-005 | Planillas 5 y 9 (smoke) | P2 | [06](./06-reportes-nomenclador.md#cu-rep-005) |

### 07 — Asistente (29 intents)

Ver [07-asistente-intents.md](./07-asistente-intents.md) — un caso por intent YAML (`CU-AST-001` … `CU-AST-029`).

### 08 — Post clean-legacy FHIR

| ID | Título | P | Archivo |
|----|--------|---|---------|
| CU-FHIR-001 | Referral en service_request | P0 | [08](./08-post-clean-legacy-fhir.md#cu-fhir-001) |
| CU-FHIR-002 | Grids sin tablas consultas_* | P0 | [08](./08-post-clean-legacy-fhir.md#cu-fhir-002) |
| CU-FHIR-003 | CPO / procedure odontología | P1 | [08](./08-post-clean-legacy-fhir.md#cu-fhir-003) |
| CU-FHIR-004 | Balance/régimen Observation/NutritionOrder | P1 | [08](./08-post-clean-legacy-fhir.md#cu-fhir-004) |
| CU-FHIR-005 | RBAC fase 04 (MVC retirado) | P1 | [08](./08-post-clean-legacy-fhir.md#cu-fhir-005) |
| CU-FHIR-006 | Greenfield vs staging | P2 | [08](./08-post-clean-legacy-fhir.md#cu-fhir-006) |

---

## Suites sugeridas por release

| Suite | Casos | Tiempo orientativo |
|-------|-------|-------------------|
| **Smoke P0** | Todos P0 (~45) | 4–8 h |
| **Regresión FHIR** | [08](./08-post-clean-legacy-fhir.md) + CU-CAP-010/011 | 2 h |
| **Paciente móvil** | CU-TR-004, CU-TUR-004/005, CU-LAB-001, CU-REC-002, CU-ATN-* | 2–3 h |
| **Staff día** | CU-TR-*, CU-TUR-*, CU-EMER-*, CU-CAP-003 | 3–4 h |
| **Internación completa** | CU-IMP-* + CU-CAP-006/011 | 2–3 h |
| **Asistente full** | [07](./07-asistente-intents.md) | 4–6 h |

---

## Datos de prueba recomendados

Mantener un **efector de prueba** con:

- Al menos 2 servicios (uno con turnos, uno IMP o EMER).
- Profesional con agenda (PES) y paciente con documento conocido.
- Paciente app registrado con push token (opcional).
- `EncounterDefinition` configurado para AMB + odontología si aplica.

Referencia esquema greenfield: `web/u257309594_bioenlace.sql`.

---

## Actualización

Al agregar intents o APIs nuevas: nuevo ID en tabla maestra + sección en archivo de dominio. Convención ID: `CU-{DOMINIO}-{NNN}`.
