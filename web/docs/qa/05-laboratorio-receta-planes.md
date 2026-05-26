# QA — Laboratorio, receta, planes, atención paciente

[← Índice](./README.md)

---

## CU-LAB-001 — Paciente ve resultados

| Campo | Valor |
|-------|-------|
| **ID** | CU-LAB-001 |
| **Prioridad** | P0 |
| **Actor** | Paciente |

### Pasos

1. Login paciente (CU-TR-004).
2. Pantalla laboratorio o intent `laboratorio.ver-resultados-como-paciente`.
3. Paciente con `observation` / reportes vinculados en BD de prueba.

### Resultado esperado

- Listado de estudios; sin error; datos legibles.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-LAB-002 — Ingesta / actualización resultados (LIS)

| Campo | Valor |
|-------|-------|
| **ID** | CU-LAB-002 |
| **Prioridad** | P1 |

### Nota

No hay intent YAML `laboratorio.sincronizar-*` en el repo; validar el **pipeline de ingesta** (cron, integración o API staff) según [laboratorio.md](../producto/laboratorio.md).

### Pasos

1. Disparar ingesta de prueba (fixture o mensaje LIS).
2. Paciente abre CU-LAB-001 / CU-AST-010 y ve el estudio nuevo.

### Resultado esperado

- `observation` / reportes vinculados al paciente; sin tablas legacy.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-REC-001 — Emitir receta electrónica

| Campo | Valor |
|-------|-------|
| **ID** | CU-REC-001 |
| **Prioridad** | P0 |
| **Actor** | Staff |

### Pasos

1. Tras captura con medicación o flujo receta dedicado.
2. API `clinical/electronic-prescription/*` emitir.
3. Descargar PDF; verificar QR si aplica.

### Referencias

- [receta-electronica.md](../producto/receta-electronica.md)

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-REC-002 — Paciente ve recetas

| Campo | Valor |
|-------|-------|
| **ID** | CU-REC-002 |
| **Prioridad** | P1 |

### Intent

- `receta.ver-recetas-como-paciente`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-PLAN-001 — Care plan en captura

| Campo | Valor |
|-------|-------|
| **ID** | CU-PLAN-001 |
| **Prioridad** | P1 |

### Pasos

1. Guardar medicación en encounter ambulatorio.
2. Verificar `care_plan` + `medication_request` vinculados.

### Referencias

- [planes-de-tratamiento.md](../producto/planes-de-tratamiento.md)

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-PLAN-002 — Recordatorios paciente

| Campo | Valor |
|-------|-------|
| **ID** | CU-PLAN-002 |
| **Prioridad** | P1 |

### Intent

- `tratamiento.recordatorios-como-paciente`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-PLAN-003 — Adherencia staff

| Campo | Valor |
|-------|-------|
| **ID** | CU-PLAN-003 |
| **Prioridad** | P1 |

### Intent

- `tratamiento.adherencia-resumen-staff`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-ATN-001 — Resumen post-atención

| Campo | Valor |
|-------|-------|
| **ID** | CU-ATN-001 |
| **Prioridad** | P1 |

### Pasos

1. Cerrar encounter ambulatorio con publicación de resumen si el flujo lo exige.
2. Paciente ve resumen en app (no dictado crudo).

### Referencias

- [resumen-atencion-paciente.md](../producto/resumen-atencion-paciente.md)

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-ATN-002 — Mis atenciones / última atención

| Campo | Valor |
|-------|-------|
| **ID** | CU-ATN-002 |
| **Prioridad** | P1 |

### Intents

- `atencion.mis-atenciones-como-paciente`
- `atencion.ver-ultima-como-paciente`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|
