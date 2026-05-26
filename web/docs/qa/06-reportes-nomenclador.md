# QA — Reportes ministeriales y nomenclador

[← Índice](./README.md) · HIS: [his-completo](../his-completo/README.md)

**Precondición común:** CU-TR-002; efector con encounters de prueba en el rango de fechas del reporte (AMB, odontología, farmacia según caso).

---

## CU-NOM-001 — Nomenclador motivos

| Campo | Valor |
|-------|-------|
| **ID** | CU-NOM-001 |
| **Prioridad** | P1 |
| **Actor** | Admin / staff con permiso |
| **Superficie** | Web `/nomenclador/motivos` |

### Pasos

1. Abrir listado motivos de consulta.
2. Crear o editar un motivo (`reason_text` o catálogo equivalente).
3. Usar el motivo en captura (texto libre o selector) y guardar encounter.

### Resultado esperado

- ABM sin error; motivo disponible en captura.
- Sin referencias rotas a tablas `consultas_motivo` eliminadas.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-NOM-002 — Nomenclador diagnósticos

| Campo | Valor |
|-------|-------|
| **ID** | CU-NOM-002 |
| **Prioridad** | P1 |
| **Superficie** | Web `/nomenclador/diagnosticos` |

### Pasos

1. Buscar diagnóstico CIE/SNOMED en nomenclador.
2. Alta de término local si el flujo lo permite.
3. Guardar encounter con ese diagnóstico → verificar `clinical_condition`.

### Resultado esperado

- Condición FHIR persistida; planilla 4 incluye el dx si aplica.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-NOM-003 — Nomenclador medicamentos y prácticas

| Campo | Valor |
|-------|-------|
| **ID** | CU-NOM-003 |
| **Prioridad** | P1 |
| **Superficie** | `/nomenclador/medicamentos`, `/nomenclador/practicas` |

### Pasos

1. Listar y filtrar medicamento; repetir con práctica.
2. Guardar captura que referencie ambos.

### Resultado esperado

- `medication_request` y `service_request` (category procedure) coherentes con nomenclador.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-NOM-004 — Nomenclador alergias

| Campo | Valor |
|-------|-------|
| **ID** | CU-NOM-004 |
| **Prioridad** | P1 |
| **Superficie** | `/nomenclador/alergias` |

### Pasos

1. ABM alergia en nomenclador.
2. Registrar alergia en antecedentes paciente o captura si el workflow lo expone.

### Resultado esperado

- Sin SQL a tablas legacy de consulta; antecedente vinculado a persona/encounter según modelo actual.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-REP-001 — Planilla reporte 4 (ambulatorio)

| Campo | Valor |
|-------|-------|
| **ID** | CU-REP-001 |
| **Prioridad** | P1 |
| **Superficie** | Web `ReporteController::actionPlanilla4` |

### Precondiciones

- Encounters AMB cerrados en mes de prueba con motivo, dx, prácticas y medicación.

### Pasos

1. Menú reportes → Planilla 4 (o URL `/reporte/planilla4`).
2. Elegir efector, servicio, período (mes/año).
3. Generar vista previa / export PDF si aplica.
4. Comparar conteo de filas con consulta SQL a `encounter` del período (misma fecha/servicio).

### Resultado esperado

- `EncounterReporteBusqueda` devuelve datos; vista `_planilla4` renderiza sin error.
- **No** aparece error por tabla `consultas` inexistente.
- Motivos/dx desde `clinical_condition` / reason según implementación.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-REP-002 — Planilla C7 odontología

| Campo | Valor |
|-------|-------|
| **ID** | CU-REP-002 |
| **Prioridad** | P1 |
| **Superficie** | `actionPlanillac7` |

### Precondiciones

- Encounters odontológicos con `procedure` + `procedure_odontology_ext` y estados CPO (CU-CAP-008).

### Pasos

1. Generar planilla C7 para efector/servicio/fecha con prácticas odontológicas.
2. Verificar columnas de piezas, códigos y estados.

### Resultado esperado

- `searchReporteOdontologia` usa `procedure`, no `consultas_odontologia_*`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-REP-003 — Reporte farmacia

| Campo | Valor |
|-------|-------|
| **ID** | CU-REP-003 |
| **Prioridad** | P2 |
| **Superficie** | Reporte farmacia en `ReporteController` |

### Pasos

1. Ejecutar reporte farmacia con filtros efector/servicio/fecha/tipo atención.
2. Validar que medicaciones listadas provienen de `medication_request` del encounter.

### Resultado esperado

- `searchReporteFarmacia` completa sin fallback a `consultas_medicamentos`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-REP-004 — Expediente legal staff

| Campo | Valor |
|-------|-------|
| **ID** | CU-REP-004 |
| **Prioridad** | P2 |

### Pasos

1. Export legal / expediente para paciente con historial FHIR (si el módulo está habilitado).
2. Verificar que el collector usa `encounter` y recursos hijos, no `consultas`.

### Referencias

- `LegalRecordExportDataCollector` (sin joins a tablas drop).

### Resultado esperado

- PDF/ZIP generado; contenido clínico legible.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-REP-005 — Planillas 5 y 9 (smoke)

| Campo | Valor |
|-------|-------|
| **ID** | CU-REP-005 |
| **Prioridad** | P2 |

### Pasos

1. `actionPlanilla5` y `actionPlanilla9` con mismos criterios de CU-REP-001.
2. Confirmar render sin excepción.

### Resultado esperado

- Grids cargan; totales coherentes con muestra manual.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|
