# QA — Validación post clean-legacy (FHIR)

[← Índice](./README.md) · Plan: [clean-legacy](../plans/clean-legacy/README.md) · Migraciones: [MIGRATIONS.md](../plans/clean-legacy/MIGRATIONS.md)

Suite para ejecutar **después** de aplicar migraciones pendientes (CU-TR-007) en un entorno con datos reales o staging clonado.

---

## CU-FHIR-001 — Derivaciones en `service_request`

| Campo | Valor |
|-------|-------|
| **ID** | CU-FHIR-001 |
| **Prioridad** | P0 |

### Precondiciones

- Migración `m260526_160002` aplicada.
- Al menos una derivación guardada vía captura (CU-CAP-010).

### Pasos

1. SQL: `SELECT id, category, referral_status, target_efector_id, target_service_id FROM service_request WHERE category = 'referral' ORDER BY id DESC LIMIT 10;`
2. UI referencias (`/referencias` o listado paciente) — CU-TUR-007.
3. Crear turno desde derivación pendiente — CU-TUR-008.

### Resultado esperado

- Filas referral con workflow (`referral_status`, targets).
- Vista `view_consulta_motivo` **no** existe en BD.
- `ConsultaDerivaciones` (si se usa en código) extiende `ServiceRequest` sin tabla `consultas_derivaciones`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-FHIR-002 — Sin tablas `consultas*`

| Campo | Valor |
|-------|-------|
| **ID** | CU-FHIR-002 |
| **Prioridad** | P0 |

### Pasos

1. Migración `m260526_150002` aplicada (idempotente en greenfield).
2. SQL: verificar ausencia de tablas hijas legacy, p. ej.  
   `SHOW TABLES LIKE 'consultas%';` → solo debe listar lo que el plan **no** eliminó (idealmente vacío o sin hijas).
3. Recorrer smoke P0: timeline (CU-CAP-001), planilla 4 (CU-REP-001), internación bundle (CU-IMP-009).

### Resultado esperado

- Ningún error `Table '…consultas_…' doesn't exist` en logs PHP.
- `Encounter::legacyChildRelation()` no ejecuta SQL si la tabla no existe.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-FHIR-003 — Odontología CPO y reportes

| Campo | Valor |
|-------|-------|
| **ID** | CU-FHIR-003 |
| **Prioridad** | P1 |

### Pasos

1. Guardar práctica odontológica + estado pieza (CU-CAP-008).
2. SQL: `clinical_condition` con `note` o campo que contenga prefijo `odontology_state:`.
3. SQL: `procedure` + `procedure_odontology_ext` para el mismo `encounter_id`.
4. Planilla C7 — CU-REP-002.

### Resultado esperado

- CPO no en `consultas_odontologia_estados`.
- Reporte mensual/enfermería odontología usa `EncounterReporteBusqueda::searchReporteOdontologia`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-FHIR-004 — Internación auxiliar (balance, régimen, suministro)

| Campo | Valor |
|-------|-------|
| **ID** | CU-FHIR-004 |
| **Prioridad** | P1 |

### Pasos

1. Captura IMP con balance, régimen y suministro (CU-CAP-011).
2. API bundle o `InpatientClinicalQuery` para el episodio.
3. SQL muestra:
   - `observation` (categoría fluid-balance o equivalente),
   - `nutrition_order`,
   - `medication_administration`.

### Resultado esperado

- `InpatientEncounterAuxService` persiste FHIR; sin lectura `consultas_balancehidrico`, `consultas_regimen`, `consultas_suministro*`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-FHIR-005 — RBAC fase 04 (MVC retirado)

| Campo | Valor |
|-------|-------|
| **ID** | CU-FHIR-005 |
| **Prioridad** | P1 |

### Precondiciones

- Migración `m260526_170001` aplicada.

### Pasos

1. CU-TR-003 menú principal.
2. CU-TR-008 rutas retiradas (`guardia/*` MVC clínico, `turnos/create` web, etc.).
3. Verificar que flujos sustitutos funcionan: API guardia, `/turnos/index`, `/turnos/espera`.

### Resultado esperado

- Permisos alineados; sin 403 en rutas API vigentes; MVC muerto 410 o sin ruta.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-FHIR-006 — Greenfield vs staging

| Campo | Valor |
|-------|-------|
| **ID** | CU-FHIR-006 |
| **Prioridad** | P2 |

### Pasos

1. En entorno greenfield (`u257309594_bioenlace.sql`): instalar app, correr migraciones pendientes, smoke P0 mínimo.
2. En staging con datos legacy ya migrados: repetir CU-FHIR-001–004.

### Resultado esperado

- Ambos entornos operativos; diferencias solo en volumen de datos históricos.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|
