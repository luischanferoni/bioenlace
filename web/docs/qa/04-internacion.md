# QA — Internación (IMP)

[← Índice](./README.md) · Producto: [internacion.md](../producto/internacion.md)

**Precondición:** sesión `encounter_class = IMP` (CU-TR-002).

---

## CU-IMP-001 — Mapa de camas

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-001 |
| **Prioridad** | P0 |

### Pasos

1. `/internacion/index` o intent `internacion.mapa-camas-flow`.
2. Verificar pisos/salas/camas con colores libre/ocupada/bloqueada/aislamiento.
3. `GET /api/v1/clinical/internacion` (mapa).

### Resultado esperado

- Indicadores de ocupación coherentes.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-IMP-002 — Marcar estado cama

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-002 |
| **Prioridad** | P1 |

### Pasos

1. Acciones B/A/L en mapa o `POST .../cama/{id}/marcar-estado`.

### Resultado esperado

- Estado persiste; visible al refrescar.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-IMP-003 — Ingreso internación

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-003 |
| **Prioridad** | P0 |

### Pasos

1. Ingreso manual o desde guardia (CU-EMER-008).
2. Flow `internacion.ingreso-flow` o formulario web.
3. Asignar cama libre.

### Resultado esperado

- `seg_nivel_internacion` activa; cama ocupada; episodio FHIR si aplica.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-IMP-004 — Atender desde mapa

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-004 |
| **Prioridad** | P0 |

### Pasos

1. Desde mapa/ronda, **Atender** → timeline con `parent=INTERNACION`.
2. CU-CAP-006.

### Resultado esperado

- No abre MVC `internacion-diagnostico/*`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-IMP-005 — Cambio de cama

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-005 |
| **Prioridad** | P1 |

### Intent

- `internacion.cambio-cama-flow`

### Resultado esperado

- Cama anterior liberada; nueva ocupada; historial `seg_nivel_internacion_hcama`.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-IMP-006 — Alta estructurada

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-006 |
| **Prioridad** | P0 |

### Pasos

1. Intent `internacion.alta-estructurada-flow` o API alta formulario.
2. Elegir plantilla; completar epicrisis; confirmar.
3. Verificar `fecha_fin` internación y cama libre.

### Resultado esperado

- Externación OK; care plan completado si aplica.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-IMP-007 — ABM plantillas epicrisis

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-007 |
| **Prioridad** | P1 |

### Pasos

1. `/internacion-epicrisis-plantilla/index` — listar.
2. Crear plantilla con placeholders `{paciente}`, `{fecha_ingreso}`, etc.
3. Activar/desactivar; usar en CU-IMP-006.

### API

- `GET/POST .../internacion-epicrisis-plantilla/*`

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-IMP-008 — Ficha administrativa view

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-008 |
| **Prioridad** | P1 |

### Pasos

1. `/internacion/view?id=<id>` — datos cama, fechas, enlace a historia.

### Resultado esperado

- Sin pestañas clínicas MVC; enlace a timeline funciona.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-IMP-009 — Bundle clínico API

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-009 |
| **Prioridad** | P1 |

### Pasos

1. `InpatientClinicalQuery::bundleForInternacion(id)` vía API episodio si expuesto.
2. Verificar `medicationRequests`, `serviceRequests`, `conditions`, `fluidBalances`, `nutritionOrders`.

### Resultado esperado

- Datos desde FHIR; no desde `seg_nivel_internacion_medicamento` eliminada.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|

---

## CU-IMP-010 — MVC clínico retirado (410)

| Campo | Valor |
|-------|-------|
| **ID** | CU-IMP-010 |
| **Prioridad** | P1 |

### Pasos

1. GET create en medicamento/practica/diagnostico internación controllers.

### Resultado esperado

- 410 con mensaje de migración a timeline.

### Registro de ejecución

| Entorno | Fecha | Resultado | Notas |
|---------|-------|-----------|-------|
