# QA — Urgencias / guardia (EMER)

[← Índice](./README.md) · Producto: [urgencias-guardia.md](../producto/urgencias-guardia.md)

**Precondición común:** sesión con `encounter_class = EMER` (CU-TR-002).

---

## CU-EMER-001 — Tablero guardia

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-001 |
| **Prioridad** | P0 |

### Pasos

1. `set-session` EMER en efector con guardia habilitada.
2. Abrir inicio pacientes / tablero (`site/pacientes` o intent `urgencias.ver-tablero-guardia`).
3. Verificar columnas/estados: ingresado, espera triage, espera médico, en atención.

### API

- `GET /api/v1/clinical/emergency-guardia/tablero`

### Resultado esperado

- Cola del efector; sin referencias a `GuardiaController` MVC.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-EMER-002 — Registrar triage

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-002 |
| **Prioridad** | P0 |

### Pasos

1. Seleccionar paciente en tablero sin triage o intent `urgencias.triage-paciente-guardia`.
2. Completar Manchester 1–5, motivo, vitales opcionales.
3. `POST .../emergency-guardia/{id}/registrar-triage`

### Resultado esperado

- Estado → espera médico; evento en `guardia_circuito_event`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-EMER-003 — Tomar caso / asignar médico

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-003 |
| **Prioridad** | P0 |

### Pasos

1. `POST .../{id}/asignar` con PES de sesión.

### Resultado esperado

- Médico asignado; push `EMERGENCY_ASSIGNED_TO_YOU` si configurado.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-EMER-004 — Iniciar atención → captura

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-004 |
| **Prioridad** | P0 |

### Pasos

1. `POST .../{id}/iniciar-atencion`
2. Abrir `captura_url` devuelta.
3. Guardar encounter mínimo (CU-CAP-007).

### Resultado esperado

- `encounter_id` válido; circuito `en_atencion`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-EMER-005 — Derivar a otro efector

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-005 |
| **Prioridad** | P1 |

### Pasos

1. `GET .../listar-efectores-derivacion`
2. `POST .../{id}/derivar` con `id_efector_derivacion` y condiciones.

### Resultado esperado

- Estado derivado; trazabilidad en tablero origen/destino según diseño.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-EMER-006 — Finalizar / egreso

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-006 |
| **Prioridad** | P0 |

### Pasos

1. `POST .../{id}/finalizar` con datos de libro de guardia requeridos.

### Resultado esperado

- Episodio finalizado; no aparece en cola activa.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-EMER-007 — Indicadores resumen

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-007 |
| **Prioridad** | P1 |

### API

- `GET .../indicadores-resumen`
- Export CSV si habilitado: `indicadores-export-csv`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-EMER-008 — Solicitar internación

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-008 |
| **Prioridad** | P0 |

### Pasos

1. `POST .../{id}/solicitar-internacion`
2. Completar ingreso web `internacion/create?id_guardia=` o flow `internacion.ingreso-flow`.

### Resultado esperado

- `seg_nivel_internacion.id_guardia` poblado; paciente en mapa IMP.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-EMER-009 — Push crítico / asignación

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-009 |
| **Prioridad** | P2 |

### Pasos

1. Triage nivel 1–2 → verificar push `EMERGENCY_PATIENT_CRITICAL` en app médico.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-EMER-010 — Re-triage

| Campo | Valor |
|-------|-------|
| **ID** | CU-EMER-010 |
| **Prioridad** | P2 |

### Resultado esperado

- Evento `re_triage` auditable; cola actualizada.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|
